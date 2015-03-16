<?php 

/*
    The contents of this file are subject to the Common Public Attribution License
    Version 1.0 (the "License"); you may not use this file except in compliance with
    the License. You may obtain a copy of the License at
    http://opensource.org/licenses/cpal_1.0. The License is based on the Mozilla
    Public License Version 1.1 but Sections 14 and 15 have been added to cover use
    of software over a computer network and provide for limited attribution for the
    Original Developer. In addition, Exhibit A has been modified to be consistent with
    Exhibit B.
    
    Software distributed under the License is distributed on an "AS IS" basis, WITHOUT
    WARRANTY OF ANY KIND, either express or implied. See the License for the
    specific language governing rights and limitations under the License.
    
    The Original Code is the SXWeb project.
    
    The Original Developer is the Initial Developer.
    
    The Initial Developer of the Original Code is Skylable Ltd (info-copyright@skylable.com). 
    All portions of the code written by Initial Developer are Copyright (c) 2013 - 2015
    the Initial Developer. All Rights Reserved.

    Contributor(s):    

    Alternatively, the contents of this file may be used under the terms of the
    Skylable White-label Commercial License (the SWCL), in which case the provisions of
    the SWCL are applicable instead of those above.
    
    If you wish to allow use of your version of this file only under the terms of the
    SWCL and not to allow others to use your version of this file under the CPAL, indicate
    your decision by deleting the provisions above and replace them with the notice
    and other provisions required by the SWCL. If you do not delete the provisions
    above, a recipient may use your version of this file under either the CPAL or the
    SWCL.
*/


/**
 * Model class for managing accounts.
 */
class My_Accounts extends Zend_Db_Table_Abstract  {
	protected $_name = 'users';
	protected $_primary = 'id';
    protected $_sequence = TRUE;
    
    const
        // Exception code
        // When too many reset password tickets requested
        EXCEPTION_RESET_PASSWORD_TOO_MANY_TICKETS = 666;

    /**
     * Creates a new account.
     * 
     * Actual _mandatory_ parameters are:
     * 'email' - string - the user email
     * 'password' - string - the password (plain or crypted)
     * 'password_is_plain' - flag - TRUE the password is plain an should be crypted, FALSE the password is already crypted
     * 'auth_token' - string - skylable auth token
     * 'is_active' - boolean - flag: TRUE the user is active, FALSE otherwise
     * 'activation_key' - string - a random activation key to use to two pass activation of non active accounts
     * 
     * Optional parameters
     * 'preferences' - a Zend_Config object with default user preferences
     * 
     * @param array $params
     * @return integer the new user id
     * @throws Exception
     */
    public function createAccount($params = array()) {
        $this->getAdapter()->beginTransaction();
        try {
            $password = $params['password'];
            if (array_key_exists('password_is_plain', $params)) {
                if ($params['password_is_plain']) $password = self::getPasswordHash($params['password']);
            }

            $data = array(
                'email' => $params['email'],
                'passwd' => $password,
                'secret_key' => $params['secret_key'],
                'active' => ($params['is_active'] ? 1 : 0),
                'preferences' => ''
            );
            
            if (array_key_exists('preferences', $params)) {
                if (is_object($params['preferences'])) {
                    $data['preferences'] = Zend_Json::encode( $params['preferences']->toArray() );
                }
            }
            
            $user_id = $this->insert($data);
            if ($params['is_active'] == FALSE) {
                $this->getAdapter()->insert('users_act_keys', array(
                    'key' => $params['activation_key'],
                    'uid' => $user_id
                ));
            }
            $this->getAdapter()->commit();
            return $user_id;
        }
        catch(Exception $e) {
            $this->getAdapter()->rollBack();
            throw $e;
        }
    }
    
    /**
     * Return the user resulting from the authentication process.
     * 
     * @param Zend_Auth_Adapter_DbTable $db_table The adapter user by Zend_Auth
     * @return My_User|boolean FALSE on failed authentication otherwise the user object
     * @throws Exception
     */
    public function getUserFromAuth(Zend_Auth_Adapter_DbTable $db_table) {
        $row = $db_table->getResultRowObject();
        if ($row === FALSE) {
            return FALSE;
        }
        
        $user = $this->getUserFromDBRow($row);
        return $user;
    }
   
