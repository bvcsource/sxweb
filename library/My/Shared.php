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

        $db = $this->getAdapter();
        $db->beginTransaction();
        try {
            // Purge old elements
            $this->getAdapter()->delete('shared', array( 'NOW() > expire_at ' ) );

            // Adds the new one
            $db->query('INSERT INTO '.
                $db->quoteIdentifier('shared').' ('.
                $db->quoteIdentifier('file_id').', '.
                $db->quoteIdentifier('user_auth_token').', '.
                $db->quoteIdentifier('file_path').', '.
                $db->quoteIdentifier('created_at').', '.
                $db->quoteIdentifier('expire_at').', '.
                $db->quoteIdentifier('file_password').') '.
                ' SELECT @mykey := SHA1(UUID()), ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND), ?',
                array( strval($user_auth_token), strval($path), $expire, ((is_null($password) || strlen($password) == 0) ? '' : sha1($password)) )
            );
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
     * The password, if not empty, is an sha1 hash of the plain password.
     *
     * @param string $hash
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
