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
 * 
 * Manages sharing of files.
 *
 * Let associate a hash code to a file.
 */
class My_Shared extends Zend_Db_Table_Abstract {
	protected $_name = 'shared';
	protected $_primary = 'file_id';
    protected $_sequence = FALSE;

    /**
     * Add the file to the shared files table and return the
     * associated unique ID.
     *
     * The returned hash is the SHA1 of an unique UUID.
     *
     * If you already shared the file, throws an exception.
     *
     * @param string $path complete file path on SX cluster (volume + file)
     * @param string $user_auth_token SX auth token of the user which shares
     * @param integer $expire number of seconds after which the shared file expire
     * @param string $password NULL or the plain password to protect the download
     * @return string|boolean the unique shared file hash or FALSE on error
     * @throws Exception
     * @throws Zend_Db_Table_Exception
     */
    public function add($path, $user_auth_token, $expire, $password = NULL) {
        $logger = Zend_Controller_Front::getInstance()->getParam('bootstrap')->getResource('log');
        if(empty($path) || empty($user_auth_token)) {
            $logger->debug(__METHOD__.': Empty path or user token');
            return FALSE;
        }
        $file = basename($path);
        if (empty($file)) {
            $logger->debug(__METHOD__.': Empty file basename');
            return FALSE;
        }
        $path = My_Utils::removeSlashes($path, TRUE);

        if ((is_null($password) || strlen($password) == 0)) {
            $the_password = '';
        } else {
            $the_password = $this->getPasswordHash($password);
        }

        $db = $this->getAdapter();
        $db->beginTransaction();
        try {
            // Purge old elements
            $this->getAdapter()->delete('shared', array( 'NOW() > expire_at ' ) );

            // Adds the new one
            if ($this->avoidExpireTimeOverflow($expire) === FALSE) {
                $db->query('INSERT INTO '.
                    $db->quoteIdentifier('shared').' ('.
                    $db->quoteIdentifier('file_id').', '.
                    $db->quoteIdentifier('user_auth_token').', '.
                    $db->quoteIdentifier('file_path').', '.
                    $db->quoteIdentifier('created_at').', '.
                    $db->quoteIdentifier('expire_at').', '.
                    $db->quoteIdentifier('file_password').') '.
                    ' SELECT @mykey := SHA1(UUID()), ?, ?, NOW(), ' . $db->quote('9999-12-31') . ', ?',
                    array( strval($user_auth_token), strval($path), $the_password )
                );
            } else {
                $db->query('INSERT INTO '.
                    $db->quoteIdentifier('shared').' ('.
                    $db->quoteIdentifier('file_id').', '.
                    $db->quoteIdentifier('user_auth_token').', '.
                    $db->quoteIdentifier('file_path').', '.
                    $db->quoteIdentifier('created_at').', '.
                    $db->quoteIdentifier('expire_at').', '.
                    $db->quoteIdentifier('file_password').') '.
                    ' SELECT @mykey := SHA1(UUID()), ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND), ?',
                    array( strval($user_auth_token), strval($path), $expire, $the_password )
                );    
            }
            
            $key = $db->fetchOne('SELECT @mykey');

            $db->commit();
            return $key;
        }
        catch(Exception $e) {
            $this->getAdapter()->rollBack();
            if ($e->getCode() == 23000) {
                throw new My_NotUniqueException('', 0, $e);
            }
            throw $e;
        }
    }

    /**
     * Check if the expire time interval will overflow the year 9999 when
     * added to the current date.
     * 
     * Returns FALSE when the date overflows, otherwise return the expire time.
     * 
     * @param integer $expire_time expire time in seconds
     * @return bool|integer
     */
    protected function avoidExpireTimeOverflow($expire_time) {
        $expire_at = new DateTime();
        $expire_at->add( new DateInterval( 'PT' . strval($expire_time) . 'S' ) );
        if (intval($expire_at->format('Y')) < 9999) {
            return $expire_time;
        } 
        return FALSE;
    }

