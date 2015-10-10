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


// Require base defines
require_once realpath('./defines.inc.php');

if (sxweb_get_missing_extensions() !== FALSE || !sxweb_php_version_is_ok()) {
    require realpath('./misconfigured.php');
    exit();
}

// Include the special PHP config if present
if (@file_exists('./config.inc.php')) {
    require_once realpath('./config.inc.php');
}

// Define path to application directory
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../application'));

// Define application environment
defined('APPLICATION_ENV')
    || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

// Ensure library/ is in include_path
// Note: we add also the Zend Framework path
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(APPLICATION_PATH . '/../library'),
    realpath(APPLICATION_PATH . '/../library/es'),
    realpath(APPLICATION_PATH . '/../library/Zend/library'),
    get_include_path(),
)));

// Are we installing into a Docker container?
if (defined('SXWEB_DOCKER_INST')) {
    if (@file_exists(APPLICATION_PATH . '/configs/skylable_docker.ini')) {
        header('Location: /install.php');
        exit();
    }
}

// Before continuing checks if the application is properly configured
if (!@file_exists(APPLICATION_PATH . '/configs/skylable.ini')) {
    if (@is_readable(APPLICATION_PATH . '/../public/install.php')) {
        header('Location: /install.php');
        exit();    
    } else {
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', TRUE, 500);
        error_log('SXWeb Fatal Error: configuration file and installer missing.');
        die('Mis-configured application, can\'t proceed. Please contact your sysadmin.');        
    }
}

// Check for stale install.php file
if (APPLICATION_ENV !== 'development') {
    if (!defined('SXWEB_DOCKER_INST')) {
        if (@file_exists('./install.php') || @is_readable('./install.php')) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', TRUE, 500);
            include realpath('../library/fixinstall.php');
            exit();
        }    
    }
}

/** Zend_Application */
require_once 'Zend/Application.php';

$error = FALSE;

try {

    // Create application, bootstrap, and run
    $application = new Zend_Application(
        APPLICATION_ENV,
        APPLICATION_PATH . '/configs/application.ini'
    );

    /*
     * Before bootrapping do some sanity checks
     * */

    // Data dir exists and is writable?
    $data_dir = APPLICATION_PATH . '/../data/';
    if (!@is_dir($data_dir)) {
        throw new Zend_Exception('Internal error: directory ' . $data_dir .' don\'t exists.');
    }

    if (!@is_writable($data_dir)) {
        throw new Zend_Exception('Internal error: directory ' . $data_dir .' is not writable.');
    }

    // Checks for resources
    $res = $application->getOption('resources');

    // Initializes the session save path, if needed
    if (isset($res['session']['save_path'])) {
        $path = $res['session']['save_path'];
        if (!empty($path)) {
            if (!@file_exists($path)) {
                if (!@mkdir($path, 0775)) {
                    throw new Zend_Exception('Internal error: failed to create session save path: ' . $path);
                }
            }

            if (!@is_dir($path)) {
                throw new Zend_Exception('Internal error: session save path is not a directory. Path is: ' . $path);
            }
            
            if (!@is_writable($path)) {
                throw new Zend_Exception('Internal error: session save path is not writable. Path is: ' . $path);
            }
        }
    }

    $application->bootstrap();

}
catch (Zend_Application_Exception $e) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', TRUE, 500);
    error_log($e->getMessage());
    die('Fatal error: mis-configured application.');
}
catch(Exception $e) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', TRUE, 500);
    $layout = new Zend_Layout(APPLICATION_PATH . '/layouts/scripts/');
    $layout->setLayout('application-failure');
    $layout->assign('exception', $e);
    $layout->content = '';
    echo $layout->render();

    try {
        // Tries to log...
        $log = $application->getBootstrap()->getResource('Log');
        if (is_object($log)) {
            $log->err($e->getMessage());
        } else {
            error_log($e->getMessage());
        }
    }
    catch(Exception $e) {

    }
    die();
}

$application->run();

