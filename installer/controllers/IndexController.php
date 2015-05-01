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
class IndexController extends Zend_Controller_Action {

    protected
        /**
         * The translator.
         * 
         * @var Zend_Translate
         */
        $_translator = NULL;
    
    public
        /**
         * Flag to tell our custom front controller if we want to
         * render the current action using the default renderer 
         * @var bool
         */
        $render_the_script = TRUE;

    /**
     * Returns an associative array with the base configuration 
     * @return array
     */
    public function getBaseConfig() {
        
        $app_path = 'APPLICATION_PATH ';
        
        $cfg = array(
            //sx cluster url sx://...
            'cluster' => "sx://cluster.example.com",
            
            'cluster_ssl' => true,
            'cluster_port' => '',
            'cluster_ip' => '',

            // Base URL used to generate other URLs, ie the shared file URL
            'url' => $this->view->ServerUrl(),
            
            // Main directory 
            'local' => $app_path . '"/../"',

            // Upload directory
            'upload_dir' => $app_path . '"/../data/files"',

            // local directory where you will store your sx keys
            'sx_local' => $app_path . '"/../data/sx"',
            
            // Dowload limits
            //  maximum concurrent downloads per logged user
            'downloads' => 5,
            
            // maximum concurrent downloads per IP address (used for shared files)
            'downloads_ip' => 30,
            
            // time window in seconds per single user
            'downloads_time_window' => 20,
            
            // time window in seconds per IP address
            'downloads_time_window_ip' => 20,

            // Update upload_max_filesize, memory_limit, post_max_size, max_execution_time accordingly
            'max_upload_filesize' => 0,

            // shared file expire time in seconds
            // default 1 week = 60*60*24*7
            'shared_file_expire_time' => 604800,

            // how long cookie is valid?
            // 3600*24*15 - 1296000 - 15 days
            'remember_me_cookie_seconds' => 1296000,

            // The cookie domain
            // remember to put a dot where needed
            // ie: is ".example.com" and not "example.com"
            'cookie_domain' => ".example.com",

            // Elastic search hosts
            'elastic_hosts' => array( "localhost" ),

            // URL to use to contact the tech support
            'tech_support_url' => "http://skylable.zendesk.com",
            
            // Password recovery
            'password_recovery' => FALSE,
            'admin_key' => '',
            
            // DB configuration
            'db.adapter' => "pdo_mysql",
            'db.params.host' => "localhost",
            'db.params.port' => 3306,
            'db.params.username' => "",
            'db.params.password' => "",
            'db.params.dbname' => "sxweb",
            'db.params.charset' => "utf8",

            // Email
            'mail.transport.type' => "smtp",
            'mail.transport.name' => "example.com",
            'mail.transport.host' => "localhost",
            'mail.transport.auth' => 'none',
            'mail.transport.username' => '',
            'mail.transport.password' => '',
            'mail.transport.ssl' => '',
            'mail.transport.port' => '',
            'mail.transport.register' => true, 
    
            'mail.defaultFrom.email' => "noreply@example.com",
            'mail.defaultFrom.name' => "SXWeb",
            'mail.defaultReplyTo.email' => '',
            'mail.defaultReplyTo.name' => ''
        );
        
        // Parameters to skip
        $skip_list = array('db.params.username','db.params.password',
            'db.adapter','db.isDefaultTableAdapter','db.params.charset');
        
        // Check for a valid skylable.ini and integrate its configuration
        if (@file_exists(APP_CONFIG_BASE_PATH . 'skylable.ini')) {
            $skylable_ini = @parse_ini_file( APP_CONFIG_BASE_PATH . 'skylable.ini', TRUE, INI_SCANNER_RAW );
            if ($skylable_ini !== FALSE) {
                foreach($skylable_ini as $k => $v) {
                    if (trim($k) == 'production') {
                        foreach($skylable_ini[$k] as $dk => $dv) {
                            if (array_key_exists($dk, $cfg) && !in_array($dk, $skip_list)) {
                                $cfg[$dk] = $dv;
                            }
                        }
                    }    
                }
            } 
        } 
        
        return $cfg;
    }

    public function init() {
        $this->_translator = $this->getTranslator();
    }

    /**
     * Return the translator object.
     * 
     * @return Zend_Translate|null
     * @throws Zend_Exception
     */
    public function getTranslator() {
        if (Zend_Registry::isRegistered('Zend_Translate')) {
            return Zend_Registry::get('Zend_Translate');
        }
        return NULL;
    }

    /**
     * Translate the given string.
     * 
     * @param string $str string to translate
     * @return string
     */
    public function translate($str) {
        if (is_object($this->_translator)) {
            return $this->_translator->translate($str);
        }
        return $str;
    }

    /**
     * Convert a php.ini "pretty" value to bytes
     * @param $value
     * @return integer
     */
    public function toBytes($value) {
        switch (substr ($value, -1))
        {
            case 'M': 
            case 'm': 
                return (int)$value * 1048576;
            case 'K': 
            case 'k': 
                return (int)$value * 1024;
            case 'G': 
            case 'g': 
                return (int)$value * 1073741824;
            default: 
                return $value;
        }
    }
    
    public function indexAction() {
        $this->view->headTitle($this->translate('Welcome!'));
        
        // Prepares the session
        $session = new Zend_Session_Namespace();
        $session->config = $this->getBaseConfig();
        $session->last_step = 'index';
    }

