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
     * You can provide a My_User object or an associative array.
     * In this case the actual _mandatory_ parameters are:
     * 'login' - string - the user login
     * 'email' - string - the user email
     * 'password' - string - the password (plain or encrypted)
     * 'password_is_plain' - flag - TRUE the password is plain an should be encrypted, FALSE the password is already encrypted
     * 'secret_key' - string - SX cluster user auth token
     * 'is_active' - boolean - flag: TRUE the user is active, FALSE otherwise
     * 
     * 
     * Optional parameters
     * 'preferences' - a Zend_Config object with default user preferences
     * 'role' - The user role: one of the My_User::ROLE_* constants
     * 
     * @param array|My_User $params
     * @return integer the new user id
     * @throws Exception
     */
    public function createAccount($params = array()) {
        if ($params instanceof My_User) {
            $this->getLogger()->debug(__METHOD__ . ': Using an object');
            $data = array(
                'login' => $params->getLogin(),
                'email' => $params->getEmail(),
                'secret_key' => $params->getSecretKey(),
                'active' => ($params->isActive() ? 1 : 0),
                'preferences' => Zend_Json::encode( $params->getPreferences()->toArray() ),
                'user_role' => $params->getRoleId()
            );
        } elseif (is_array($params)) {
            $this->getLogger()->debug(__METHOD__ . ': Using data');
            if (!array_key_exists('login', $params)) {
                $this->getLogger()->debug(__METHOD__ . ': invalid data, no login');
                return FALSE;
            }
            
            $data = array(
                'login' => $params['login'],
                'email' => $params['email'],
                // 'passwd' => $password,
                'secret_key' => $params['secret_key'],
                'active' => ($params['is_active'] ? 1 : 0),
                'preferences' => '',
                'user_role' => My_User::ROLE_GUEST
            );

            if (array_key_exists('role', $params)) {
                if (My_User::checkRole( $params['role'] )) {
                    $data['user_role'] = $params['role'];
                }
            }

            if (array_key_exists('preferences', $params)) {
                if (is_object($params['preferences'])) {
                    $data['preferences'] = Zend_Json::encode( $params['preferences']->toArray() );
                }
            }
        } else {
            $this->getLogger()->debug(__METHOD__ . ': Invalid input');
            return FALSE;
        }
        
        $this->getAdapter()->beginTransaction();
        try {
            
            $user_id = $this->insert($data);
            
            $this->getAdapter()->commit();
            $this->getLogger()->debug(__METHOD__ . ': User successfully created, UID: ' . strval($user_id));
            if ($params instanceof My_User) {
                $params->setId($user_id);
            }
            
            return $user_id;
        }
        catch(Exception $e) {
            $this->getAdapter()->rollBack();
            throw $e;
        }
    }

    /**
     * Updates the given user.
     * 
     * @param My_User $user
     * @return bool TRUE on succes, FALSE on failure
     * @throws Exception
     */
    public function updateUser(My_User $user) {
        if ($user->isNew() || !is_numeric($user->getId())) {
            $this->getLogger()->debug(__METHOD__ . ': Invalid user.');
            return FALSE;
        }
        $db = $this->getAdapter();
        $db->beginTransaction();
        try {
            $this->getLogger()->debug(__METHOD__ . ': Updating user UID: '.strval($user->getId()) );
            $this->update(
                array(
                    'login' => $user->getLogin(),
                    'email' => $user->getEmail(),
                    'secret_key' => $user->getSecretKey(),
                    'active' => ($user->isActive() ? 1 : 0),
                    'user_role' => $user->getRoleId(),
                    'preferences' => Zend_Json::encode( $user->getPreferences()->toArray() ) 
                ), array( 'id = ?' => $user->getId()) 
            );
            $db->commit();
            $this->getLogger()->debug(__METHOD__ . ': User successfully updated');
            return TRUE;
        }
        catch(Exception $e) {
            $db->rollBack();
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
     * Check user credentials.
     * 
     * Check if a user exists and is active, then authenticate it using some credentials.
     *
     * Use this method to do the user login: when everything is ok, you'll get the 
     * requested user. 
     * 
     * @param string $login the user login
     * @param string $plain_password the user password
     * @return bool|My_User FALSE if the user is not valid or credentials are wrong
     */
    public function checkUserCredentials($login, $plain_password) {
        $row = $this->fetchRow( $this->select()->where('login = ?', $login)->where('active = 1') );
        
        if (!empty($row)) {
            
            // Check if the user is active
            if (is_array($row)) {
                $row = (object)$row;
            }
            
            /*
            $pass_hash = $this->getPasswordHash( $plain_password, array( 'salt' => $row->passwd ) );
            
            if (strcmp($pass_hash, $row->passwd) == 0) {
                $user = $this->getUserFromDBRow($row);
                return $user;    
            }
            */
            $user = $this->getUserFromDBRow($row);
            return $user;
        }
        return FALSE;
    }

    /**
     * Tells if an account exists and return it.
     * 
     * Return FALSE if the account don't exists or a My_User object.
     * 
     * Important: you must check if the user is active or not.
     * 
     * @param string $login the user login to check
     * @return bool|My_User
     */
    public function accountExists($login) {
        $row = $this->fetchRow( $this->select()->where('login = ?', $login)->limit(1) );

        if (!empty($row)) {
            if (is_array($row)) {
                $row = (object)$row;
            }
       
            $user = $this->getUserFromDBRow($row);
            return $user;
        }
        return FALSE;
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
        $user = new My_User($row->id, $row->login, $row->email, $row->secret_key, $row->user_role, (bool)$row->active);
        
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
     * Generates a password hash from a plain password.
     * 
     * Every password stored into the DB is a hash obtained with this method.
     * 
     * Current options:
     * 'salt' - use this salt instead of a random generated one
     * 
     * @param string $plain_password the plain password
     * @param array $options
     * @return string|boolean the password hash, FALSE on errors
     */
    public static function getPasswordHash($plain_password, $options = array()) {
        if (array_key_exists('salt', $options)) {
            $salt = $options['salt'];
        } else {
            $salt = My_Utils::getRandomBytes(16);

            $base64_digits =  'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
            $bcrypt64_digits = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            $base64_string = base64_encode($salt);
            $salt = strtr(rtrim($base64_string, '='), $base64_digits, $bcrypt64_digits);
            $salt = '$2y$10$' . base64_encode($salt);
        }

        return crypt($plain_password, $salt);
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
            
            /*
             * Fixes bug #1474
             * In demo mode, don't save some settings.
             * */
            if (My_BaseAction::isDemoMode()) {
                $prefs = $user->getPreferences()->toArray();
                $keys_to_delete = array(My_User::PREF_LANGUAGE, My_User::PREF_PAGE_SIZE, My_User::PREF_LAST_VISITED_PATH);
                foreach($keys_to_delete as $k) {
                    if (array_key_exists($k, $prefs)) {
                        unset($prefs[$k]);
                    }
                }
                
                $this->update(array( 'preferences' => Zend_Json::encode( $prefs ) ), array( 'id = ?' => $user->getId()) );         
            } else {
                $this->update(array( 'preferences' => Zend_Json::encode( $user->getPreferences()->toArray() ) ), array( 'id = ?' => $user->getId()) );    
            }
            
            
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
     * @param string $login the user login
     * @param string $email the user email
     * @return bool|string the generated token or FALSE on non-existent user
     * @throws Exception
     * @throws Zend_Db_Exception
     * @throws Zend_Exception
     */
    public function generatePasswordResetToken($login, $email) {
        
            // counts already made tickets
            $this->getAdapter()->beginTransaction();
            try {
                $this->getAdapter()->delete('user_reset_password','TIMESTAMPDIFF(HOUR,`date`, CURRENT_TIMESTAMP()) > 24');
                $cnt = $this->getAdapter()->fetchRow('SELECT COALESCE(SUM(counter), 0) as cnt FROM user_reset_password WHERE login = ?', $login);
                
                if ($cnt === FALSE) {
                    $this->getAdapter()->commit();
                    return FALSE;
                }
                
                // This will generate a 64 byte hash
                $hash = bin2hex( My_Utils::getRandomBytes(32) );
                
                if ($cnt['cnt'] > 0) {
                    // Updates existing info
                    if ($cnt['cnt'] >= 3) { // Too many tickets...
                        $this->getAdapter()->commit();
                        throw new Zend_Exception('Too many tickets sent.', self::EXCEPTION_RESET_PASSWORD_TOO_MANY_TICKETS);
                    } else {
                        $this->getAdapter()->update('user_reset_password', array(
                            'hash' => $hash,
                            'email' => $email,
                            'counter' => new Zend_Db_Expr( 'counter + 1' )
                        ), array(
                            'login = ?' => $login
                        ));
                    }
                } else {
                    // Creates a new record
                    $this->getAdapter()->insert('user_reset_password', array(
                        'hash' => $hash,
                        'email' => $email,
                        'login' => $login,
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
            
        
        return FALSE;
    }

    /**
     * Get the user given a password recovery token.
     * 
     * Checks if the token is expired.
     * 
     * @param string $token
     * @return bool|My_User
     * @throws Exception
     */
    public function getUserFromPasswordRecoveryToken($token) {
        if (empty($token)) return FALSE;
        $db = $this->getAdapter();
        $db->beginTransaction();
        try {
            $db->delete('user_reset_password','TIMESTAMPDIFF(HOUR,`date`, CURRENT_TIMESTAMP()) > 24');
            $res = $db->fetchRow('SELECT `login` FROM user_reset_password WHERE `hash` = ? LIMIT 1', $token );

            $db->commit();
            
            if ($res === FALSE) {
                return FALSE;
            }
            
            return $res['login'];

        }
        catch(Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * 
     * Delete the specified password recovery token and purge expired ones.
     * 
     * @param string $token if empty only purge expired tokens
     * @return bool
     * @throws Exception
     */
    public function purgePasswordRecoveryToken($token) {
        $db = $this->getAdapter();
        $db->beginTransaction();
        try {
            $db->delete('user_reset_password','TIMESTAMPDIFF(HOUR,`date`, CURRENT_TIMESTAMP()) > 24');
            if (!empty($token)) {
                $db->delete('user_reset_password','hash = '.$db->quote($token) );
            }
            $db->commit();
            return TRUE;

        }
        catch(Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Return the system logger
     * 
     * @return Zend_Logger
     */
    public function getLogger() {
        return Zend_Controller_Front::getInstance()->getParam('bootstrap')->getResource('log');
    }
}
