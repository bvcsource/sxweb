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
        ROLE_REGISTERED = 'registered';
     
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
            $_email,
            $_auth_token,
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
     * @param string $email the user name
     * @param string $auth_token the SX auth token
     * @param string $role the user role
     */
    public function __construct($id, $email = '', $auth_token = '', $role = self::ROLE_GUEST) {
        $this->_id = $id;
        $this->_email = $email;
        $this->_auth_token = $auth_token;
        $this->_role = ($role !== self::ROLE_REGISTERED ? self::ROLE_GUEST : $role);
        $this->_prefs = new My_Preferences(array(
            self::PREF_FILE_SORT_ORDER => 0,
            self::PREF_LAST_VISITED_PATH => '',
        ));
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
     * Return the user email.
     * 
     * @return string the enail
     */
    public function getEmail() {
        return $this->_email;
    }
    
    /**
     * Return the user auth token to interact with Skylable services
     * 
     * @return string the auth token
     */
    public function getAuthToken() {
        return $this->_auth_token;
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
        return ($this->_role === 'guest');
    }

    /**
     * Register the roles that an user can have into and the Acl.
     * @param Zend_Acl $acl
     */
    public static function registerAclRoles(Zend_Acl $acl) {
        $acl->addRole( new Zend_Acl_Role(self::ROLE_GUEST));
        $acl->addRole( new Zend_Acl_Role(self::ROLE_REGISTERED));
    }
    
    /**
     * Returns the role id (a string) for this user
     * @return string
     */
    public function getRoleId() {
        return $this->_role;
    }
}