    /**
     * Update the share info for a given file.
     * 
     * If a parameter is NULL it is ignored.
     * 
     * IMPORTANT: passing an empty string as password will remove the actual password.
     * 
     * @param string $file_id the unique file ID
     * @param string $password the plain password
     * @param string $expire_time the expire time
     * @return bool
     * @throws Exception
     */
    public function updateFile($file_id, $password = NULL, $expire_time = NULL) {
        
        if (empty($file_id)) {
            return FALSE;
        }
        
        $upd = array();
        if (!is_null($password)) {
            if (strlen($password) == 0) {
                $upd['file_password'] = '';
            } else {
                $upd['file_password'] = $this->getPasswordHash($password);
            }
        }
        
        if (!is_null($expire_time)) {
            if (!is_numeric($expire_time)) {
                return FALSE;
            }
            if ($this->avoidExpireTimeOverflow($expire_time) === FALSE) {
                $upd['expire_at'] = new Zend_Db_Expr( '9999-12-31' );    
            } else {
                $upd['expire_at'] = new Zend_Db_Expr( 'DATE_ADD(NOW(), INTERVAL '.strval($expire_time).' SECOND)' );    
            }
        }
        
        if (empty($upd)) {
            return FALSE;
        }

        $upd['created_at'] = new Zend_Db_Expr( 'NOW()' );
        
        $db = $this->getAdapter();
        $db->beginTransaction();
        try {
            $this->update($upd, $db->quoteInto('file_id = ?', strval($file_id)) );
            $db->commit();
            return TRUE;
        }
        catch(Exception $e) {
            $db->rollBack();
            throw $e;
        }
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
     * Tells if the file is already shared.
     *
     * IMPORTANT: the path consists of path + file.
     * Valid path: '/volume/foo/file.ext'
     *
     * @param string $path the complete file path
     * @param string $user_auth_token the SX user auth token of the user which shared the file
     * @param string $key on success stores the unique key of the file, empty on failure
     * @return bool TRUE if the file exists, FALSE otherwise
     * @throws Zend_Db_Table_Exception
     */
    public function fileExists($path, $user_auth_token, &$key) {
        $key = '';
        if(empty($path) || empty($user_auth_token)) {
            return FALSE;
        }
        $path = My_Utils::removeSlashes($path, TRUE);

        $data = $this->getAdapter()->fetchOne(
            $this->getAdapter()->select()
            ->from($this->info(self::NAME), 'file_id' )
            ->where('user_auth_token = ?', $user_auth_token)
            ->where('file_path = ?', $path)
                // Shouldn't be older than the purge limit
            ->where('expire_at > NOW()' )
        );
        if ($data !== FALSE) {
            $key = $data;
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Delete old shared files.
     *
     * @return int the number of deleted rows
     * @throws Zend_Db_Table_Exception
     * @throws Exception
     */
    public function purge() {
        $this->getAdapter()->beginTransaction();
        try {
            $res = $this->getAdapter()->delete('shared', array( 'NOW() > expire_at ' ) );
            $this->getAdapter()->commit();
            return $res;
        }
        catch(Exception $e) {
            $this->getAdapter()->rollBack();
            throw $e;
        }

    }

    /**
     * Get a shared file using its unique hash, but only if not expired.
     *
     * The password you get is encrypted.
     * If the password is empty, the file is not password protected.
     * To verify the password use it as a salt for {@see getPasswordHash}.
     * Do:
     * <code>
     * 
     *  // $password_from_request contains the plain password you get from the user
     *  
     * 
     *  $the_file = $my_shared->getSharedFile('...', TRUE);
     *  if (strlen($the_file['password']) > 0) {
     *    $password_check = $my_shared->getPasswordHash( $password_from_request, array( 'salt' => $the_file->password ) );
     *    if (strcmp($the_file->password, $password_check) == 0) {
     *      echo 'The password is right!';
     *    } else {
     *      echo 'Wrong password, sir!';    
     *    }   
     *  }
     * </code> 
     *
     * @param string $hash the file hash
     * @param bool $return_data TRUE returns the entire record, FALSE only the file path.
     * @return bool|string|array FALSE if the file don't exists, or the file path or the data record
     * @throws Zend_Db_Table_Exception
     */
    public function getSharedFile($hash, $return_data = FALSE) {
        $this->purge();

        $data = $this->getAdapter()->fetchRow(
            $this->getAdapter()->select()
                ->from($this->info(self::NAME), '*' )
                ->where('file_id = ?', $hash)
                // Shouldn't be older than the purge limit
                ->where('expire_at > NOW()' )
        );
        if ($data !== FALSE) {
            return ($return_data ? $data : $data['file_path']);
        }
        return FALSE;
    }
}
