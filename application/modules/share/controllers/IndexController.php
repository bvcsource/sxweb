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



/*
 * To test use:
 *
 *  wget -O- --no-check-certificate --post-data='{"access_key":"fdfdsdfds","path":"/vol/my/file" }' https://share.indian.skylable.com/api/share > asdasd
 */

/**
 * Manages the share API.
 *
 * Let a user share a file using its credentials.
 *
 * Accepts a JSON string and returns a JSON string.
 *
 * ---- Input:
 *
 * <code>
 * {
 *  "access_key" : "...",
 *  "path" : "/a/b/c",
 *  "expire_time" : NNN,
 *  "password" : ...
 * }
 * </code>
 *
 * "access_key" is the auth token of the user, "path" a complete path of a file
 * on the SX cluster.
 *
 * "expire_time" seconds after which the shared file expires. Is not mandatory and is expressed in seconds.
 * "password" non mandatory, a string at least 6 char long to protect the file.
 *
 * ---- Output
 * On success returns a JSON so formed:
 *
 * <code>
 * {
 *  "status" : true,
 *  "publink" : "sx://..."
 * }
 * </code>
 *
 * Where "publink" is the URL to use to access the file
 *
 * On error, returns an HTTP Code and the JSON:
 *
 * <code>
 * {
 *  "status": false,
 *  "error" : "error reason"
 * }
 * </code>
 *
 */
class Share_IndexController extends My_BaseAction {

