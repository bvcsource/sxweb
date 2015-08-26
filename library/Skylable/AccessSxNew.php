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
 * Interacts with the Skylable services.
 *
 * Before using this class you should configure the services editing
 * the file: application/configs/skylable.ini
 * This file is then read at bootstrap and stored into the Zend_Registry under
 * the key 'skylable', as a Zend_Config instance.
 *
 * <code>
 * $skylable_config = Zend_Registry::get('skylable');
 * </code>
 *
 */
class Skylable_AccessSxNew {

    // List cache
    protected static
        // The cache is static because is shared, so if you
        // create, in a same script, more instances for the same user
        // there's no need to worry
        // BUT more users: nearly no cache.
        $volume_list_cache = array(
            'user' => NULL,
            'data' => NULL,
            'timestamp' => NULL
        );
    const
        // Number of seconds after which expire the cache
        EXPIRE_CACHE = 5;

    const
        // Sort order constants
        SORT_NONE = 0,
        SORT_BY_NAME_ASC = 1,
        SORT_BY_NAME_DESC = 2,
        SORT_BY_SIZE_ASC = 3,
        SORT_BY_SIZE_DESC = 4,
        SORT_BY_DATE_ASC = 5,
        SORT_BY_DATE_DESC= 6,

        // Last valid sort constants value
        SORT_LAST_VALID_VALUE = 6;

    const
        // File type list filters
        LIST_FILES = 1,
        LIST_VOLUMES = 2,
        LIST_DIRECTORIES = 4,
        LIST_ALL = 7;

    const
        // All errors in the 100 range belongs to executeShellCommand
        ERROR_CANT_INITIALIZE_PROCESS = 100,
        ERROR_CANT_CREATE_ERROR_LOG = 101,
        ERROR_UNEXPECTED_END_OF_FILE = 105,
        ERROR_STREAM_READING_ERROR = 108,
        ERROR_STDIN_INJECT_FAIL = 109,

        ERROR_INVALID_USER = 200,
        ERROR_INITIALIZATION_FAILURE = 201;

    const
        // Fake file created when creating dirs
        NEWDIR_FILENAME = '.sxnewdir';

    protected
        /**
         * String that contains the complete cluster credentials
         * Format: sx://LOGIN@CLUSTER
         * @var string
         */
        $_cluster_string = '',
        /**
         * @var string the last executed command by executeShellCommand
         */
        $_last_executed_command = '',

        /**
         * @var mixed hold the last error log
         */
        $_last_error_log,
        /**
         * @var My_User
         */
        $_user,

        /**
         * Directory where everything user related is stored
         * @var string user base dir
         */
        $_base_dir = '',

        /**
         * Extra configuration parameters
         * 
         * @var Zend_Config
         */
        $_params;

    /**
     * Initialize using a user: this will create all necessary dirs and configs.
     * 
     * Valid parameters:
     * 'password' - string - the plain user password
     * 'initialize' - boolean - FALSE don't initialize, TRUE (default) do initializations
     * 'user_auth_key' - string - the user secret key to use instead of password or identity
     *
     * @param My_User $user
     * @param string $base_dir directory of operations, if NULL generate one using the user
     * @param array $params additional parameters 
     * @throws Skylable_AccessSxException
     * @throws Exception
     * @see initialize
     */
    public function  __construct(My_User $user, $base_dir = NULL, $params = array()) {
        $this->_user = $user;
        $this->_params = new Zend_Config($params);
        
        // Check user validity
        $user_is_valid = FALSE;
        if ( isset($this->_params->user_auth_key) ) {
            $user_is_valid = TRUE;
        } else {
            if ($this->_user->isNew()) {
                if (isset($this->_params->password) ) {
                    $user_is_valid = (strlen($user->getLogin()) > 0);
                }
            } else {
                // We are reusing user data
                $user_is_valid = TRUE;
            }
        }
            
        
        if (!$user_is_valid) {
            throw new Skylable_AccessSxException('Invalid user', self::ERROR_INVALID_USER);
        }
        
        if (empty($base_dir)) {

            if (strlen(trim($this->_user->getLogin())) == 0) {
                throw new Skylable_AccessSxException('Invalid user: empty login', self::ERROR_INVALID_USER);
            }
            
            $this->_base_dir = My_Utils::slashPath(Zend_Registry::get('skylable')->get('sx_local')).sha1( $this->_user->getLogin() );
        } else {
            $this->_base_dir = strval($base_dir);
        }
        $this->getLogger()->debug(__METHOD__.': base dir: ' . $this->_base_dir );
        $this->updateClusterString( $user->getLogin() );
        
        if ($this->_params->get('initialize', TRUE) === TRUE) {
            if (!$this->initialize()) {
                throw new Skylable_AccessSxException('Failed to initialize user', self::ERROR_INITIALIZATION_FAILURE);
            }    
        }
    }

    /**
     * Update the base dir using the provided user or an absolute path.
     * 
     * If the provided base dir is empty, try to use the current user to update
     * the base directory. 
     * 
     * @param null|string $base_dir
     * @throws Skylable_AccessSxException
     * @throws Zend_Exception
     */
    public function updateBaseDir($base_dir = NULL) {
        if (empty($base_dir)) {
            
            if (!is_object($this->_user)) {
                throw new Skylable_AccessSxException('Invalid user: not set', self::ERROR_INVALID_USER);
            }

            if (strlen(trim($this->_user->getLogin())) == 0) {
                throw new Skylable_AccessSxException('Invalid user: empty login', self::ERROR_INVALID_USER);
            }

            $this->_base_dir = My_Utils::slashPath(Zend_Registry::get('skylable')->get('sx_local')).sha1( $this->_user->getLogin() );
        } else {
            $this->_base_dir = strval($base_dir);
        }
    }

    /**
     * Update the internal cluster string using the given login.
     * 
     * @param string $login an user login
     * @return bool|string
     * @throws Zend_Exception
     */
    protected function updateClusterString($login) {
        $cluster = Zend_Registry::get('skylable')->get('cluster', FALSE);
        if (empty($cluster)) {
            $this->getLogger()->err(__METHOD__.': Invalid cluster: '.print_r($cluster, TRUE));
            return FALSE;
        }
        $this->_cluster_string = 'sx://' . (empty($login) ? '' : strval($login) . '@') . parse_url($cluster, PHP_URL_HOST);
        return $this->_cluster_string;
    }

    /**
     * Deletes the user directory.
     */
    public function purgeProfile() {
        if (is_object($this->_user)) {
            if (@file_exists($this->_base_dir)) {
                if (@is_dir($this->_base_dir)) {
                    return My_Utils::deleteDir(realpath( $this->_base_dir ));        
                }
            }
        }
        return FALSE;
    }

    /**
     * Returns the last error log.
     *
     * @return mixed
     */
    public function getLastErrorLog() {
        return $this->_last_error_log;
    }

    /**
     * Returns the last executed command by {@link executeShellCommand}.
     *
     * @return string
     */
    public function getLastExecutedCommand() {
        return $this->_last_executed_command;
    }

    /**
     * Creates all the local paths and initialize the user directory structure.
     * 
     * @param $force boolean TRUE force initialization, FALSE otherwise
     * @throws Skylable_AccessSxException
     * @return boolean
     */
    public function initialize($force = FALSE) {
        if (!$force) {
            if ($this->isInitialized()) {
                return TRUE;
            }    
        }
        
        $sxinit_params = array();
        $sxinit_params['login'] = $this->_user->getLogin();
        if (isset($this->_params->password)) {
            $sxinit_params['password'] = $this->_params->password;
        }
        $sc = $this->_user->getSecretKey();
        if (empty($sc)) {
            if (isset($this->_params->user_auth_key)) {
                $sc = $this->_params->user_auth_key;
            }
        }
        if (!empty($sc)) {
            $sxinit_params['user_auth_key'] = $sc;
        }
        
        $this->updateClusterString( $sxinit_params['login'] );
        $path = $this->getBaseDir();
        $this->getLogger()->debug(__METHOD__.': using path: '.$path);
        // Directory already exists, force
        if (@is_dir($path)) {
            $this->getLogger()->debug(__METHOD__.': sxinit into already existing path: '.$path);
            return $this->sxinit($path, $sxinit_params, TRUE);
        }

        if (@mkdir($path, 0775) === TRUE) {
            $this->getLogger()->debug(__METHOD__.': sxinit into: '.$path);
            return $this->sxinit($path, $sxinit_params );
        }
        return FALSE;
    }

    /**
     * Tells if the local cluster services are initialized.
     *
     * @return bool
     * @throws Zend_Exception
     */
    public function isInitialized() {
        $path = $this->getBaseDir();
        if (!@is_dir($path)) {
            $this->getLogger()->err(__METHOD__.': path is not a directory: '.$path);
            return FALSE;
        }
        $path .= '/'.substr(Zend_Registry::get('skylable')->get('cluster'), 5);
        if (!@is_dir($path)) {
            $this->getLogger()->err(__METHOD__.': path is not a directory: '.$path);
            return FALSE;
        }

        // Check for the key
        $path .= '/auth/' . (strlen($this->_user->getLogin()) > 0 ? $this->_user->getLogin() : 'default');
        if (@file_exists($path)) {
            $authkey = file_get_contents($path);
            if ($authkey !== FALSE) {
                return (strcmp($authkey, $this->_user->getSecretKey()) == 0);
            }
        }
        return FALSE;
    }

    /**
     * Returns the current user secret key stored locally.
     * 
     * Don't get the key from the SX server
     * 
     * @return bool|string
     * @throws Zend_Exception
     */
    public function getLocalUserSecretKey() {
        $path = $this->getBaseDir() .
            '/'.substr(Zend_Registry::get('skylable')->get('cluster'), 5) .
            '/auth/'. (strlen($this->_user->getLogin()) > 0 ? $this->_user->getLogin() : 'default');
        if (@file_exists($path)) {
            $authkey = @file_get_contents($path);
            if ($authkey !== FALSE) {
                return trim($authkey);
            } else {
                $this->getLogger()->err(__METHOD__ . ': Failed to fetch user key from: ' . $path);
            }
        } else {
            $this->getLogger()->debug(__METHOD__ . ': User is not initialized!');
        }

        return FALSE;
    }

