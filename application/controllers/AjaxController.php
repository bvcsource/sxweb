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
                'url' => '/',
                'error' => 'Your credentials are expired, you need to login again.'
            ))
        );
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
            echo '<p>Invalid input.<p>';
            return FALSE;
        }

        try {
            $access_sx = new Skylable_AccessSxNew( Zend_Auth::getInstance()->getIdentity() );
            $status = $access_sx->copy($files, My_Utils::slashPath($dest), TRUE, '');
            if ($status === FALSE) {
                $errors = $access_sx->getLastErrorLog();
                $this->getResponse()->setHttpResponseCode(400);
                echo '<p>Copy failed.<p>';
            } else {
                echo '<p>Files successfully copied.<p>';
            }
        }
        catch(Exception $e) {
            $this->getResponse()->setHttpResponseCode(500);
            echo '<p>Internal error.<p>';
            return FALSE;
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
            echo '<p>Invalid input.<p>';
            return FALSE;
        }

        try {
            $access_sx = new Skylable_AccessSxNew( Zend_Auth::getInstance()->getIdentity() );
            $status = $access_sx->move($files, My_Utils::slashPath($dest), TRUE, '');
            if ($status === FALSE) {
                $errors = $access_sx->getLastErrorLog();
                $this->getResponse()->setHttpResponseCode(400);
                echo '<p>Move failed.<p>';
            } else {
                echo '<p>Files successfully moved.<p>';
            }
        }
        catch(Exception $e) {
            $this->getResponse()->setHttpResponseCode(500);
            echo '<p>Internal error.<p>';
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
                $access_sx  = new Skylable_AccessSxNew( Zend_Auth::getInstance()->getIdentity() );
                $this->view->url = $path;
                // $this->view->volumes = $access_sx->listVolumes();
                $this->paginateFiles($path, $access_sx);
                // $this->view->list = $access_sx->sxls($path, $this->getFileSortOrder() );
                $this->view->acl = $access_sx->getVolumeACL( My_Utils::getRootFromPath( $path ) );
                $this->renderScript("directory_listing.phtml");
            }
            catch (Exception $e) {
                $this->getLogger()->debug(__METHOD__ . ': exception: ' . $e->getMessage() );
                $this->sendErrorResponse(self::ERROR_MSG_INTERNAL_ERROR, 500);
            }
        } else {
            $this->sendErrorResponse(self::ERROR_MSG_INVALID_INPUT);
        }
    }

    const
        ERROR_MSG_OPERATION_FAILED = '<p>File operation failed.</p>',
        ERROR_MSG_INVALID_INPUT = '<p>Invalid input.</p>',
        ERROR_MSG_INTERNAL_ERROR = '<p>Internal error. Can\'t proceed.</p>';

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
            $this->sendErrorResponse(self::ERROR_MSG_INVALID_INPUT);
            $this->getLogger()->debug(__METHOD__.': invalid source path: '.print_r($source, TRUE));
            return FALSE;
        }

        $validate_filename = new My_ValidateFilename();
        if (!$validate_filename->isValid($new_name)) {
            $this->getLogger()->debug(__METHOD__.': invalid destination name: '.print_r($new_name, TRUE));
            $this->sendErrorResponse('Invalid destination name.');
            return FALSE;
        }

        $the_new_path = My_Utils::slashPath( dirname($source) ) . $new_name;
        if (!$validate_path->isValid($the_new_path)) {
            $this->getLogger()->debug(__METHOD__.': invalid destination path: '.print_r($the_new_path, TRUE));
            $this->sendErrorResponse('Invalid destination name.');
            return FALSE;
        }

        // Same file?
        if (strcmp(My_Utils::removeSlashes($source), My_Utils::removeSlashes($the_new_path)) == 0) {
            $this->getLogger()->debug(__METHOD__.': same source and destination');
            return TRUE;
        }

        try {
            $access_sx = new Skylable_AccessSxNew( Zend_Auth::getInstance()->getIdentity() );

            if ($access_sx->move($source, $the_new_path, TRUE)) {
                $this->getLogger()->debug(__METHOD__.': rename successful.');
            } else {
                $this->getLogger()->debug(__METHOD__.': rename failed.');
                $this->sendErrorResponse('Rename failed.');
            }
        }
        catch(Exception $e) {
            $this->getLogger()->debug(__METHOD__.': exception: '.$e->getMessage());
            $this->sendErrorResponse('Invalid destination name.');
        }

    }

    /**
     * Creates a new shared file (or reuse the old one already shared).
     *
     * Note: to avoid problems with names containing spaces send the
     * 'path' parameter urlencoded.
     *
     * Must be called with a POST request.
     *
     * Parameters:
     * 'path' - The complete path of the file to share (including the volume)
     *
     * @return bool
     */
    public function shareAction() {

        if (!Zend_Auth::getInstance()->hasIdentity()) {
            $this->forbidden();
            return FALSE;
        }

        if (!$this->getRequest()->isPost()) {
            $this->sendErrorResponse(self::ERROR_MSG_INVALID_INPUT);
            return FALSE;
        }

        $path = $this->getRequest()->getParam('path');

        $this->getLogger()->debug(__METHOD__.': path is: '.print_r($path, TRUE) );

        try {
            $access_sx = new Skylable_AccessSxNG(array( 'secret_key' => Zend_Auth::getInstance()->getIdentity()->getSecretKey(), 'cluster' => parse_url(Zend_Registry::get('skylable')->get('cluster'), PHP_URL_HOST) ));
            $validate_path = new My_ValidateSxPath( $access_sx, My_ValidateSxPath::FILE_TYPE_FILE );
            if (!$validate_path->isValid($path)) {
                $this->sendErrorResponse('<p>File not found or invalid.</p>');
                return FALSE;
            }
        }
        catch(Exception $e) {
            $this->getLogger()->debug(__METHOD__.': exception: '.$e->getMessage());
            $this->sendErrorResponse(self::ERROR_MSG_INTERNAL_ERROR, 500);
            return FALSE;
        }

        try {
            $sh = new My_Shared();
            $key = '';
            if (!$sh->fileExists($path, Zend_Auth::getInstance()->getIdentity()->getSecretKey(), $key)) {
                $key = $sh->add($path, Zend_Auth::getInstance()->getIdentity()->getSecretKey(), Zend_Registry::get('skylable')->get('shared_file_expire_time') );
                if ($key === FALSE) {
                    $this->sendErrorResponse('<p>Failed to create file link.</p>');
                    return FALSE;
                }
            }
            $this->_helper->viewRenderer->setNoRender(FALSE);
            $this->view->url = Zend_Registry::get('skylable')->get('url') . "/shared/file/" . $key . "/" . rawurlencode(basename($path));
        } catch (My_NotUniqueException $e) {
            $this->getInvokeArg('bootstrap')->getResource('log')->err($e->getMessage());
            $this->sendErrorResponse('<p>Failed to create file link.</p>');
            return FALSE;
        } catch (Exception $e) {
            $this->getInvokeArg('bootstrap')->getResource('log')->err($e->getMessage());
            $this->sendErrorResponse(self::ERROR_MSG_INTERNAL_ERROR, 500);
        }

    }

    /**
     * Lets you download a shared file.
     *
     * Parameters:
     * 'key' - the sha1 key indicating a file to download
     * 'download' - string (value ignored)
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
            $this->view->assign('error_msg', 'Invalid shared file key');
            return FALSE;
        }

        try {
            $shared = new My_Shared();
            $file_data = $shared->getSharedFile($key, TRUE);
            if ($file_data === FALSE) {
                $this->enableView();
                $this->getResponse()->setHttpResponseCode(404);
                $this->_helper->layout()->setLayout("shared");
                $this->view->assign('error_msg', 'File not found or expired.');
                return FALSE;
            }

            /*
             * When called by a browser shows the download page,
             * then when the parameter 'download' arrives, send the file.
             * 
             * If the script is called with a downloader like wget or curl, the
             * download starts immediately.
             * */
            $start_download = $this->getRequest()->getParam('download');
            
            if (preg_match('/^(Wget|curl)/', $_SERVER['HTTP_USER_AGENT']) == 1) {
                $start_download = TRUE;
            }

            if (is_null($start_download)) {
               // Show the info window
                $this->enableView();
                $this->_helper->layout()->setLayout("shared");
                $this->view->file = basename($file_data['file_path']);
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

                if ($tickets->registerTicket( $user_id, $user_ip ) === FALSE) {
                    $this->getResponse()->setHttpResponseCode(500);
                    $this->view->error_title = 'Too many concurrent downloads!';
                    $this->view->error_message = 'Please wait a minute and retry. ';
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
                        $the_user = new My_User('', '', $file_data['user_auth_token']);
                        $the_dir = My_Utils::mktempdir( Zend_Registry::get('skylable')->get('sx_local'), 'Skylable_' );
                        if ($the_dir === FALSE) {
                            throw new Exception('Failed to create the user dir into: '. Zend_Registry::get('skylable')->get('sx_local') );
                        } else {
                            $base_dir = $the_dir;
                            $this->getLogger()->debug(__METHOD__.': temporary user dir is: '.$the_dir);
                        }
                    }

                    $access_sx = new Skylable_AccessSxNew( $the_user, $base_dir );

                    // Get file data
                    $the_file = $access_sx->getFileInfo($file_data['file_path']);

                    if ($the_file === FALSE) {
                        // File not found.
                        $allow_download = FALSE;
                        $this->getResponse()->setHttpResponseCode(404);
                        $this->view->error_title = 'File not found!';
                        $this->view->error_message = 'The file &quot;'.htmlentities(basename($file_data['file_path'])).'&quot; was not found.';
                    } else {
                        if ($the_file['type'] !== 'FILE') {
                            $this->getResponse()->setHttpResponseCode(500);
                            $this->view->error_title = 'Invalid file type!';
                            $this->view->error_message = 'The file &quot;'.htmlentities($file_data['file_path']).'&quot; can\'t be downloaded.';
                            $allow_download = FALSE;
                        }
                    }
                }

                if ($allow_download) {
                    $this->disableView();
                    $this->getLogger()->debug(__METHOD__.': purge dir: '.(isset($base_dir) ? $base_dir : '' ));
                    $res = new My_DownloadResponse($access_sx, $the_file, '', (isset($base_dir) ? $base_dir : '' ));
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
            $this->getLogger()->debug(__METHOD__.': exception: '.$e->getMessage());
            $this->enableView();
            $this->getResponse()->setHttpResponseCode(500);
            $this->_helper->layout()->setLayout("shared");
            $this->view->assign('error_msg', 'Internal error, please retry later.');
            return FALSE;
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
            $this->view->error = "Invalid path.";
            return false;
        }
        $path = My_Utils::slashPath($path);

        try {

            $access_sx = new Skylable_AccessSxNew( Zend_Auth::getInstance()->getIdentity() );

            $this->view->path = $path;
            $volumes = $access_sx->listVolumes( Skylable_AccessSxNew::SORT_BY_NAME_ASC );

            // Removes all the non unlocked volumes from the list.
            foreach ($volumes as $k => $v) {
                if ($v['filter'] == 'aes256') {
                    if (!$access_sx->volumeIsUnlocked($v['path']) ) {
                        unset($volumes[$k]);
                    }
                }
            }
            $this->view->vol = $volumes;
            $this->view->list = $access_sx->sxls($path, Skylable_AccessSxNew::SORT_BY_NAME_ASC, FALSE,  Skylable_AccessSxNew::LIST_DIRECTORIES );
            if ($this->view->list === FALSE) {
                $this->getResponse()->setHttpResponseCode(500);
                $this->view->has_error = TRUE;
                $this->view->error = "Internal error. Please retry later.";
            }

        } catch (Exception $e) {
            $this->getResponse()->setHttpResponseCode(500);
            $this->view->has_error = TRUE;
            $this->view->error = "Internal error. Please retry later.";
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
                $access_sx = new Skylable_AccessSxNew( Zend_Auth::getInstance()->getIdentity() );
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
                        'error' => 'Failed to create directory',
                        'url' => ''
                    ));
                }

            }
            catch(Exception $e) {
                $this->getResponse()->setHttpResponseCode(500);
                echo Zend_Json::encode(array(
                    'status' => FALSE,
                    'error' => 'Internal error: failed to create directory',
                    'url' => ''
                ));
            }
        } else {
            $this->getResponse()->setHttpResponseCode(400);
            echo Zend_Json::encode(array(
                'status' => FALSE,
                'error' => 'Invalid directory name',
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
            echo '<p>Invalid input.<p>';
            return FALSE;
        }

        try {
            $access_sx = new Skylable_AccessSxNew( Zend_Auth::getInstance()->getIdentity() );
            $status = $access_sx->remove($files, TRUE);
            if ($status === FALSE) {
                $errors = $access_sx->getLastErrorLog();
                $this->getResponse()->setHttpResponseCode(400);
                echo '<p>Failed to delete files.<p>';
            } else {
                echo '<p>Files successfully deleted.<p>';
            }
        }
        catch(Exception $e) {
            $this->getResponse()->setHttpResponseCode(500);
            echo '<p>Internal error.<p>';
            return FALSE;
        }
    }
}
