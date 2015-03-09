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
 * Removes an user account.
 */
class RmaccController extends My_BaseAction {

    /**
     * Deletes the current user.
     * 
     * Parameters:
     * 'remove' - string, if 'yes' effectively delete the user
     */
	public function indexAction() {

        if ($this->isDemoMode()) {
            $this->redirect('/demo');
            return FALSE;
        }

        $this->_helper->layout->setLayout('simple');
        
        if (!Zend_Auth::getInstance()->hasIdentity()) {
            return FALSE;
        }
        
		$remove = $this->getParam('remove');
        if ($remove === 'yes') {
            try {
                $model = new My_Accounts();
                if ($model->deleteAccount( Zend_Auth::getInstance()->getIdentity()->getId() )) {
                    
                    $access_sx = new Skylable_AccessSxNew( Zend_Auth::getInstance()->getIdentity() );
                    $access_sx->purgeProfile();

                    Zend_Auth::getInstance()->clearIdentity();
                    Zend_Session::forgetMe();
                    Zend_Session::destroy();

                    $this->_helper->getHelper('FlashMessenger')->addMessage('Account successfully deleted!','success');
                    
                    $this->redirect("/index");
                } else {
                    $this->_helper->getHelper('FlashMessenger')->addMessage('Failed to remove the account','error');
                    $this->redirect("/index");
                }
            }
            catch(Exception $e) {
                $this->_helper->getHelper('FlashMessenger')->addMessage('Failed to remove the account','error');
                $this->getLogger()->err(__METHOD__.': exception: '.$e->getMessage());
                $this->redirect("/index");
            }
        }
	}
}