    /**
     * Returns the path were all user data are stored.
     *
     * @return string the path
     */
    public function getBaseDir() {
        return $this->_base_dir;
    }

    /**
     * Initializes the user into the specified directory.
     * 
     * Launch sxinit using the specified directory as target for configuration creation.
     * 
     * The base parameters are taken from the skylable.ini file, that is 
     * saved into the Zend_Registry 'skylable' key at bootstrap.
     * 
     * You must supply some user parameters:
     * login - the user login
     * password - the user password
     * user_auth_key - the user secret auth key
     *
     * @param string $destination_path
     * @param string $params
     * @param bool $force_reinit
     * @return bool
     * @throws Exception
     * @throws Zend_Exception
     */
    public function sxinit($destination_path, $params, $force_reinit = FALSE) {
        $this->_last_error_log = '';
        if (empty($destination_path) || !is_string($destination_path)) {
            $this->getLogger()->err(__METHOD__.': Invalid destination path: '.print_r($destination_path, TRUE));
            return FALSE;
        }
        
        // Compatibility mode, use the user auth key
        $has_auth_key = FALSE;
        if (isset($params['user_auth_key'])) {
            if (is_string($params['user_auth_key']) && !empty($params['user_auth_key'])) {
                $has_auth_key = TRUE;
            } else {
                $this->getLogger()->err(__METHOD__.': Invalid user key (not a string or empty): '.print_r($params['user_auth_key'], TRUE));
                return FALSE;
            }
        }
        
        // New mode: login+password
        $has_password = FALSE;
        if (isset($params['password'])) {
            if (is_string($params['password']) && (strlen($params['password']) > 0) ) {
                $has_password = TRUE;
            } else {
                $this->getLogger()->err(__METHOD__.': Invalid password (not a string or empty)');
                return FALSE;
            }
        }
        
        if (!$has_auth_key) {
            // We need a login
            if (!isset($params['login'])) {
                $this->getLogger()->err(__METHOD__.': You must supply the user login.');
                return FALSE;
            }
        }
        

        $cluster = Zend_Registry::get('skylable')->get('cluster', FALSE);
        if (empty($cluster)) {
            $this->getLogger()->err(__METHOD__.': Invalid cluster: '.print_r($cluster, TRUE));
            return FALSE;
        }
        
        $cluster_ssl = Zend_Registry::get('skylable')->get('cluster_ssl', TRUE);
        $this->getLogger()->notice(__METHOD__.': Cluster SSL: '.var_export($cluster_ssl, TRUE));
        if (empty($cluster_ssl)) {
            $cluster_ssl = FALSE; 
        } else {
            $cluster_ssl = (bool)$cluster_ssl;
        }
        
        $cluster_port = Zend_Registry::get('skylable')->get('cluster_port', FALSE);
        if (empty($cluster_port)) {
            $cluster_port = FALSE;
        }
        if ($cluster_port !== FALSE) {
            if (!is_numeric($cluster_port)) {
                $this->getLogger()->err(__METHOD__.': Invalid cluster port: '.print_r($cluster_port, TRUE));
                return FALSE;
            }

            if ($cluster_port < 1 || $cluster_port > 65535) {
                $this->getLogger()->err(__METHOD__.': Invalid cluster port (out of range): '.print_r($cluster_port, TRUE));
                return FALSE;
            }
        }
        
        $cluster_ip = Zend_Registry::get('skylable')->get('cluster_ip', FALSE);
        if ($cluster_ip !== FALSE) {
            if (is_string($cluster_ip)) {
                if (strlen($cluster_ip) > 0) {
                    $cluster_ip = '-l '.My_utils::escapeshellarg($cluster_ip);
                } else {
                    $this->getLogger()->notice(__METHOD__.': Cluster IP is empty.');
                }
            } else {
                $this->getLogger()->notice(__METHOD__.': Cluster IP is not a string.');
            }
        }
        
        $sxinit_cmd = 'sxinit -b '.
            ($force_reinit ? '--force-reinit ' : '').
            ($cluster_ssl ? '' : '--no-ssl ').
            ($cluster_port !== FALSE ? '--port='.strval($cluster_port).' ' : '').
            ($cluster_ip !== FALSE ? $cluster_ip.' ' : '').
            '-c '.My_utils::escapeshellarg($destination_path);
        
        $tmp_file = @tempnam( $this->getBaseDir(), 'sxinit_' );
        if ($tmp_file === FALSE) {
            $this->getLogger()->err(__METHOD__.': Failed to create temporary files into: ' . $this->getBaseDir());
            return FALSE;
        }

        $auth_data_ok = @file_put_contents($tmp_file, ($has_auth_key ? $params['user_auth_key'].PHP_EOL : $params['password'].PHP_EOL) );
        
        if ($auth_data_ok === FALSE) {
            $this->getLogger()->err(__METHOD__.': Failed to write auth data to file: ' . $tmp_file);
            @unlink($tmp_file);
            return FALSE;
        }
        
        if ($has_auth_key) {
            $sxinit_cmd .= ' --key -a '.My_utils::escapeshellarg($tmp_file);
        } else {
            $sxinit_cmd .= ' -p '.My_utils::escapeshellarg($tmp_file);
        }
        if (isset($params['login'])) {
            $cluster = 'sx://'.$params['login'] . '@' . parse_url($cluster, PHP_URL_HOST);
        }
        /*
        if (!$has_auth_key) {
            $cluster = 'sx://'.$params['login'] . '@' . parse_url($cluster, PHP_URL_HOST);
        }
        */
        $sxinit_cmd .= ' '.My_utils::escapeshellarg($cluster);

        try {
            $ret = $this->executeShellCommand(
                $sxinit_cmd,
                '',
                $output,
                $exitcode,
                $this->_last_error_log);
            @unlink($tmp_file);
            if ($exitcode == 0) {
                return TRUE;
            } else {
                $this->getLogger()->err(__METHOD__.': sxinit failed: '.$this->_last_error_log);
                return FALSE;
            }    
        }
        catch (Exception $e) {
            @unlink($tmp_file);
            throw $e;
        }
        
    }

    /**
     * Remove files or directories
     *
     * @param string|array $path string or array of string with paths to remove
     * @param bool $delete_recursive delete dirs recursively
     * @return bool
     */
    public function remove($path, $delete_recursive = TRUE) {
        $this->_last_error_log = '';
        if (!$this->isInitialized()) {
            return FALSE;
        }

        if (is_array($path)) {
            foreach($path as $k => $v) {
                if (!is_string($v)) {
                    unset($path[$k]);
                    continue;
                } elseif(strlen($v) == 0) {
                    unset($path[$k]);
                    continue;
                }
                $path[$k] = My_utils::escapeshellarg( $this->_cluster_string.'/'.My_Utils::removeSlashes($v, TRUE) );
            }
            if (count($path) == 0) {
                return FALSE;
            }
        } elseif (strlen($path) == 0) {
            return FALSE;
        } else {
            $path = My_utils::escapeshellarg( $this->_cluster_string.'/'.My_Utils::removeSlashes($path, TRUE) );
        }

        $ret = $this->executeShellCommand(
            'sxrm '.
            ($delete_recursive ? '-r ' : '').
            '-c '.My_utils::escapeshellarg($this->_base_dir).' '.
            (is_array($path) ? implode(' ', $path) : $path),
            '', $output, $exitcode, $this->_last_error_log);
        if ($exitcode == 0) {
            return TRUE;
        }

        return FALSE;
    }



    /**
     * List the user volumes.
     *
     * Returned value is an array of associative arrays:
     * array(
     *  array(
     *   'type' => 'VOL',
     *   'replica' => 'rep:[0-9]' - replica count
     *   'revs' => 'rev:[0-9]' - revision
     *   'access' => 'r','w','rw' - access privileges
     *   'filter' => string, the filter for data, for encrypted volumes is 'aes256'
     *   'used_space' => long integer with used space in bytes
     *   'volume_size' => long integer with volume size in bytes
     *   'usage' => 'nn%' string with the usage percentage
     *   'server' => string with the server URI
     *   'path' => string with the volume name (starts with a /)
     *   'url' => string with the complete volume URL
     *  ),
     *  ...
     *
     * )
     *
     * Returns FALSE on errors.
     *
     * For sorting use one of the SORT_BY_* constants.
     *
     * Every operation is cached.
     *
     * @param int $sort_order
     * @param bool $ignore_cache TRUE ignore caching, FALSE use caching
     * @return bool|array
     * @throws Exception
     * @throws Zend_Exception
     */
    public function listVolumes($sort_order = self::SORT_NONE, $ignore_cache = FALSE) {
        if ($ignore_cache) {
            return $this->sxls('', $sort_order, FALSE, self::LIST_VOLUMES);
        } else {
            $user_id = $this->_user->getId();
            if (is_null($user_id)) {
                $user_id = 'null';
            }
            if (self::$volume_list_cache['user'] === $user_id &&
                intval(self::$volume_list_cache['timestamp']) <= (time() + self::EXPIRE_CACHE) ) {
                $this->getLogger()->debug(__METHOD__.': using cached data.');
                return self::$volume_list_cache['data'];
            } else {
                $this->getLogger()->debug(__METHOD__.': updating cache.');
                $data = $this->sxls('', $sort_order, FALSE, self::LIST_VOLUMES);
                if ($data !== FALSE) {

                    self::$volume_list_cache['user'] = $user_id;
                    self::$volume_list_cache['timestamp'] = time();
                    self::$volume_list_cache['data'] = $data;
                } else {
                    self::$volume_list_cache['user'] = self::$volume_list_cache['timestamp'] = self::$volume_list_cache['data'] = NULL;
                }
                return $data;
            }
        }
    }

