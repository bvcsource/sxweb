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
 * Validate a shared file password.
 * 
 * This validator accepts an empty string: if the string is not empty
 * it must be at least PASSWORD_MIN_LENGTH char long.
 */
class My_ValidateSharedFilePassword extends Zend_Validate_StringLength {

    protected $_messageTemplates = array(
        self::INVALID   => "Invalid password: should be between %min% and %max% alphanumeric chars.",
        self::TOO_SHORT => "Invalid password: is less than %min% characters long",
        self::TOO_LONG  => "Invalid password: is more than %max% characters long",
    );
    
    const
        /**
         * String length defines.
         */
        PASSWORD_MIN_LENGTH = 8,
        PASSWORD_MAX_LENGTH = 30;

    public function __construct($allow_empty_password = TRUE) {
        parent::__construct(array( 'min' => ($allow_empty_password ? 0 : self::PASSWORD_MIN_LENGTH), 'max' => self::PASSWORD_MAX_LENGTH));
    }

    public function isValid($value) 
    {
        $is_valid = parent::isValid($value);
        
        if ($is_valid) {
            // Be compatible with Zend_Validate_StringLength length guessing
            if ($this->_encoding !== null) {
                $length = iconv_strlen($value, $this->_encoding);
            } else {
                $length = iconv_strlen($value);
            }

            // If the string is non empty, it must be at least
            // PASSWORD_MIN_LENGTH chars long.
            if ($length > 0) {
                $this->setMin(self::PASSWORD_MIN_LENGTH);
                return parent::isValid($value);
            }
        } 
        return $is_valid;
    }


}