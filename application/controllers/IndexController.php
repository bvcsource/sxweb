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
 * The main action controller
 * 
 */
class IndexController extends My_BaseAction {

    /**
     * Create a new user account.
     *
     * Parameters:
     * 'key' - string - the external user key: if present automatically fill in the 'user key' field.
     * 
     */
    public function createAction() {

        if ($this->isDemoMode()) {
            $this->redirect('/demo');
            return FALSE;
        }
        
        // Sets the simple layout
        $this->_helper->layout->setLayout('simple');
        
        // Initializes the login form
        $form = new Application_Form_CreateAccount();
        $form->setAction('/index/create')->setMethod('post');
        
        $form->setDecorators(array(
            'FormElements',
            'FormErrors',
            'Form'
        ));
        
        $this->view->assign('form', $form);

        $session = new Zend_Session_Namespace();

        // Checks for the user key from the url
        if ($this->getRequest()->isGet()) {
            $user_key = $this->getRequest()->getParam('key');
            $validate_key = new My_ValidateUserKey();
            if ($validate_key->isValid($user_key)) {
                // Tell the layout that we have a user key from request
                $this->getInvokeArg('bootstrap')->getResource('Log')->debug('User supplied key: '.strval($user_key));
                $this->view->has_user_key = TRUE;
                $form->setDefault('frm_user_key', $user_key);
                $session->user_key = $user_key;
            }
        }

        // If the form is not launched, shows it blank
        if (!$this->getRequest()->isPost()) {
            return $this->render("create");
        }

        // If the form is not valid, shows errors
        if (!$form->isValid($_POST)) {
            // Check for the saved key into the session
            if (isset($session->user_key)) {
                $the_key = $form->getValue('frm_user_key');
                if (strcmp($the_key, $session->user_key) == 0) {
                    $this->view->has_user_key = TRUE;
                }
            }

            return $this->render('create');
        }
        
        $values = $form->getValues();
        
        $new_account_data = array(
            'email' => $values['frm_email'],
            'password' => $values['frm_password'],
            'password_is_plain' => TRUE,
            'secret_key' => $values['frm_user_key'],
            'is_active' => FALSE,
            'activation_key' => bin2hex(openssl_random_pseudo_bytes(20)),
            'role' => My_User::ROLE_REGISTERED
        );
        
        $accounts_model = new My_Accounts();
        $account_key_model = new My_AccountKeys();
        
        try {
            $user_id = $accounts_model->createAccount($new_account_data);

            $view = Zend_Layout::getMvcInstance()->getView();
            $view->hash  = $account_key_model->getAccountActivationHash($new_account_data['activation_key'], $new_account_data['email'], $user_id);
            $view->url = Zend_Registry::get('skylable')->url;

            $mail = new Zend_Mail();

            $html_msg = $view->render("create_account_mail.phtml");
            $mail->setBodyText( strip_tags( $html_msg ) );
            $mail->setBodyHtml( $html_msg );
            
            $mail->addTo($new_account_data['email']);

            $mail->setSubject($view->translate("SXWeb - The new account has been created."));

            $mail->send( $this->getInvokeArg('bootstrap')->getResource('Mail')->getMail() );
        }
        catch(Zend_Mail_Transport_Exception $e) {
            $this->getInvokeArg('bootstrap')->getResource('log')->debug('CreateAction: exception '. strval($e->getCode()) . ' - '.$e->getMessage() );

            $form->addError("Internal Error: failed to send your activation email, please retry later.");
            return $this->render('create');
        }
        catch(Exception $e) {
            $this->getInvokeArg('bootstrap')->getResource('log')->debug('CreateAction: exception '. strval($e->getCode()) . ' - '.$e->getMessage() );
            if ($e->getCode() == 23000) {
                // Integrity constraint violation
                $form->getElement('frm_email')->addError("Account already created.");
            } else {
                $form->addError("Internal Error: can't create account, please retry later.");
            }
            return $this->render('create');
        }

        // Clean the session
        if (isset($session->user_key)) {
            unset($session->user_key);
        }

        // The user is successfully created
        return $this->render('create_success');
    }