    /**
     * Gets informations about a file path.
     *
     * On error returns FALSE, on success returns an associative array which the same
     * data found into a row of the {@link sxls} method.
     *
     * @param string $filepath
     * @return bool|array
     * @throws Exception
     * @throws Zend_Exception
     */
    public function getFileInfo($filepath) {
        $this->_last_error_log = '';

        if (strlen($filepath) == 0 || !is_string($filepath)) {
            $this->getLogger()->err(__METHOD__.': Invalid file path'. var_export($filepath, TRUE) );
            return FALSE;
        }

        if (!$this->isInitialized()) {
            return FALSE;
        }

        $ret = $this->executeShellCommand(
            'sxls -l '.
            '-c '.My_utils::escapeshellarg($this->_base_dir).' '.
            My_utils::escapeshellarg( $this->_cluster_string.'/'.My_Utils::removeSlashes($filepath, TRUE)),
            '', $output, $exitcode, $this->_last_error_log, array($this, 'processSxLsOutput'), array($this, 'parseErrors'), array(self::LIST_ALL) );
        if ($exitcode == 0) {
            if (count($output) == 0) {
                // File not found!
                $this->getLogger()->notice(__METHOD__.': File '. $filepath .' not found.');
                $this->_last_error_log = 'File '. $filepath .' not found.';
                return FALSE;
            } else {
                return reset($output);
            }
        }
        return FALSE;
    }

    /**
     * List files on a cluster.
     *
     * Returned value is an array of associative arrays:
     * array(
     *  array(
     *   'type' => 'DIR' or 'FILE' indicating respectively a directory or a file,
     *   'server' => string with the server URI
     *   'path' => string with the volume name (starts with a /)
     *   'url' => string with the complete volume URL
     *
     * If the type is FILE:
     *   'date' => string with the date in this format YYYY-MM-DD HH:MM
     *   'size' => long integer with the file size in bytes
     *  ),
     *  ...
     *
     * )
     *
     * Returns FALSE on errors.
     *
     * For sorting use one of the SORT_BY_* constants.
     *
     * For selecting the file type use the LIST_* constants: remember that are bit a
     * bit selectors (so you must do a bitwise OR on the constants).
     *
     * @param string $path path to list
     * @param int $sort_order sort order
     * @param bool $recursive flag: recursively list directories
     * @param int $file_types include only these file types
     * @return bool|array FALSE on error, the file list otherwise
     * @throws Exception
     * @throws Zend_Exception
     */
    public function sxls($path, $sort_order = self::SORT_NONE, $recursive = FALSE, $file_types = self::LIST_ALL) {
        $this->_last_error_log = '';
        if (!$this->isInitialized() || !is_string($path)) {
            $this->getLogger()->err(__METHOD__.': not initialized or path is not a string.');
            return FALSE;
        }

        $ret = $this->executeShellCommand(
            'sxls -l '.
            ($recursive ? '-r ' : '').
            '-c '.My_utils::escapeshellarg($this->_base_dir).' '.
            My_utils::escapeshellarg( $this->_cluster_string.'/'.My_Utils::removeSlashes($path, TRUE)),
            '', $output, $exitcode, $this->_last_error_log, array($this, 'processSxLsOutput'), array($this, 'parseErrors'), array($file_types) );
        if ($exitcode == 0) {
            switch($sort_order) {
                case self::SORT_BY_NAME_DESC:
                    usort($output, function($a, $b) { return (-1 * strnatcasecmp($a['path'], $b['path'])); });
                    break;
                case self::SORT_BY_NAME_ASC:
                    usort($output, function($a, $b) { return strnatcasecmp($a['path'], $b['path']); });
                    break;
                case self::SORT_BY_SIZE_DESC:
                    usort($output,
                        function($a, $b) {
                            if ($a['size'] > $b['size']) {
                                return -1;
                            } elseif($a['size'] == $b['size']) {
                                return 0;
                            }
                        return 1;
                    });
                    break;
                case self::SORT_BY_SIZE_ASC:
                    usort($output,
                        function($a, $b) {
                            if ($a['size'] > $b['size']) {
                                return 1;
                            } elseif($a['size'] == $b['size']) {
                                return 0;
                            }
                            return -1;
                        });
                    break;
                case self::SORT_BY_DATE_DESC:
                    usort($output,
                        function($a, $b) {
                            $d1 = strtotime($a['date']);
                            $d2 = strtotime($b['date']);
                            if ($d1 > $d2) {
                                return -1;
                            } elseif($d1 == $d2) {
                                return 0;
                            }
                            return 1;
                        });
                    break;
                case self::SORT_BY_DATE_ASC:
                    usort($output,
                        function($a, $b) {
                            $d1 = strtotime($a['date']);
                            $d2 = strtotime($b['date']);
                            if ($d1 > $d2) {
                                return 1;
                            } elseif($d1 == $d2) {
                                return 0;
                            }
                            return -1;
                        });
                    break;
            }
            return $output;
        } else {
            $this->checkForErrors($this->_last_error_log, TRUE);
        }
        return FALSE;
    }

    /**
     * Process the output of the sxls command into an array
     *
     * Returns an array with operation status:
     * array(
     *  'status' - boolean operation status
     *  'error' - string with error message(s)
     * )
     *
     * @param resource $fd file descriptor with input data
     * @param array $output
     * @param integer $file_types file types to include in listing
     * @return array
     */
    protected function processSxLsOutput($fd, &$output, $file_types) {
        $retval = array(
            'status' => TRUE,
            'error' => ''
        );
        $output = array();

        while( ($data_line = fgets($fd)) !== FALSE ) {
            if (preg_match('/^\s*(?<type>VOL)\s+(?<replica>rep:[0-9]+)\s+(?<revs>rev:[0-9]+)\s+(?<access>[r-][w-])\s+'.
                    '(?<filter>[a-z0-9\-]+)\s+(?<used_space>[0-9]+)\s+(?<volume_size>[0-9]+)\s+'.
                    '(?<usage>[0-9]+%)\s+(?<owner>.+(?!sx:\/\/))?\s*((?<server>sx:\/\/[^\/]*)(?<path>.*))/', $data_line, $matches) == 1 && ($file_types & self::LIST_VOLUMES) ) {
            
            

                // Skips some entries
                if (preg_match('/^\/libres3-[A-Fa-f0-9]{40}/', $matches['path']) == 1) {
                    continue;
                }

                $matches['url'] = $matches[10];
                for($i = 0; $i < 13; $i++) {
                    unset($matches[$i]);
                }

                $output[] = $matches;

            } elseif (preg_match('/^\s*(?<type>DIR|([0-9]{4}\-[0-9]{2}\-[0-9]{2}\s[0-9]{2}:[0-9]{2}))\s+(?<size>[0-9]*)\s+'.
                    '((?<server>sx:\/\/[^\/]*)(?<path>.*))/', $data_line, $matches) == 1 && ($file_types & (self::LIST_FILES | self::LIST_DIRECTORIES )) ) {

                // Skips the fake directory entries
                if (strrpos($matches['path'], '/'.self::NEWDIR_FILENAME, -1) !== FALSE) {
                    continue;
                }

                if ($matches['type'] != 'DIR') {
                    $matches['date'] = $matches['type'];
                    $matches['type'] = 'FILE';
                } else {
                    $matches['date'] = '';
                }

                // Skip if needed
                if ( ($matches['type'] == 'DIR' && (($file_types & self::LIST_DIRECTORIES) == 0 ) ) ||
                    ($matches['type'] == 'FILE' && (($file_types & self::LIST_FILES) == 0 ) ) ) {
                    continue;
                }

                if (empty($matches['size'])) {
                    $matches['size'] = 0;
                } else {
                    $matches['size'] = intval($matches['size']);
                }

                $matches['url'] = $matches[4];
                for($i = 0; $i < 7; $i++) {
                    unset($matches[$i]);
                }

                $output[] = $matches;

            } else {
                $this->getLogger()->debug('Matching FAILED: '. $data_line);
            }
        }
        if (!feof($fd)) { // fgets exited
            $retval['status'] = FALSE;
            $retval['error'] = new Exception('Unexpected end of the file', ERROR_UNEXPECTED_END_OF_FILE);
            $this->getLogger()->debug(__METHOD__.' - Unexpected end of the file');
        }
        return $retval;
    }

    /**
     * Returns the ACL for the volume.
     *
     * Returns an array of associative array with user and permissions, ie:
     *
     * Array (
     *  [user] => 'user name'
     *  [perms] => Array - permissions
     *   (
     *     [0] => read
     *     [1] => write
     *     [2] => owner
     *   )
     * )
     *
     *
     * @param string $volume
     * @return bool|array FALSE on failure, or an array
     * @throws Exception
     * @throws Zend_Exception
     */
    public function getVolumeACL($volume) {
        $this->_last_error_log = '';
        if (!is_string($volume) || empty($volume)) {
            return FALSE;
        }

        $vol = My_Utils::getRootFromPath($volume);
        if (strlen($vol) == 0) {
            return FALSE;
        }

        $ret = $this->executeShellCommand(
            'sxacl volshow '.
            '-c '.My_utils::escapeshellarg($this->_base_dir).' '.
            My_utils::escapeshellarg( $this->_cluster_string.'/'.$vol )
            , '', $output, $exit_code, $this->_last_error_log, array($this, 'processVolumeACL'), array($this, 'parseErrors') );
        if ($exit_code == 0) {
            return $output;
        } else {
            $this->checkForErrors($this->_last_error_log, TRUE);
            return FALSE;
        }
    }

    /**
     * Process the ACL for a volume
     *
     * @param resource $fd input file descriptor
     * @param array $data output data
     * @return array
     */
    private function processVolumeACL($fd, &$data) {
        $ret = array(
            'status' => TRUE,
            'error' => ''
        );
        $data = array();

        while( ($data_line = fgets($fd)) !== FALSE ) {
            if (($p = strpos($data_line, ':')) !== FALSE) {
                $e = array('user' => substr($data_line, 0, $p), 'perms' => array());
                if (preg_match_all('/(\s*(\w+))/', substr($data_line, $p), $matches) > 0) {
                    $e['perms'] = $matches[2];
                }
                $data[] = $e;
            }

        }
        if (!feof($fd)) { // fgets exited
            $retval['status'] = FALSE;
            $retval['error'] = new Exception('Unexpected end of the file', ERROR_UNEXPECTED_END_OF_FILE);
            $this->getLogger()->debug(__METHOD__.' - Unexpected end of the file');
        }

        return $ret;
    }


