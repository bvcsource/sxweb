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
 * Base action for all the frontend controllers.
 * 
 * Holds informations about:
 * - sort order
 * - last visited volume and path
 * 
 *
 */
class My_BaseAction extends Zend_Controller_Action {
    protected

            /**
             * @var My_Accounts the user model
             */
            $_user_model = NULL;

    public function preDispatch() {
        parent::preDispatch(); 
        $this->applyUserLocale();
    }

    /**
     * Tells if an user has rights to manage a volume.
     * 
     * The $volume_acl parameter is the return value of {@link Skylable_AccessSx::getVolumeACL}
     * 
     * @param array $volume_acl the volume ACL
     * @return bool
     */
    public function userCanManageVolume($volume_acl) {
        if (!Zend_Auth::getInstance()->hasIdentity()) {
            return FALSE;
        }
        
        if (empty($volume_acl) || !is_array($volume_acl)) {
            return FALSE;
        }
        
        // This preference key is populated upon login
        $whoami = Zend_Auth::getInstance()->getIdentity()->getPreferences()->get(My_User::PREF_WHO_AM_I);

        if (Zend_Auth::getInstance()->getIdentity()->getRoleId() === My_User::ROLE_ADMIN) {
            return TRUE;
        } else {
            foreach($volume_acl as $acl_info) {
                if (strcmp($acl_info['user'], $whoami) == 0) {
                    if (in_array('owner', $acl_info['perms'])) {
                        return TRUE;
                    } elseif (in_array('manager', $acl_info['perms'])) {
                        return TRUE;
                    }
                }
            }
        }
        return FALSE;
    }

    /**
     * Returns the URL to a shared file.
     * 
     * @param string $key the unique file key
     * @param string $path the complete path to the file
     * @return string the URL to access the file
     * @throws Zend_Exception
     */
    public function getSharedFileURL($key, $path) {
        return $this->getSXWebURL() . "/shared/file/" . $key . "/" . rawurlencode(basename($path));
    }

    /**
     * Returns the SXWeb base URL.
     * 
     * How this URL is generated:
     * - If the 'url' configuration parameter is not empty use it
     * - Use the View 'serverUrl' call to generate the URL: you need a view.
     * 
     * The 'url' configuration parameter is stored into the 'skylable' registry key.
     * 
     * @return string the URL
     * @throws Zend_Exception
     */
    public function getSXWebURL() {
        $url = '';
        if (Zend_Registry::isRegistered('skylable')) {
            $url = Zend_Registry::get('skylable')->get('url', FALSE);    
        }
        
        if ($url === FALSE || empty($url)) {
            $url = My_Utils::serverUrl();
        }
        return $url;
    }

    /**
     * Apply the preferred user locale (if any).
     */
    public function applyUserLocale() {
        if (Zend_Auth::getInstance()->hasIdentity()) {
            $pref_lang = Zend_Auth::getInstance()->getIdentity()->getPreferences()->get(My_User::PREF_LANGUAGE, FALSE);
            if (is_string($pref_lang)) {
                $locale = new Zend_Locale($pref_lang);
                Zend_Registry::set('Zend_Locale', $locale);
                $this->getTranslator()->setLocale($locale);
            }
        }
    }

    /**
     * Returns the global Zend_Translate object.
     * 
     * @return Zend_Translate
     * @throws Zend_Controller_Exception
     */
    public function getTranslator() {
        if (!Zend_Registry::isRegistered('Zend_Translate')) {
            // Use the default Translate object
            $t = $this->getInvokeArg('bootstrap')->getResource('translate');
            if (is_object($t)) {
                Zend_Registry::set('Zend_Translate', $t);
            } else {
                // Initializes a fake translator object
                $t = new Zend_Translate(
                    array(
                        'adapter' => 'array',
                        'content' => array('Foo' => 'Foo'),
                        'locale' => 'en',
                        'disableNotices' => TRUE
                    )
                );
                
                Zend_Registry::set('Zend_Translate',  $t );
            }
        }
        return Zend_Registry::get('Zend_Translate');
    }
    
