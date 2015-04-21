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
 * The site wide ACL.
 * 
 * Here you can control what an user can access.
 *
 */
class My_Acl extends Zend_Acl {
    public function __construct() {
        
        // Let the user implementation define the roles
        My_User::registerAclRoles($this);

        $this->addResource('default/error');
        $this->addResource('default/error/error');
        $this->addResource('default/activateaccount');
        $this->addResource('default/activateaccount/index');
        $this->addResource('default/index');
        $this->addResource('default/index/index');
        
        $this->addResource('default/index/login');
        $this->addResource('default/index/resetpassword');
        $this->addResource('default/index/reset');
        $this->addResource('default/index/logout');
        $this->addResource('default/index/demo');
        $this->addResource('default/ajax');
        $this->addResource('default/ajax/shared');
        $this->addResource('default/search');
        $this->addResource('default');
        $this->addResource('menu');
        $this->addResource('search');
        $this->addResource('settings');
        $this->addResource('share/index/share');
        
        $this->addResource('default/index/ping');
        $this->addResource('default/upload/upload');
        
        // NOTE: the "resource" is a string in the form:
        // 'module/controller/action' or 'module/controller' or 'module'.
        // 
        // A guest user is limited
        //
        $this->allow(My_User::ROLE_GUEST, 
                array('default/error', 'default/error/error', 
            'default/activateaccount/index',  
            'default/index/login', 'default/index/demo', 
            'default/index/resetpassword', 'default/index/reset',
            'default/ajax/shared', 'share/index/share', 'default/index/ping', 'default/ajax', 'default/upload/upload'
                    ) 
                );
        
        // A registered user can do anything
        $this->allow(My_User::ROLE_REGISTERED, null);
        $this->allow(My_User::ROLE_ADMIN, null);
    }
}