    /**
     * Checks PHP requirements.
     */
    public function step1Action() {

        $this->view->headTitle($this->translate('Step #1'));

        if (!$this->sessionIsValid('index')) {
            $this->redirect( $this->view->ServerUrl() . '/install.php' );
        }
        
        $session = new Zend_Session_Namespace();
        
        $this->view->php_components = array();
        
        // PHP version
        $this->view->can_proceed = version_compare(PHP_VERSION, '5.3.9', '>=');
        $this->view->php_components[] = array('PHP', sprintf($this->translate('At least %s'), '5.3.9'), (version_compare(PHP_VERSION, '5.3.9', '>=') ? $this->translate('Found') : $this->translate('Upgrade needed') ) );

        // PHP extensions
        $ext = get_loaded_extensions();

        $needed = array(
            'date' => $this->translate('Date extension'),
            'PDO' => $this->translate('PDO extension'),
            'pdo_mysql' => $this->translate('PDO MySql extension'),
            'openssl' => $this->translate('OpenSSL extension'),
            'curl' => $this->translate('cUrl extension'),
            'SPL' => $this->translate('PHP SPL extension library'),
            'json' => $this->translate('JSON extension'),
            'session' => $this->translate('Session extension')
        );
        foreach($needed as $need_ext => $label) {
            $found = in_array($need_ext, $ext); 
            if (!$found) {
                $this->view->can_proceed = FALSE;
            }
            $this->view->php_components[] = array($label, $this->translate('Yes'), ($found ? $this->translate('Found') : sprintf('<span class="label label-danger">%s</span>', $this->translate('Not found')) ) );
        }
        
        // PHP Uploads conf
        // name => needed value
        $php_ini_settings = array(
            'file_uploads' => 1,
            'upload_max_filesize' => '64M', 
            'upload_tmp_dir' => '', 
            'memory_limit' => '128M', 
            'post_max_size' => '64M', 
            'max_execution_time' => 600,
            'max_input_time' => 600
        );
        
        $this->view->php_ini_settings = array();
        foreach($php_ini_settings as $ini_key => $req) {
            $this->view->php_ini_settings[] = array($ini_key, ini_get($ini_key), $req);
        }
        
        $this->view->max_upload_filesize = min( array( $this->toBytes(ini_get('post_max_size')), $this->toBytes(ini_get('memory_limit')), $this->toBytes(ini_get('upload_max_filesize'))  ) );

        $session->config['max_upload_filesize'] = $this->view->max_upload_filesize;
        if (ini_get('file_uploads') == FALSE ) {
            $this->view->can_proceed = FALSE;
        }
        
        // SX commands
        $sx_cmd = array(
            'sxinit','sxcp','sxrm','sxmv','sxacl','sxrev'
        );
        
        $this->view->sx_commands_search_path = $this->getExecPath();
        if (empty($this->view->sx_commands_search_path)) {
            $this->view->sx_commands_search_path_error = $this->translate('The path is empty!');
        }
            
        $this->view->sx_commands = array();
        foreach($sx_cmd as $cmd) {
            $str = exec($cmd.' -V', $output, $ret_val);
            if (empty($output)) {
                $this->view->can_proceed = FALSE;
                $this->view->sx_commands[] = array( $cmd, '', sprintf('<span class="label label-danger">%s</span>', $this->translate('Not found')) );
            } else {
                $this->view->sx_commands[] = array( $cmd, '', $this->translate('Found') );
            }
        }    
        
        
        // Check the data dir
        if (!@file_exists(APPLICATION_DATA_PATH)) {
            
            if (!@mkdir(APPLICATION_DATA_PATH, 0775)) {
                $this->view->can_proceed = FALSE;
                $this->view->data_path_problem = $this->translate('Can&apos;t create the directory.');
            }

        } else {
            if (@is_dir(APPLICATION_DATA_PATH)) {
                if (!@is_writable(APPLICATION_DATA_PATH)) {
                    $this->view->can_proceed = FALSE;
                    $this->view->data_path_problem = $this->translate('Data path is not writable.');
                }
            } else {
                $this->view->can_proceed = FALSE;
                $this->view->data_path_problem = $this->translate('Data path is not a directory.');
            }
        }
        
        if ($this->view->can_proceed) {
            $session->last_step = 'step1';   
        } else {
            $session->last_step = FALSE;
        }
    }

    /**
     * Returns the execution path of exec() command
     * @return string
     */
    public function getExecPath() {
        $the_path = getenv('PATH');
        if ($the_path !== FALSE) {
            return $the_path;
        }
        if (isset($_SERVER['PATH'])) {
            return $_SERVER['PATH'];
        }
        return '';
    }

    /**
     * Load a file containing SQL code and breaks it into an array of SQL strings.
     * 
     * Also remove the comments.
     * 
     * The returned array can be empty.
     * 
     * @param string $sql_file_path the file to load
     * @return array|bool FALSE if the file is not readable, an array otherwise
     */
    protected function getSQLArray($sql_file_path) {
        $sql = @file_get_contents($sql_file_path);
        if ($sql === FALSE) {
            return FALSE;
        }

        $sql = preg_replace('/^\s*--.*/m', '', $sql);
        $sql_arr = array_map( 'trim', explode(';', $sql));
        foreach($sql_arr as $k => $v) {
            if (strlen($v) == 0) {
                unset($sql_arr[$k]);
            }
        }
        
        return $sql_arr;
    }

