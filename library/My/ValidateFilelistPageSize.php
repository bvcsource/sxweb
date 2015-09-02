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
 * Validate the file list page size.
 * Valid value are:
 * an empty string: means no pagination at all
 * an integer between 1-65535: the desired page size
 */
class My_ValidateFilelistPageSize extends Zend_Validate_Abstract {

    const
        WRONGVALUE = 'wrongvalue';
    protected
        $_messageTemplates = array(
            self::WRONGVALUE => "The value must be an integer between 1 and 65535 or an empty string"
        );
    
    /**
     * Returns true if and only if $value meets the validation requirements
     *
     * If $value fails validation, then this method returns false, and
     * getMessages() will return an array of messages that explain why the
     * validation failed.
     *
     * @param  mixed $value
     * @return boolean
     * @throws Zend_Validate_Exception If validation of $value is impossible
     */
    public function isValid($value) {
        
        $this->_setValue($value);
        
        if (strlen($value) == 0) {
            return TRUE;
        }
        
        if (preg_match('/\d+/', $value) == 1) {
            if ($value >= 1 && $value <= 65535) {
                return TRUE;    
            } 
        }
        
        $this->_error(self::WRONGVALUE);
        
        return FALSE;
        
    }


}