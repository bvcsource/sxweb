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
 * A Response class that sends revisions files to the client.
*/
class My_DownloadRevisionResponse extends Zend_Controller_Response_Http {

    protected
        $_access_sx, $_file_data, $_password, $_purge_dir, $_disposition;

    /**
     * Send the file to the browser.
     *
     * $file_data is an associative array as returned by the {@link Skylable_AccessSx::sxrevList }
     * method.
     *
     * @param Skylable_AccessSx $access_sx
     * @param array $file_data
     * @param string $password
     * @param string $purge_dir if non empty path to delete when download succeded
     */
    public function __construct(Skylable_AccessSx $access_sx, $file_data, $password = '', $purge_dir = '', $disposition = Skylable_AccessSx::DOWNLOAD_DISPOSITION_ATTACHMENT) {
        $this->_access_sx = $access_sx;
        $this->_file_data = $file_data;
        $this->_password = $password;
        $this->_purge_dir = $purge_dir;
        $this->_disposition = $disposition;
    }

    public function getLogger() {
        return Zend_Controller_Front::getInstance()->getParam('bootstrap')->getResource('log');
    }

    public function sendResponse() {
        // Don't send the headers, download() will send them
        set_time_limit(0);

        try {
            $ret = $this->_access_sx->sxrevDownload($this->_file_data, $this->_password, $this->_disposition);
            if ($ret !== TRUE) {
                $this->getLogger()->debug(__METHOD__.': sxrevDownload() retval is: '.var_export($ret, TRUE));
            }
        }
        catch(Exception $e) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', TRUE, 500);
            $this->getLogger()->debug(__METHOD__.': exception: '.$e->getMessage());
        }

        try {
            if (strlen($this->_purge_dir) > 0) {
                $this->getLogger()->debug(__METHOD__.': purging directory: '.$this->_purge_dir);
                My_Utils::deleteDir($this->_purge_dir);
            }
        }
        catch(Exception $e) {
            $this->getLogger()->debug(__METHOD__.': exception: '.$e->getMessage());
        }

    }

}