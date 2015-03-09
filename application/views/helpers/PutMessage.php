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
 * Adds/shows messages/notifications into the page
 */
class Zend_View_Helper_PutMessage extends Zend_View_Helper_Abstract  {
    protected 
        $_messages = array();
    
    const
        // Message type
        MESSAGE_INFO = 1,
        MESSAGE_ERROR = 2,
        MESSAGE_SUCCESS = 3;

    /**
     * @param null $msg
     * @param int $type
     * @return $this
     */
    public function putMessage($msg = NULL, $type = self::MESSAGE_INFO) {
        if (!is_null($msg)) {
            
            if (!array_key_exists($type, $this->_messages)) $this->_messages[$type] = array();
            $this->_messages[$type][] = $msg;
        }
        return $this;
    }
    
    public function addInfo($msg) {
        $this->putMessage($msg, self::MESSAGE_INFO);
    }
    
    public function addError($msg) {
        $this->putMessage($msg, self::MESSAGE_ERROR);
    }
    
    public function addSuccess($msg) {
        $this->putMessage($msg, self::MESSAGE_SUCCESS);
    }

    /**
     * Delete all messages of a certain type. If no type is specified deletes all messages.
     * @param null $type
     */
    public function clear($type = NULL) {
        if (!is_null($type)) {
            if (array_key_exists($type, $this->_messages)) $this->_messages[$type] = array();
        } else {
            $this->_messages = array();    
        }
    }
    
    public function __toString() {
        $out = '';

        $context = array(
            self::MESSAGE_SUCCESS => 'success',
            self::MESSAGE_INFO => 'info',
            self::MESSAGE_ERROR => 'failure'
        );
        
        foreach($context as $type => $css_class) {
            if (array_key_exists($type, $this->_messages)) {
                foreach($this->_messages[$type] as $msg) {
                    $out .= '<div class="'.$css_class.' notification-fix"><span class="inf" ><span class="icon-failure ir"></span>'.
                        $this->view->escape($msg).'</span></div>'.PHP_EOL;
                }
            }

        }
            
        return $out;
    }


}