    /**
     * Executes a shell command and processes output and errors.
     *
     * Returned value is an array:
     * array(
     *  'status' => boolean - TRUE on success, FALSE on failure
     *  'stdin_process' => array('status' => TRUE, 'error' => NULL),
     *  'stdout_process' => array('status' => TRUE, 'error' => NULL),
     *  'stderr_process' => array('status' => TRUE, 'error' => NULL)
     *  );
     *
     * 'stdin_process','stdout_process','stderr_process' are array that contains the
     * result of the processing of STDIN, STDOUT and STDERR of the called command.
     * The format is:
     * array(
     *  'status' => boolean, TRUE on success, FALSE on failure
     *  'error' => integer|Exception, reason of the failure
     * )
     *
     * The 'error' key can contain an integer value which equals to one of the
     * ERROR_* constants or can be an Exception object.
     * You should set the appropriate error code into the exception.
     *
     * Callback format:
     * callback($file_descriptor, &$data)
     * The output callback will receive the STDOUT of the command pipe,
     * the error callback will receive the STDERR of the command (a regular file, not a pipe).
     * The $data parameter should be filled with the appropriate content read from the files
     * passed as parameters.
     * The callback's return value is used as value for the 'stdout_process' or
     * 'stderr_process' return value's associative array.
     *
     * This method throws exceptions only if it can't initialize or run the command,
     * the user is responsible to manager error statuses.
     *
     * IMPORTANT:
     * don't forget to add a PHP_EOL at the end of $input parameter if you want to simulate
     * user input.
     *
     * @param string $command the command string to execute
     * @param string $input string to use as command input (when needed)
     * @param mixed $output the command output
     * @param integer $exit_code the command exit code
     * @param mixed $error_log the error messages
     * @param callback $output_callback callback to process the output
     * @param callback $error_callback callback to process the errors
     * @param array $output_callback_params additional parameters to pass to the output callback
     * @param array $error_callback_params additional parameters to pass to the error callback
     * @return array
     * @throws Exception
     */
    protected function executeShellCommand($command, $input, &$output, &$exit_code, &$error_log, $output_callback = NULL, $error_callback = NULL, $output_callback_params = array(), $error_callback_params = array() ) {
        $this->_last_executed_command = $command;

        $pipes = array();

        $process_log_fd = @fopen('php://temp','r+');
        if ($process_log_fd === FALSE) {
            throw new Exception("Failed to create error log", self::ERROR_CANT_CREATE_ERROR_LOG);
        }

        $this->getLogger()->debug(__METHOD__.': executing: '.strval($command));

        $output = '';
        $error_log = '';

        $process = proc_open($command,
            array(
                0 => array('pipe', 'r'),
                1 => array('pipe', 'w'),
                2 => $process_log_fd
            ), $pipes);

        if (is_resource($process)) {

            // This will be returned
            $ret_val = array(
                'status' => TRUE,
                'stdin_process' => array('status' => TRUE, 'error' => NULL),
                'stdout_process' => array('status' => TRUE, 'error' => NULL),
                'stderr_process' => array('status' => TRUE, 'error' => NULL)
            );

            if (strlen($input) > 0) {
                $status = @fwrite($pipes[0], $input);
                $this->getLogger()->debug(__METHOD__.': injecting input: '.($status === FALSE ? 'failed' : 'successful, wrote '.strval($status).' bytes' ));
                if ($status === FALSE) {
                    $ret_val['stdin_process']['status'] = FALSE;
                    $ret_val['stdin_process']['error'] = self::ERROR_STDIN_INJECT_FAIL;
                }
            }

            fclose($pipes[0]);

            // Get the process output
            if (is_callable($output_callback)) {
                $ret_val['stdout_process'] = call_user_func_array($output_callback, (empty($output_callback_params) ? array($pipes[1], &$output) : array_merge( array($pipes[1], &$output), $output_callback_params ) ) );
            } else {
                if (($output = stream_get_contents($pipes[1])) === FALSE) {
                    $ret_val['stdout_process']['status'] = FALSE;
                    $ret_val['stdout_process']['error'] = self::ERROR_STREAM_READING_ERROR;
                }
            }

            @fclose($pipes[1]);
            $exit_code = proc_close($process);
            if ($exit_code != 0) {
                $this->getLogger()->err(__METHOD__.': non zero return value for command: '.strval($command));
                $this->getLogger()->err(__METHOD__.': command return value is:' . print_r($exit_code, TRUE));
            } else {
                $this->getLogger()->debug(__METHOD__.': command return value is:' . print_r($exit_code, TRUE));    
            }
            

            // Analyses the process error log
            if (is_callable($error_callback)) {
                $ret_val['stderr_process'] = call_user_func_array($error_callback, (empty($error_callback_params) ? array($process_log_fd, &$error_log) : array_merge( array($process_log_fd, &$error_log), $error_callback_params ) ) );
            } else {
                @fseek($process_log_fd, 0, SEEK_SET);
                if (($error_log = stream_get_contents($process_log_fd)) === FALSE) {
                    $ret_val['stderr_process']['status'] = FALSE;
                    $ret_val['stderr_process']['error'] = self::ERROR_STREAM_READING_ERROR;
                }
            }

            @fclose($process_log_fd);

            if ($exit_code != 0) {
                $this->getLogger()->err(__METHOD__.': command STDOUT:' . print_r($output, TRUE));
                $this->getLogger()->err(__METHOD__.': command STDERR:' . print_r($error_log, TRUE));
                $this->getLogger()->err(__METHOD__.': command execution log:' . print_r($ret_val, TRUE));
            }
            
            return $ret_val;

        } else {
            throw new Exception('Failed to initialize the process.', self::ERROR_CANT_INITIALIZE_PROCESS);
        }
    }

    const
        DOWNLOAD_DISPOSITION_INLINE = 'inline',
        DOWNLOAD_DISPOSITION_ATTACHMENT = 'attachment';

    /**
     * Send a file to the browser.
     *
     * @param array $file_data
     * @param string $password
     * @param string $disposition
     * @return array|bool
     * @throws Exception
     * @throws Zend_Exception
     */
    public function download($file_data, $password = '', $disposition = self::DOWNLOAD_DISPOSITION_ATTACHMENT) {

        /*
        $file_data = $this->getFileInfo($filename);
        if ($file_data === FALSE) {
            $this->getLogger()->debug(__METHOD__ . ': Failed to get file data for file: ' . $filename);
            return FALSE;
        } else {
            if ($file_data['type'] !== 'FILE') {
                $this->getLogger()->debug(__METHOD__ . ': File: ' . $filename . ' isn\'t a regular file.');
                return FALSE;
            }
        }
        */

        if ($file_data['type'] !== 'FILE') {
            $this->getLogger()->err(__METHOD__ . ': File: ' . $file_data['path'] . ' isn\'t a regular file.');
            return FALSE;
        }

        // If the file is empty take the easy way
        if ($file_data['size'] == 0) {
            $this->getLogger()->debug(__METHOD__ . ': File has zero size, skipping shell command use.');
            header("Cache-Control: no-cache, must-revalidate");
            header("Pragma: no-cache");
            header("Content-Disposition: ".$disposition."; filename=\"".rawurlencode(basename($file_data['path'])).'"');
            header("Content-Type: ".My_Utils::getFileMIME($file_data['path']));
            header("Content-Length: ".strval( $file_data['size'] ));
            header('Content-Transfer-Encoding: binary');
            return TRUE;
        }

        $pipes = array();

        $process_log_fd = fopen('php://temp','r+');
        if ($process_log_fd === FALSE) {
            throw new Exception("Failed to create error log", self::ERROR_CANT_CREATE_ERROR_LOG);
        }

        $this->_last_error_log = '';

        $cmd = 'sxcat '.
            '-c '.My_utils::escapeshellarg($this->_base_dir).' '.
            My_utils::escapeshellarg($this->_cluster_string.'/'.My_Utils::removeSlashes($file_data['path'], TRUE) );

        $this->getLogger()->debug(__METHOD__ . ': executing: '.$cmd);
        $process = proc_open($cmd,
            array(
                0 => array('pipe', 'r'),
                1 => array('pipe', 'w'),
                2 => $process_log_fd
            ), $pipes);

        if (is_resource($process)) {
            // This will be returned
            $ret_val = array(
                'status' => TRUE,
                'stdin_process' => array('status' => TRUE, 'error' => NULL),
                'stdout_process' => array('status' => TRUE, 'error' => NULL),
                'stderr_process' => array('status' => TRUE, 'error' => NULL)
            );

            if (strlen($password) > 0) {
                $status = fwrite($pipes[0], $password.PHP_EOL);
                $this->getLogger()->debug(__METHOD__.': injecting input: '.($status === FALSE ? 'failed' : 'successful, wrote '.strval($status).' bytes' ));
                if ($status === FALSE) {
                    $ret_val['stdin_process']['status'] = FALSE;
                    $ret_val['stdin_process']['error'] = ERROR_STDIN_INJECT_FAIL;
                }
            }

            fclose($pipes[0]);

            // Reads 1KB of data, if is ok, send the rest to the browser
            if (($first_chunk = fread($pipes[1], 1024)) !== FALSE) {
                if (strlen($first_chunk) > 0) {
                    $this->getLogger()->debug(__METHOD__.': Sending file...');
                    header("Cache-Control: no-cache, must-revalidate");
                    header("Pragma: no-cache");
                    header("Content-Disposition: ".$disposition."; filename=\"".rawurlencode(basename($file_data['path'])).'"');
                    header("Content-Type: ".My_Utils::getFileMIME($file_data['path']));
                    // This fixes problems with zcomp volumes
                    // header("Content-Length: ".strval( $file_data['size'] ));
                    header('Content-Transfer-Encoding: binary');
                    ob_end_clean();
                    Zend_Session::writeClose();
                    $this->getLogger()->debug(__METHOD__.': Sending first chunk...');
                    echo $first_chunk;
                    $this->getLogger()->debug(__METHOD__.': Passthru of the file...');
                    fpassthru($pipes[1]);
                } else {
                    $this->getLogger()->debug(__METHOD__.': First chunk is empty, this is bad.');
                }
            } else {
                $this->getLogger()->debug(__METHOD__.': Reading first chunk failed.');
            }

            @fclose($pipes[1]);

            $exit_code = proc_close($process);
            $this->getLogger()->debug(__METHOD__.': command return value is:' . print_r($exit_code, TRUE));

            // Analyses the process error log
            fseek($process_log_fd, 0, SEEK_SET);
            $ret_val['stderr_process'] = $this->parseErrors($process_log_fd, $this->_last_error_log);

            @fclose($process_log_fd);

            return $ret_val;
        } else {
            throw new Exception('Failed to initialize the process.', self::ERROR_CANT_INITIALIZE_PROCESS);
        }
    }


