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
        $this->getLogger()->debug(__METHOD__.': RAW DATA: '.print_r($json, TRUE));
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
            $this->getLogger()->err(__METHOD__.': RAW DATA parsing error: '.$e->getMessage());
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
            $validate_password = new My_ValidateSharedFilePassword();
            if ($validate_password->isValid($data['password'])) {
                $password = $data['password'];
            } else {
                echo Zend_Json::encode(array(
                    'status' => FALSE,
                    'error' => implode( "\n", $validate_password->getMessages())
                ));
                return FALSE;
            }
         }
         
         if (array_key_exists('expire_time', $data)) {
             $this->getLogger()->debug(__METHOD__.': expire time: '.print_r($data['expire_time'], TRUE));
             $validate_expire_time = new My_ValidateSharedFileExpireTime(My_ValidateSharedFileExpireTime::TIME_IN_SECONDS);
             if ($validate_expire_time->isValid($data['expire_time'])) {
                 $expire_time = intval( $data['expire_time'] );
             } else {
                 echo Zend_Json::encode(array(
                     'status' => FALSE,
                     'error' => 'Invalid expire time (too long or non numeric)'
                 ));
                 return FALSE;
             }
         }
         if (is_null($expire_time)) {
             $expire_time = Zend_Registry::get('skylable')->get('shared_file_expire_time');
         }

        try {

            // If the file is empty, you are trying to share a volume
            $the_file = My_Utils::skipPath($path, 2);
            $this->getLogger()->debug(__METHOD__.': file to share: '.print_r($the_file, TRUE));

            if (strlen($the_file) == 0) {
                echo Zend_Json::encode(array(
                    'status' => FALSE,
                    'error' => 'Invalid path: you can\'t share a volume!'
                ));
                return FALSE;
            }
            
            
            // Create a fake user to do the various checks
            $this->getLogger()->debug(__METHOD__.': creating a temporary identity.');
            $the_user = new My_User(NULL, '', '', $data['access_key']);
            $the_dir = My_Utils::mktempdir( Zend_Registry::get('skylable')->get('sx_local'), 'Skylable_' );
            if ($the_dir === FALSE) {
                $this->getLogger()->err(__METHOD__.': Failed to create the user dir into: '. Zend_Registry::get('skylable')->get('sx_local'));
                throw new Exception('Internal error: failed to create temporary files' );
            } else {
                $base_dir = $the_dir;
                $this->getLogger()->debug(__METHOD__.': temporary user dir is: '.$the_dir);
            }

            $access_sx = new Skylable_AccessSx( $the_user, $base_dir, array( 'user_auth_key' => $data['access_key'] ));

            // Check if the volume is encrypted
            if ($access_sx->volumeIsEncrypted( My_Utils::getRootFromPath($path) )) {
                echo Zend_Json::encode(array(
                    'status' => FALSE,
                    'error' => 'Sharing from encrypted volumes is not allowed'
                ));
                $this->cleanupUserData($base_dir);
                return FALSE;
            }
            
            // Removing slashes ensures that we can check a directory
            // getFileInfo uses sxls, removing slashes from path make sxls lists
            // only the parent directory ("/foo" lists "foo", "/foo/" lists "foo" contents) 
            $file_info = $access_sx->getFileInfo( My_Utils::removeSlashes( $path ) );
            if ($file_info === FALSE) {
                echo Zend_Json::encode(array(
                    'status' => FALSE,
                    'error' => 'File not found'
                ));
                $this->cleanupUserData($base_dir);
                return FALSE;
            } else {
                // Check if the file is a directory
                if (strcasecmp($file_info['type'], 'file') != 0) {
                    echo Zend_Json::encode(array(
                        'status' => FALSE,
                        'error' => 'Invalid path: you can\'t share a directory!'
                    ));
                    $this->cleanupUserData($base_dir);
                    return FALSE;
                }
                
            }

            // Access to the SX cluster is not needed anymore, clean up
            $this->cleanupUserData($base_dir);


            // If we are here, everything about the file is fine, save into the DB

            // Get the shared file access string
            $shared_model = new My_Shared();
            $file_key = '';
            if ($shared_model->fileExists($path, $data['access_key'], $file_key)) {
                /*
                 * fix for bug #1379:
                 * if the file exists, and the password is not set into the request
                 * then remove the password from the already shared file
                 * */
                if (is_null($password)) {
                    $password = '';
                }
                $ok_up = $shared_model->updateFile($file_key, $password, $expire_time);
                if ($ok_up) {
                    echo Zend_Json::encode(array(
                        'status' => TRUE,
                        'publink' => $this->getSharedFileURL($file_key, $path)
                    ));    
                } else {
                    echo Zend_Json::encode(array(
                        'status' => FALSE,
                        'error' => 'Failed to share file'
                    ));
                }
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
                        'publink' => $this->getSharedFileURL($file_key, $path)
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
            if (isset($base_dir)) {
                $this->cleanupUserData($base_dir);
            }
        }

    }

    /**
     * Remove the temporary directory
     * @param string $base_dir the path to remove
     */
    private function cleanupUserData($base_dir) {
        if (@is_dir($base_dir)) {
            My_Utils::deleteDir($base_dir);
        }
    }

}