    /**
     * Return an hash that you can use to retrieve an user.
     * 
     * Returns FALSE when:
     * - the user don't exists
     * - the users isn't active
     * 
     * @param integer $user_id
     * @return string|boolean the hash or FALSE on error
     * @throws Exception
     */
    public function getUserIdentityHash($user_id) {
        $row = $this->fetchRow( $this->select()
                ->from($this->info(self::NAME),
                       array('id_hash' => new Zend_Db_Expr('SHA1(CONCAT(id,email,passwd,'. $this->getAdapter()->quote(Zend_Registry::get('skylable')->get('auth_salt')) .'))') )
                 )
                ->where('id = ?', $user_id)
                ->where('active = 1')
                );
        if (empty($row)) {
            return FALSE;
        } else {
            return $row['id_hash'];
        }
    }
    
    /**
     * Retrieve an user using the identity hash generated with {@link getUserIdentityHash}.
     * 
     * Returns FALSE when:
     * - the user doesn't exists
     * - the users isn't active
     * 
     * @param string $identity_hash the identity hash
     * @return boolean|\My_User The user or FALSE on error.
     */
    public function getUserByIdentityHash($identity_hash) {
        $row = $this->fetchRow( 
                $this->select()
                ->from($this->info(self::NAME),
                       array('*', 'id_hash' => new Zend_Db_Expr('SHA1(CONCAT(id,email,passwd,'. $this->getAdapter()->quote(Zend_Registry::get('skylable')->get('auth_salt')) .'))') )
                )
                ->where('active = 1')
                ->having('id_hash = ?', $identity_hash )
                );
      
        if (empty($row)) {
            return FALSE;
        } else {
            $user = $this->getUserFromDBRow($row);
            return $user;
        }
    }
    
    /**
     * Create a user from data extracted from DB.
     * 
     * @param stdClass|array|Zend_Db_Table_Row_Abstract $row
     * @return My_User
     */
    protected function getUserFromDBRow($row) {
        if (empty($row)) {
            return NULL;
        }
        if (is_array($row)) {
            $row = (object)$row;
        }
        $user = new My_User($row->id, $row->email, $row->secret_key, ($row->active == 1 ? My_User::ROLE_REGISTERED : My_User::ROLE_GUEST));
        
        try {
            $json = Zend_Json::decode($row->preferences);
            $p_arr = new Zend_Config( (is_array($json) ? $json : array() )  );
            $user->getPreferences()->merge($p_arr);
        }
        catch(Exception $e) {

        }
        return $user;
    }

    /**
     * Update the user password.
     * 
     * IMPORTANT: this method accepts only passwords hash generated using
     * {@link getPasswordHash}
     * 
     * @param integer $user_id the user id
     * @param string $oldpasswd the current user password hash
     * @param string $newpasswd the new user password hash
     * @return boolean
     * @throws Exception
     * @see getPasswordHash
     * 
     */
	public function changePassword($user_id, $oldpasswd, $newpasswd) {
        if (!is_numeric($user_id)) {
            return FALSE;
        }
        
        $this->getAdapter()->beginTransaction();
		try {
            $res = $this->update(array(
                'passwd' => $newpasswd
            ), array( 
                'id = ?' => $user_id,
                'passwd = ?' => $oldpasswd
            ));
            
            Zend_Controller_Front::getInstance()->getParam('bootstrap')->getResource('log')->err('new: '.$newpasswd.' old:'.$oldpasswd);
            
            if ($res == 1) {
                $this->getAdapter()->commit();
                return TRUE;
            } else {
                $this->getAdapter()->rollBack();
                return FALSE;
            }
		} catch (Exception $e) {
            $this->getAdapter()->rollBack();
            Zend_Controller_Front::getInstance()->getParam('bootstrap')->getResource('log')->err($e->getMessage());
        }
	}
    
    /**
     * Generates a password hash from a plain password.
     * 
     * Every password stored into the DB is a hash obtained with this method.
     * 
     * @param string $plain_password the plain password
     * @param array $options
     * @return string the password hash
     */
    public static function getPasswordHash($plain_password, $options = array()) {
        // Uses the Blowfish hashing with a 22 char salt 
        $salt = Zend_Registry::get('skylable')->get('auth_salt');
        if (strlen($salt) < 22) str_pad($salt, 22, 'abcdefghijklmnopqrstyuwxyz1234567890', STR_PAD_RIGHT);
        return crypt($plain_password, '$2y$10$' . $salt);
    }
    