    /**
     * Returns the system logger.
     *
     * @return Zend_Log
     */
    protected function getLogger() {
        return Zend_Controller_Front::getInstance()->getParam('bootstrap')->getResource('log');
    }

    /**
     * Tells if an encrypted volume need a password.
     * 
     * This applies to all volumes created with aes256=nogenkey filter option.
     * 
     * NOTE: don't check if the volume is encrypted.
     * 
     * @param $volume the volume name
     * @return boolean
     * @throws Exception
     */
    public function volumeNeedsPassword($volume) {
        
        if (empty($volume) || !is_string($volume)) {
            $this->getLogger()->debug(__METHOD__.': invalid volume');
            throw new Skylable_AccessSxException(__METHOD__.': invalid volume string.');
        }
        $volume = My_Utils::getRootFromPath( trim($volume) );
        if (strlen($volume) == 0) {
            $this->getLogger()->debug(__METHOD__.': volume is empty');
            throw new Skylable_AccessSxException(__METHOD__.': volume is empty.');
        }

        if (!$this->isInitialized()) {
            $this->getLogger()->debug(__METHOD__.': not initialized');
            throw new Skylable_AccessSxException(__METHOD__.': not initialized.');
        }

        $tmp_file = @tempnam($this->_base_dir, 'sxtmp_');
        if ($tmp_file === FALSE) {
            throw new Exception(__METHOD__.': Failed to create temporary file.');
        }
        
        // Execute the sxcp command trying to put a file into the volume and check the results
        $this->_last_error_log = '';

        $this->_last_executed_command = 'sxcp -q '.
            '-c '.My_utils::escapeshellarg($this->_base_dir).' '.
            My_utils::escapeshellarg( $tmp_file ).' '.
            My_utils::escapeshellarg( $this->_cluster_string.'/'.My_Utils::removeSlashes( $volume.'/'.self::NEWDIR_FILENAME, TRUE ) );

        // stdin, stdout, stderr pipes
        $pipes = array();

        $process_log_fd = @fopen('php://temp','r+');
        if ($process_log_fd === FALSE) {
            throw new Exception("Failed to create error log", self::ERROR_CANT_CREATE_ERROR_LOG);
        }

        $this->getLogger()->debug(__METHOD__.': executing: '.strval($this->_last_executed_command));

        $process = proc_open($this->_last_executed_command,
            array(
                0 => array('pipe', 'r'),
                1 => array('pipe', 'w'),
                2 => $process_log_fd
            ), $pipes);

        if (is_resource($process)) {
            @fclose($pipes[0]);
            $output = @stream_get_contents($pipes[1]);
            @fclose($pipes[1]);

            $exit_code = proc_close($process);

            @fseek($process_log_fd, 0, SEEK_SET);
            $error_log = @stream_get_contents($process_log_fd);
            @fclose($process_log_fd);

            @unlink($tmp_file);

            $this->getLogger()->debug(__METHOD__.': output: '.print_r($output, TRUE));
            $this->getLogger()->debug(__METHOD__.': error log: '.print_r($error_log, TRUE));

            if (stripos($error_log, 'set the volume password now') !== FALSE) {
                return TRUE;
            }
        } else {
            @unlink($tmp_file);
            throw new Exception('Failed to initialize the process.', self::ERROR_CANT_INITIALIZE_PROCESS);
        }
        
        return FALSE;
    }

    /**
     * Sets the volume password of an encrypted volume created 
     * with aes256=nogenkey filter option.
     * 
     * @param $volume the volume name
     * @param $password the plain password
     * @return bool TRUE on success, FALSE on failure
     * @throws Exception
     * @throws Skylable_AccessSxException
     * @throws Skylable_InvalidPasswordException
     */
    public function volumeSetPassword($volume, $password) {
        if (empty($volume) || !is_string($password) || !is_string($volume)) {
            return FALSE;
        }
        $volume = My_Utils::getRootFromPath( trim($volume) );
        if (strlen($volume) == 0) {
            return FALSE;
        }

        if (!$this->isInitialized()) {
            return FALSE;
        }

        $tmp_file = @tempnam($this->_base_dir, 'sxtmp_');
        try {
            if ($tmp_file !== FALSE) {
                $ret = $this->put($tmp_file, $volume.'/'.self::NEWDIR_FILENAME, FALSE, $password.PHP_EOL.$password.PHP_EOL);
                @unlink($tmp_file);
                if ($ret === TRUE) {
                    return TRUE;
                }
            }
        }
        catch(Exception $e) {
            @unlink($tmp_file);

            /**
             * FIXME: test this corner case
             * 
             * Corner case: we are trying to write to a read only volume
             * the password can be ok, but we get a permission denied error.
             * Nonetheless the volume could be unlocked.
             * */
            if ($e instanceof Skylable_AccessSxException) {
                if (!($e instanceof Skylable_InvalidPasswordException)) {
                    $this->getLogger()->debug(__METHOD__ . ': read only volume corner case.');
                    foreach($this->_last_error_log['errors'] as $err) {
                        // Failed to upload file content hashes: Permission denied: not enough privileges
                        if (stripos($err, 'failed to upload') !== FALSE &&
                            stripos($err, 'not enough privileges') !== FALSE) {
                            if ($this->volumeIsUnlocked($volume)) {
                                $this->getLogger()->debug(__METHOD__ . ': read only volume corner case: volume unlocked.');
                                return TRUE;
                            }
                        }
                    }
                    $this->getLogger()->debug(__METHOD__ . ': read only volume corner case: ignored.');
                }
            }
            throw $e;
        }
        return FALSE;
    }

    /**
     * Tells if a volume is unlocked.
     * 
     * A locked volume is encrypted and the opening key is not given.
     * 
     * @param string $volume the volume string
     * @return boolean TRUE if the volume is unlocked, FALSE otherwise
     * @throws Exception
     */
    public function volumeIsUnlocked($volume) {
        if (empty($volume)) {
            self::getLogger()->debug(__METHOD__ . ': Volume: '.print_r($volume, TRUE) );
            throw new Exception(__METHOD__ .': invalid volume.');
        }

        if (!$this->isInitialized()) {
            self::getLogger()->err(__METHOD__ . ': user not initialized. ');
            return FALSE;
        }
        
        $path = @realpath($this->_base_dir) . DIRECTORY_SEPARATOR . substr(Zend_Registry::get('skylable')->get('cluster'), 5) .
                DIRECTORY_SEPARATOR . 'volumes' . DIRECTORY_SEPARATOR . My_Utils::getRootFromPath($volume) . DIRECTORY_SEPARATOR;
        
        // If the volume path doesn't exists, can't proceed
        if (!@is_dir($path)) {
            self::getLogger()->err(__METHOD__ . ': Path don\'t exists: '.$path);
            return FALSE;
        }
        
        // If the filter file doesn't exists, the volume isn't encrypted
        if (@file_exists($path . 'filter')) {
            // Open the filter to know if the key is there
            $filter = file($path . 'filter', FILE_IGNORE_NEW_LINES);
            if ( empty($filter) ) {
                return FALSE;
            }
            return @file_exists( $path . DIRECTORY_SEPARATOR . $filter[0] . DIRECTORY_SEPARATOR . 'key' ); 
        }
        return FALSE;
    }
    
    /**
     * Tells if a volume is encrypted.
     * 
     * All the slashes into the volume name will be removed.
     * 
     * @param string $volume a volume string
     * @return boolean TRUE if the volume is encrypted, FALSE otherwise
     * @throws Exception
     */
    public function volumeIsEncrypted($volume) {
        if (empty($volume) ) {
            self::getLogger()->debug(__METHOD__ . ': Volume: '.print_r($volume, TRUE) );
            throw new Exception(__METHOD__ .': invalid volume');
        }
        
        $volume = '/'.My_Utils::getRootFromPath($volume);
        $volumes = $this->listVolumes();
        if ($volumes !== FALSE) {
            foreach($volumes as $vol) {
                if (strncasecmp($vol['path'], $volume, strlen($volume)) == 0) {
                    return ($vol['filter'] == 'aes256');
                }
            }
        }
        
        return FALSE;
    }

    /**
     * Tells if a volume supports encryption.
     * 
     * The $volume_info parameter is one of the associative arrays returned 
     * by {@link Skylable_AccessSxNew::listVolumes}.
     * 
     * @param array $volume_info
     * @return bool
     */
    public function volumeIsEncrypted2($volume_info) {
        if (is_array($volume_info)) {
            if (array_key_exists('filter', $volume_info) ) {
                return (strcmp($volume_info['filter'], 'aes256') == 0);
            }
        }
        return FALSE;
    }

    /**
     * Unlock an encrypted volume.
     *
     * Stores the user password for further accesses.
     *
     * @param string $volume the volume
     * @param string $password the plain password
     * @return bool TRUE on success, FALSE on failure
     * @throws Exception
     * @throws Zend_Exception
     */
    public function unlockVolume($volume, $password) {
        if (empty($volume) || !is_string($password) || !is_string($volume)) {
            return FALSE;
        }
        $volume = My_Utils::getRootFromPath( trim($volume) );
        if (strlen($volume) == 0) {
            return FALSE;
        }

        if (!$this->isInitialized()) {
            return FALSE;
        }

        $tmp_file = @tempnam($this->_base_dir, 'sxtmp_');
        try {
            if ($tmp_file !== FALSE) {
                $ret = $this->put($tmp_file, $volume.'/'.self::NEWDIR_FILENAME, FALSE, $password.PHP_EOL);
                @unlink($tmp_file);
                if ($ret === TRUE) {
                    return TRUE;
                }
            }
        }
        catch(Exception $e) {
            @unlink($tmp_file);
            
            /*
             * Corner case: we are trying to write to a read only volume
             * the password can be ok, but we get a permission denied error.
             * Nonetheless the volume could be unlocked.
             * */
            if ($e instanceof Skylable_AccessSxException) {
                if (!($e instanceof Skylable_InvalidPasswordException)) {
                    $this->getLogger()->debug(__METHOD__ . ': read only volume corner case.');
                    foreach($this->_last_error_log['errors'] as $err) {
                        // Failed to upload file content hashes: Permission denied: not enough privileges
                        if (stripos($err, 'failed to upload') !== FALSE && 
                            stripos($err, 'not enough privileges') !== FALSE) {
                            if ($this->volumeIsUnlocked($volume)) {
                                $this->getLogger()->debug(__METHOD__ . ': read only volume corner case: volume unlocked.');
                                return TRUE;
                            }
                        }
                    }
                    $this->getLogger()->debug(__METHOD__ . ': read only volume corner case: ignored.');
                }
            }
            throw $e;
        }
        return FALSE;
    }
    
    