    /**
     * Disable the view
     */
    public function disableView() {
        $this->_helper->viewRenderer->setNoRender(TRUE);
        $this->_helper->layout()->disableLayout();
    }

    /**
     * Enable the view
     */
    public function enableView() {
        $this->_helper->viewRenderer->setNoRender(FALSE);
        $this->_helper->layout()->enableLayout();
    }

    /**
     * Return the logger
     *
     * @return Zend_Log
     */
    public function getLogger() {
        return $this->getInvokeArg('bootstrap')->getResource('log');
    }

    /**
     * Returns the User model singleton.
     * 
     * @return My_Accounts the user model
     */
    public function getUserModel() {
        if (!is_object($this->_user_model)) {
            $this->_user_model = new My_Accounts();
        }
        return $this->_user_model;
    }
    
    /**
     * Returns the last visited path by a user, this includes the volume
     * which is the first component of the path.
     * 
     * Example: in '/foo/bar/baz'
     * /foo is the volume
     * /bar/baz the path on the volume
     * 
     * @return string
     */
    public function getLastVisitedPath() {
        if (Zend_Auth::getInstance()->hasIdentity()) {
            return Zend_Auth::getInstance()->getIdentity()->getPreferences()->get(My_User::PREF_LAST_VISITED_PATH);
        } else {
            return '';
        }
    }
    
    /**
     * Returns the file sort order or NULL if none is defined
     * 
     * @return integer
     */
    public function getFileSortOrder() {
        if (Zend_Auth::getInstance()->hasIdentity()) {
            return Zend_Auth::getInstance()->getIdentity()->getPreferences()->get(My_User::PREF_FILE_SORT_ORDER);
        } else {
            return 0;
        }
    }

    /**
     * Sets the file sort order.
     * 
     * @param Integer $order
     */
    public function setFileSortOrder($order) {
        if (Zend_Auth::getInstance()->hasIdentity()) {
            Zend_Auth::getInstance()->getIdentity()->getPreferences()->set(My_User::PREF_FILE_SORT_ORDER, (int)$order);
            $this->updateStorage();
        }
    }
    
    /**
     * Sets the last visited user path.
     * 
     * @param string $path the last visited path
     */
    public function setLastVisitedPath($path) {
        if (Zend_Auth::getInstance()->hasIdentity()) {
            Zend_Auth::getInstance()->getIdentity()->getPreferences()->set(My_User::PREF_LAST_VISITED_PATH, strval($path));
            $this->updateStorage();
        }
    }
     
    /**
     * Update the stored datas
     */
    protected function updateStorage() {
        if (Zend_Auth::getInstance()->hasIdentity()) {
            try {
                if ($this->getUserModel()->updateUserPreferences( Zend_Auth::getInstance()->getIdentity() )) {
                    $this->getInvokeArg('bootstrap')->getResource('log')->debug(__METHOD__ . ': success for ID: ' . strval( Zend_Auth::getInstance()->getIdentity()->getId() ) );
                } else {
                    $this->getInvokeArg('bootstrap')->getResource('log')->debug(__METHOD__ . ': failure for ID: ' . strval( Zend_Auth::getInstance()->getIdentity()->getId() ) );
                }
            }
            catch(Exception $e) {
                $this->getInvokeArg('bootstrap')->getResource('log')->err(__METHOD__ . ': exception: ' . $e->getMessage() );
            }
        }
    }