    /**
     * Initialize or upgrade the DB schema.
     */
    public function initdbAction() {

        $this->view->headTitle($this->translate('Step #2'));
        
        if (!$this->sessionIsValid('step2')) {
            $this->redirect($this->view->ServerUrl() . '/install.php?step=step1');
        }

        $this->view->headTitle($this->translate('Setting up the DB'));

        $session = new Zend_Session_Namespace();

        // Install or upgrade the DB
        $session->last_step = 'step2';

        try {
            $db_conn = Zend_Db::factory('Pdo_Mysql', array(
                'username' => $session->config['db.params.username'],
                'password' => $session->config['db.params.password'],
                'dbname' => $session->config['db.params.dbname'],
                'host' => $session->config['db.params.host'],
                'port' => (empty($session->config['db.params.port']) ? '3306' : $session->config['db.params.port'] ),
                'charset' => 'utf8'
            ));

            // If the DB is already populated skip creation, tries upgrade
            $tables = $db_conn->listTables();    
        }
        catch(Exception $e) {
            $session->last_step = 'step1';
            $this->view->error = $e->getMessage();
            $this->view->error_title = $this->translate('<strong>Fail!</strong> Database connection failed!');
            $this->view->message = $this->translate('Please check your connection parameters and retry.');
            return FALSE;
        }
        
        // Check if we need to create the DB schema
        $do_create_db = FALSE;
        if (empty($tables)) {
            $do_create_db = TRUE;
        } else {
            $base_tables = array('shared', 'tickets', 'user_reset_password', 'users', 'users_act_keys');
            foreach($base_tables as $t) {
                if (!in_array($t, $tables)) {
                    $do_create_db = TRUE;
                    break;
                }
            }
        }

        if ($do_create_db) {
            $sql_arr = $this->getSQLArray( INSTALLER_SQL_PATH . '/sxweb.sql' );
            if ($sql_arr !== FALSE) {
                try {
                    foreach($sql_arr as $sql) {
                        $db_conn->query($sql);    
                    }
                    
                    $this->view->message = $this->translate('Successfully created the database schema.');
                }
                catch(Exception $e) {
                    $session->last_step = 'step1';
                    $this->view->error = $e->getMessage();
                    $this->view->error_title = $this->translate('<strong>Fail!</strong> Database creation failed!');
                    $this->view->message = $this->translate('Failed to create the database schema.');
                }

            } else {
                $this->view->error_title = $this->translate('<strong>Fail!</strong> I/O error!');
                $this->view->error = $this->translate('Failed to load <code>sxweb.sql</code>. Please check your installation.');
                $session->last_step = 'step1';
            }
        } else {
            // Guess the DB schema version
            /*
             * The 0.2.0 branch lacks the 'sxweb_config' table
             * */
            try {
                $db_conn->setFetchMode(Zend_Db::FETCH_ASSOC);
                $db_version = FALSE;
                if (in_array('sxweb_config', $tables)) {
                    $data = $db_conn->fetchRow('SELECT * FROM ' . $db_conn->quoteIdentifier('sxweb_config') . ' WHERE ' . $db_conn->quoteIdentifier('item') . ' = ' . $db_conn->quote('db_version') . ' LIMIT 1');
                    if (!empty($data)) {
                        $db_version = $data['value'];
                    }
                } else {
                    $db_version = '0.2.0';
                }
            }
            catch(Exception $e) {
                $session->last_step = 'step2';
                $this->view->error = $e->getMessage();
                $this->view->error_title = $this->translate('<strong>Fail!</strong> Database access error!');
                $this->view->message = $this->translate('Failed to retrieve the DB schema version, you should upgrade manually.');
                
                $this->view->can_proceed = FALSE;
                return FALSE;
            }

            if ($db_version === FALSE) {
                $this->view->message = $this->translate('Something went wrong...');
                $this->view->error_title = $this->translate('<strong>Fail!</strong> Database access error!');
                $this->view->error = $this->translate('Failed to retrieve the DB schema version, you should upgrade manually.');
                $this->view->can_proceed = FALSE;
                
                return FALSE;
            } else {
                // DB is old, upgrade
                $upgrade_success = TRUE;
                $upgrade_problems = array();

                if (version_compare($db_version, SXWEB_VERSION, '<=')) {
                    $upgrade_steps = array();
                    if (version_compare($db_version, '0.2.0', '==')) {
                        $upgrade_steps = array(
                            'from_02_to_03',
                            'from_03_to_04'
                        );
                    } elseif (version_compare($db_version, '0.3.0', '==')) {
                        $upgrade_steps = array(
                            'from_03_to_04'
                        );
                    }
                    
                    // Prepare the list of SQL files to apply
                    $upgrade_sql_file_list = array();
                    foreach($upgrade_steps as $sql_dir) {
                        $the_sql_dir = INSTALLER_SQL_PATH . '/upgrade/' . $sql_dir;
                        if (@is_dir($the_sql_dir)) {
                            $sql_files = glob($the_sql_dir . '/*.sql');
                            if ($sql_files !== FALSE) {
                                foreach ($sql_files as $sql_file) {
                                    $upgrade_sql_file_list[] = $sql_file;
                                }
                            }
                        }
                    }
                    
                    // Apply the SQL files
                    foreach($upgrade_sql_file_list as $sql_file) {
                        $sql_arr = $this->getSQLArray($sql_file);
                        if ($sql_arr === FALSE) {
                            $upgrade_success = FALSE;
                            $upgrade_problems[] = sprintf( $this->translate('Failed to load SQL file: <code>%s</code>') , $sql_file);
                        } else {
                            try {
                                foreach($sql_arr as $sql) {
                                    $db_conn->query($sql);    
                                }
                            }
                            catch(Exception $e) {
                                $upgrade_success = FALSE;
                                $upgrade_problems[] = sprintf( $this->translate('Failed to apply SQL file: <code>%s</code>, SQL error: <code>%s</code>') , $sql_file, $e->getMessage());
                            }
                        }
                        
                        if (!$upgrade_success) {
                            break;
                        }
                    }
                }

                if ($upgrade_success) {
                    $this->view->message = $this->translate('Database successfully upgraded.');
                } else {
                    $session->last_step = 'step1';
                    $this->view->error =  implode('<br />'.PHP_EOL, $upgrade_problems);
                    $this->view->error_title = $this->translate('<strong>Fail!</strong> Database upgrade failed!');
                    $this->view->message = $this->translate('Something went wrong...');
                    $this->view->can_proceed = FALSE; 
                }
            }
        }
    }
    