    /**
     * Creates a directory into the specified path.
     *
     * Creates a file .sxnewdir into the path
     *
     * @param string $path the base path
     * @param string $dirname the directory name
     * @return bool
     * @throws Exception
     */
    public function mkdir($path, $dirname) {
        if (empty($dirname) || empty($path)) {
            self::getLogger()->debug(__METHOD__. ': invalid path: ' . print_r($path, TRUE) . " or dirname:" . print_r($dirname, TRUE));
            return FALSE;
        }
        $dest_path = My_Utils::joinDirectories(array($path, $dirname));

        // Check for the empty file into the local directory
        $file = tempnam($this->_base_dir, 'Skylable_AccessSx_');
        if ($file !== FALSE) {
            try {
                $status = $this->put($file, My_Utils::slashPath($dest_path).self::NEWDIR_FILENAME);
                unlink($file);
            }
            catch(Exception $e) {
                unlink($file);
                throw $e;
            }
            $this->getLogger()->debug(__METHOD__.': command status: '.var_export($status, TRUE));
            return $status;
        }

        return FALSE;
    }

    /**
     * Copies file(s) cluster to cluster.
     *
     * You can specify multiple sources, but only one destination.
     *
     * @param string|array $source source path (a single string or an array of strings)
     * @param string $destination destination path
     * @param bool $recursive TRUE copies dirs recursively, FALSE otherwise
     * @param string $password unlocks encrypted volumes
     * @return bool TRUE on success, FALSE on failure
     */
    public function copy($source, $destination, $recursive = TRUE, $password = '') {
        $this->_last_error_log = '';
        if (!$this->isInitialized()) {
            return FALSE;
        }

        if (!is_string($destination)) {
            return FALSE;
        }
        if (strlen($destination) == 0) {
            return FALSE;
        }

        if (is_array($source)) {
            foreach($source as $k => $v) {
                if (!is_string($v)) {
                    unset($source[$k]);
                    continue;
                } elseif(strlen($v) == 0) {
                    unset($source[$k]);
                    continue;
                }
                $source[$k] = My_utils::escapeshellarg( $this->_cluster_string.'/'.My_Utils::removeSlashes($v, TRUE) );
            }
            if (count($source) == 0) {
                return FALSE;
            }
        } elseif (strlen($source) == 0) {
            return FALSE;
        } else {
            $source = My_utils::escapeshellarg( $this->_cluster_string.'/'.My_Utils::removeSlashes($source, TRUE) );
        }

        $ret = $this->executeShellCommand(
            'sxcp -q --ignore-errors '.
            ($recursive ? '-r ' : '').
            '-c '.My_utils::escapeshellarg($this->_base_dir).' '.
            (is_array($source) ? implode(' ', $source) : $source).' '.
            My_utils::escapeshellarg( $this->_cluster_string.'/'.My_Utils::removeSlashes( $destination, TRUE ) ),
            $password, $output, $exitcode, $this->_last_error_log, NULL, array($this, 'parseErrors'));
        if ($exitcode == 0) {
            return TRUE;
        }
        $this->checkForErrors($this->_last_error_log, TRUE);

        return FALSE;
    }

    /**
     * Copies files from the local filesystem to the cluster.
     *
     * The cluster name is automatically added to the destination.
     *
     * @param string|array $source the source file(s)
     * @param string $destination the destination path
     * @param bool $recursive TRUE copies directories recursively, FALSE otherwise
     * @param string $password unlocks encrypted volumes
     * @return bool TRUE on success, FALSE on failure
     */
    public function put($source, $destination, $recursive = TRUE, $password = '') {
        $this->_last_error_log = '';
        if (!$this->isInitialized()) {
            return FALSE;
        }

        if (!is_string($destination)) {
            return FALSE;
        }
        if (strlen($destination) == 0) {
            return FALSE;
        }

        if (is_array($source)) {
            foreach($source as $k => $v) {
                if (!is_string($v)) {
                    unset($source[$k]);
                    continue;
                } elseif(strlen($v) == 0) {
                    unset($source[$k]);
                    continue;
                } elseif (!file_exists($v)) {
                    $this->getLogger()->debug(__METHOD__.': File don\'t exists: '.print_r($v, TRUE));
                    unset($source[$k]);
                    continue;
                }
                $source[$k] = My_utils::escapeshellarg( $v );
            }
            if (count($source) == 0) {
                $this->getLogger()->debug(__METHOD__.': No valid files to upload');
                return FALSE;
            }
        } elseif (strlen($source) == 0) {
            return FALSE;
        } else {
            if (!@file_exists($source)) {
                $this->getLogger()->debug(__METHOD__.': File don\'t exists: '.print_r($source, TRUE));
                return FALSE;
            }
            $source = My_utils::escapeshellarg( $source );
        }

        $ret = $this->executeShellCommand(
            'sxcp -q --ignore-errors '.
            ($recursive ? '-r ' : '').
            '-c '.My_utils::escapeshellarg($this->_base_dir).' '.
            (is_array($source) ? implode(' ', $source) : $source).' '.
            My_utils::escapeshellarg( $this->_cluster_string.'/'.My_Utils::removeSlashes( $destination, TRUE ) ),
            $password, $output, $exitcode, $this->_last_error_log, NULL, array($this, 'parseErrors'));

        if ($exitcode == 0) {
            return TRUE;
        }
        $this->checkForErrors($this->_last_error_log, TRUE);

        return FALSE;
    }

    /**
     * STUB: parse sxcp output.
     *
     * @param resource $fd file descriptor to analyze
     * @param array $output processed output
     * @return array
     */
    protected function parseCopyOutput($fd, &$output) {
        $retval = array(
            'status' => TRUE,
            'error' => ''
        );
        $output = array();

        while( ($data_line = fgets($fd)) !== FALSE ) {
            $output[] = $data_line;
        }
        if (!feof($fd)) { // fgets exited
            $retval['status'] = FALSE;
            $retval['error'] = new Exception('Unexpected end of the file', ERROR_UNEXPECTED_END_OF_FILE);
            $this->getLogger()->debug(__METHOD__.' - Unexpected end of the file');
        }
        return $retval;
    }

    /**
     * Moves file(s) cluster to cluster.
     *
     * You can specify multiple sources, but only one destination.
     *
     * @param string|array $source source path (a single string or an array of strings)
     * @param string $destination destination path
     * @param bool $recursive TRUE moves dirs recursively, FALSE otherwise
     * @param string $password unlocks encrypted volumes
     * @return bool TRUE on success, FALSE on failure
     */
    public function move($source, $destination, $recursive = TRUE, $password = '') {
        $this->_last_error_log = '';
        if (!$this->isInitialized()) {
            return FALSE;
        }

        if (!is_string($destination)) {
            return FALSE;
        }
        if (strlen($destination) == 0) {
            return FALSE;
        }

        if (is_array($source)) {
            foreach($source as $k => $v) {
                if (!is_string($v)) {
                    unset($source[$k]);
                    continue;
                } elseif(strlen($v) == 0) {
                    unset($source[$k]);
                    continue;
                }
                $source[$k] = My_utils::escapeshellarg( $this->_cluster_string.'/'.My_Utils::removeSlashes($v, TRUE) );
            }
            if (count($source) == 0) {
                return FALSE;
            }
        } elseif (strlen($source) == 0) {
            return FALSE;
        } else {
            $source = My_utils::escapeshellarg( $this->_cluster_string.'/'.My_Utils::removeSlashes($source, TRUE) );
        }

        $ret = $this->executeShellCommand(
            'sxmv '.
            ($recursive ? '-r ' : '').
            '-c '.My_utils::escapeshellarg($this->_base_dir).' '.
            (is_array($source) ? implode(' ', $source) : $source).' '.
            My_utils::escapeshellarg( $this->_cluster_string.'/'.My_Utils::removeSlashes( $destination, TRUE ) ),
            $password, $output, $exitcode, $this->_last_error_log, NULL, array($this, 'parseErrors'));
        if ($exitcode == 0) {
            return TRUE;
        }

        $this->checkForErrors($this->_last_error_log, TRUE);

        return FALSE;
    }

    /**
     * Parses the error log.
     *
     * @param resource $fd
     * @param mixed $error_log
     * @return array
     * @throws Exception
     */
    protected function parseErrors($fd, &$error_log) {
        $retval = array(
            'status' => TRUE,
            'error' => ''
        );
        $error_log  = array(
            'errors' => array(),
            'messages' => array()
        );

        $this->getLogger()->debug(__METHOD__.' - Parsing errors...');

        @fseek($fd, SEEK_SET, 0);

        $error_count = 0;
        $msg_count = 0;

        while( ($data_line = fgets($fd)) !== FALSE ) {
            $data_line = trim($data_line);
            $this->getLogger()->debug($data_line);
            if (strncasecmp('ERROR: ', $data_line, 7) == 0) {
                $error_count++;
                $error_log['errors'][] = substr($data_line, 7);
            } else {
                $msg_count++;
                $error_log['messages'][] = $data_line;
            }
        }

        $this->getLogger()->debug(__METHOD__.sprintf(" - Found %d errors, %d messages", $error_count, $msg_count));

        if (!feof($fd)) { // fgets exited
            $retval['status'] = FALSE;
            $retval['error'] = new Exception('Unexpected end of the file', ERROR_UNEXPECTED_END_OF_FILE);
            $this->getLogger()->debug(__METHOD__.' - Unexpected end of the file');
        }

        $this->getLogger()->debug(__METHOD__.' - ...finished.');

        return $retval;

    }

