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
 * The user of the web application.
 * 
 * A user can have two access level:
 * guest - an anonymous, low privileged user
 * registered - a registered and logged in user with access to his own volume
 *
 */
class My_User implements Zend_Acl_Role_Interface {
    
     const
        // User roles
        ROLE_GUEST = 'guest',
        ROLE_REGISTERED = 'registered',
        ROLE_ADMIN = 'admin';
     
     const
        // Common preferences tags
         // File sort order: integer, one of the Skylable_AccessSx::SORT_BY_* constants
        PREF_FILE_SORT_ORDER = 'file_sort_order',
         // Last visited path, complete with volume
        PREF_LAST_VISITED_PATH = 'last_visited_path',
         // Flag tells if we shouldn't show the user welcome dialog
        PREF_DONT_SHOW_WELCOME = 'dont_show_welcome',
         // Integer, number of elements shown in a page while file listing or searching
         PREF_PAGE_SIZE = 'page_size' ;
    
    protected
            /**
             * @var Zend_Config user preferences
             */
            $_prefs,
            
            $_id,
            $_active,
            $_login,
            $_email,
            $_secret_key,
            /**
             * the actual user role
             * 
             * @var string 
             */
            $_role;

    /**
     * Creates a new user of the web application.
     *
     * @param integer $id the unique user ID or NULL for non persistent users
     * @param string $login the user login
     * @param string $email the user name
     * @param string $secret_key the SX user secret key
     * @param string $role the user role
     * @param bool $is_active user active flag: TRUE the user is active.
     * @throws Exception
     */
    public function __construct($id = NULL, $login = '', $email = '', $secret_key = '', $role = self::ROLE_GUEST, $is_active = TRUE) {
        $this->_id = $id;
        $this->_email = strval($email);
        $this->_login = strval($login);
        $this->_active = (bool)$is_active;
        $this->setSecretKey($secret_key);
        
        if (!$this->checkRole($role)) {
            throw new Exception(__CLASS__ . ': Invalid role.');
        }
        $this->_role = $role;
        
        $this->_prefs = new My_Preferences(array(
            self::PREF_FILE_SORT_ORDER => 0,
            self::PREF_LAST_VISITED_PATH => '',
        ));
    }

    /**
     * Tells if the user role is valid.
     * 
     * @param string $role a string with the role
     * @return bool TRUE if the role is valid, FALSE otherwise
     */
    public static function checkRole($role) {
        return in_array($role, array(self::ROLE_GUEST, self::ROLE_REGISTERED, self::ROLE_ADMIN));
    }
    
    /**
     * Returns the user preferences.
     * 
     * @return Zend_Config
     */
    public function getPreferences() {
        return $this->_prefs;
    }
    
    /**
     * Return the unique user id.
     * Returns an unsigned integer or NULL if the user is not saved on the DB.
     * @return integer the unique user id or NULL.
     */
    public function getId() {
        return $this->_id;
    }
    
    /**
     * Tells if the user is already stored on the DB.
     * 
     * @return boolean TRUE the user isn't stored into the db, FALSE otherwise.
     */
    public function isNew() {
       return is_null($this->_id);
    }

    /**
     * Return the user login.
     * 
     * @return string the login
     */
    public function getLogin() {
        return $this->_login;
    }
    
    /**
     * Return the user email.
     * 
     * @return string the enail
     */
    public function getEmail() {
        return $this->_email;
    }

    /**
     * Sets the user email.
     * 
     * @param string $email
     */
    public function setEmail($email) {
        $this->_email = strval($email);
    }
    
    /**
     * Returns the secret key used to interact with Skylable services
     *
     * @return string the secret key
     */
    public function getSecretKey() {
        return $this->_secret_key;
    }

    /**
     * Sets the user secret key
     * @param string $secret_key the secret key
     */
    public function setSecretKey($secret_key) {
        $this->_secret_key = $secret_key;
    }
    
    /**
     * Factory method: returns a new guest user
     * @return My_User
     */
    public static function newGuest() {
        $guest = new My_User(NULL);
        return $guest;
    }
    
    /**
     * Tells if this user is a guest.
     * @return boolean TRUE if is a guest, FALSE otherwise
     */
    public function isGuest() {
        return ($this->_role == self::ROLE_GUEST);
    }

    /**
     * Tells is this user is an admin
     * @return bool TRUE the user is an admin, FALSE otherwise
     */
    public function isAdmin() {
        return ($this->_role === self::ROLE_ADMIN);
    }

    /**
     * Tells if this user is registered.
     * 
     * An admin user is also registered.
     * 
     * @return bool TRUE the user is registered, FALSE otherwise
     */
    public function isRegistered() {
        return ($this->_role === self::ROLE_ADMIN || $this->_role === self::ROLE_REGISTERED);
    }

    /**
     * Register the roles that an user can have into and the Acl.
     * @param Zend_Acl $acl
     */
    public static function registerAclRoles(Zend_Acl $acl) {
        $acl->addRole( new Zend_Acl_Role(self::ROLE_GUEST));
        $acl->addRole( new Zend_Acl_Role(self::ROLE_REGISTERED));
        $acl->addRole( new Zend_Acl_Role(self::ROLE_ADMIN));
    }
    
    /**
     * Returns the role id (a string) for this user
     * @return string
     */
    public function getRoleId() {
        return $this->_role;
    }

    /**
     * Tells if the user is active.
     * 
     * @return bool TRUE if is active, FALSE otherwise
     */
    public function isActive() {
        return $this->_active;
    }
    
    public function copy(My_User $source) {
        $this->_id = $source->_id;
        $this->_active = $source->_active;
        $this->_login = $source->_login;
        $this->_email = $source->_email;
        $this->_secret_key = $source->_secret_key;
        $this->_role  = $source->_role;
        $this->_prefs = clone $source->_prefs;
    }
}