    /**
     * DB configuration
     * @throws Zend_Form_Exception
     */
    public function step2Action() {
        
        if (!$this->sessionIsValid('step1')) {
            $this->redirect($this->view->ServerUrl() . '/install.php?step=step1');
        }

        $this->view->headTitle($this->translate('Step #2'));

        $session = new Zend_Session_Namespace();

        // Always populate from session, then update from request
        $this->view->frm_db_host = $session->config['db.params.host'];
        $this->view->frm_db_port = $session->config['db.params.port'];
        $this->view->frm_db_user = $session->config['db.params.username'];
        $this->view->frm_db_password = '';

        $form = new Zend_Form();
        $form->addElement( 'text', 'frm_db_name', array(
            'validators' => array( new Zend_Validate_StringLength(array('min' => 1, 'max' => 255)) ),
            'filters' => array(
                'StringTrim'
            ),
            'required' => TRUE
        ));

        $form->addElement( 'text', 'frm_db_user', array(
            'validators' => array( new Zend_Validate_StringLength(array('min' => 1, 'max' => 255)) ),
            'filters' => array( 'StringTrim' ),
            'required' => TRUE
        ));

        $form->addElement( 'text', 'frm_db_password', array(
            'validators' => array( new Zend_Validate_StringLength(array('min' => 0, 'max' => 255)) ),
            'required' => FALSE
        ));

        $form->addElement( 'text', 'frm_db_host', array(
            'validators' => array( new Zend_Validate_StringLength(array('min' => 1, 'max' => 255)) ),
            'filters' => array( 'StringTrim' ),
            'required' => TRUE
        ));

        $form->addElement( 'text', 'frm_db_port', array(
            'validators' => array( new Zend_Validate_Regex('/^[1-9][0-9]{1,4}$/') ),
            'filters' => array( 'StringTrim' ),
            'required' => FALSE
        ));

        $data_map = array(
            'frm_db_name' => 'db.params.dbname',
            'frm_db_host' => 'db.params.host',
            'frm_db_port' => 'db.params.port',
            'frm_db_user' => 'db.params.username',
            'frm_db_password' => 'db.params.password'
        );
        
        // Check for the form
        if ($this->getRequest()->isPost()) {
            
            if ($form->isValid( $this->getRequest()->getParams() )) {
                $values = $form->getValues();

                foreach($data_map as $field => $param) {
                    $session->config[$param] = $values[$field];
                    
                    $this->view->$field = $session->config[$param];
                }
                
                // Test the connection
                try {
                    $db_conn = Zend_Db::factory('Pdo_Mysql', array(
                        'username' => $session->config['db.params.username'],
                        'password' => $session->config['db.params.password'],
                        'dbname' => $session->config['db.params.dbname'],
                        'host' => $session->config['db.params.host'],
                        'port' => (empty($session->config['db.params.port']) ? '3306' : $session->config['db.params.port'] ),
                        'charset' => 'utf8'
                    ));
                    
                    if (is_null($db_conn->getConnection())) {
                        $session->last_step = 'step1';
                        $this->view->error = $this->translate('Connection failed.');
                    } else {
                        $session->last_step = 'step2';
                        $this->view->message = $this->translate('<strong>Important!</strong> If you are upgrading, before continuing to prevent data loss, please <em>backup your database</em>.');
                    }
                }
                catch(Exception $e) {
                    $session->last_step = 'step1';
                    
                    $this->view->error = $e->getMessage();
                    echo $this->view->render('step2b.phtml');
                }
                
                $this->render_the_script = FALSE;
                echo $this->view->render('step2b.phtml');

            } else {
                $this->view->errors = $form->getMessages();
                $values = $form->getValues();
                
                foreach($values as $vk => $vv) {
                    if (array_key_exists($vk, $this->view->errors)) {
                        $this->view->$vk = '';
                    } else {
                        $this->view->$vk = $vv;
                    }
                }
                foreach($data_map as $field => $param) {
                    $session->config[$param] = $this->view->$field;
                }
            }
            
        } else {

            foreach($data_map as $field => $param) {
                $this->view->$field = $session->config[$param];
            }

        }
        
        
    }