    /**
     * Show a path using a paginator.
     *
     * Prepare the paginator and puts it into the $view_paginator view slot.
     * The file list goes into the $view_slot.
     *
     * @param string $path the path to show
     * @param Skylable_AccessSx $access_sx
     * @param string $view_slot
     * @param string $view_paginator
     * @throws Zend_Paginator_Exception
     */
    protected function paginateFiles($path, Skylable_AccessSx $access_sx, $view_slot = 'list', $view_paginator = 'paginator') {
        // Get view configuration from user
        $page_size = 50;
        $user_page_size = Zend_Auth::getInstance()->getIdentity()->getPreferences()->get(My_User::PREF_PAGE_SIZE, -1);
        if (is_numeric($user_page_size)) {
            if ($user_page_size > 0) {
                $page_size = $user_page_size;
            }
        } else if (strlen($user_page_size) == 0) {
            $page_size = -1; // Don't paginate the results
        }

        try {
            $file_list = $access_sx->sxls($path, $this->getFileSortOrder() );    
        }
        catch(Exception $e) {
            if ($e instanceof Skylable_AccessSxException) {
                $file_list = FALSE;
                $this->view->putMessage()->addError($e->getMessage());
            } else {
                throw $e;
            }
        }
        
        if (is_array($file_list)) {
            // Puts dirs before files
            $file_list_dirs = array_filter($file_list, function($f){ return ($f['type'] == 'DIR'); });
            $file_list_files = array_filter($file_list, function($f){ return ($f['type'] == 'FILE'); });

            $paginator = Zend_Paginator::factory(array_merge($file_list_dirs, $file_list_files));
            $paginator->setItemCountPerPage( $page_size );
            $paginator->setPageRange( 9 );

            $current_page = $this->_getParam('page');
            if (preg_match('/^\d+$/', $current_page) == 1) {
                $current_page = abs(intval($current_page));
            } else {
                $current_page = 1;
            }

            $paginator->setCurrentPageNumber($current_page);

            $this->view->assign($view_paginator, $paginator);

            $this->view->assign($view_slot, $paginator->getCurrentItems());
        } else {
            $this->view->assign($view_slot, $file_list);
            $this->view->assign($view_paginator, NULL);
        }
    }

    /**
     * Tells if we are in demo mode.
     * 
     * @return bool
     */
    public static function isDemoMode() {
        if (defined('SXWEB_DEMO_MODE')) {
            return (bool)SXWEB_DEMO_MODE;
        }
    }

    /**
     * Returns an associative array of available languages.
     * 
     * Format is:
     * array(
     * 'language string' => 'language name'
     * )
     * 
     * @return array
     */
    public static function getLanguageList() {
        return array(
            'de' => 'Deutsch',
            'en' => 'English',
            'it' => 'Italiano',
            'pl' => 'Polski',
            'ru' => 'русский'
        );
    }

    /**
     * Centralized way to manage the userUse this method when you 
     * @param string $where where in the code the problem happened, set always as __METHOD__
     * @param Exception $e the exception
     * @param Skylable_AccessSx $access_sx the source of the exception
     */
    public function invalidCredentialsExceptionHandler($where = '', Exception $e, Skylable_AccessSx $access_sx, $http_response_code = 403) {
        $this->getResponse()->setHttpResponseCode($http_response_code);
        if (empty($where)) {
            $where = __METHOD__;
        }
        $this->getLogger()->err($where. ': Skylable_AccessSx library error, last command: '.var_export($access_sx->getLastExecutedCommand(), TRUE));
        $this->getLogger()->err($where. ': Error code: '.strval($e->getCode()).' Errors:'. $e->getMessage());

        $this->view->error_title = $this->getTranslator()->translate('Invalid credentials!');
        $this->view->error_message = $this->getTranslator()->translate('Your credentials are changed, please authenticate again.');
        $this->view->error_message .= ' '.sprintf('<a href="/login" title="%s">%s</a>', $this->getTranslator()->translate('Sign in again...'), $this->getTranslator()->translate('Lets you sign in again') );
        $this->_helper->layout()->setLayout('application-failure');
        $this->_helper->layout()->assign('exception', $e);
        $this->_helper->layout()->assign('show_message', FALSE);
        $this->renderScript('error/malfunction.phtml');

        $this->logoutUser();
    }

    /**
     * Do what's needed to log out an user.
     */
    protected function logoutUser() {
        if (!Zend_Auth::getInstance()->hasIdentity()) {
            return FALSE;
        }
        try {
            $access_sx = new Skylable_AccessSx( Zend_Auth::getInstance()->getIdentity(), '', array('initialize' => FALSE) );
            $access_sx->purgeProfile();
        }
        catch(Exception $e) {

        }
        Zend_Auth::getInstance()->clearIdentity();
        Zend_Session::forgetMe();
        Zend_Session::destroy();
    }

}