    /**
     * Check if the execution error log has errors and throw exception.
     *
     * Works only with an error log made by {@link Skylable_AccessSxNew::parseErrors}.
     *
     * @param array $log
     * @param bool $throw_exception TRUE on errors throw an exception, FALSE return the value
     * @return bool TRUE if there are errors, FALSE otherwise
     * @throws Skylable_AccessSxException
     * @throws Skylable_InvalidPasswordException
     * @throws Skylable_RevisionException
     * @throws Skylable_InvalidCredentialsException
     */
    protected function checkForErrors($log, $throw_exception = TRUE) {
        if (is_array($log)) {
            // Messages are useful only to check if the provided password is wrong
            if (array_key_exists('messages', $log)) {
                if (is_array($log['messages'])) {
                    if ($throw_exception) {
                        // Check to see if the password is wrong
                        foreach($log['messages'] as $msg) {
                            if (stripos($msg, 'invalid password') !== FALSE) {
                                throw new Skylable_InvalidPasswordException(implode('\n', $log['messages']));
                            }
                        }
                    }
                }
            }
            if (array_key_exists('errors', $log)) {
                if (is_array($log['errors'])) {
                    if (count($log['errors']) > 0) {
                        if ($throw_exception) {
                            // Check to see if the credentials are invalid
                            // this can throw a wrong exception
                            foreach($log['errors'] as $err) {
                                if (stripos($err, 'invalid credentials') !== FALSE) {
                                    throw new Skylable_InvalidCredentialsException(implode('\n', $log['errors']));
                                } elseif(stripos($err, 'failed to retrieve file revisions') !== FALSE) {
                                    if (stripos($err, 'Failed to list file revisions') !== FALSE) {
                                        $code = Skylable_RevisionException::REVISIONS_NOT_FOUND;
                                    } elseif (stripos($err, 'Failed to locate volume') !== FALSE) {
                                        $code = Skylable_RevisionException::REVISIONS_VOLUME_NOT_FOUND;
                                    } else {
                                        $code = Skylable_RevisionException::REVISIONS_ERROR;
                                    }
                                    throw new Skylable_RevisionException(implode('\n', $log['errors']), $code);
                                }
                            }
                            throw new Skylable_AccessSxException(implode('\n', $log['errors']));
                        } else {
                            return TRUE;
                        }
                    }
                }
            }
        }
        return FALSE;
    }

    /**
     * Tells who you are on the SX cluster.
     *
     * @return bool|string FALSE on error, a string with the user name
     * @throws Exception
     * @throws Zend_Exception
     */
    public function whoami() {
        $this->_last_error_log = '';
        if (!$this->isInitialized()) {
            return FALSE;
        }

        $ret = $this->executeShellCommand('sxacl whoami '.
            '-c '.My_utils::escapeshellarg($this->_base_dir).' '.
            My_utils::escapeshellarg( $this->_cluster_string ),
            '', $out, $exit_code, $this->_last_error_log, NULL, array($this, 'parseErrors'));
        if ($exit_code == 0) {
            return trim($out);
        }
        return FALSE;
    }

    /**
     * Provides the activation link for SXDrive
     * @return bool|string FALSE on error, a string with the activation link
     * @throws Exception
     * @throws Zend_Exception
     */
    public function getUserLink() {
        $info = $this->clusterInfo();
        if ($info !== FALSE) {
            return $info['configuration_link'];            
        }
        return $info;
    }

    /**
     * Get cluster info, as of command 'sxinit -I'.
     * 
     * On success returns an associative array, with these keys:
     * 
     * array(
     *  'cluster_name' => '',
     *  'cluster_uuid' => '',
     * 'nodes' => '',
     * 'port' => '',
     * 'use_ssl' => '',
     * 'ca_file' => '',
     * 'current_profile' => '',
     * 'configuration_directory' => '',
     * 'libsx_version' => '',
     * 'configuration_link' => '' 
     * )
     * 
     * Keys are parsed from the command output, put to lower case, with spaces converted to '_'.
     * 
     * @return bool|array
     * @throws Exception
     */
    public function clusterInfo() {
        $this->_last_error_log = '';
        if (!$this->isInitialized()) {
            return FALSE;
        }

        $ret = $this->executeShellCommand('sxinit -I '.
            '-c '.My_utils::escapeshellarg($this->_base_dir).' '.
            My_utils::escapeshellarg( $this->_cluster_string ),
            '', $out, $exit_code, $this->_last_error_log, array($this, 'parseClusterInfo'), array($this, 'parseErrors'));
        if ($exit_code == 0) {
            $this->getLogger()->debug(__METHOD__.': returned data: '.var_export($out, TRUE));
            return $out;
        } 
        $this->checkForErrors($this->_last_error_log);
        return FALSE;
    }

    /**
     * Parse the 'sxinit -I' command output
     * 
     * @param $fd
     * @param $data
     * @return array
     */
    protected function parseClusterInfo($fd, &$data) {
        $retval = array(
            'status' => TRUE,
            'error' => ''
        );
        $data = array();

        while( ($data_line = fgets($fd)) !== FALSE ) {
            if (preg_match('/^([^:]+):\s+(.*)/', $data_line, $matches) == 1) {
                $data[strtolower(str_replace(' ', '_', $matches[1]))] = trim($matches[2]); 
            }
        }
        if (!feof($fd)) { // fgets exited
            $retval['status'] = FALSE;
            $retval['error'] = new Exception('Unexpected end of the file', ERROR_UNEXPECTED_END_OF_FILE);
            $this->getLogger()->debug(__METHOD__.' - Unexpected end of the file');
        }
        return $retval;
    }

    /**
     * Tells the user role on the SX cluster.
     *
     * @return bool|string FALSE on error, a string with the user role
     * @throws Exception
     * @throws Zend_Exception
     */
    public function getUserRole() {
        $this->_last_error_log = '';
        if (!$this->isInitialized()) {
            return FALSE;
        }

        $ret = $this->executeShellCommand('sxacl whoami --role '.
            '-c '.My_utils::escapeshellarg($this->_base_dir).' '.
            My_utils::escapeshellarg( $this->_cluster_string ),
            '', $out, $exit_code, $this->_last_error_log, NULL, array($this, 'parseErrors'));
        if ($exit_code == 0) {
            if (preg_match('/\(([^\)]+)\)$/', trim($out), $matches) == 1) {
                return $matches[1];
            } else {
                return '';
            }
        }
        return FALSE;
    }

    /**
     * Change (password based) user authentication key.
     * 
     * A normal user can change its own key and a cluster administrator 
     * can change a key of any user.
     * 
     * If you don't specify a username, you'll change the key of the current user.
     * 
     * @param string $password the password
     * @param string $username the user of which change the key
     * @throws Exception
     * @return bool|string
     */
    public function sxaclUserNewKey($password, $username = '') {
        $this->_last_error_log = '';
        if (!$this->isInitialized()) {
            return FALSE;
        }
        
        if (strlen($username) == 0) {
            $username = $this->_user->getLogin();
        }
        
        $pass_file = tempnam($this->getBaseDir(), 'sxacl_pass_');
        if ($pass_file === FALSE) {
            $this->getLogger()->debug(__METHOD__.': failed to create password file into: ' . $this->getBaseDir());
            throw new Skylable_AccessSxException('Failed to create temporary data file.');
        }
        $auth_file = tempnam($this->getBaseDir(), 'sxacl_auth_');
        if ($auth_file === FALSE) {
            $this->getLogger()->debug(__METHOD__.': failed to create auth file into: ' . $this->getBaseDir());
            throw new Skylable_AccessSxException('Failed to create temporary data file.');
        }
        if (@file_put_contents($pass_file, $password . PHP_EOL) === FALSE) {
            $this->getLogger()->debug(__METHOD__.': failed to write password into:  ' . $this->getBaseDir());
            @unlink($pass_file);
            @unlink($auth_file);
            throw new Skylable_AccessSxException('Failed to store password.');
        }

        $ret = $this->executeShellCommand('sxacl usernewkey '.
            '-c '.My_utils::escapeshellarg($this->_base_dir).' '.
            '-p '.My_Utils::escapeshellarg( $pass_file ). ' '.
            '-a '.My_Utils::escapeshellarg( $auth_file ). ' '.
            My_utils::escapeshellarg( $username ).' '.
            My_utils::escapeshellarg( $this->_cluster_string ),
            '', $out, $exit_code, $this->_last_error_log, NULL, array($this, 'parseErrors'));
        if ($exit_code == 0) {
            $new_user_key = @file_get_contents($auth_file);
            @unlink($pass_file);
            @unlink($auth_file);
            if ($new_user_key !== FALSE) {
                return $new_user_key;
            }
        } else {
            @unlink($pass_file);
            @unlink($auth_file);    
        }
        
        $this->checkForErrors($this->_last_error_log, TRUE);
        return FALSE;
    }

    /**
     * Lists revisions for a given file.
     * 
     * Returns an array of associative array with the revisions, ordered by the most recent first.
     * 
     * array(
     *  array(
     *   'id' => revision number (used in
     *   'date' => revision date YYYY-MM-DD
     *   'time' => revision time hh:mm
     *   'rev' => revision name
     *   'size' => revision size
     *   
     * )
     * )
     * 
     * @param string $path the complete path
     * @return bool|array
     * @throws Exception
     * @throws Skylable_AccessSxException
     */
    public function sxrevList($path) {
        $this->_last_error_log = '';
        if (!$this->isInitialized()) {
            return FALSE;
        }

        $ret = $this->executeShellCommand('sxrev list '.
            '-c '.My_utils::escapeshellarg($this->_base_dir).' '.
            My_utils::escapeshellarg( $this->_cluster_string.'/'.My_Utils::removeSlashes($path, TRUE) ),
            '', $out, $exit_code, $this->_last_error_log, array($this, 'parseSxrevListOutput'), array($this, 'parseErrors'));
        if ($exit_code == 0) {
            return $out;
        } else {
            $this->checkForErrors($this->_last_error_log, TRUE);    
        }

        return FALSE;
    }

