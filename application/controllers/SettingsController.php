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
 * Manage user settings.
 *
 */
class SettingsController extends My_BaseAction {

    public function preDispatch() {
        parent::preDispatch();
        $this->_helper->layout()->setLayout('clean');
    }


    public function indexAction() {
        // $this->forward('account');
    }

    /**
     * Manages user preferences.
     * 
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public function preferencesAction() {
        $pref_form = new Application_Form_UserPreferences();
        $pref_form->setAction('/settings/preferences')->setMethod('post');
        $pref_form->setDecorators(array(
            'FormErrors',
            'Form'
        ));
        $this->view->form = $pref_form;
        
        // Pre-compile the form.
        $lang = Zend_Auth::getInstance()->getIdentity()->getPreferences()->get(My_User::PREF_LANGUAGE, '');
        if (empty($lang)) {
            if (Zend_Registry::isRegistered('Zend_Locale')) {
                // Gets the current language, if any
                $lang = Zend_Registry::get('Zend_Locale')->getLanguage();
            } elseif (Zend_Registry::isRegistered('Zend_Translate')) {
                // Gets the current language, if any
                $lang = Zend_Registry::get('Zend_Translate')->getLocale()->getLanguage();
            } else {
                $def_lang = Zend_Registry::get('skylable')->get('default_language', NULL);
                if (!empty($def_lang) && $def_lang !== 'auto' ) {
                    $lang = $def_lang;
                } else {
                    // Defaults to english
                    $lang = 'en';    
                }
            }
        }
        $pref_form->setDefault('frm_language', $lang);
        
        $page_size = Zend_Auth::getInstance()->getIdentity()->getPreferences()->get(My_User::PREF_PAGE_SIZE, -1);
        if ($page_size > 0) {
            $pref_form->setDefault('frm_file_list_size', $page_size);
        } else {
            $pref_form->setDefault('frm_file_list_size', 20);
        }
        

        if (!$this->getRequest()->isPost()) {
            return FALSE;
        }

        if (!$pref_form->isValid($_POST)) {
            return FALSE;
        }

        $user = Zend_Auth::getInstance()->getIdentity();
        
        $user->getPreferences()->set(My_User::PREF_LANGUAGE, $pref_form->getValue('frm_language'));
        $user->getPreferences()->set(My_User::PREF_PAGE_SIZE, $pref_form->getValue('frm_file_list_size'));
        
        try {
            if ($this->getUserModel()->updateUserPreferences( Zend_Auth::getInstance()->getIdentity() )) {
                $this->view->show_success = TRUE;
                Zend_Auth::getInstance()->getStorage()->write($user);
                $this->applyUserLocale();
            } else {
                $pref_form->addError($this->getTranslator()->translate('Failed to update user preferences, please retry later.'));
            }
        }
        catch(Exception $e) {
            $pref_form->addError('Internal error: update failed.');
            $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());
        }
        
    }
    
    public function sxdriveAction() {
        
    }
    
    /**
     * Manage account settings
     */
    public function accountAction() {

        $form = new Application_Form_UserSettings();
        $form->setAction('/account_settings')->setMethod('post');
        
        $form->setDecorators(array(
            // 'FormElements',
            'FormErrors',
            'Form'
        ));
        
        $this->view->form = $form;
        
        if (!$this->getRequest()->isPost()) {
            return $this->render('account');
        }

        if ($this->isDemoMode()) {
            $this->redirect('/demo');
            return FALSE;
        }
        
        if (!$form->isValid($_POST)) {
            return $this->render('account');
        }
        
        if (!My_Utils::passwordRecoveryIsAllowed()) {
            return FALSE;
        }
        
        try {
            $values = $form->getValues();

            // Create a fake admin user into a temp dir
            $tempdir = My_Utils::mktempdir( Zend_Registry::get('skylable')->get('sx_local') ,'Skylable_');
            if ($tempdir === FALSE) {
                throw new Exception('Failed to create temporary directory');
            }

            $fake_admin = new My_User(NULL, 'admin', '', Zend_Registry::get('skylable')->get('admin_key'));
            $access_sx = new Skylable_AccessSxNew($fake_admin, $tempdir, array( 'user_auth_key' => Zend_Registry::get('skylable')->get('admin_key') ) );
            $this->getLogger()->debug(__METHOD__ . ': Old user key: '.var_export( Zend_Auth::getInstance()->getIdentity()->getSecretKey(), TRUE ));
            $new_user_key = $access_sx->sxaclUserNewKey($values['frm_new_password'], Zend_Auth::getInstance()->getIdentity()->getLogin());

            $this->getLogger()->debug(__METHOD__ . ': New user key: '.var_export( $new_user_key, TRUE ));
            
            My_Utils::deleteDir($tempdir);
            if ($new_user_key === FALSE) {
                $form->addError('Update failed: the current password isn\'t valid.');
            } else {
                $this->view->assign('show_success', TRUE);
                
                // Re-initialize the user
                $this->getLogger()->debug(__METHOD__ . ': Re-initializing the user.');
                $user = Zend_Auth::getInstance()->getIdentity();
                $user->setSecretKey('');
                $access_sx = new Skylable_AccessSxNew( $user, NULL, array( 'password' => $values['frm_new_password'], 'initialize' => FALSE ) );
                $init_ok = $access_sx->initialize(TRUE);
                
                if ($init_ok) {
                    $user_secret_key = $access_sx->getLocalUserSecretKey();
                    $user->setSecretKey( $user_secret_key );
                    $this->getUserModel()->updateUser($user);
                    Zend_Auth::getInstance()->getStorage()->write($user);
                }
            }
        }
        catch(Exception $e) {
            $form->addError('Internal error: update failed.');
            if (isset($tempdir)) {
                if (@is_dir($tempdir)) {
                    My_Utils::deleteDir($tempdir);
                }
            }
        }

    }

    /**
     * Serve the SXDrive config file.
     *
     * The file is named: sxconfig.sx
     * @throws Zend_Exception
     */
    public function clusterConfigAction() {
        $this->disableView();
        /*
         * Format is:
         *
         * sx://$clustername;token=$authkey,volume=$volname
         */
        try {
            $access_sx = new Skylable_AccessSxNew( Zend_Auth::getInstance()->getIdentity() );

            $str = $access_sx->getUserLink();    
        }
        catch (Exception $e) {
            $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());
            $str = '';
        }
        

        $this->getResponse()->setRawHeader("Cache-Control: no-cache, must-revalidate");
        $this->getResponse()->setRawHeader("Pragma: no-cache");
        $this->getResponse()->setRawHeader('Content-Disposition: attachment; filename="sxconfig.sx"');
        $this->getResponse()->setRawHeader("Content-Type: text/plain; charset=UTF-8");
        $this->getResponse()->setRawHeader("Content-Length: ".strval( strlen($str) ));
        // $this->getResponse()->setRawHeader('Content-Transfer-Encoding: binary');
        echo $str;
    }

}