    /**
     * Do the login
     * 
     */
    public function loginAction() {
        
        if (Zend_Auth::getInstance()->hasIdentity()) {
            return $this->redirect('/');
        }

        // Sets the simple layout
        $this->_helper->layout->setLayout('simple');
        
        // Initializes the login form
        $form = new Application_Form_Login();
        $form->setAction('/index/login')->setMethod('post');
        
        $this->view->assign('form', $form);
        
        $form->setDecorators(array(
            'FormElements',
            'FormErrors',
            'Form'
        ));
        
        // If the form is not launched, shows it blank
        if (!$this->getRequest()->isPost()) {
            return $this->render("login");
        }
        
        
        // If the form is not valid, shows errors
        if (!$form->isValid($_POST)) {
            return $this->render('login');
        }
        
        // Validate and authenticate the user
        $values = $form->getValues();
        
        // Check if we should remember the user
        $remember_me = $this->getRequest()->getParam('frm_remember_me');
        if ($remember_me === 'yes') {
            $this->getInvokeArg('bootstrap')->getResource('log')->debug('Index::Login - Remembering the user');
            Zend_Session::rememberMe( Zend_Registry::get('skylable')->get('remember_me_cookie_seconds') );
        } else {
            Zend_Session::forgetMe();
        }
        
        try {
            // Store the user identity, if credentials are ok.
            
            $user = $this->getUserModel()->checkUserCredentials( $values['frm_login'], $values['frm_password'] );
            if (!is_object($user)) {
                $form->addError("Email address or password are wrong, please retry.");
                return $this->render('login');
            }
            
            Zend_Auth::getInstance()->getStorage()->write($user);
        }
        catch(Exception $e) {
            $form->addError("Internal error, please retry later.");
            Zend_Session::forgetMe();
            Zend_Auth::getInstance()->clearIdentity();
            return $this->render('login');
        }

        try {
            $access_sx = new Skylable_AccessSxNew( Zend_Auth::getInstance()->getIdentity() );
            $whoami = $access_sx->whoami();
            $this->getLogger()->debug('You are: '.var_export($whoami, TRUE));
            if (!empty($whoami)) {
                Zend_Auth::getInstance()->getIdentity()->getPreferences()->set('whoami', $whoami);
            }
        }
        catch(Exception $e) {

        }
        
        
        /**
         * Re-initializes the user preferences: now that we have a valid identity
         * we could retrieve the correct values.
         * 
         * If we have a referer to be redirected, retrieve it and go
         * 
         */
        $session = new Zend_Session_Namespace();
        if (isset($session->referer)) {
            $referer = $session->referer;
            unset($session->referer);
            
            // Only some URLs gets you redirected
            $valid_redirect = array(
                '/vol','/open', '/account_settings'
            );
            $ok_redirect = FALSE;
            foreach($valid_redirect as $redirect_part) {
                $pos = strpos($referer, $redirect_part);
                $this->getInvokeArg('bootstrap')->getResource('log')->debug('Part '.$redirect_part .' at Position: '.  var_export($pos, TRUE));
                if ($pos !== FALSE) {
                    if ($pos == 0) {
                        $ok_redirect = TRUE;
                        break;
                    }
                }
            }
            if (!$ok_redirect) {
                $this->getInvokeArg('bootstrap')->getResource('log')->debug('Ignore redirect to referer: '.$referer);
                $referer = '';
            }
            if (!empty($referer)) {
                $this->getInvokeArg('bootstrap')->getResource('log')->debug('Login redirects to referer: '.$referer);
                $this->redirect($referer);
                return;
            }
        }
        
        
        $last_url = $this->getLastVisitedPath();
        if (!empty($last_url)) {
            $this->getInvokeArg('bootstrap')->getResource('log')->debug('Login redirects to path: '.$last_url);
            $this->redirect('/vol'.My_Utils::slashPath($last_url));
            return;
        }
        
        $this->getRequest()->clearParams();
        $this->redirect('/');
    }
  