    /**
     * Cluster configuration.
     * 
     * @throws Zend_Form_Exception
     */
    public function step3Action() {

        /**
         * NOTE: remember that we need to translate 
         * the 'frm_cluster_ssl' param
         * from the string 'y' to a boolean
         */
        
        if (!$this->sessionIsValid('step2')) {
            $this->redirect($this->view->ServerUrl() . '/install.php?step=step1');
        }

        $this->view->headTitle($this->translate('Step #3'));

        $session = new Zend_Session_Namespace();
        $session->last_step = 'step2';

        $data_map = array(
            'frm_cluster' => 'cluster',
            'frm_cluster_ssl' => 'cluster_ssl',
            'frm_cluster_port' => 'cluster_port',
            'frm_cluster_ip' => 'cluster_ip',
            'frm_admin_key' => 'admin_key',
            'frm_allow_password_recovery' => 'password_recovery',

        );

        // Always populate from session, then update from request
        $this->view->frm_sx_cluster = $session->config['cluster'];

        $form = new Zend_Form();
        $form->addElement( 'text', 'frm_cluster', array(
            'validators' => array( 
                new Zend_Validate_StringLength(array('min' => 1, 'max' => 255)),
                new Zend_Validate_Regex('/^sx:\/\/[a-zA-Z0-9]+/')
            ),
            'filters' => array(
                'StringTrim'
            ),
            'required' => TRUE
        ));

        $form->addElement( 'checkbox', 'frm_allow_password_recovery', array(
            'checkedValue' => 'y',
            'uncheckedValue' => 'n',
            'required' => TRUE
        ));
        
        $form->addElement( 'text', 'frm_admin_key', array(
            'validators' => array(
                new My_ValidateUserKey()
            ),
            'filters' => array(
                'StringTrim'
            ),
            'required' => TRUE
        ));

        $form->addElement( 'checkbox', 'frm_cluster_ssl', array(
            'checkedValue' => 'y',
            'uncheckedValue' => 'n',
            'required' => TRUE
        ));

        $form->addElement( 'text', 'frm_cluster_ip', array(
            'validators' => array( new Zend_Validate_Ip() ),
            'filters' => array( 'StringTrim' ),
            'required' => FALSE
        ));

        $form->addElement( 'text', 'frm_cluster_port', array(
            'validators' => array( new Zend_Validate_Between(array(
                'min' => 1,
                'max' => 65535,
                'inclusive' => TRUE
            )) ),
            'filters' => array( 'StringTrim' ),
            'required' => FALSE
        ));

        if ($this->getRequest()->isPost()) {

            if ($form->isValid($this->getRequest()->getParams())) {
                $values = $form->getValues();

                foreach($data_map as $field => $param) {
                    if ($field == 'frm_cluster_ssl' || $field == 'frm_allow_password_recovery') {
                        $session->config[$param] = $this->view->$field = ($values[$field] == 'y');
                    } else {
                        $this->view->$field = $values[$field];
                        $session->config[$param] = $values[$field];    
                    }
                }
                
                // If you allow password recovery, you must supply a valid admin key
                $can_proceed = TRUE;
                if ($values['frm_allow_password_recovery'] == 'y') {
                    if (strlen($values['frm_admin_key']) == 0) {
                        $form->addErrorMessage($this->translate('You must supply an admin key'));
                        $form->getElement('frm_admin_key')->addErrorMessage( $this->translate('You must supply an admin key') );
                        $this->view->errors = $form->getMessages();
                        $can_proceed = FALSE;
                    }
                }

                if ($can_proceed) {
                    $session->last_step = 'step3';

                    $this->redirect($this->view->ServerUrl() . '/install.php?step=step4');    
                } 

            } else {

                $this->view->errors = $form->getMessages();
                $values = $form->getValues();

                foreach($values as $vk => $vv) {
                    if (array_key_exists($vk, $this->view->errors)) {
                        $this->view->$vk = '';
                    } else {
                        $this->view->$vk = $vv;
                    }
                    
                    if ($vk == 'frm_cluster_ssl' || $vk == 'frm_allow_password_recovery') {
                        $this->view->$vk = ($this->view->$vk == 'y');
                    }
                }
                foreach($data_map as $field => $param) {
                    $session->config[$param] = $this->view->$field;
                }
            }

        } else {
            foreach($data_map as $field => $param) {
               $this->view->$field = $session->config[$param];    
            }
        }
    }

    /**
     * Action for unknown actions
     */
    public function noneAction() {

    }
    
