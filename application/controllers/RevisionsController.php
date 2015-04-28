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
            $rev_id = $this->getRequest()->getParam('rev_id');
            if (!empty($rev_id) && is_string($rev_id)) {
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
                                    echo '<p>',$this->getTranslator()->translate('Revision successfully copied.'),'</p>';
                                } else {
                                    echo '<p>',$this->getTranslator()->translate('Failed to copy the revision.'),'</p>';
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
                $this->view->file_path = substr( $path, 0, strrpos($path, '/') );
                $this->setLastVisitedPath($this->view->file_path);
                
                $this->view->revisions = $revisions;
            }
        }
        catch(Exception $e) {
            $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());
            $this->view->error = $this->getTranslator()->translate('Internal error.');
        }
    }
}