    /**
     * Parse the sxrev list output.
     * 
     * @param $fd
     * @param $data
     * @return array
     */
    private function parseSxrevListOutput($fd, &$data) {
        $ret = array(
            'status' => TRUE,
            'error' => ''
        );
        $data = array();

        while( ($data_line = fgets($fd)) !== FALSE ) {
            if (preg_match('/^(?<id>\d+)\.\s+(?<date>[^\s]+)\s+(?<time>[^\s]+)\s+size:(?<size>\d+)\s+rev:"(?<rev>[^"]+)/', $data_line, $matches) == 1) {
                
                for($i = 0; $i < 6; $i++) {
                    unset($matches[$i]);
                }
                $data[] = $matches;
            }
        }
        if (!feof($fd)) { // fgets exited
            $retval['status'] = FALSE;
            $retval['error'] = new Exception('Unexpected end of the file', ERROR_UNEXPECTED_END_OF_FILE);
            $this->getLogger()->debug(__METHOD__.' - Unexpected end of the file');
        }

        return $ret;
    }

    /**
     * Delete the given revision.
     * 
     * @param string $path the complete file path
     * @param string $rev_id the revision ID
     * @return bool TRUE on success, FALSE on failure
     * @throws Exception
     * @throws Skylable_AccessSxException
     */
    public function sxrevDelete($path, $rev_id) {
        $this->_last_error_log = '';
        if (!$this->isInitialized()) {
            return FALSE;
        }
        
        if (strlen($rev_id) == 0) {
            $this->getLogger()->debug(__METHOD__.': Empty revision ID');
            return FALSE;
        }

        $ret = $this->executeShellCommand('sxrev delete '.
            '-c '.My_utils::escapeshellarg($this->_base_dir).' '.
            '-r '.My_utils::escapeshellarg($rev_id).' '.
            My_utils::escapeshellarg( $this->_cluster_string.'/'.My_Utils::removeSlashes($path, TRUE) ),
            '', $out, $exit_code, $this->_last_error_log, NULL, array($this, 'parseErrors'));
        if ($exit_code == 0) {
            return TRUE;
        } else {
            $this->checkForErrors($this->_last_error_log, TRUE);
        }

        return FALSE;
    }

    /**
     * Copies a revision to a destination.
     * 
     * @param string $source_path the complete path of the revision to copy
     * @param string $rev_id the revision ID
     * @param string $destination_path the destination of the copy
     * @param bool $destination_is_remote TRUE copies the destination from cluster to cluster, FALSE copies the revision to a local file
     * @return bool TRUE on success, FALSE on failure
     * @throws Exception
     * @throws Skylable_AccessSxException
     */
    public function sxrevCopy($source_path, $rev_id, $destination_path, $destination_is_remote = TRUE) {
        $this->_last_error_log = '';
        if (!$this->isInitialized()) {
            return FALSE;
        }

        if (strlen($rev_id) == 0) {
            $this->getLogger()->debug(__METHOD__.': Empty revision ID');
            return FALSE;
        }
        
        if ($destination_is_remote) {
            $dest = My_utils::escapeshellarg( $this->_cluster_string.'/'.My_Utils::removeSlashes($destination_path, TRUE) );
        } else {
            $dest = $destination_path;
        }

        $ret = $this->executeShellCommand('sxrev copy '.
            '-c '.My_utils::escapeshellarg($this->_base_dir).' '.
            '-r '.My_utils::escapeshellarg($rev_id).' '.
            My_utils::escapeshellarg( $this->_cluster_string.'/'.My_Utils::removeSlashes($source_path, TRUE) ).' '.
            $dest,
            '', $out, $exit_code, $this->_last_error_log, NULL, array($this, 'parseErrors'));
        if ($exit_code == 0) {
            return TRUE;
        } else {
            $this->checkForErrors($this->_last_error_log, TRUE);
        }

        return FALSE;
    }

    /**
     * Send a revision to the browser.
     * 
     * File data array must contain:
     * 'path' - complete file path
     * 'size' - file size in bytes
     * 'rev' - revision string
     *
     * @param array $file_data
     * @param string $password
     * @param string $disposition
     * @return array|bool
     * @throws Exception
     * @throws Zend_Exception
     */
    public function sxrevDownload($file_data, $password = '', $disposition = self::DOWNLOAD_DISPOSITION_ATTACHMENT) {

        // If the file is empty take the easy way
        if ($file_data['size'] == 0) {
            $this->getLogger()->debug(__METHOD__ . ': File has zero size, skipping shell command use.');
            header("Cache-Control: no-cache, must-revalidate");
            header("Pragma: no-cache");
            header("Content-Disposition: ".$disposition."; filename=\"".rawurlencode(basename($file_data['path'])).'"');
            header("Content-Type: ".My_Utils::getFileMIME($file_data['path']));
            header("Content-Length: ".strval( $file_data['size'] ));
            header('Content-Transfer-Encoding: binary');
            return TRUE;
        }

        $pipes = array();

        $process_log_fd = fopen('php://temp','r+');
        if ($process_log_fd === FALSE) {
            throw new Exception("Failed to create error log", self::ERROR_CANT_CREATE_ERROR_LOG);
        }

        $this->_last_error_log = '';

        $cmd = 'sxrev copy '.
            '-c '.My_utils::escapeshellarg($this->_base_dir).' '.
            '-r '.My_utils::escapeshellarg($file_data['rev']).' '.
            My_utils::escapeshellarg($this->_cluster_string.'/'.My_Utils::removeSlashes($file_data['path'], TRUE) ).
            ' -';

        $this->getLogger()->debug(__METHOD__ . ': executing: '.$cmd);
        $process = proc_open($cmd,
            array(
                0 => array('pipe', 'r'),
                1 => array('pipe', 'w'),
                2 => $process_log_fd
            ), $pipes);

        if (is_resource($process)) {
            // This will be returned
            $ret_val = array(
                'status' => TRUE,
                'stdin_process' => array('status' => TRUE, 'error' => NULL),
                'stdout_process' => array('status' => TRUE, 'error' => NULL),
                'stderr_process' => array('status' => TRUE, 'error' => NULL)
            );

            if (strlen($password) > 0) {
                $status = fwrite($pipes[0], $password.PHP_EOL);
                $this->getLogger()->debug(__METHOD__.': injecting input: '.($status === FALSE ? 'failed' : 'successful, wrote '.strval($status).' bytes' ));
                if ($status === FALSE) {
                    $ret_val['stdin_process']['status'] = FALSE;
                    $ret_val['stdin_process']['error'] = ERROR_STDIN_INJECT_FAIL;
                }
            }

            fclose($pipes[0]);

            // Reads 1KB of data, if is ok, send the rest to the browser
            if (($first_chunk = fread($pipes[1], 1024)) !== FALSE) {
                if (strlen($first_chunk) > 0) {
                    $this->getLogger()->debug(__METHOD__.': Sending file...');
                    header("Cache-Control: no-cache, must-revalidate");
                    header("Pragma: no-cache");
                    header("Content-Disposition: ".$disposition."; filename=\"".rawurlencode(basename($file_data['path'])).'"');
                    header("Content-Type: ".My_Utils::getFileMIME($file_data['path']));
                    // This fixes problems with zcomp volumes 
                    // header("Content-Length: ".strval( $file_data['size'] ));
                    header('Content-Transfer-Encoding: binary');
                    ob_end_clean();
                    Zend_Session::writeClose();
                    $this->getLogger()->debug(__METHOD__.': Sending first chunk...');
                    echo $first_chunk;
                    $this->getLogger()->debug(__METHOD__.': Passthru of the file...');
                    fpassthru($pipes[1]);
                } else {
                    $this->getLogger()->debug(__METHOD__.': First chunk is empty, this is bad.');
                }
            } else {
                $this->getLogger()->debug(__METHOD__.': Reading first chunk failed.');
            }

            @fclose($pipes[1]);

            $exit_code = proc_close($process);
            $this->getLogger()->debug(__METHOD__.': command return value is:' . print_r($exit_code, TRUE));

            // Analyses the process error log
            fseek($process_log_fd, 0, SEEK_SET);
            $ret_val['stderr_process'] = $this->parseErrors($process_log_fd, $this->_last_error_log);

            @fclose($process_log_fd);

            return $ret_val;
        } else {
            throw new Exception('Failed to initialize the process.', self::ERROR_CANT_INITIALIZE_PROCESS);
        }
    }
    
    public function userlist() {

        $this->_last_error_log = '';
        if (!$this->isInitialized()) {
            return FALSE;
        }

        $ret = $this->executeShellCommand('sxacl userlist '.
            '-c '.My_utils::escapeshellarg($this->_base_dir).' '.
            My_utils::escapeshellarg( $this->_cluster_string ),
            '', $out, $exit_code, $this->_last_error_log, array($this, 'parseSxaclUserlistOutput'), array($this, 'parseErrors'));
        if ($exit_code == 0) {
            return $out;
        } else {
            $this->checkForErrors($this->_last_error_log, TRUE);
        }

        return FALSE;
    }


    private function parseSxaclUserlistOutput($fd, &$data) {
        $ret = array(
            'status' => TRUE,
            'error' => ''
        );
        $data = array();

        while( ($data_line = fgets($fd)) !== FALSE ) {
            if (preg_match('/^(?<user>[^\(]+)\((?<type>[^\)]+)/', $data_line, $matches) == 1) {

                for($i = 0; $i < 3; $i++) {
                    unset($matches[$i]);
                }
                $data[trim($matches['user'])] = $matches['type'];
            }
        }
        if (!feof($fd)) { // fgets exited
            $retval['status'] = FALSE;
            $retval['error'] = new Exception('Unexpected end of the file', ERROR_UNEXPECTED_END_OF_FILE);
            $this->getLogger()->debug(__METHOD__.' - Unexpected end of the file');
        }

        return $ret;
    }


    /**
     * Set the user, but don't set internal paths.
     * 
     * You should update them manually.
     * 
     * @param My_User $user
     * @param boolean $update_base_dir TRUE calls updateBaseDir, FALSE otherwise
     * @see updateBaseDir
     */
    public function setUser(My_User $user, $update_base_dir = FALSE) {
        $this->_user = $user;
        if ($update_base_dir) {
            $this->updateBaseDir();
        }
    }
}
