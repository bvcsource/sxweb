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
    
    public
        /**
         * Flag to tell our custom front controller if we want to
         * render the current action using the default renderer 
         * @var bool
         */
        $render_the_script = TRUE;
    
    public function getBaseConfig() {
        
        $app_path = 'APPLICATION_PATH ';
        
        $cfg = array(
            //sx cluster url sx://...
            'cluster' => "sx://cluster.example.com",

            // Base URL used to generate other URLS, ie the shared file URL
            'url' => "https://sxweb.example.com",
            
            // Main directory 
            'local' => $app_path . '/../',

            // Upload directory
            'upload_dir' => $app_path . '/../data/files',

                // local directory where you will store your sx keys
            'sx_local' => $app_path . "/../data/sx",
            
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
            
            // DB configuration
            'db.adapter' => "pdo_mysql",
            'db.params.host' => "localhost",
            'db.params.port' => 3306,
            'db.params.username' => "",
            'db.params.password' => "",
            'db.params.dbname' => "sxweb",
            'db.params.charset' => "utf8",
            'db.isDefaultTableAdapter' => true,

            // Email
            'mail.transport.type' => "smtp",
            'mail.transport.name' => "example.com",
            'mail.transport.host' => "localhost",
            'mail.transport.auth' => 'none',
            'mail.transport.username' => '',
            'mail.transport.password' => '',
            'mail.transport.register' => true, 
    
            'mail.defaultFrom.email' => "noreply@example.com",
            'mail.defaultFrom.name' => "SXWeb",
            'mail.defaultReplyTo.email' => '',
            'mail.defaultReplyTo.name' => ''
        );
        
        return $cfg;
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
        $this->view->headTitle('Welcome!');
        
        // Prepares the session
        $session = new Zend_Session_Namespace();
        $session->config = $this->getBaseConfig();
        $session->last_step = 'index';

        /*
         * TODO:
         * controlla se sia presente il file di configurazione skylable.ini
         * - SI: puoi solo fare l'upgrade
         * - NO: procedi con l'installazione (mostra un messaggio)
         * 
         * upgrade:
         * verifica la connessione al DB
         * - NO: non puoi fare nulla
         * - SI: verifica la versione del DB
         * -- Se la versione è minore di quella attuale allora occorre fare l'upgrade
         */
    }

    /**
     * Checks PHP requirements.
     */
    public function step1Action() {

        if (!$this->sessionIsValid('index')) {
            $this->redirect( $this->view->ServerUrl() . '/install.php' );
        }
        
        $session = new Zend_Session_Namespace();
        
        $this->view->php_components = array();
        
        // PHP version
        $this->view->can_proceed = version_compare(PHP_VERSION, '5.3.9', '>=');
        $this->view->php_components[] = array('PHP', 'At least 5.3.9', (version_compare(PHP_VERSION, '5.3.9', '>=') ? 'Found' : 'Upgrade needed' ) );

        // PHP extensions
        $ext = get_loaded_extensions();

        $needed = array(
            'date' => 'Date extension',
            'PDO' => 'PDO extension',
            'pdo_mysql' => 'PDO MySql extension',
            'openssl' => 'OpenSSL extension',
            'curl' => 'cUrl extension',
            'SPL' => 'PHP SPL extension library',
            'json' => 'JSON extension',
            'session' => 'Session extension'
        );
        foreach($needed as $need_ext => $label) {
            $found = in_array($need_ext, $ext); 
            if (!$found) {
                $this->view->can_proceed = FALSE;
            }
            $this->view->php_components[] = array($label, 'Yes', ($found ? 'Found' : 'Not found' ) );
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
            'sxinit','sxcp','sxrm','sxmv','sxacl'
        );
        $this->view->sx_commands = array();
        foreach($sx_cmd as $cmd) {
            $str = exec('which '.$cmd, $output, $ret_val);
            if (empty($output)) {
                $this->view->can_proceed = FALSE;
                $this->view->sx_commands[] = array( $cmd, '', 'Not found' );
            } else {
                $this->view->sx_commands[] = array( $cmd, implode('', $output), 'Found' );
                $output = '';
            }
        }
        
        // Check the data dir
        if (!@file_exists(APPLICATION_DATA_PATH)) {
            
            if (!@mkdir(APPLICATION_DATA_PATH, 0775)) {
                $this->view->can_proceed = FALSE;
                $this->view->data_path_problem = $this->view->translate('Can&apos;t create the directory.');
            }

        } else {
            if (@is_dir(APPLICATION_DATA_PATH)) {
                if (!@is_writable(APPLICATION_DATA_PATH)) {
                    $this->view->can_proceed = FALSE;
                    $this->view->data_path_problem = $this->view->translate('Data path is not writable.');
                }
            } else {
                $this->view->can_proceed = FALSE;
                $this->view->data_path_problem = $this->view->translate('Data path is not a directory.');
            }
        }
        
        if ($this->view->can_proceed) {
            $session->last_step = 'step1';   
        } else {
            $session->last_step = FALSE;
        }
    }

    public function step2Action() {
        
        if (!$this->sessionIsValid('step1')) {
            $this->redirect($this->view->ServerUrl() . '/install.php?step=step1');
        }

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
                        $this->render_the_script = FALSE;
                        $this->view->error = $this->view->translate('Connection failed.');
                        echo $this->view->render('step2b.phtml');
                    } else {
                        $session->last_step = 'step2';
                        $this->render_the_script = FALSE;
                        
                        // If already populated skip creation
                        $tables = $db_conn->listTables();
                        if (empty($tables)) {
                            $sql = @file_get_contents(INSTALLER_SQL_PATH . '/sxweb.sql');
                            if ($sql !== FALSE) {
                                try {
                                    $db_conn->query($sql);
                                    $this->view->message = $this->view->translate('Successfully created database tables.');
                                }
                                catch(Exception $e) {
                                    $session->last_step = 'step1';
                                    $this->view->error = $e->getMessage();
                                    $this->view->message = $this->view->translate('Database creation failed!');
                                }
                                
                            } else {
                                $this->view->error = $this->view->translate('Failed to load <code>sxweb.sql</code>.');
                            }
                        } else {
                            $this->view->message = $this->view->translate('Database is already populated and is leaved as is.');
                        }
                        
                        echo $this->render('step2b');
                    }
                }
                catch(Exception $e) {
                    $session->last_step = 'step1';
                    $this->render_the_script = FALSE;
                    $this->view->error = $e->getMessage();
                    echo $this->view->render('step2b.phtml');
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
        if (!$this->sessionIsValid('step2')) {
            $this->redirect($this->view->ServerUrl() . '/install.php?step=step1');
        }

        $session = new Zend_Session_Namespace();
        $session->last_step = 'step2';

        // Always populate from session, then update from request
        $this->view->frm_sx_cluster = $session->config['cluster'];

        $form = new Zend_Form();
        $form->addElement( 'text', 'frm_sx_cluster', array(
            'validators' => array( 
                new Zend_Validate_StringLength(array('min' => 1, 'max' => 255)),
                new Zend_Validate_Regex('/^sx:\/\/[a-zA-Z0-9]+/')
            ),
            'filters' => array(
                'StringTrim'
            ),
            'required' => TRUE
        ));


        if ($this->getRequest()->isPost()) {

            if ($form->isValid($this->getRequest()->getParams())) {
                $values = $form->getValues();

                $session->config['cluster'] = $values['frm_sx_cluster'];

                $this->view->frm_sx_cluster = $values['frm_sx_cluster'];
                
                $session->last_step = 'step3';

                $this->redirect($this->view->ServerUrl() . '/install.php?step=step4');
            } else {
                $this->view->frm_sx_cluster = '';
                // $this->view->errors = $form->getMessages();
                $this->view->errors = array( 'frm_sx_cluster' => array( $this->view->translate('Invalid cluster address')));
            }
            
        }
        
        
    }

    public function noneAction() {

    }
    
    public function step4Action() {
        /*
        if (!$this->sessionIsValid('step3')) {
            $this->redirect($this->view->ServerUrl() . '/install.php?step=step1');
        }*/

        $session = new Zend_Session_Namespace();

        $data_map = array(
            
            
            'frm_mail_type' => 'mail.transport.type',
            'frm_mail_smtp_host' => 'mail.transport.host',
            'frm_mail_sender_host' => 'mail.transport.name',
                
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