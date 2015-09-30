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
 * Check the shared file expire time.
 * 
 * The expire time is expressed in SECONDS.
 */
class My_ValidateSharedFileExpireTime extends Zend_Validate_Abstract {
    const
        // Units of time to use
        TIME_IN_HOURS = 1,
        TIME_IN_SECONDS = 2;
    
    protected 
        $_time_unit;
    
    public function __construct($time_unit = self::TIME_IN_HOURS)
    {
        $this->_time_unit = $time_unit;
    }

    public function isValid($value)
    {
        $this->_setValue($value);

        if (!is_numeric($value)) {
            return FALSE;
        }
        // 10 chars is more than sufficient to handle years and seconds
        if (preg_match('/^[1-9]\d{0,9}$/', $value) == 0) {
            return FALSE;
        }
        
        if (intval($value) == 0) {
            return FALSE;
        }

        // The passed value is numeric, check if added to the current year
        // passes the year 9999, maximum value for a DATETIME DB field.
        try {
            $expire_at = new DateTime();
            $expire_at->add( new DateInterval( 'PT' . $value . ($this->_time_unit == self::TIME_IN_HOURS ? 'H' : 'S') ) );
            if (intval($expire_at->format('Y')) < 9999) {
                return TRUE;
            }
        }
        catch(Exception $e) {
        }

        return FALSE;
    }


}