    /**
     * Updates the user preferences.
     * 
     * @param My_User $user the user to work on
     * @return boolean TRUE on success, FALSE on failure
     * @throws Exception
     */
    public function updateUserPreferences(My_User $user) {
        if ($user->isNew()) {
            return FALSE;
        }
        $db = $this->getAdapter();
        $db->beginTransaction();
        try {
            $this->update(array( 'preferences' => Zend_Json::encode( $user->getPreferences()->toArray() ) ), array( 'id = ?' => $user->getId()) );
            $db->commit();
            return TRUE;
        }
        catch(Exception $e) {
           $db->rollBack();
           throw $e;
        }
    }

    /**
     * Deletes an account using its unique ID.
     * 
     * @param integer $user_id the user id
     * @return bool TRUE on success, FALSE on failure
     * @throws Exception
     * @throws Zend_Db_Exception
     */
    public function deleteAccount($user_id) {
        if (empty($user_id) || !is_numeric($user_id)) {
            return FALSE;
        }
        $this->getAdapter()->beginTransaction();
        try {
            $this->delete( array('id = ?' => $user_id) );
            $this->getAdapter()->commit();
            return TRUE;
        }
        catch(Exception $e) {
            $this->getAdapter()->rollBack();
            throw $e;
        }
    }

    // ------------- Password reset
    /**
     * Generates a unique token to use into the reset password procedure.
     * 
     * @param string $email the user email
     * @return bool|string the generated token or FALSE on non-existent user
     * @throws Exception
     * @throws Zend_Db_Exception
     * @throws Zend_Exception
     */
    public function generatePasswordResetToken($email) {
        $res = $this->fetchRow( $this->select()->where('email = ?', $email)->limit(1) );
        if (count($res) > 0) {
            // counts already made tickets
            $this->getAdapter()->beginTransaction();
            try {
                $this->getAdapter()->delete('user_reset_password','TIMESTAMPDIFF(HOUR,`date`, CURRENT_TIMESTAMP()) > 24');
                $cnt = $this->getAdapter()->fetchRow('SELECT COALESCE(SUM(counter), 0) as cnt FROM user_reset_password WHERE uid = ?', $res['id']);
                
                if ($cnt === FALSE) {
                    $this->getAdapter()->commit();
                    return FALSE;
                }

                $hash = hash_hmac('sha256', $res['id'] . bin2hex( openssl_random_pseudo_bytes(50) ), Zend_Registry::get('skylable')->get('auth_salt') );
                
                if ($cnt['cnt'] > 0) {
                    // Updates existing info
                    if ($cnt['cnt'] >= 3) { // Too many tickets...
                        $this->getAdapter()->commit();
                        throw new Zend_Exception('Too many tickets sent.', self::EXCEPTION_RESET_PASSWORD_TOO_MANY_TICKETS);
                    } else {
                        $this->getAdapter()->update('user_reset_password', array(
                            'hash' => $hash,
                            'counter' => new Zend_Db_Expr( 'counter + 1' )
                        ), array(
                            'uid' => $res['id']
                        ));
                    }
                } else {
                    // Creates a new record
                    $this->getAdapter()->insert('user_reset_password', array(
                        'hash' => $hash,
                        'uid' => $res['id'],
                        'counter' => 1
                    ));
                }
                $this->getAdapter()->commit();
                return $hash;
            }
            catch(Zend_Db_Exception $e) {
                $this->getAdapter()->rollBack();
                throw $e;
            }
            
        }
        return FALSE;
    }

    /**
     * Finalize the password reset procedure: stores the new password.
     * 
     * @param string $hash the reset password hash
     * @param string $plain_password the plain new password
     * @return bool TRUE on success, FALSE on failure
     * @throws Exception
     */
    public function doResetPassword($hash, $plain_password) {
        // $logger = Zend_Controller_Front::getInstance()->getParam('bootstrap')->getResource('log');
        $db = $this->getAdapter();
        $db->beginTransaction();
        if (empty($hash)) return FALSE;
        try {
            $db->delete('user_reset_password','TIMESTAMPDIFF(HOUR,`date`, CURRENT_TIMESTAMP()) > 24');
            $res = $db->fetchRow('SELECT * FROM user_reset_password WHERE hash = ? LIMIT 1', $hash );
            
            if ($res === FALSE) {
                $db->commit();
                return FALSE;
            }
            
            $this->update(
                array( 
                    'passwd' => $this->getPasswordHash($plain_password)
                ), array('id = ?' => $res['uid']) 
            );
            $db->delete('user_reset_password','uid = '.$db->quote($res['uid']) );
            $db->commit();
            return TRUE;
            
        }
        catch(Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

}
