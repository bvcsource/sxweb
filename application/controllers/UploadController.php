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
 * Handle file uploads.
 */
class UploadController extends My_BaseAction {

    /**
     * Uploads files to the cluster.
     *
     * Parameters:
     * 'vol' - string, the destination volume
     * 'url' - string, the optional destination path
     * 'files[]' - array, the uploaded files
     *
     *
     */
	public function uploadAction() {
        $this->disableView();

        require_once 'UploadHandler.php';

        $dir = Zend_Registry::get('skylable')->get('upload_dir');

        if (strlen(trim($dir)) > 0) {
            $dir = My_Utils::slashPath( $dir );
            if (Zend_Auth::getInstance()->hasIdentity()) {
                $upload_path = My_Utils::slashPath( $dir . strval(Zend_Auth::getInstance()->getIdentity()->getId()) ) ;
            } else {
                $upload_path = NULL;
            }

            $uh = new UploadHandler( array(
                // 'sxurl' => $the_path,
                'sx_volume_param' => 'vol',
                'sx_path_param' => 'url',
                'upload_dir' => $upload_path,
                'image_versions' => array()
            ) );

        } else {
            $this->getLogger()->err(__METHOD__.': Check your config, upload dir is empty.');

            $this->getResponse()->setHttpResponseCode(500);
            $this->getResponse()->setRawHeader('Content-Type: application/json; charset=UTF-8');
            echo json_encode(array('error' => $this->getTranslator()->translate('Check your config, upload dir is empty.')));

            return FALSE;
        }
    }

}

