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
 * This is the main ACL plugin: controls the access to the
 * controllers and actions of the web application.
 *
 */
class My_Plugin_Acl extends Zend_Controller_Plugin_Abstract {
    private
            $_acl;
    
    public function __construct(Zend_Acl $acl) {
        $this->_acl = $acl;
    }
    
    public function preDispatch(Zend_Controller_Request_Abstract $request) {
        
        // Retrieve the user
        $user = (Zend_Auth::getInstance()->hasIdentity() ? Zend_Auth::getInstance()->getIdentity() : NULL);
        if (!is_object($user)) {
            $user = My_User::newGuest();
        }
        
        // Get the destination
        $action = $request->getActionName();
        $controller = $request->getControllerName();
        $module = $request->getModuleName();
        
        $log = Zend_Controller_Front::getInstance()->getParam('bootstrap')->getResource('log');
        $log->debug(__METHOD__.': current route: '.print_r(Zend_Controller_Front::getInstance()->getRouter()->getCurrentRouteName(), TRUE) );
        $log->debug('--------> ACL Check');
        $log->debug(
                sprintf('Check ACL: user: %s - module: %s - controller: %s - action: %s', 
                        $user->getRoleId(),
                        (is_null($module) ? 'null' : $module ),
                        (is_null($controller) ? 'null' : $controller ),
                        (is_null($action) ? 'null' : $action )
                )
         );
        
        // Speeds up the ACL check for registered users
        if ($user->getRoleId() == 'registered') {
            $log->debug('Registered user, can do anything!');
            $log->debug('<-------- ACL Check');
            return;
        }
        
        $is_allowed = FALSE;
        
        // Check for module access
        try {
            if (empty($module)) {
                $module = 'default';
            }
            $log->debug('ACL #0 - module: ' . $module);
            $is_allowed = $this->_acl->isAllowed($user, $module, 'view');
            
        }
        catch(Exception $e) {
            $is_allowed = FALSE;
            $log->debug('ACL #0 - Route not found!');
        }
        
        try {
            /*
             * When a resource is not found or registered the ACL raises an 
             * exception: we catch it to avoid error messages
             */
            $log->debug('ACL #1 - module/controller: ' . $module. '/' .$controller );
            $is_allowed = $this->_acl->isAllowed($user, $module . '/' .$controller, 'view');
        }
        catch(Exception $e) {
            $is_allowed = FALSE;
            $log->debug('ACL #1 - Route not found!');
        }
        
        try {

            if (!$is_allowed && !is_null($action)) {
                $log->debug('ACL #2 - module/controller/action: '. $module . '/'. $controller . '/' . $action );
                
                $is_allowed = $this->_acl->isAllowed($user, $module . '/' . $controller . '/' . $action, 'view');
            }
        }
        catch(Exception $e) {
            $is_allowed = FALSE;
            $log->debug('ACL #2 - Route not found!');
        }
        
        $log->debug('<-------- ACL Check');

        if (!$is_allowed) {
            $log->debug('ACL - Not allowed!');
            
            /**
             * If we are here, we are a guest: save the referer and
             * move to the login page.
             * I don't like this, but marketing decides...
             */
            $request->clearParams();
            $session = new Zend_Session_Namespace();
            $session->referer = $request->getRequestUri();
            $log->debug('ACL - Saved referer: ' . $request->getRequestUri() );
            
            return $request->setModuleName('default')
                    ->setControllerName('index')
                    ->setActionName('login');
        }
    }
    
}
