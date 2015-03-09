<?php

// The application version
if (!defined('SXWEB_VERSION')) define('SXWEB_VERSION', '0.3.0');

// Include the special PHP config if present
if (@file_exists('./config.inc.php')) {
    require_once realpath('./config.inc.php');
}