    /**
     * Record a new shared file.
     *
     * @return bool
     */
     public function shareAction() {

         $this->disableView();
         $this->getResponse()->setHeader('Content-Type', 'application/json');

        if (!$this->getRequest()->isPost()) {
            echo Zend_Json::encode(array(
                'status' => FALSE,
                'error' => 'Invalid request method'
            ));
            return FALSE;
        }
        $json = $this->getRequest()->getRawBody();
        try {
            $data = Zend_Json::decode($json, Zend_JSon::TYPE_ARRAY);
            if (is_null($data)) {
                echo Zend_Json::encode(array(
                    'status' => FALSE,
                    'error' => 'Invalid json string'
                ));
                return FALSE;
            }
        }
        catch(Zend_Json_Exception $e) {
            echo Zend_Json::encode(array(
                'status' => FALSE,
                'error' => 'Invalid json string'
            ));
            return FALSE;
        }

        // Check if parameter exists
        if (!array_key_exists('access_key', $data) || !array_key_exists('path', $data)) {
            echo Zend_Json::encode(array(
                'status' => FALSE,
                'error' => 'Invalid json input'
            ));
            return FALSE;
        }

        // Validate parameters
        $user_key_check = new My_ValidateUserKey();
        if (!$user_key_check->isValid($data['access_key'])) {
            echo Zend_Json::encode(array(
                'status' => FALSE,
                'error' => 'Invalid access key'
            ));
            return FALSE;
        }
        $path_check = new My_ValidatePath();
        $path = rawurldecode($data['path']);
        if (!$path_check->isValid($path)) {
            echo Zend_Json::encode(array(
                'status' => FALSE,
                'error' => 'Invalid path'
            ));
            return FALSE;
        }

        $this->getInvokeArg('bootstrap')->getResource('log')->debug(__METHOD__.': path is: '.$path );

         // Get optional parameters
         $password = NULL;
         $expire_time = NULL;
         if (array_key_exists('password', $data)) {
            if (is_string($data['password'])) {
                $l = strlen($data['password']);
                if ($l >= 6 || $l <= 30) {
                    $password = $data['password'];
                } else {
                    echo Zend_Json::encode(array(
                        'status' => FALSE,
                        'error' => 'Invalid password (must be at least 6 char long)'
                    ));
                    return FALSE;
                }
            } else {
                echo Zend_Json::encode(array(
                    'status' => FALSE,
                    'error' => 'Invalid password'
                ));
                return FALSE;
            }
         }
         if (array_key_exists('expire_time', $data)) {
            if (is_string($data['expire_time'])) {
                if (preg_match('/^[1-9]\d{0,10}$/', $data['expire_time']) == 1) {
                    $expire_time = intval( $data['expire_time'] );
                } else {
                    echo Zend_Json::encode(array(
                        'status' => FALSE,
                        'error' => 'Invalid expire time (too long or non numeric)'
                    ));
                    return FALSE;
                }
            } else {
                echo Zend_Json::encode(array(
                    'status' => FALSE,
                    'error' => 'Invalid expire time'
                ));
                return FALSE;
            }
         }
         if (is_null($expire_time)) {
             $expire_time = Zend_Registry::get('skylable')->get('shared_file_expire_time');
         }

        try {

            // Use the user key to access the file on the cluster
            // 1. Check if the volume is encrypted (and the user is valid)
            $access_sx = new Skylable_AccessSxNG( My_Utils::getAccessSxNGOpt(NULL, array( 'secret_key' => $data['access_key'] )) );
            $volume = My_Utils::getRootFromPath($path);
            $the_file = My_Utils::skipPath($path, 2);
            try {
                $volumes = $access_sx->volumeList();
                if ($volumes === FALSE) {
                    echo Zend_Json::encode(array(
                        'status' => FALSE,
                        'error' => 'Initialization failed'
                    ));
                    return FALSE;
                } else {
                    $volume_found = FALSE;
                    foreach($volumes as $vol => $vol_data) {
                        if (strcmp($vol, $volume) == 0) {
                            $volume_found = TRUE;
                            break;
                        }
                    }
                    if (!$volume_found) {
                        echo Zend_Json::encode(array(
                            'status' => FALSE,
                            'error' => 'Invalid path'
                        ));
                        return FALSE;
                    }
                }
            }
            catch(Skylable_AccessSxException $e) {
                echo Zend_Json::encode(array(
                    'status' => FALSE,
                    'error' => $e->getMessage()
                ));
                return FALSE;
            }

            // 2. Check if the file exists
            try {

                $file_list = $access_sx->ls($volume, $the_file );
                if ($file_list === FALSE) {
                    echo Zend_Json::encode(array(
                        'status' => FALSE,
                        'error' => 'Invalid path'
                    ));
                    return FALSE;
                } else {
                    if (count($file_list['fileList']) == 0) {
                        echo Zend_Json::encode(array(
                            'status' => FALSE,
                            'error' => 'File not found'
                        ));
                        return FALSE;
                    } else {
                        $file_found = FALSE;
                        foreach($file_list['fileList'] as $f_name => $f_data) {
                            if (strcmp($f_name, $the_file) == 0) {
                                if (empty($f_data)) {
                                    // The file is a directory
                                    echo Zend_Json::encode(array(
                                        'status' => FALSE,
                                        'error' => 'Invalid path: you can\'t share a directory!'
                                    ));
                                    return FALSE;
                                }
                                $file_found = TRUE;
                                break;
                            }
                        }
                        if (!$file_found) {
                            echo Zend_Json::encode(array(
                                'status' => FALSE,
                                'error' => 'File not found'
                            ));
                            return FALSE;
                        }
                    }
                }
            }
            catch(Skylable_AccessSxException $e) {
                echo Zend_Json::encode(array(
                    'status' => FALSE,
                    'error' => $e->getMessage()
                ));
                return FALSE;
            }

            // If we are here, everything about the file is fine, save into the DB

            // Get the shared file access string
            $shared_model = new My_Shared();
            $file_key = '';
            if ($shared_model->fileExists($path, $data['access_key'], $file_key)) {
                echo Zend_Json::encode(array(
                    'status' => TRUE,
                    'publink' => $this->getPublink($file_key, $path)
                ));
            } else {
                $file_key = $shared_model->add($path, $data['access_key'], $expire_time, $password );
                if ($file_key === FALSE) {
                    echo Zend_Json::encode(array(
                        'status' => FALSE,
                        'error' => 'Failed to share file'
                    ));
                } else {
                    echo Zend_Json::encode(array(
                        'status' => TRUE,
                        'publink' => $this->getPublink($file_key, $path)
                    ));
                }
            }
        }
        catch(Exception $e) {
            echo Zend_Json::encode(array(
                'status' => FALSE,
                'error' => 'Internal error'
            ));
            $this->getInvokeArg('bootstrap')->getResource('log')->debug(__METHOD__.': '.$e->getMessage().$e->getTraceAsString());
        }

    }

    /**
     * Given a shared file info, returns the URL to use to access it
     *
     * NOTE: $path is the complete path of the file
     *
     * @param string $file_key
     * @param string $path
     * @return string
     * @throws Zend_Exception
     */
    protected function getPublink($file_key, $path) {
        return Zend_Registry::get('skylable')->get('url') . "/shared/file/" . $file_key . "/" . rawurlencode(basename($path));
        // return "https://share.".parse_url(Zend_Registry::get('skylable')->get('cluster'), PHP_URL_HOST)."/shared/file/".$file_key."/".rawurlencode(basename($path));
    }
}