    /**
     * Main entry point.
     * 
     * Parameters:
     * 'sort' - integer - sort order
     * 'url' - string - directory on the server
     * 'dont_show_welcome' - string - if 'yes' don't show the welcome dialog next time the user logs in.
     * 
     * @return boolean
     * @throws Exception
     */
    public function indexAction() {
        $logger = $this->getInvokeArg('bootstrap')->getResource('log');
        
        // Enforce use of the standard layout
        $this->_helper->layout->setLayout('layout');
        
        $logger->debug("IndexAction - Step #1");
        if (!Zend_Auth::getInstance()->hasIdentity()) {
            $this->redirect("/login");
            return FALSE;
        }
        $this->view->cluster = Zend_Registry::get('skylable')->get('cluster');

        // Get the path from the request
        $path_validator = new My_ValidatePath();
        $path = '';
        if ($path_validator->isValid( $this->getParam('path') )) {
            $path = "/" . $path_validator->value . "/"; 
            $logger->debug(__METHOD__. ": path from remote is :".$path);
        } else {
            $logger->debug(__METHOD__. ": path from remote is empty!");
        }
        
        // Checks for sort order change
        $sort_order_check = new My_ValidateSortOrder();
        if ($sort_order_check->isValid($this->getRequest()->getParam('sort'))) {
            $this->setFileSortOrder( $this->getRequest()->getParam('sort') );
        }
        
        $this->view->sort = $this->getFileSortOrder();

        try {

            $access_sx = new Skylable_AccessSxNew(Zend_Auth::getInstance()->getIdentity());

            $logger->debug("IndexAction - Step #2");
            $this->view->volumes = $access_sx->listVolumes();
            
            // Get the volume from the path
            $volume = My_Utils::getRootFromPath($path);
            
            // Check if the volume exists
            if (!empty($volume)) {
                $volume_exists = FALSE;
                foreach($this->view->volumes as $vol) {
                    if (strcmp($volume, My_Utils::getRootFromPath($vol['path']) ) == 0) {
                        $volume_exists = TRUE;
                        break;
                    }
                }
                
                // Volume don't exists, clear also the path.
                // we will get a default one
                if (!$volume_exists) {
                    $logger->debug(__METHOD__. ": volume " . $volume ." don't exists ");
                    $path = '';
                    $volume = '';
                }
            }
            
            // If we don't have an URL use the first volume
            // in the volume list or the last visited volume.
            if (empty($volume)) {
                $volume = My_Utils::getRootFromPath($this->getLastVisitedPath());
                if (!empty($volume)) {
                    $path = $volume;
                    $this->setLastVisitedPath($path);
                    $logger->debug('Using last visited volume: '.$path);
                } elseif (count($this->view->volumes) > 0) {
                    $vol = reset( $this->view->volumes );
                    $path = $vol['path'];
                    $volume = My_Utils::getRootFromPath($path);
                    $this->setLastVisitedPath($path);
                    $logger->debug('Using first volume: '.$path);
                } else {
                    $logger->debug('No volumes found!');
                    // Shows a blank page: no volumes at all.
                    $this->_helper->layout->setLayout('clean');
                    $this->render('no-volumes');
                    return FALSE;
                }
            }
            
            if (!empty($path)) {
                $logger->debug('Saving path: '.$path);
                $this->setLastVisitedPath($path);
            }
            
            $logger->debug('URL is: '.$this->getLastVisitedPath());
            
            // Verifies if the volume is encrypted: if so
            // shows the password request and handle it
            try {
                $logger->debug('Volume from URL is: '.$volume);
                if ($access_sx->volumeIsEncrypted($volume)) {
                    $logger->debug('IndexController:index: volume is encrypted.');
                    if (!$access_sx->volumeIsUnlocked( $volume )) {
                        $logger->debug('IndexController:index: volume is locked.');

                        // Check if the unlock form is submitted
                        $show_unlock_form = TRUE;
                        $form = new Zend_Form();
                        $unlock_password = $this->getRequest()->getParam('frm_vol_password');
                        if (!is_null($unlock_password) && $this->getRequest()->isPost() ) {
                            $validate_vol_password = new My_ValidateVolumePassword();
                            if ($validate_vol_password->isValid($unlock_password)) {
                                $result = NULL;
                                if ($access_sx->unlockVolume($volume, $unlock_password)) {
                                   $show_unlock_form = FALSE; 
                                } else {
                                    $logger->debug(__METHOD__.' unlocking volume errors: '.print_r($access_sx->getLastErrorLog(), TRUE));
                                    $form->addError("Volume unlock failed. Wrong password?");
                                }
                            } else {
                                $form->addError("Incorrect password.");
                            }
                        }

                        if ($show_unlock_form) {
                            $form->addDecorators(array('FormErrors', 'Form'));
                            $form->setAction( $this->getRequest()->getRequestUri() )->setMethod('post');
                            $this->view->form = $form;
                            $this->_helper->layout->setLayout('clean');
                            $this->render('locked-volume');

                            return;
                        }
                    }
                }
            }
            catch(Exception $e) {
                $logger->debug('Exception: '.$e->getMessage());
                $this->forward('error', 'error');
                return;
            }
            
            $this->view->url = $this->getLastVisitedPath();
            $logger->debug('View URL is: '.$this->view->url);
            $this->_helper->layout->title = "Index of " . $this->getLastVisitedPath();

            
            $logger->debug("IndexAction - Step #3");
            $logger->debug('Listing: ' . $this->view->url );

            // Pagination
            $this->paginateFiles($this->view->url, $access_sx);

            // $this->view->list = $access_sx->sxls($this->view->url, $this->getFileSortOrder() );
            
            $logger->debug("IndexAction - Step #4");
            $this->view->acl = $access_sx->getVolumeACL( My_Utils::getRootFromPath( $path ) );

            $session = new Zend_Session_Namespace();

            if (!empty($this->view->url)  ) {
                if (!$this->view->canWriteToVolume() ) {
                    if ($session->readOnlyMSG != true) {
                        $session->readOnlyMSG = true;
                        $this->view->putMessage()->addInfo('No write access to this volume.');
                    }
                }
                if (!$this->view->hasReadAccess()) {
                    if ($session->writeOnlyMSG != true) {
                        $session->writeOnlyMSG = true;
                        $this->view->putMessage()->addInfo('No read access to this volume.');
                    }
                }
            }

            // Interacts with the user welcome dialog
            $this->view->show_welcome_window = TRUE;

            if (isset($session->welcome_dialog_viewed)) {
                $this->view->show_welcome_window = FALSE;
            }

            if (Zend_Auth::getInstance()->getIdentity()->getPreferences()->get(My_User::PREF_DONT_SHOW_WELCOME, FALSE) ) {
                $this->view->show_welcome_window = FALSE;
            }

        }
        catch (Skylable_AccessSxException $e) {
            $logger->err(__METHOD__. ': Skylable_AccessSxNew library error, last command: '.var_export($access_sx->getLastExecutedCommand(), TRUE));
            $logger->err(__METHOD__. ': Error code: '.strval($e->getCode()).' Errors:'. $e->getMessage());

            $this->view->error_title = 'Internal error!';
            $this->view->error_message = 'Application encountered an internal error. Please retry in a few seconds.';
            $this->_helper->layout()->setLayout('application-failure');
            $this->_helper->layout()->assign('exception', $e);
            $this->_helper->layout()->assign('show_message', FALSE);
            $this->renderScript('error/malfunction.phtml');

            return FALSE;
        }
        catch (Exception $e) {
            $logger->err(__METHOD__. ': An error occurred: CODE: '.strval($e->getCode()).' MSG:'. $e->getMessage());
            // Quick fix...
            $this->forward('malfunction', 'error');
            return FALSE;
        }
    }