    public function step4Action() {
        
        if (!$this->sessionIsValid('step3')) {
            $this->redirect($this->view->ServerUrl() . '/install.php?step=step1');
        }

        $this->view->headTitle($this->translate('Step #4'));
        
        $session = new Zend_Session_Namespace();

        $data_map = array(
            'frm_mail_type' => 'mail.transport.type',
            'frm_mail_smtp_host' => 'mail.transport.host',
            'frm_mail_sender_host' => 'mail.transport.name',

            'frm_mail_ssl' => 'mail.transport.ssl',
            'frm_mail_port' => 'mail.transport.port',
                
            'frm_mail_auth' => 'mail.transport.auth',
            'frm_mail_username' => 'mail.transport.username',
            'frm_mail_password' => 'mail.transport.password',
                
            'frm_default_from_mail' => 'mail.defaultFrom.email',
            'frm_default_from_name' => 'mail.defaultFrom.name',
            'frm_default_replyto_mail' => 'mail.defaultReplyTo.email',
            'frm_default_replyto_name' => 'mail.defaultReplyTo.name'
        );

        $form = new Zend_Form();
        $form->addElement( 'text', 'frm_mail_type', array(
            'validators' => array(
                new Zend_Validate_InArray( array('smtp', 'sendmail') )
            ),
            'filters' => array(
                'StringTrim'
            ),
            'required' => TRUE
        ));
        
        $form->addElement( 'text', 'frm_mail_smtp_host', array(
            'validators' => array( new Zend_Validate_StringLength(array('min' => 1, 'max' => 255)) ),
            'filters' => array( 'StringTrim' ),
            'required' => TRUE
        ));

        $form->addElement( 'text', 'frm_mail_sender_host', array(
            'validators' => array( new Zend_Validate_StringLength(array('min' => 0, 'max' => 255)) ),
            'filters' => array( 'StringTrim' ),
            'required' => FALSE
        ));

        $form->addElement( 'text', 'frm_mail_ssl', array(
            'validators' => array( new Zend_Validate_InArray( array( 'ssl', 'tls') ) ),
            'filters' => array( 'StringTrim' ),
            'required' => FALSE
        ));

        $form->addElement( 'text', 'frm_mail_port', array(
            'validators' => array( 'validators' => array( new Zend_Validate_Between( array( 'min' => 1, 'max' => 65535, 'inclusive' => TRUE ) ) ) ),
            'filters' => array( 'StringTrim' ),
            'required' => FALSE
        ));

        $form->addElement( 'text', 'frm_mail_auth', array(
            'validators' => array( new Zend_Validate_InArray( array('none', 'plain', 'login', 'crammd5' ) ) ),
            'filters' => array( 'StringTrim' ),
            'required' => TRUE
        ));

        $form->addElement( 'text', 'frm_mail_username', array(
            'validators' => array( new Zend_Validate_StringLength(array('min' => 0, 'max' => 255)) ),
            'filters' => array( 'StringTrim' ),
            'required' => FALSE
        ));

        $form->addElement( 'text', 'frm_mail_password', array(
            'validators' => array( new Zend_Validate_StringLength(array('min' => 0, 'max' => 255)) ),
            'filters' => array( 'StringTrim' ),
            'required' => FALSE
        ));

        $form->addElement( 'text', 'frm_default_from_mail', array(
            'validators' => array( new Zend_Validate_EmailAddress() ),
            'filters' => array( 'StringTrim' ),
            'required' => TRUE
        ));
        $form->addElement( 'text', 'frm_default_from_name', array(
            'validators' => array( new Zend_Validate_StringLength(array('min' => 1, 'max' => 255)) ),
            'filters' => array( 'StringTrim' ),
            'required' => TRUE
        ));
        
        $form->addElement( 'text', 'frm_default_replyto_mail', array(
            'validators' => array( new Zend_Validate_EmailAddress() ),
            'filters' => array( 'StringTrim' ),
            'required' => FALSE
        ));
        $form->addElement( 'text', 'frm_default_replyto_name', array(
            'validators' => array( new Zend_Validate_StringLength(array('min' => 0, 'max' => 255)) ),
            'filters' => array( 'StringTrim' ),
            'required' => FALSE
        ));


        if ($this->getRequest()->isPost()) {

            if ($form->isValid($this->getRequest()->getParams())) {
                $values = $form->getValues();

                foreach($data_map as $field => $param) {
                    $this->view->$field = $values[$field];

                    $session->config[$param] = $values[$field];
                }

                $session->last_step = 'step4';
                $this->redirect($this->view->ServerUrl() . '/install.php?step=step5');
                
            } else {
                
                $this->view->errors = $form->getMessages();
                $values = $form->getValues();

                foreach($values as $vk => $vv) {
                    if (array_key_exists($vk, $this->view->errors)) {
                        $this->view->$vk = '';
                    } else {
                        $this->view->$vk = $vv;
                    }
                }
                foreach($data_map as $field => $param) {
                    $session->config[$param] = $this->view->$field;
                }
            }

        } else {
            foreach($data_map as $field => $param) {
                $this->view->$field = $session->config[$param];
            }
        }
    }

