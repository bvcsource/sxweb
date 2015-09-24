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
 * Manages all the AJAX calls.
 *
 * Returns a JSON with the request result.
 * Important: a success HTTP code don't means that the action
 * was successful
 *
 *
 * Returns the right HTTP error code:
 * Success:
 * 200 - Generic success
 * 201 - Resource created
 *
 * Failure:
 * 400 - Bad request: input parameters error
 * 403 - Forbidden, user not authenticated
 * 404 - Not found
 */
class AjaxController extends My_BaseAction {

    public function preDispatch() {
        parent::preDispatch();
        $this->disableView();
    }

    /**
     * Set a new page size for the user.
     * 
     * 'size' - integer, from 1 to max
     */
    public function pagesizeAction() {
        if (!Zend_Auth::getInstance()->hasIdentity()) {
            $this->forbidden();
            return FALSE;
        }

        
        $validate_size = new My_ValidateFilelistPageSize();
        $size = $this->getRequest()->getParam('size');
        $this->getLogger()->debug('Page Size: '.var_export($size, TRUE));
        if ($validate_size->isValid($size)) {
            
            $user = Zend_Auth::getInstance()->getIdentity();
            
            $user->getPreferences()->set(My_User::PREF_PAGE_SIZE, $size);

            try {
                if ($this->getUserModel()->updateUserPreferences( Zend_Auth::getInstance()->getIdentity() )) {
                    Zend_Auth::getInstance()->getStorage()->write($user);

                    $this->getResponse()->setHttpResponseCode(200);
                    echo Zend_Json::encode(array(
                        'status' => TRUE,
                        'error' => '',
                        'url' => $this->view->serverUrl() . '/vol' . $this->getLastVisitedPath()
                    ));
                } else {
                    $this->getResponse()->setHttpResponseCode(400);
                    echo Zend_Json::encode(array(
                        'status' => FALSE,
                        'error' => $this->view->translate('Failed to update file list page size.'),
                        'url' => $this->view->serverUrl() . '/vol' . $this->getLastVisitedPath()
                    ));
                }
            }
            catch(Exception $e) {
                $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());
                $this->getResponse()->setHttpResponseCode(500);
                echo Zend_Json::encode(array(
                    'status' => FALSE,
                    'error' => $this->view->translate('Internal error. Failed to update file list page size.'),
                    'url' => $this->view->serverUrl() . '/vol' . $this->getLastVisitedPath()
                ));
            }
            
        } else {
            $this->getResponse()->setHttpResponseCode(400);
            echo Zend_Json::encode(array(
                'status' => FALSE,
                'error' => $this->view->translate('Invalid value, failed to update file list page size.'),
                'url' => $this->view->serverUrl() . '/vol' . $this->getLastVisitedPath()
            ));
        }
        
        
    }

    /**
     * Sends the standard AJAX "Forbidden access" response.
     *
     * @throws Zend_Controller_Response_Exception
     */
    private function forbidden() {
        $this->getResponse()->setHttpResponseCode(403);
        $this->getResponse()->setHeader('Content-Type', 'application/json');
        $this->getResponse()->clearBody();
        $this->getResponse()->setBody(
            Zend_Json::encode(array(
                'status' => FALSE,
                'url' => '/logout',
                'error' => $this->getTranslator()->translate('Your credentials are expired, you need to login again.')
            ))
        );
    }

    /**
     * Check if a file exists. It's an AJAX method.
     *
     * Parameters:
     * 'path' - the complete path of the file (volume + path)
     */
    public function fileexistsAction() {
        
        if (!Zend_Auth::getInstance()->hasIdentity()) {
            $this->forbidden();
            return FALSE;
        }
        
        $the_path = $this->getRequest()->getParam('path');
        $validate_path = new My_ValidatePath();
        if (!$validate_path->isValid($the_path)) {
            $this->getResponse()->setHttpResponseCode(400);
            echo Zend_Json::encode(array(
                'status' => FALSE,
                'error' => $this->getTranslator()->translate('Invalid path.'),
                'url' => ''
            ));
        }
        
        try {
            $access_sx = new Skylable_AccessSxNG( My_Utils::getAccessSxNGOpt( Zend_Auth::getInstance()->getIdentity() ) );
            if ($access_sx->fileExists( $the_path, $file_type )) {
                $this->getResponse()->setHttpResponseCode(200);
                echo Zend_Json::encode(array(
                    'status' => TRUE,
                    'error' => '',
                    'url' => ''
                )); 
            } else {
                $this->getResponse()->setHttpResponseCode(200);
                echo Zend_Json::encode(array(
                    'status' => FALSE,
                    'error' => '',
                    'url' => ''
                ));
            }
        }
        catch(Skylable_AccessSxException $e) {
            $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());
            $this->getResponse()->setHttpResponseCode(500);
            echo Zend_Json::encode(array(
                'status' => FALSE,
                'error' => $this->getTranslator()->translate('Internal error.'),
                'url' => ''
            ));            
        }
        catch(Exception $e) {
            $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());
            $this->getResponse()->setHttpResponseCode(500);
            echo Zend_Json::encode(array(
                'status' => FALSE,
                'error' => $this->getTranslator()->translate('Internal error.'),
                'url' => ''
            ));
        }
    }


    /**
     * Copies a file or a dir
     *
     * Parameters:
     * 'dest' - destination directory
     * 'files' - array of files
     *
     * Return:
     *
     */
    public function copyAction() {

        if (!Zend_Auth::getInstance()->hasIdentity()) {
            $this->forbidden();
            return FALSE;
        }

        // $validate_path = new My_ValidateSxPath(Zend_Auth::getInstance()->getIdentity()->getId(), My_ValidateSxPath::FILE_TYPE_DIR);
        $dest = $this->getRequest()->getParam('dest');
        $files = $this->getRequest()->getParam('files');
        if (!is_array($files)) {
            $files = array($files);
        }

        $this->getInvokeArg('bootstrap')->getResource('log')->debug(__METHOD__.': dest dir: '.print_r($dest, TRUE) );
        $this->getInvokeArg('bootstrap')->getResource('log')->debug(__METHOD__.': files: '.print_r($files, TRUE) );

        // Validates input data
        $validate_path = new My_ValidatePath();
        foreach($files as $k => $fn) {
            if (!$validate_path->isValid($fn)) {
                unset($files[$k]);
            } else {
                $files[$k] = My_Utils::removeTrailingSlash($fn);
            }
        }

        if (empty($files) || !$validate_path->isValid($dest)) {
            $this->getResponse()->setHttpResponseCode(400);
            echo '<p>',$this->getTranslator()->translate('Invalid input.'),'</p>';
            return FALSE;
        }

        try {
            $access_sx = new Skylable_AccessSx( Zend_Auth::getInstance()->getIdentity() );
            $status = $access_sx->copy($files, My_Utils::slashPath($dest), TRUE, '');
            if ($status === FALSE) {
                $errors = $access_sx->getLastErrorLog();
                $this->getResponse()->setHttpResponseCode(400);
                echo '<p>', $this->getTranslator()->translate('Copy failed.'),'</p>';
            } else {
                echo '<p>',$this->getTranslator()->translate('Files successfully copied.'),'</p>';
            }
        }
        catch (Skylable_InvalidCredentialsException $e) {
            $this->getLogger()->err(__METHOD__.': exception: ' .$e->getMessage());
            $this->forbidden();
        }
        catch(Exception $e) {
            $this->getLogger()->err(__METHOD__.': exception: ' .$e->getMessage());
            $this->getResponse()->setHttpResponseCode(500);
            echo '<p>', $this->getTranslator()->translate('Internal error.') ,'</p>';
        }
    }


    /**
     * Moves a file or a directory
     *
     * Parameters:
     * 'dest' - destination directory
     * 'files' - array of files
     *
     * Return:
     *
     */
    public function moveAction() {

        if (!Zend_Auth::getInstance()->hasIdentity()) {
            $this->forbidden();
            return FALSE;
        }

        $dest = $this->getRequest()->getParam('dest');
        $files = $this->getRequest()->getParam('files');
        if (!is_array($files)) {
            $files = array($files);
        }

        $this->getInvokeArg('bootstrap')->getResource('log')->debug(__METHOD__.': dest dir: '.print_r($dest, TRUE) );
        $this->getInvokeArg('bootstrap')->getResource('log')->debug(__METHOD__.': files: '.print_r($files, TRUE) );

        // Validates input data
        $validate_path = new My_ValidatePath();
        foreach($files as $k => $fn) {
            if (!$validate_path->isValid($fn)) {
                unset($files[$k]);
            } else {
                $files[$k] = My_Utils::removeTrailingSlash($fn);
            }
        }

        if (empty($files) || !$validate_path->isValid($dest)) {
            $this->getResponse()->setHttpResponseCode(400);
            echo '<p>',$this->getTranslator()->translate('Invalid input.'),'</p>';
            return FALSE;
        }

        try {
            $access_sx = new Skylable_AccessSx( Zend_Auth::getInstance()->getIdentity() );
            $status = $access_sx->move($files, My_Utils::slashPath($dest), TRUE, '');
            if ($status === FALSE) {
                $errors = $access_sx->getLastErrorLog();
                $this->getResponse()->setHttpResponseCode(400);
                echo '<p>', $this->getTranslator()->translate('Move failed.'),'</p>';
            } else {
                echo '<p>',$this->getTranslator()->translate('File(s) successfully moved.'),'</p>';
            }
        }
        catch (Skylable_InvalidCredentialsException $e) {
            $this->getLogger()->err(__METHOD__.': exception: ' .$e->getMessage());
            $this->forbidden();
        }
        catch(Exception $e) {
            $this->getLogger()->err(__METHOD__.': exception: ' .$e->getMessage());
            $this->getResponse()->setHttpResponseCode(500);
            echo '<p>', $this->getTranslator()->translate('Internal error.') ,'</p>';
            return FALSE;
        }
    }

    /*
     * file list
     */
    public function filelistAction() {

        if (!Zend_Auth::getInstance()->hasIdentity()) {
            $this->forbidden();
            return FALSE;
        }

        $path = $this->getRequest()->getParam('path');

        $validate_path = new My_ValidatePath();
        if ($validate_path->isValid($path)) {
            try {
                $access_sx  = new Skylable_AccessSx( Zend_Auth::getInstance()->getIdentity() );
                $this->view->url = $path;
                $this->view->volumes = $access_sx->listVolumes();
                $this->paginateFiles($path, $access_sx);
                // $this->view->list = $access_sx->sxls($path, $this->getFileSortOrder() );
                $this->view->acl = $access_sx->getVolumeACL( My_Utils::getRootFromPath( $path ) );
                $this->renderScript("directory_listing.phtml");
            }
            catch (Exception $e) {
                $this->getLogger()->err(__METHOD__ . ': exception: ' . $e->getMessage() );
                $this->sendErrorResponse('<p>',$this->getTranslator()->translate('Internal error. Can\'t proceed.'),'</p>', 500);
            }
        } else {
            $this->sendErrorResponse('<p>',$this->getTranslator()->translate('Invalid input.'),'</p>');
        }
    }

    /**
     * Sends an error response
     * 
     * @param string $body
     * @param int $http_error_code
     * @throws Zend_Controller_Response_Exception
     */
    protected function sendErrorResponse($body, $http_error_code = 400) {
        $this->getResponse()->setHttpResponseCode($http_error_code);
        $this->getResponse()->clearBody();
        $this->getResponse()->setBody($body);
    }

    /**
     * Rename a file.
     *
     * Parameter:
     * source - string with the source path
     * new_name - string with the new name
     *
     * @return bool
     */
    public function renameAction() {

        if (!Zend_Auth::getInstance()->hasIdentity()) {
            $this->forbidden();
            return FALSE;
        }

        $source = $this->getRequest()->getParam('source');
        $new_name = $this->getRequest()->getParam('new_name');

        $validate_path = new My_ValidatePath();
        if (!$validate_path->isValid($source)) {
            $this->sendErrorResponse('<p>',$this->getTranslator()->translate('Invalid input.'),'</p>');
            $this->getLogger()->debug(__METHOD__.': invalid source path: '.print_r($source, TRUE));
            return FALSE;
        }

        $validate_filename = new My_ValidateFilename();
        if (!$validate_filename->isValid($new_name)) {
            $this->getLogger()->debug(__METHOD__.': invalid destination name: '.print_r($new_name, TRUE));
            $this->sendErrorResponse($this->getTranslator()->translate('Invalid destination name.'));
            return FALSE;
        }

        $the_new_path = My_Utils::slashPath( dirname($source) ) . $new_name;
        if (!$validate_path->isValid($the_new_path)) {
            $this->getLogger()->debug(__METHOD__.': invalid destination path: '.print_r($the_new_path, TRUE));
            $this->sendErrorResponse($this->getTranslator()->translate('Invalid destination path.'));
            return FALSE;
        }

        // Same file?
        if (strcmp(My_Utils::removeSlashes($source), My_Utils::removeSlashes($the_new_path)) == 0) {
            $this->getLogger()->debug(__METHOD__.': same source and destination');
            return TRUE;
        }

        try {
            $access_sx = new Skylable_AccessSx( Zend_Auth::getInstance()->getIdentity() );

            if ($access_sx->move($source, $the_new_path, TRUE)) {
                $this->getLogger()->debug(__METHOD__.': rename successful.');
            } else {
                $this->getLogger()->debug(__METHOD__.': rename failed.');
                $this->sendErrorResponse($this->getTranslator()->translate('Rename failed.'));
            }
        }
        catch (Skylable_InvalidCredentialsException $e) {
            $this->getLogger()->err(__METHOD__.': exception: ' .$e->getMessage());
            $this->forbidden();
        }
        catch(Exception $e) {
            $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());
            $this->sendErrorResponse($this->getTranslator()->translate('Invalid destination name.'));
        }

    }

    /**
     * Creates a new shared file (or reuse the old one already shared).
     *
     * Note: to avoid problems with names containing spaces send the
     * 'path' parameter urlencoded.
     *
     * Must be called with a POST request. Works in two steps:
     * Step 1 - shows a confirmation dialog
     * Step 2 - create and finish
     *
     * Parameters:
     * 'create' - string - do the final step 
     * 'path' - The complete path of the file to share (including the volume)
     * Only present in final step
     * 'share_password' - string - the plain password to use to protect the file
     * 'share_expire_time' - integer - hours after which the download link expires
     *
     * @return bool
     */
    public function shareAction() {

        if (!Zend_Auth::getInstance()->hasIdentity()) {
            $this->forbidden();
            return FALSE;
        }

        if (!$this->getRequest()->isPost()) {
            $this->sendErrorResponse('<p>',$this->getTranslator()->translate('Invalid input.'),'</p>');
            return FALSE;
        }

        $path = $this->getRequest()->getParam('path');

        $this->getLogger()->debug(__METHOD__.': path is: '.print_r($path, TRUE) );

        try {
            $access_sx = new Skylable_AccessSxNG( My_Utils::getAccessSxNGOpt( Zend_Auth::getInstance()->getIdentity() ) );
            $validate_path = new My_ValidateSxPath( $access_sx, My_ValidateSxPath::FILE_TYPE_FILE );
            if (!$validate_path->isValid($path)) {
                $this->sendErrorResponse('<p>'.$this->getTranslator()->translate('File not found or invalid.').'</p>');
                return FALSE;
            }

            /*
             * Sharing from encrypted volumes is not possible.
             */
            $access_sx2 = new Skylable_AccessSx( Zend_Auth::getInstance()->getIdentity() );
            $this->view->volumes = $access_sx2->listVolumes();
            if ($this->view->volumeIsEncrypted( My_Utils::getRootFromPath( $path ) )) {
                $this->sendErrorResponse('<p>'.$this->getTranslator()->translate('File not found or invalid.').'</p>');
                return FALSE;
            }
        }
        catch (Skylable_InvalidCredentialsException $e) {
            $this->getLogger()->err(__METHOD__.': exception: ' .$e->getMessage());
            $this->forbidden();
            return FALSE;
        }
        catch(Exception $e) {
            $this->getLogger()->debug(__METHOD__.': exception: '.$e->getMessage());
            $this->sendErrorResponse('<p>',$this->getTranslator()->translate('Internal error. Can\'t proceed.'),'</p>', 500);
            return FALSE;
        }

        if (is_null($this->getRequest()->getParam('create'))) {
            // Check if the file is already shared
            try {
                $my_shared = new My_Shared();
                $shared_file_key = '';
                if ($my_shared->fileExists($path, Zend_Auth::getInstance()->getIdentity()->getSecretKey(), $shared_file_key)) {
                    $this->view->file_already_shared = TRUE;
                }
            }
            catch(Exception $e) {
                $this->getLogger()->debug(__METHOD__.': exception: '.$e->getMessage());
                $this->sendErrorResponse('<p>',$this->getTranslator()->translate('Internal error. Can\'t proceed.'),'</p>', 500);
                return FALSE;
            }
            
            // First step: show the dialog
            $this->_helper->viewRenderer->setNoRender(FALSE);
            $this->view->share_path = $path;
            $this->view->share_file = basename($path);
            $this->view->share_expire_time = Zend_Registry::get('skylable')->get('shared_file_expire_time') / 3600; // Convert to hours
            $this->view->share_password = '';
            $this->render('share-dialog');
            return TRUE;
        } else {
            // Check for errors
            $errors = array();
            $password = $this->getRequest()->getParam('share_password');
            $expire_time = $this->getRequest()->getParam('share_expire_time');
            
            if (is_numeric($expire_time)) {
                // Converts (or tries to) expire time from HOURS to SECONDS
                $expire_time *= 3600;
            }
            
            $password_check = new My_ValidateSharedFilePassword();
            if ($password_check->isValid($password)) {
                $confirm_password = $this->getRequest()->getParam('share_password_confirm');

                if (strcmp($password, strval($confirm_password)) != 0) {
                   $errors[] = $this->getTranslator()->translate('Passwords mismatch'); 
                }
            } else {
                $errors = array_merge($errors, $password_check->getMessages());
            }
            
            $expire_time_check = new My_ValidateSharedFileExpireTime();
            if (!$expire_time_check->isValid($expire_time)) {
                $errors[] = $this->getTranslator()->translate('Invalid expire time');
                $expire_time = '';
            }
            
            if (!empty($errors)) {
                $this->_helper->viewRenderer->setNoRender(FALSE);
                $this->view->errors = $errors;
                $this->view->share_path = $path;
                $this->view->share_file = basename($path);
                $this->view->share_expire_time = (empty($expire_time) ? $expire_time : $expire_time / 3600); // Convert from seconds to hours 
                $this->view->share_password = '';
                $this->getResponse()->setHttpResponseCode(400);
                $this->render('share-dialog');
                return TRUE;
            }
        }
        

        try {
            $sh = new My_Shared();
            $key = '';
            
            /*
             * Update the shared file info, if already exists.
             * */
            if ($sh->fileExists($path, Zend_Auth::getInstance()->getIdentity()->getSecretKey(), $key)) {
                $ok_up = $sh->updateFile($key, $password, $expire_time);
                if (!$ok_up) {
                    $this->sendErrorResponse('<p>' . $this->getTranslator()->translate('Failed to create the file share link.').'</p>');
                    return FALSE;
                }
            } else {
                $key = $sh->add($path, Zend_Auth::getInstance()->getIdentity()->getSecretKey(), $expire_time, $password );
                if ($key === FALSE) {
                    $this->sendErrorResponse('<p>' . $this->getTranslator()->translate('Failed to create the file share link.').'</p>');
                    return FALSE;
                }
            }
            
            $this->_helper->viewRenderer->setNoRender(FALSE);
            $this->view->url = $this->getSharedFileURL($key, $path);
        } catch (My_NotUniqueException $e) {
            $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());
            $this->sendErrorResponse('<p>' . $this->getTranslator()->translate('Failed to create the file share link.').'</p>');
        } catch (Exception $e) {
            $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());
            $this->sendErrorResponse('<p>',$this->getTranslator()->translate('Internal error. Can\'t proceed.'),'</p>', 500);
        }

    }

    /**
     * Returns the HTML for a window containing:
     * - a "from" path in which the operation you want to do will act
     * - volume and path selector for the destination of the operation
     *
     * Is used for the move and copy window.
     *
     * Parameter:
     *
     * 'path' - the destination path
     *
     * @return bool
     */
    public function windowAction() {

        if (!Zend_Auth::getInstance()->hasIdentity()) {
            $this->forbidden();
            return FALSE;
        }

        $this->_helper->viewRenderer->setNoRender(false);

        $path = $this->getRequest()->getParam('path', NULL);
        $validate_path = new My_ValidatePath();

        $this->getLogger()->debug(__METHOD__.': path is: '.print_r($path, TRUE));

        if (!$validate_path->isValid($path)) {
            $this->getResponse()->setHttpResponseCode(404);
            $this->getLogger()->debug(__METHOD__.': path is invalid, reason:'.print_r($validate_path->getErrors(), TRUE));
            $this->view->has_error = TRUE;
            $this->view->error = $this->getTranslator()->translate("Invalid path.");
            return false;
        }
        $path = My_Utils::slashPath($path);

        try {

            $access_sx = new Skylable_AccessSx( Zend_Auth::getInstance()->getIdentity() );

            $this->view->path = $path;
            $volumes = $access_sx->listVolumes( Skylable_AccessSx::SORT_BY_NAME_ASC );

            // Removes all the non unlocked volumes from the list.
            foreach ($volumes as $k => $v) {
                if ($v['filter'] == 'aes256') {
                    if (!$access_sx->volumeIsUnlocked($v['path']) ) {
                        unset($volumes[$k]);
                    }
                }
            }
            $this->view->vol = $volumes;
            $this->view->list = $access_sx->sxls($path, Skylable_AccessSx::SORT_BY_NAME_ASC, FALSE,  Skylable_AccessSx::LIST_DIRECTORIES );
            if ($this->view->list === FALSE) {
                $this->getResponse()->setHttpResponseCode(500);
                $this->view->has_error = TRUE;
                $this->view->error = $this->getTranslator()->translate("Internal error. Please retry later.");
            }

        }
        catch (Skylable_InvalidCredentialsException $e) {
            $this->getLogger()->err(__METHOD__.': exception: ' .$e->getMessage());
            $this->getResponse()->setHttpResponseCode(403);
            $this->view->has_error = TRUE;
            $this->view->error = $this->getTranslator()->translate('Your credentials are expired, you need to login again.');
        }
        catch (Exception $e) {
            $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());
            $this->getResponse()->setHttpResponseCode(500);
            $this->view->has_error = TRUE;
            $this->view->error = $this->getTranslator()->translate("Internal error. Please retry later.");
        }
    }

    /**
     * Interacts with the welcome user dialog.
     *
     * Parameters:
     * 'dont_show_dialog' - string - if 'yes' don't show the dialog anymore
     *
     * Response:
     * none, nothing can go wrong
     */
    public function welcomeAction() {
        // If the parameter 'dont_show_welcome' is 'yes' disable the dialog on the next
        // login.
        $dont_show_welcome = $this->getRequest()->getParam('dont_show_welcome', FALSE);
        if ($dont_show_welcome === 'yes') {
            // Update only if necessary
            if (Zend_Auth::getInstance()->getIdentity()->getPreferences()->get(My_User::PREF_DONT_SHOW_WELCOME, FALSE) === FALSE) {
                Zend_Auth::getInstance()->getIdentity()->getPreferences()->set(My_User::PREF_DONT_SHOW_WELCOME, TRUE);
                $this->updateStorage();
            }
        }

        // You viewed the welcome dialog, store the info into the session
        $welcome_dialog_viewed = $this->getRequest()->getParam('welcome_dialog_viewed', FALSE);
        if ($welcome_dialog_viewed === 'yes') {
            $session = new Zend_Session_Namespace();
            $session->welcome_dialog_viewed = TRUE;
        }
    }

    /**
     * Creates a directory into the specified path.
     *
     * Works only if invoked in a POST request.
     *
     * Parameters:
     * 'name' - name of the dir to create
     * 'path' - path where create the dir (complete path including volume)
     *
     */
    public function createdirAction() {

        if (!Zend_Auth::getInstance()->hasIdentity()) {
            $this->forbidden();
            return FALSE;
        }
        $path = $this->getRequest()->getParam('path');
        $dir_name = $this->getRequest()->getParam('name');

        $validate_dir_name = new My_ValidateDirName();
        $validate_path = new My_ValidatePath();

        if ($validate_path->isValid($path) && $validate_dir_name->isValid($dir_name)) {
            try {
                $access_sx = new Skylable_AccessSx( Zend_Auth::getInstance()->getIdentity() );
                if ($access_sx->mkdir($path, $dir_name)) {
                    $this->getResponse()->setHttpResponseCode(200);
                    echo Zend_Json::encode(array(
                        'status' => TRUE,
                        'error' => '',
                        'url' => ''
                    ));
                } else {
                    $this->getResponse()->setHttpResponseCode(201);
                    echo Zend_Json::encode(array(
                        'status' => FALSE,
                        'error' => $this->getTranslator()->translate('Failed to create directory'),
                        'url' => ''
                    ));
                }

            }
            catch(Skylable_InvalidCredentialsException $e) {
                $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());
                $this->forbidden();
            }
            catch(Exception $e) {
                $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());
                $this->getResponse()->setHttpResponseCode(500);
                echo Zend_Json::encode(array(
                    'status' => FALSE,
                    'error' => $this->getTranslator()->translate('Internal error: failed to create directory'),
                    'url' => ''
                ));
            }
        } else {
            $this->getResponse()->setHttpResponseCode(400);
            echo Zend_Json::encode(array(
                'status' => FALSE,
                'error' => $this->getTranslator()->translate('Invalid directory name'),
                'url' => ''
            ));
        }
    }

    /**
     * Delete files action.
     *
     * Parameters:
     * files - string or array of string of paths to remove
     */
    public function deleteAction() {
        if (!Zend_Auth::getInstance()->hasIdentity()) {
            $this->forbidden();
            return FALSE;
        }

        $files = $this->getRequest()->getParam('files');
        if (!is_array($files)) {
            $files = array($files);
        }

        $this->getLogger()->debug(__METHOD__.': files: '.print_r($files, TRUE) );

        // Validates input data
        $validate_path = new My_ValidatePath();
        foreach($files as $k => $fn) {
            if (!$validate_path->isValid($fn)) {
                unset($files[$k]);
            } else {
                $files[$k] = My_Utils::removeTrailingSlash($fn);
            }
        }

        if (empty($files)) {
            $this->getResponse()->setHttpResponseCode(400);
            echo '<p>'.$this->getTranslator()->translate('Invalid input.'),'</p>';
            return FALSE;
        }

        try {
            $access_sx = new Skylable_AccessSx( Zend_Auth::getInstance()->getIdentity() );
            $status = $access_sx->remove($files, TRUE);
            if ($status === FALSE) {
                $errors = $access_sx->getLastErrorLog();
                $this->getResponse()->setHttpResponseCode(400);
                echo '<p>',$this->getTranslator()->translate('Failed to delete files.'),'</p>';
            } else {
                echo '<p>',$this->getTranslator()->translate('Files successfully deleted.'),'</p>';
            }
        }
        catch(Skylable_InvalidCredentialsException $e) {
            $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());
            $this->forbidden();
        }
        catch(Exception $e) {
            $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());
            $this->getResponse()->setHttpResponseCode(500);
            echo '<p>',$this->getTranslator()->translate('Internal error.'),'</p>';
            return FALSE;
        }
    }


    /**
     * Lets you download a shared file.
     *
     * Accepts HTTP Basic Auth for user credentials.
     *
     * Parameters:
     * 'key' - the sha1 key indicating a file to download
     * 'file' - the file name, ignored
     * 'download' - string (value ignored)
     * 'password' - the plain password of a password protected file
     * 'raw' - if equals '1', the file will be sent inline
     */
    public function sharedAction() {

        $key = $this->getRequest()->getParam('key');

        // Check for key validity
        $validate_hash = new My_ValidateSharedFileHash();
        if (!$validate_hash->isValid($key)) {
            $this->getLogger()->debug(__METHOD__.': Invalid shared file hash: '.print_r($key));
            $this->enableView();
            $this->getResponse()->setHttpResponseCode(401);
            $this->_helper->layout()->setLayout("shared");
            $this->view->assign('error_msg', $this->getTranslator()->translate('Invalid shared file key'));
            return FALSE;
        }

        // Tell if the file must be sent inline
        $is_inline = ($this->getRequest()->getParam('raw') === '1');

        try {
            $shared = new My_Shared();
            $file_data = $shared->getSharedFile($key, TRUE);
            if ($file_data === FALSE) {
                $this->enableView();
                $this->getResponse()->setHttpResponseCode(404);
                $this->_helper->layout()->setLayout("shared");
                $this->view->assign('error_msg', $this->getTranslator()->translate('File not found or expired.'));
                return FALSE;
            }
            
            $this->view->file_type = My_Utils::getFileType($file_data['file_path']);
            $this->view->download_url = '/shared/file/'.$key.'/'.rawurlencode( basename($file_data['file_path']) );

            $is_password_protected = strlen($file_data['file_password']) > 0;

            // You are using a download agent like wget or curl, send the basic http auth header if needed
            $is_download_agent = (preg_match('/^(Mozilla|Opera)/', $_SERVER['HTTP_USER_AGENT']) == 0);

            if ($is_download_agent) {
                if ($is_password_protected && !isset($_SERVER['PHP_AUTH_PW'])) {
                    $this->getResponse()->clearBody();
                    $this->getResponse()->setRawHeader('WWW-Authenticate: Basic realm="SXWeb"');
                    $this->getResponse()->setRawHeader('HTTP/1.0 401 Unauthorized');
                    return FALSE;
                }
            }

            $password_is_valid = FALSE;

            /*
             * If 'password' and 'download' parameters exists, you are using the
             * password request form.
             * */
            $password = $this->getRequest()->getParam('password');
            if (is_null($this->getRequest()->getParam('download'))) {
                if (isset($_SERVER['PHP_AUTH_PW'])) {
                    $this->getLogger()->debug(__METHOD__.' HTTP AUTH password supplied');
                    $password = $_SERVER['PHP_AUTH_PW'];
                }
            }

            if (!is_null($password) && $is_password_protected) {
                $password_is_valid = (strcmp($file_data['file_password'],
                        $shared->getPasswordHash( strval($password), array( 'salt' => $file_data['file_password'] ) ) ) == 0);
            }

            /*
             * When called by a browser shows the download page,
             * then when the parameter 'download' arrives, send the file.
             * 
             * If the script is called with a downloader like wget or curl, the
             * download starts immediately.
             * */
            $start_download = $this->getRequest()->getParam('download');

            if ($is_download_agent) {
                if ($is_password_protected && !$password_is_valid) {
                    $start_download = FALSE;
                } else {
                    $start_download = TRUE;
                }
            }

            if (is_null($start_download)) {
                // Show the info window
                $this->enableView();
                $this->_helper->layout()->setLayout("simple");
                $this->view->file = basename($file_data['file_path']);
                if ($is_password_protected) {
                    $this->view->ask_password = TRUE;
                }
            } else {
                if (Zend_Auth::getInstance()->hasIdentity()) {
                    $user_ip = $_SERVER['REMOTE_ADDR'];
                    $user_id = Zend_Auth::getInstance()->getIdentity()->getId();
                } else {
                    $user_ip = $_SERVER['REMOTE_ADDR'];
                    $user_id = NULL;
                }

                $tickets = new My_Tickets();
                $allow_download = TRUE;

                if ($is_password_protected && !$password_is_valid ) {

                    $this->enableView();
                    $this->getResponse()->setHttpResponseCode(403);
                    $this->_helper->layout()->setLayout("shared");
                    $this->view->assign('error_msg', sprintf($this->getTranslator()->translate('Invalid password! <a href="%s">Retry...</a>'), $this->view->ServerUrl().'/shared/file/'.$key.'/'.rawurlencode(basename($file_data['file_path'])) ));
                    return FALSE;

                } elseif ($tickets->registerTicket( $user_id, $user_ip ) === FALSE) {
                    $this->getResponse()->setHttpResponseCode(500);
                    $this->view->error_title = $this->getTranslator()->translate('Too many concurrent downloads!');
                    $this->view->error_message = $this->getTranslator()->translate('Please wait a minute and retry.');
                    $allow_download = FALSE;
                } else {

                    /*
                    * If the user is logged and the user is the sharer, reuse the Sx dir
                    * */
                    $base_dir = NULL;
                    if (Zend_Auth::getInstance()->hasIdentity()) {
                        if ( strcmp(Zend_Auth::getInstance()->getIdentity()->getSecretKey(), $file_data['user_auth_token']) == 0 ) {
                            $this->getLogger()->debug(__METHOD__.': logged in user, reusing identity.');
                            $the_user = Zend_Auth::getInstance()->getIdentity();
                        }
                    }
                    if (!isset($the_user)) {
                        $this->getLogger()->debug(__METHOD__.': different user, creating a temporary identity.');
                        $the_user = new My_User(NULL, '', '', $file_data['user_auth_token']);
                        $the_dir = My_Utils::mktempdir( Zend_Registry::get('skylable')->get('sx_local'), 'Skylable_' );
                        if ($the_dir === FALSE) {
                            throw new Exception('Failed to create the user dir into: '. Zend_Registry::get('skylable')->get('sx_local') );
                        } else {
                            $base_dir = $the_dir;
                            $this->getLogger()->debug(__METHOD__.': temporary user dir is: '.$the_dir);
                        }
                    }

                    $access_sx = new Skylable_AccessSx( $the_user, $base_dir, array( 'user_auth_key' => $file_data['user_auth_token'] ));

                    // Get file data
                    $the_file = $access_sx->getFileInfo($file_data['file_path']);

                    if ($the_file === FALSE) {
                        // File not found.
                        $allow_download = FALSE;
                        $this->getResponse()->setHttpResponseCode(404);
                        $this->view->error_title = $this->getTranslator()->translate('File not found!');

                        $this->view->error_message = sprintf($this->getTranslator()->translate('The file &quot;%s&quot; was not found.'),$this->view->escape(basename($file_data['file_path'])) );
                    } else {
                        if ($the_file['type'] !== 'FILE') {
                            $this->getResponse()->setHttpResponseCode(500);
                            $this->view->error_title = $this->getTranslator()->translate('Invalid file type!');

                            $this->view->error_message = sprintf($this->getTranslator()->translate('The file &quot;%s&quot; can\'t be downloaded.'), $this->view->escape($file_data['file_path']));

                            $allow_download = FALSE;
                        }
                    }
                }

                if ($allow_download) {
                    $this->disableView();
                    $this->getLogger()->debug(__METHOD__.': purge dir: '.(isset($base_dir) ? $base_dir : '' ));

                    $res = new My_DownloadResponse($access_sx, $the_file, '', (isset($base_dir) ? $base_dir : '' ),
                        ($is_inline ? Skylable_AccessSx::DOWNLOAD_DISPOSITION_INLINE : Skylable_AccessSx::DOWNLOAD_DISPOSITION_ATTACHMENT) );
                    $this->getFrontController()->setResponse($res);
                } else {
                    $this->_helper->layout()->setLayout('application-failure');
                    $this->_helper->layout()->assign('show_message', FALSE);
                    $this->renderScript('error/malfunction.phtml');

                    if (isset($base_dir)) {
                        if (@is_dir($base_dir)) {
                            My_Utils::deleteDir($base_dir);
                        }
                    }
                }
            }

        }
        catch(Exception $e) {
            $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());
            $this->enableView();
            $this->getResponse()->setHttpResponseCode(500);
            $this->_helper->layout()->setLayout("shared");
            $this->view->assign('error_msg', $this->getTranslator()->translate('Internal error, please retry later.'));
            return FALSE;
        }
    }

    /**
     * AJAX call to manage volumes. Returns a JSON object with the following properties:
     * 'status' - boolean - TRUE operation succeeded, FALSE on failure
     * 'message' - string - on failure contains the error to show to the user, on success the text/html to show
     *
     * The 'setter' operations must be POST requests.
     *
     * Parameters (mandatory):
     * 'volume' - string the volume on which operate
     * 'operation' - string, the requested operation on the volume. Valid operations:
     *      'rev' - set the maximum revisions for a volume
     *      'modperm' - modify user privileges and permissions on a volume
     *
     * Parameters for operations:
     * REV
     * 'rev_count' - integer - the new maximum revisions number.
     * 
     * MODPERM
     * 'user' - string - the user to which change privileges
     * 'frm_permissions' - array - array of new permissions to grant, one char per index: 'r' - read, 'w' - write, 'm' - manager
     */
    public function managevolumeAction() {

        if (!Zend_Auth::getInstance()->hasIdentity() || $this->isDemoMode()) {
            $this->forbidden();
            return FALSE;
        }
        
        // Enable the view to let the View JSON helper work.
        $this->enableView();
        
        $volume = $this->getRequest()->getParam('volume');
        $operation = $this->getRequest()->getParam('operation');

        // The JSON reply
        $this->view->reply = array(
            'status' => TRUE,
            'error' => '',
            'message' => '',
            'url' => ''
        );

        // Parameter check
        $vol_check = new My_ValidatePath();
        if (!$vol_check->isValid($volume)) {
            $this->view->reply['status'] = FALSE;
            $this->view->reply['message'] = $this->getTranslator()->translate('Fatal error: invalid volume.');
            return FALSE;
        }

        $volume = My_Utils::getRootFromPath($volume);
        if (strlen($volume) == 0) {
            $this->view->reply['status'] = FALSE;
            $this->view->reply['message'] = $this->getTranslator()->translate('Fatal error: invalid volume.');
            return FALSE;
        }

        if (!in_array($operation, array('rev', 'modperm'))) {
            $this->view->reply['status'] = FALSE;
            $this->view->reply['message'] = $this->getTranslator()->translate('Fatal error: invalid operation.');
            return FALSE;
        }

        if ($operation === 'rev'  ) {
            if (!$this->getRequest()->isPost()) {
                $this->view->reply['status'] = FALSE;
                $this->view->reply['message'] = $this->getTranslator()->translate('Fatal error: invalid request.');
                return FALSE;
            }

            $rev_count = $this->getRequest()->getParam('rev_count');
            $rev_count_valid = FALSE;
            if (preg_match('/^\d+$/', $rev_count) == 1 ) {
                $this->getLogger()->debug(__METHOD__.' REV COUNT: '.var_export($rev_count, TRUE));
                $rev_count_valid = (intval($rev_count) >= 1);
            }
            if (!$rev_count_valid) {
                $this->view->reply['status'] = FALSE;
                $this->view->reply['message'] = $this->getTranslator()->translate('Invalid maximum revision limit.');
                return FALSE;
            }

            try {
                $user = Zend_Auth::getInstance()->getIdentity();
                $access_sx = new Skylable_AccessSx( $user );
                $out = $access_sx->setVolumeMaximumRevisions($volume, $rev_count);

                $this->getLogger()->debug(__METHOD__.': OUTPUT:'.print_r($out, TRUE));
                $this->view->reply['status'] = TRUE;
                $this->view->reply['message'] = $this->getTranslator()->translate('Successfully set maximum revision limit.');
                $this->view->reply['rev_count'] = $out['revisions_limit'];
            }
            catch(Skylable_RevisionException $e) {
                if ($e->getCode() == Skylable_RevisionException::REVISIONS_SAME_LIMITS) {
                    $this->view->reply['status'] = TRUE;
                    $this->view->reply['message'] = $this->getTranslator()->translate('Maximum revisions limit unchanged.');
                    $this->view->reply['rev_count'] = $rev_count;
                } else {
                    $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());

                    $this->view->reply['status'] = FALSE;
                    $this->view->reply['message'] = $this->getTranslator()->translate('Failed to set maximum revision limit.');
                }
            }
            catch(Skylable_VolumeNotFoundException $e) {
                $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());

                $this->view->reply['status'] = FALSE;
                $this->view->reply['message'] = $this->getTranslator()->translate('Volume not found.');
            }
            catch(Exception $e) {
                $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());

                $this->view->reply['status'] = FALSE;
                $this->view->reply['message'] = $this->getTranslator()->translate('Failed to set maximum revision limit.');
            }

        } elseif ($operation === 'modperm'  ) {
            if (!$this->getRequest()->isPost()) {
                $this->view->reply['status'] = FALSE;
                $this->view->reply['message'] = $this->getTranslator()->translate('Fatal error: invalid request.');
                return FALSE;
            }

            $dest_user = $this->getRequest()->getParam('user');
            $perms = $this->getRequest()->getParam('frm_permissions');

            $this->getLogger()->debug(__METHOD__.': USER:'.print_r($dest_user, TRUE));
            $this->getLogger()->debug(__METHOD__.': PERMS:'.print_r($perms, TRUE));
            
            $validate_user = new My_ValidateUserLogin();
            if (!$validate_user->isValid($dest_user)) {
                $this->view->reply['status'] = FALSE;
                $this->view->reply['message'] = $this->getTranslator()->translate('Fatal error: invalid user.');
                return FALSE;
            }
            
            /*
             * Validate passed permissions.
             * The permissions array can be empty: this means you revoke all privileges. 
             */ 
            if (!is_array($perms) ) {
                $this->view->reply['status'] = FALSE;
                $this->view->reply['message'] = $this->getTranslator()->translate('Fatal error: invalid permissions.');
                return FALSE;
            }
            $grants = array();
            $perms = array_unique($perms);
            foreach($perms as $p) {
                if ($p === 'r') {
                    $grants[] = Skylable_AccessSx::PRIVILEGE_READ;
                } elseif ($p === 'w') {
                    $grants[] = Skylable_AccessSx::PRIVILEGE_WRITE;
                } elseif ($p === 'm') {
                    $grants[] = Skylable_AccessSx::PRIVILEGE_MANAGER;
                }
            }
            $revokes = array_diff(array( Skylable_AccessSx::PRIVILEGE_READ, Skylable_AccessSx::PRIVILEGE_WRITE, Skylable_AccessSx::PRIVILEGE_MANAGER ), $grants );

            $this->getLogger()->debug(__METHOD__.': GRANTS:'.print_r($grants, TRUE));
            $this->getLogger()->debug(__METHOD__.': REVOKES:'.print_r($revokes, TRUE));

            /**
             * FIXME - this is a hack for bug #1246
             * Check the sxacl version: if >= 2.0 use only one invocation, otherwise 
             * if >= 1.2 use the revoke then grant  
             */
            $exit_code = exec('sxacl -V', $output, $ret_val);
            $use_new_sxacl = FALSE;
            if (empty($output)) {
                // Command was not found...
                $this->getLogger()->err(__METHOD__.': sxacl command not found');
                $this->view->reply['status'] = FALSE;
                $this->view->reply['message'] = $this->getTranslator()->translate('Failed to change user privileges.');
                return FALSE;
            } else {
                if (preg_match('/^sxacl\s+(.+)/', $output[0], $matches) == 1) {
                    $sxacl_version = trim($matches[1]);
                    $this->getLogger()->err(__METHOD__.': sxacl version is: '.$sxacl_version);
                    
                    if (version_compare($sxacl_version, '2.0', '>=')) {
                        $use_new_sxacl = TRUE;
                    } elseif (version_compare($sxacl_version, '1.2', '>=')) {
                        $use_new_sxacl = FALSE;
                    } else {
                        $this->getLogger()->err(__METHOD__.': sxacl version is too low');
                        $this->view->reply['status'] = FALSE;
                        $this->view->reply['message'] = $this->getTranslator()->translate('Failed to change user privileges.');
                        return FALSE;
                    }
                } else {
                    $this->getLogger()->err(__METHOD__.': unknown sxacl version; command output is: '.print_r($output, TRUE));
                    $use_new_sxacl = FALSE;
                }
            }

            try {
                
                $user = Zend_Auth::getInstance()->getIdentity();
                $access_sx = new Skylable_AccessSx( $user );

                // Get the current volume ACL
                $vol_acl = $access_sx->getVolumeACL($volume);
                if ($vol_acl === FALSE || !is_array($vol_acl) || empty($vol_acl)) {
                    $this->getLogger()->err(__METHOD__.': failed to retrieve volume ACL');
                    $this->view->reply['status'] = FALSE;
                    $this->view->reply['message'] = $this->getTranslator()->translate('Failed to change user privileges.');
                    return FALSE;
                }
                
                // If the user is not an admin or owner, he can't revoke or grant the 'manager' privilege
                $i_am_god = FALSE;
                foreach($vol_acl as $acl) {
                    if (strcmp($acl['user'], $user->getLogin()) == 0 ) {
                        if (in_array('owner', $acl['perms'])) {
                            $i_am_god = TRUE;
                        }
                    }
                }
                if ($user->getRoleId() == My_User::ROLE_ADMIN) {
                    $i_am_god = TRUE;
                }
                
                if (!$i_am_god) { // Remove the "manager" privilege from the list
                    $this->getLogger()->debug(__METHOD__.': user is not owner or admin of the volume: '.$volume);
                    foreach($grants as $gk => $gv) {
                        if ($gv == Skylable_AccessSx::PRIVILEGE_MANAGER) {
                            $this->getLogger()->debug(__METHOD__.': removed manager privilege from GRANTS');
                            unset($grants[$gk]);
                        }
                    }
                    foreach($revokes as $rk => $rv) {
                        if ($rv == Skylable_AccessSx::PRIVILEGE_MANAGER) {
                            $this->getLogger()->debug(__METHOD__.': removed manager privilege from REVOKES');
                            unset($revokes[$rk]);
                        }
                    }
                }
                
                if ($use_new_sxacl === TRUE) {
                    $this->getLogger()->debug(__METHOD__.': calling sxacl directly');
                    $out = $access_sx->sxaclVolumePermissions($dest_user, $volume, $grants, $revokes);
                    $this->getLogger()->debug(__METHOD__.': OUTPUT:'.print_r($out, TRUE));

                    if ($out !== FALSE) {
                        $this->view->reply['status'] = TRUE;
                        $this->view->reply['message'] = $this->getTranslator()->translate('Successfully changed user privileges.');
                        $this->view->volume_acl = $out;
                        $this->view->reply['acl_table'] = $this->view->render('/settings/volume_acl_table.phtml');
                        return TRUE;
                    }
                } else {
                    $this->getLogger()->debug(__METHOD__.': calling sxacl two times');
                    
                    /**
                     * Calls sxacl two times:
                     * revoke then grants privileges.
                     */
                    if (count($revokes) > 0) {
                        $step1_ok = $access_sx->sxaclVolumePermissions($dest_user, $volume, array(), $revokes);
                        $this->getLogger()->debug(__METHOD__.': FIRST CALL OUTPUT:'.print_r($step1_ok, TRUE));
                    } else {
                        $step1_ok = TRUE;
                    }

                    if ($step1_ok !== FALSE) {
                        if (count($grants) > 0) {
                            $out = $access_sx->sxaclVolumePermissions($dest_user, $volume, $grants);
                        } else {
                            $out = $step1_ok;
                        }
                        $this->getLogger()->debug(__METHOD__.': SECOND CALL OUTPUT:'.print_r($out, TRUE));

                        if ($out !== FALSE) {
                            $this->view->reply['status'] = TRUE;
                            $this->view->reply['message'] = $this->getTranslator()->translate('Successfully changed user privileges.');
                            $this->view->volume_acl = $out;
                            $this->view->reply['acl_table'] = $this->view->render('/settings/volume_acl_table.phtml');
                            return TRUE;
                        }
                    }    
                }
                
                // If we are here, something went wrong...
                $this->view->reply['status'] = FALSE;
                $this->view->reply['message'] = $this->getTranslator()->translate('Failed to change user privileges.');
                
            }
            catch(Skylable_FailedToModifyVolumeACLException $e) {
                $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());

                $this->view->reply['status'] = FALSE;
                $this->view->reply['message'] = $this->getTranslator()->translate('Failed to change user privileges.');
            }
            catch(Skylable_UserNotFoundException $e) {
                $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());

                $this->view->reply['status'] = FALSE;
                $this->view->reply['message'] = $this->getTranslator()->translate('Failed to change user privileges: user not found.');
            }
            catch(Skylable_VolumeNotFoundException $e) {
                $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());

                $this->view->reply['status'] = FALSE;
                $this->view->reply['message'] = $this->getTranslator()->translate('Volume not found.');
            }
            catch(Exception $e) {
                $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());

                $this->view->reply['status'] = FALSE;
                $this->view->reply['message'] = $this->getTranslator()->translate('Failed to change user privileges.');
            }

        }
    }
}