    /**
     * Lets you download files.
     *
     * Parameters:
     * 'path' - the file path to download
     *
     */
    public function openAction() {
        try {

            $this->view->error_title = '';
            $this->view->error_message = '';
            $this->_helper->layout()->assign('show_message', FALSE);
            $allow_download = TRUE;

            $continue_browsing = ' <a href="/" title="Continue browsing files...">Continue browsing files...</a>';

            $filename = $this->getRequest()->getParam('path');
            $filename_check = new My_ValidatePath();
            if ($filename_check->isValid($filename)) {

                $this->getLogger()->debug(__METHOD__.': Downloading: '.print_r($filename, TRUE));

                $tickets = new My_Tickets();

                if ($tickets->registerTicket( Zend_Auth::getInstance()->getIdentity()->getId(), NULL ) === FALSE) {
                    $this->getResponse()->setHttpResponseCode(500);
                    $this->view->error_title = 'Too many concurrent downloads!';
                    $this->view->error_message = 'Please wait a minute and retry. '.$continue_browsing;
                    $allow_download = FALSE;
                } else {
                    $access_sx = new Skylable_AccessSxNew( Zend_Auth::getInstance()->getIdentity() );

                    // Get file data
                    $file_data = $access_sx->getFileInfo($filename);

                    if ($file_data === FALSE) {
                        // File not found.
                        $allow_download = FALSE;
                        $this->getResponse()->setHttpResponseCode(404);
                        $this->view->error_title = 'File not found!';
                        $this->view->error_message = 'The file &quot;'.htmlentities($filename).'&quot; was not found.'.$continue_browsing;
                    } else {
                        if ($file_data['type'] !== 'FILE') {
                            $this->getResponse()->setHttpResponseCode(500);
                            $this->view->error_title = 'Invalid file type!';
                            $this->view->error_message = 'The file &quot;'.htmlentities($filename).'&quot; can\'t be downloaded.'.$continue_browsing;
                            $allow_download = FALSE;
                        }
                    }
                }
            } else {
                $this->getLogger()->debug(__METHOD__.': Invalid filename: '.print_r($filename, TRUE));
                $this->getResponse()->setHttpResponseCode(404);
                $this->view->error_title = 'File not found!';
                $this->view->error_message = 'The file &quot;'.htmlentities($filename).'&quot; was not found.'.$continue_browsing;
                $allow_download = FALSE;
            }

            if ($allow_download) {
                $this->disableView();
                $res = new My_DownloadResponse($access_sx, $file_data);
                $this->getFrontController()->setResponse($res);
            } else {
                $this->_helper->layout()->setLayout('application-failure');
                $this->renderScript('error/malfunction.phtml');
            }
        }
        catch(Exception $e) {
            $this->enableView();
            $this->getResponse()->setHttpResponseCode(500);
            $this->view->error_title = 'Internal error!';
            $this->view->error_message = 'Application encountered an internal error.'.$continue_browsing;
            $this->_helper->layout()->setLayout('application-failure');
            $this->_helper->layout()->assign('exception', $e);
            $this->renderScript('error/malfunction.phtml');
        }
    }

