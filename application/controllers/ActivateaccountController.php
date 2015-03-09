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
 * Manages activation of accounts
 */
class ActivateaccountController extends My_BaseAction {
	
    /**
     * Activate an account using the supplied hash.
     * 
     * HTTP Parameter:
     * hash - the activation hash key
     */
    public function indexAction() {

        if ($this->isDemoMode()) {
            $this->redirect('/demo');
            return FALSE;
        }
        
        // If logged in, don't do anything
        if (Zend_Auth::getInstance()->hasIdentity()) {
            $this->redirect("/");
            return false;
        }
        
        $this->_helper->layout->getLayout('simple');
        
        $validate_hash = new My_ValidateActivationHash();
        $hash = $this->getRequest()->getParam('hash');
        
        $hash_is_valid = $validate_hash->isValid( $hash );
        if ( !$hash_is_valid ) {
            
            $this->getInvokeArg('bootstrap')->getResource('log')->err('Activateaccount: invalid hash '. print_r($hash, TRUE) );
            
            $this->_helper->flashMessenger
                ->setNamespace('error')
                ->addMessage('Invalid activation code.');
           
           $this->redirect("/login");
           return false;
        }
        
        // Check if the hash is present on the DB
        try {
            $this->getInvokeArg('bootstrap')->getResource('log')->debug('Activateaccount: validating hash '. $hash );
            $model = new My_AccountKeys();
            $uid = $model->activateAccount($hash);
            if ($uid === FALSE) {
                // Activation failed...
                $this->_helper->flashMessenger
                ->setNamespace('error')
                ->addMessage('Invalid activation code.');
                
                $this->getInvokeArg('bootstrap')->getResource('log')->debug('Activateaccount: activation failed.');
            } else {
                $this->_helper->flashMessenger
                ->setNamespace('success')
                ->addMessage('Your account is successfully activated.');
                $this->getInvokeArg('bootstrap')->getResource('log')->debug('Activateaccount: activation succeded.');
            }
            $this->redirect("/login");
        }
        catch(Exception $e) {
            $this->_helper->flashMessenger
                ->setNamespace('error')
                ->addMessage('Internal error, please retry later.');
           $this->getInvokeArg('bootstrap')->getResource('log')->err('Activateaccount: activation exception: .'. strval($e->getCode()) . ' '. $e->getMessage() );
           $this->redirect("/login");
           return false;
        }
		
    }
}

