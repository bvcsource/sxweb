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
            ';mail.transport.auth' => 'login',
            ';mail.transport.username' => 'myUsername',
            ';mail.transport.password' => 'myPassword',
            'mail.transport.register' => true, 
    
            'mail.defaultFrom.email' => "noreply@example.com",
            'mail.defaultFrom.name' => "SXWeb",
            ';mail.defaultReplyTo.email' => 'Jane@example.com',
            ';mail.defaultReplyTo.name' => "Jane Doe"
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
                }
                
                $session->last_step = 'step2';

                $this->redirect($this->view->ServerUrl() . '/install.php?step=step3');
                
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

    public function step3Action() {
        $session = new Zend_Session_Namespace();
        if (!$this->sessionIsValid('step2')) {
            $this->redirect($this->view->ServerUrl() . '/install.php?step=step1');
        }
        
    }

    public function noneAction() {

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