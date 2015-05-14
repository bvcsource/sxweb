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
 * Manages file revisions
 */
class RevisionsController extends My_BaseAction {

    /**
     * Lists/copies available revisions of a file.
     * 
     * If you call it in a POST request, it will create/copy new revisions
     * 
     * Parameters:
     * 'path' - the complete (volume+path) path of a file
     * 'rev_id' - the revision hash, only if called in a POST request
     */
    public function indexAction() {
        
        $this->_helper->getHelper('Layout')->setLayout('clean');
        
        if (!Zend_Auth::getInstance()->hasIdentity()) {
            $this->redirect('/login');
            return FALSE;
        }

        $validate_path = new My_ValidatePath();
        $path = $this->getRequest()->getParam('path');
        if (!$validate_path->isValid($path)) {
            $this->view->error = $this->getTranslator()->translate('Invalid input.');
            return FALSE;
        }
        
        

        /**
         * A POST request means an action.
         */
        if ($this->getRequest()->isPost()) {
            if ($this->checkRevisionHash('rev_id', $rev_id)) {
                try {
                    $access_sx = new Skylable_AccessSxNew( Zend_Auth::getInstance()->getIdentity() );
                    $revisions = $access_sx->sxrevList($path);
                    if ($revisions === FALSE) {
                        $this->view->error = $this->getTranslator()->translate('Path not found or revisions not supported.');
                    } else {
                        // Search the wanted revision...
                        foreach($revisions as $rev) {
                            if (strcmp(sha1($rev['rev']), $rev_id) == 0) {
                                if ($access_sx->sxrevCopy($path, $rev['rev'], $path, TRUE)) {
                                    $this->view->action_result = $this->getTranslator()->translate('Revision successfully copied.');
                                } else {
                                    $this->view->error = $this->getTranslator()->translate('Failed to copy the revision.');
                                }
                                break;
                            }
                        }

                    }
                }
                catch(Exception $e) {
                    $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());

                    $this->view->error = $this->getTranslator()->translate('Internal error.');
                }
            }
        }