    /**
     * The final step, generates the skylable.ini file
     */
    public function step5Action() {
        
        if (!$this->sessionIsValid('step4')) {
            $this->redirect($this->view->ServerUrl() . '/install.php?step=step1');
        }

        $this->view->headTitle($this->translate('Step #5'));
        
        $session = new Zend_Session_Namespace();
        
        // Prepare the ini string
        $skylable_ini = '; This file holds all the application configuration'. PHP_EOL;
        $skylable_ini .= '; related to Skylable services interaction'. PHP_EOL;
        $skylable_ini .= PHP_EOL;
        $skylable_ini .= '[ production ]'.PHP_EOL;
        $skylable_ini .= PHP_EOL;
        $skylable_ini .= '; The SX Cluster URL: sx://clustername.com'.PHP_EOL;
        $skylable_ini .= 'cluster = "' . $session->config['cluster'] . '"' . PHP_EOL;
        $skylable_ini .= PHP_EOL;
        $skylable_ini .= 'cluster_ssl = ' . ($session->config['cluster_ssl'] ? 'true' : 'false' ) . PHP_EOL;
        $skylable_ini .= 'cluster_port = "' . $session->config['cluster_port'] . '"' . PHP_EOL;
        $skylable_ini .= 'cluster_ip = "' . $session->config['cluster_ip'] . '"' . PHP_EOL;
        
        $skylable_ini .= PHP_EOL;
        $skylable_ini .= '; Base URL used to generate other URLs, ie the shared file URL'.PHP_EOL;
        $skylable_ini .= 'url = "' . $session->config['url'] . '"' . PHP_EOL;

        $skylable_ini .= PHP_EOL;
        $skylable_ini .= '; Main directory' . PHP_EOL; 
        $skylable_ini .= 'local = ' . $session->config['local'] . PHP_EOL;

        $skylable_ini .= PHP_EOL;
        $skylable_ini .= '; Upload directory' . PHP_EOL;
        $skylable_ini .= 'upload_dir = ' . $session->config['upload_dir'] . PHP_EOL;

        $skylable_ini .= PHP_EOL;
        $skylable_ini .= '; local directory where you will store your sx keys' . PHP_EOL;
        $skylable_ini .= 'sx_local = ' . $session->config['sx_local'] . PHP_EOL;

        $skylable_ini .= PHP_EOL;
        $skylable_ini .= '; Download limits' . PHP_EOL;
        $skylable_ini .= ';  maximum concurrent downloads per logged user' . PHP_EOL;
        $skylable_ini .= 'downloads = ' . $session->config['downloads'] . PHP_EOL;

        $skylable_ini .= PHP_EOL;
        $skylable_ini .= '; maximum concurrent downloads per IP address (used for shared files)' . PHP_EOL;
        $skylable_ini .= 'downloads_ip = ' . $session->config['downloads_ip'] . PHP_EOL;
        $skylable_ini .= PHP_EOL;
        $skylable_ini .= '; time window in seconds per single user' . PHP_EOL;
        $skylable_ini .= 'downloads_time_window = ' . $session->config['downloads_time_window'] . PHP_EOL;
        $skylable_ini .= PHP_EOL;
        $skylable_ini .= '; time window in seconds per IP address' . PHP_EOL;
        $skylable_ini .= 'downloads_time_window_ip = ' . $session->config['downloads_time_window_ip'] . PHP_EOL;
        $skylable_ini .= PHP_EOL;
        $skylable_ini .= '; Update upload_max_filesize, memory_limit, post_max_size, max_execution_time accordingly' . PHP_EOL;
        $skylable_ini .= 'max_upload_filesize = ' . $session->config['max_upload_filesize'] . PHP_EOL;

        $skylable_ini .= PHP_EOL;
        $skylable_ini .= '; shared file expire time in seconds' . PHP_EOL;
        $skylable_ini .= '; default 1 week = 60*60*24*7' . PHP_EOL;
        $skylable_ini .= 'shared_file_expire_time = ' . $session->config['shared_file_expire_time'] . PHP_EOL;
        
        $skylable_ini .= PHP_EOL;
        $skylable_ini .= '; how long cookie is valid?' . PHP_EOL;
        $skylable_ini .= '; 3600*24*15 - 1296000 - 15 days' . PHP_EOL;
        $skylable_ini .= 'remember_me_cookie_seconds = ' . $session->config['remember_me_cookie_seconds'] . PHP_EOL;

        $skylable_ini .= PHP_EOL;
        $skylable_ini .= '; The cookie domain' . PHP_EOL;
        $skylable_ini .= '; remember to put a dot where needed' . PHP_EOL;
        $skylable_ini .= '; ie: is ".example.com" and not "example.com"' . PHP_EOL;
        $skylable_ini .= 'cookie_domain = "' . $session->config['cookie_domain'] . '"' . PHP_EOL;

        $skylable_ini .= PHP_EOL;
        $skylable_ini .= '; Elastic search hosts' . PHP_EOL;
        foreach($session->config['elastic_hosts'] as $host) {
            $skylable_ini .= 'elastic_hosts[] = "' . $host . '"' . PHP_EOL;    
        }

        $skylable_ini .= PHP_EOL;
        $skylable_ini .= '; URL to use to contact the tech support' . PHP_EOL;
        $skylable_ini .= 'tech_support_url = "' . $session->config['tech_support_url'] . '"' . PHP_EOL;
        
        $skylable_ini .= PHP_EOL;
        $skylable_ini .= '; Flag to enable or disable password recovery: if enabled, you need also to specify the admin key' . PHP_EOL;
        $skylable_ini .= 'password_recovery = ' . ($session->config['password_recovery'] ? 'true' : 'false') . PHP_EOL;
        $skylable_ini .= '; The admin key of your cluster' . PHP_EOL;
        $skylable_ini .= (strlen($session->config['admin_key']) > 0 ? '' : '; ').'admin_key = "' . $session->config['admin_key'] . '"' . PHP_EOL;
        

        $skylable_ini .= PHP_EOL;
        $skylable_ini .= '; DB configuration' . PHP_EOL;
        $skylable_ini .= 'db.adapter = "pdo_mysql"' . PHP_EOL;
        $skylable_ini .= 'db.params.host = "' . $session->config['db.params.host'] . '"' . PHP_EOL;
        if (!empty($session->config['db.params.port'])) {
            $skylable_ini .= 'db.params.port = "' . $session->config['db.params.port'] . '"' . PHP_EOL;    
        }
        $skylable_ini .= 'db.params.username = "' . $session->config['db.params.username'] . '"' . PHP_EOL;
        $skylable_ini .= 'db.params.password = "' . $session->config['db.params.password'] . '"' . PHP_EOL;
        $skylable_ini .= 'db.params.dbname = "' . $session->config['db.params.dbname'] . '"' . PHP_EOL;
        $skylable_ini .= 'db.params.charset = "utf8" ' . PHP_EOL;

        $skylable_ini .= PHP_EOL;
        $skylable_ini .= '; Email transport configuration' . PHP_EOL;
        
        $skylable_ini .= 'mail.transport.type = "' . $session->config['mail.transport.type'] . '"' . PHP_EOL;
        $skylable_ini .= 'mail.transport.name = "' . $session->config['mail.transport.name'] . '"' . PHP_EOL;
        $skylable_ini .= 'mail.transport.host = "' . $session->config['mail.transport.host'] . '"' . PHP_EOL;

        if (empty($session->config['mail.transport.ssl']) || $session->config['mail.transport.ssl'] == 'none') {
            $skylable_ini .= '; mail.transport.ssl = ""' . PHP_EOL;
        } else {
            $skylable_ini .= 'mail.transport.ssl = "' . $session->config['mail.transport.ssl'] . '"' . PHP_EOL;
        }

        if (empty($session->config['mail.transport.port'])) {
            $skylable_ini .= '; mail.transport.port = ""' . PHP_EOL;
        } else {
            $skylable_ini .= 'mail.transport.port = ' . $session->config['mail.transport.port'] . PHP_EOL;
        }
        
        if (empty($session->config['mail.transport.auth']) || $session->config['mail.transport.auth'] == 'none') {
            $skylable_ini .= '; mail.transport.auth = ""' . PHP_EOL;
            $skylable_ini .= '; mail.transport.username = ""' . PHP_EOL;
            $skylable_ini .= '; mail.transport.password = ""' . PHP_EOL;
        } else {
            $skylable_ini .= 'mail.transport.auth = "' . $session->config['mail.transport.auth'] . '"' . PHP_EOL;
            $skylable_ini .= 'mail.transport.username = "' . $session->config['mail.transport.username'] . '"' . PHP_EOL;
            $skylable_ini .= 'mail.transport.password = "' . $session->config['mail.transport.password'] . '"' . PHP_EOL;
        }
            
        $skylable_ini .= '; Must be true' . PHP_EOL;
        $skylable_ini .= 'mail.transport.register = true' . PHP_EOL;
        $skylable_ini .= 'mail.defaultFrom.email = "' . $session->config['mail.defaultFrom.email'] . '"' . PHP_EOL;
        $skylable_ini .= 'mail.defaultFrom.name = "' . $session->config['mail.defaultFrom.name'] . '"' . PHP_EOL;
        
        if (empty($session->config['mail.defaultReplyTo.email'])) {
            $skylable_ini .= '; mail.defaultReplyTo.email = ""' . PHP_EOL;
            $skylable_ini .= '; mail.defaultReplyTo.name = ""' . PHP_EOL;
        } else {
            $skylable_ini .= 'mail.defaultReplyTo.email = "' . $session->config['mail.defaultReplyTo.email'] . '"' . PHP_EOL;
            $skylable_ini .= 'mail.defaultReplyTo.name = "' . $session->config['mail.defaultReplyTo.name'] . '"' . PHP_EOL;
        }

        $skylable_ini .= PHP_EOL;
        $skylable_ini .= '[development : production]' . PHP_EOL;
        $skylable_ini .= PHP_EOL;

        $this->view->skylable_ini = $skylable_ini;
         
        // Check if the file exists
        $skylable_ini_path = APP_CONFIG_BASE_PATH . 'skylable.ini';

        $this->view->skylable_ini_path = APP_CONFIG_BASE_PATH;
        
        if (@file_exists($skylable_ini_path)) {
            $this->view->write_success = FALSE;
            $this->view->reason = $this->translate('File already exists.');
            return FALSE;
        }
   
        if (!@is_writable(APP_CONFIG_BASE_PATH)) {
            $this->view->write_success = FALSE;
            $this->view->reason = $this->translate('Destination directory is not writable.');
            return FALSE;
        }
        
        if (@file_put_contents($skylable_ini_path, $skylable_ini) === FALSE ) {
            $this->view->write_success = FALSE;
            $this->view->reason = $this->translate('Failed to write the file.');
        } else {
            $this->view->write_success = TRUE;
        }
    }
 
    /**
     * Clears the session vars
     */
    public function deleteSession() {
        $session = new Zend_Session_Namespace();
        $session->unsetAll();
    }

    /**
     * Tells if the session is valid.
     * @return bool
     */
    public function sessionIsValid($step = NULL) {
        $session = new Zend_Session_Namespace();
        if (isset($session->last_step) && isset($session->config)) {
            if (is_null($step)) {
                return TRUE;
            } else {
                return ($session->last_step == $step);
            }
        }    
        return FALSE;
    }
    
}