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
 *
 * Checks if a path is valid and exists on the SX cluster for the given user.
 *
 */
class My_ValidateSxPath extends  Zend_Validate_Abstract {

    const
        MSG_FORMAT = 'format',
        MSG_NOT_FOUND = 'not_found',
        MSG_WRONG_TYPE = 'wrong_type',
        MSG_ACCESS_ERROR = 'access_err';
    protected
        $_messageTemplates = array(
        self::MSG_FORMAT => 'Invalid format',
        self::MSG_NOT_FOUND => 'Path not found',
        self::MSG_WRONG_TYPE => 'Wrong file type',
        self::MSG_ACCESS_ERROR => 'Cluster access error'
        );

    const
        FILE_TYPE_ANY = 0,
        FILE_TYPE_DIR = 1,
        FILE_TYPE_FILE = 2,
        FILE_TYPE_VOLUME = 3;
    protected
        $_access_error_exception = NULL,
        $_file_type,
        $_access_sx;

    public function __construct(Skylable_AccessSxNG $access_sx, $file_type = self::FILE_TYPE_ANY) {
        $this->_access_sx = $access_sx;
        $this->_file_type = $file_type;
    }

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
        $this->_access_error_exception = NULL;

        if (!is_string($value)) {
            $this->_error(self::MSG_FORMAT);
            return FALSE;
        }

        if (strlen($value) > 255 || strlen($value) == 0) {
            $this->_error(self::MSG_FORMAT);
            return FALSE;
        }

        try {
            if ($this->_file_type == self::FILE_TYPE_VOLUME) {
                $file_exists = $this->_access_sx->volumeExists($value);
                $file_type = self::FILE_TYPE_VOLUME;
            } else {
                $file_exists = $this->_access_sx->fileExists($value, $file_type);
            }

            if (!$file_exists) {
                $this->_error(self::MSG_NOT_FOUND);
                return FALSE;
            }

            if ($this->_file_type !== self::FILE_TYPE_ANY) {
                switch($file_type) {
                    case Skylable_AccessSxNG::FILE_TYPE_DIR: $ft = self::FILE_TYPE_DIR; break;
                    case Skylable_AccessSxNG::FILE_TYPE_VOLUME: $ft = self::FILE_TYPE_VOLUME; break;
                    default:
                        $ft = self::FILE_TYPE_FILE;
                }
                if ($ft != $this->_file_type) {
                    $this->_error(self::MSG_WRONG_TYPE);
                    return FALSE;
                }
            }

            return TRUE;
        }
        catch(Exception $e) {
            $this->_error(self::MSG_ACCESS_ERROR);
            $this->_access_error_exception = $e;
            return FALSE;
        }
    }

    /**
     * Returns the access exception if the validation
     * fails for this reason.
     *
     * @return Exception
     */
    public function getAccessException() {
        return $this->_access_error_exception;
    }

} 