    /**
     * Do the logout action
     */
    public function logoutAction() {
        $this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout->disableLayout();

        Zend_Auth::getInstance()->clearIdentity();
        Zend_Session::forgetMe();
        Zend_Session::destroy();
        
        $this->redirect('/index');
    }
    
    /**
     * Tells if the current user is logged.
     * Returns:
     * 'PONG' if the user is logged
     * http 403 code if not logged anymore
     */
    public function pingAction() {
        $this->getInvokeArg('bootstrap')->getResource('log')->debug('Index::Login - Preventing AJAX bug');
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        if (!Zend_Auth::getInstance()->hasIdentity()) $this->getResponse()->setHttpResponseCode(403);
        $this->getResponse()->setHeader('Content-Type', 'text/plain', TRUE);
        $this->getResponse()->clearBody();
        $this->getResponse()->appendBody( 'PONG' );
        return FALSE;
    }

    /**
     * 
     * Reset password step #1 - request.
     * 
     * Shows the form to request a password reset and send 
     * the confirmation email.
     * 
     */
    public function resetpasswordAction() {
        
        if ($this->isDemoMode()) {
            $this->redirect('/demo');
            return FALSE;
        }
        
        if (Zend_Auth::getInstance()->hasIdentity()) {
            $this->redirect('/index');
        }
        
        $this->_helper->layout->setLayout("simple");
        
        $the_email = $this->getRequest()->getParam('email');
        $validate_email = new Zend_Validate_EmailAddress();
        
        if ($validate_email->isValid($the_email)) {
            $model = new My_Accounts();
            try {
                $token = $model->generatePasswordResetToken($the_email);
                if ($token === FALSE) {
                    // Invalid user
                    $this->view->assign('errors', array($this->view->translate('Failed to send the email, please retry again later.')) );
                    $this->getLogger()->err(__METHOD__.': invalid user email request ');
                } else {
                    // Send the email
                    $view = Zend_Layout::getMvcInstance()->getView();
                    $view->hash = $token;
                    $view->url = Zend_Registry::get('skylable')->url;

                    try {

                        $mail = new Zend_Mail();
                        $html_msg = $view->render("mail.phtml");
                        $mail->setBodyText( strip_tags( $html_msg ) );
                        $mail->setBodyHtml( $html_msg );
                        $mail->addTo($the_email);
                        $mail->setSubject($view->translate('SXWeb - Password Reset Request'));
                        
                        $mail->send( $this->getInvokeArg('bootstrap')->getResource('Mail')->getMail() );
                        $this->view->assign('notifications', array($this->view->translate('We have sent you an email with a link to follow to reset your password.')) );
                    }
                    catch(Zend_Mail_Transport_Exception $e) {
                        $this->view->assign('errors', array($this->view->translate('Failed to send the email, please retry again later.')) );
                        $this->getLogger()->err(__METHOD__.': send mail exception: '.$e->getMessage());
                    }
                    
                }
                
            }
            catch(Exception $e) {
                if ($e->getCode() == My_Accounts::EXCEPTION_RESET_PASSWORD_TOO_MANY_TICKETS) {
                    $this->view->assign('errors', array($this->view->translate('You have requested too many password reset tickets, please wait 24 hours and retry.')) );       
                } else {
                    $this->view->assign('errors', array($this->view->translate('Failed to send the email, please retry again later.')) );    
                }
                
                $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());
            }
            
        } else {
            if (!empty($the_email)) {
                $this->view->assign('errors', array($this->view->translate('The email is invalid, please check it and retry.')) );    
            }
        }
    }

    /**
     * Reset password step #2 - response.
     * 
     * Let the user change the password. 
     * 
     * Parameters:
     * 'hash' - the reset password hash sent by email 
     */
    public function resetAction() {

        if ($this->isDemoMode()) {
            $this->redirect('/demo');
            return FALSE;
        }

        $this->_helper->layout->setLayout('simple');
        
        $hash = $this->getParam("hash");
        $validate_hash = new My_ValidateResetPasswordHash();
        if ($validate_hash->isValid($hash)) {
            
            if ($this->getRequest()->isPost()) {
                // Check the passwords
                $pwd1 = $this->getParam('passwd');
                $pwd2 = $this->getParam('passwd2');
                $check_password = new My_ValidateUserPassword();
                $errors = array();
                if ($check_password->isValid($pwd1)) {
                    if (strcmp($pwd1, $pwd2) != 0) {
                        $errors[] = 'Invalid passwords, please check and retry.'; 
                    }
                } else {
                    $errors = $check_password->getMessages();
                }
                
                if (count($errors) > 0) {
                    $this->view->errors = $errors;    
                } else {
                    try {
                        $model = new My_Accounts();
                        if ($model->doResetPassword($hash, $pwd1)) {
                            $this->_helper->getHelper('FlashMessenger')->addMessage('Password successfully changed!','info');
                            $this->redirect("/login");
                        } else {
                            $this->_helper->getHelper('FlashMessenger')->addMessage('Failed to change the user password, please retry.','error');
                            $this->redirect("/index");
                        }
                    }
                    catch(Exception $e) {
                        $this->_helper->getHelper('FlashMessenger')->addMessage('Internal error, please retry again later.','error');
                        $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage() );
                        $this->redirect("/index");
                    }
                }
                
            } 

        } else {
            $this->_helper->getHelper('FlashMessenger')->addMessage('Invalid reset password hash, please retry.','error');
            $this->redirect("/index");
        }
    }

    /**
     * Catch all demo mode action.
     * 
     * Redirects the actions here, when needed
     */
    public function demoAction() {
        if ($this->isDemoMode()) {
            $this->_helper->layout->setLayout('simple');
        } else {
            $this->redirect('/index');
        }
    }
}