        try {
            $this->view->path = $path;

            if (!isset($access_sx)) {
                $access_sx = new Skylable_AccessSxNew( Zend_Auth::getInstance()->getIdentity() );    
            }
            
            $revisions = $access_sx->sxrevList($path);
            if ($revisions === FALSE) {
                $this->view->error = $this->getTranslator()->translate('Path not found or revisions not supported.');
            } else {
                // Save the path
                $this->view->file_path = pathinfo( $path, PATHINFO_DIRNAME );
                $this->setLastVisitedPath($this->view->file_path);
                
                $this->view->revisions = $revisions;
            }
        }
        catch(Exception $e) {
            $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());
            $this->view->error = $this->getTranslator()->translate('Internal error.');
        }
    }

    /**
     * Lets you download specific revisions.
     *
     * Parameters:
     * 'path' - the file path to download
     * 'rev_id' - the revision hash
     *
     */
    public function downloadAction() {
        

            $this->view->error_title = '';
            $this->view->error_message = '';
            $this->_helper->layout()->assign('show_message', FALSE);
            $allow_download = TRUE;

            $continue_browsing = ' <a href="%s" title="'.$this->getTranslator()->translate('Continue browsing files...').'">'.$this->getTranslator()->translate('Continue browsing files...').'</a>';

            $filename = $this->getRequest()->getParam('path');
            $filename_check = new My_ValidatePath();
            
            if (!$filename_check->isValid($filename)) {
                $this->getLogger()->debug(__METHOD__.': Invalid filename: '.print_r($filename, TRUE));
                $this->getResponse()->setHttpResponseCode(404);
                $this->view->error_title = $this->getTranslator()->translate('Revision not found!');
                $this->view->error_message = $this->getTranslator()->translate('The requested revisions was not found') . sprintf($continue_browsing, '/');
                $allow_download = FALSE;
            } elseif (!$this->checkRevisionHash('rev_id', $rev_id)) {
                $this->getLogger()->debug(__METHOD__.': Invalid revision hash ');
                $this->getResponse()->setHttpResponseCode(404);
                $this->view->error_title = $this->getTranslator()->translate('Revision not found!');
                $this->view->error_message = $this->getTranslator()->translate('The requested revisions was not found') . sprintf($continue_browsing, pathinfo($filename, PATHINFO_DIRNAME));
                $allow_download = FALSE;
            }

        try {
            
            if ($allow_download) {

                $this->getLogger()->debug(__METHOD__.': Downloading: '.print_r($filename, TRUE));

                $tickets = new My_Tickets();

                if ($tickets->registerTicket( Zend_Auth::getInstance()->getIdentity()->getId(), NULL ) === FALSE) {
                    $this->getResponse()->setHttpResponseCode(500);
                    $this->view->error_title = $this->getTranslator()->translate('Too many concurrent downloads!');
                    $this->view->error_message = $this->getTranslator()->translate('Please wait a minute and retry. ').sprintf($continue_browsing, pathinfo($filename, PATHINFO_DIRNAME));
                    $allow_download = FALSE;
                } else {
                    $access_sx = new Skylable_AccessSxNew( Zend_Auth::getInstance()->getIdentity() );

                    // Get file data
                    $rev_data = $access_sx->sxrevList($filename);

                    if ($rev_data === FALSE || empty($rev_data)) {
                        // File not found.
                        $allow_download = FALSE;
                        $this->getResponse()->setHttpResponseCode(404);
                        $this->view->error_title = $this->getTranslator()->translate('File not found!');
                        $this->view->error_message = sprintf($this->getTranslator()->translate('The file &quot;%s&quot; was not found.'),$this->view->escape($filename)).sprintf($continue_browsing, pathinfo($filename, PATHINFO_DIRNAME));
                    } else {
                        
                        foreach($rev_data as $rev) {
                            if (strcmp(sha1($rev['rev']), $rev_id) == 0) {
                                $file_data = $rev;
                                $file_data['path'] = $filename;
                                break;
                            }
                        }
                        if (!isset($file_data)) {
                            $this->getResponse()->setHttpResponseCode(500);
                            $this->view->error_title = $this->getTranslator()->translate('Invalid file type!');
                            $this->view->error_message = sprintf($this->getTranslator()->translate('The file &quot;%s&quot; can\'t be downloaded.'), $this->view->escape($filename)).sprintf($continue_browsing, pathinfo($filename, PATHINFO_DIRNAME));
                            $allow_download = FALSE;
                        }
                    }
                }
            } 

            if ($allow_download) {
                $this->disableView();
                $res = new My_DownloadRevisionResponse($access_sx, $file_data);
                $this->getFrontController()->setResponse($res);
            } else {
                $this->_helper->layout()->setLayout('application-failure');
                $this->renderScript('error/malfunction.phtml');
            }
        }
        catch (Skylable_InvalidCredentialsException $e) {
            $this->enableView();
            
            $this->invalidCredentialsExceptionHandler(__METHOD__, $e, $access_sx, 500);

            return FALSE;
        }
        catch(Exception $e) {
            $this->enableView();
            $this->getResponse()->setHttpResponseCode(500);
            $this->view->error_title = $this->getTranslator()->translate('Internal error!');
            $this->view->error_message = $this->getTranslator()->translate('Application encountered an internal error.').sprintf($continue_browsing, pathinfo($filename, PATHINFO_DIRNAME));
            $this->_helper->layout()->setLayout('application-failure');
            $this->_helper->layout()->assign('exception', $e);
            $this->renderScript('error/malfunction.phtml');
        }
    }
    
    public function checkRevisionHash($name, &$rev) {
        $rev = $this->getRequest()->getParam($name);
        $v = new Zend_Validate_Regex('/^[a-f0-9]{40}$/');
        if ($v->isValid($rev)) {
            return TRUE;
        }
        $rev = NULL;
        return FALSE;
    }
}