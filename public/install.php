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
|| define('APPLICATION_PATH', realpath(dirname(__FILE__) . '/../installer'));

// Define application environment
defined('APPLICATION_ENV')
|| define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

// Ensure library/ is in include_path
// Note: we add also the Zend Framework path
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(APPLICATION_PATH . '/../library'),
    realpath(APPLICATION_PATH . '/../library/Zend/library'),
    get_include_path(),
)));

// Where all app data will be stored
define('SXWEB_APPLICATION_PATH', realpath(dirname(__FILE__).'/../application'));
define('APPLICATION_DATA_PATH', realpath(dirname(__FILE__).'/..').'/data');
define('INSTALLER_SQL_PATH', realpath(dirname(__FILE__).'/..').'/sql');
define('APP_CONFIG_BASE_PATH', realpath(dirname(__FILE__).'/../application').'/configs/');
define('INSTALLER_SCRIPT_PATH', __FILE__);

// Setup the autoloader
require_once 'Zend/Loader/Autoloader.php';
Zend_Loader_Autoloader::getInstance()->registerNamespace('My');
Zend_Loader_Autoloader::getInstance()->registerNamespace('Skylable');

// Prepare the logger, 'facility' => LOG_DAEMON
$log = new Zend_Log( new Zend_Log_Writer_Syslog(array('application' => 'sxweb_installer')) );
$log->addFilter( new Zend_Log_Filter_Priority( (APPLICATION_ENV == 'development' ? Zend_Log::DEBUG : Zend_Log::WARN) ) );
Zend_Registry::set('Logger', $log);

// Prepare the translator object
$translate = new Zend_Translate(
    array(
        'adapter' => 'array',
        'content' => array('foo' => 'foo'),
        'disableNotices' => TRUE
    )
);
Zend_Registry::set('Zend_Translate', $translate);

// Prepare the front controller
$front = Zend_Controller_Front::getInstance();
$front->setRequest('Zend_Controller_Request_Http');
$front->setResponse('Zend_Controller_Response_Http');

// The 'step' parameters hold the current step. How boring!
$step = $front->getRequest()->getParam('step');
if (is_null($step)) {
    $step = 'index';
}

$action_map = array(
    // step -> controller file, controller class,  action, view
    'index' => array('IndexController.php', 'IndexController', 'index', '/index.phtml'),
    'base' => array('IndexController.php', 'IndexController', 'base', '/base.phtml'),
    'step1' => array('IndexController.php', 'IndexController', 'step1', '/step1.phtml'),
    'step2' => array('IndexController.php', 'IndexController', 'step2', '/step2.phtml'),
    'step3' => array('IndexController.php', 'IndexController', 'step3', '/step3.phtml'),
    'step4' => array('IndexController.php', 'IndexController', 'step4', '/step4.phtml'),
    'step5' => array('IndexController.php', 'IndexController', 'step5', '/step5.phtml'),
    'initdb' => array('IndexController.php', 'IndexController', 'initdb', '/initdb.phtml'),
    'mailtest' => array('IndexController.php', 'IndexController', 'mailtest', '/emailtest.phtml'),
    'none' => array('IndexController.php', 'IndexController', 'none', '/none.phtml'),
);

if (!array_key_exists(strval($step), $action_map)) {
    $step = 'none';
}

$layout = new Zend_Layout(APPLICATION_PATH . '/layouts/scripts/');
$layout->setLayout('layout');
ob_start();

include APPLICATION_PATH . '/controllers/'.$action_map[$step][0];
$front->getRequest()->setActionName( $step );
$front->getRequest()->setDispatched(TRUE);
$the_class = $action_map[$step][1];
$action = new $the_class ($front->getRequest(), $front->getResponse(), array(
    'noViewRenderer' => TRUE
));
$action->view = new Zend_View();
$action->view->setScriptPath( APPLICATION_PATH . '/views/scripts/');
$layout->setView( $action->view );
$action->view->headTitle('Skylable SXWeb Install')->setSeparator(' - ');

$action->dispatch($action_map[$step][2] . 'Action');

if (isset($action->render_the_script)) {
    if ($action->render_the_script) {
        echo $action->view->render( $action->getViewScript() );        
    } else {
        // When the action uses render()
        // the output is appended to the response body
        echo $front->getResponse()->getBody();
        $front->getResponse()->clearBody();
    }
} else {
    echo $action->view->render( $action->getViewScript($step) );
}


$layout->content = ob_get_clean();

$front->getResponse()->appendBody( $layout->render() );

$front->getResponse()->sendResponse();

