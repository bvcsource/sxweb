<?php

// The application version
if (!defined('SXWEB_VERSION')) define('SXWEB_VERSION', '0.3.1');

// URL for version check
// The string SECS_SINCE_LAST_CHECK is replaced with the current timestamp
if (!defined('SXWEB_VERSION_CHECK_URL')) define('SXWEB_VERSION_CHECK_URL', 'http://cdn.skylable.com/check/sxweb-version?cur=' . urlencode(SXWEB_VERSION) . '&ts=SECS_SINCE_LAST_CHECK');

// URL for downloading the upgraded version
if (!defined('SXWEB_UPGRADE_URL'))  define('SXWEB_UPGRADE_URL', 'http://www.skylable.com/download/sxweb/#upgrade');

// Include the special PHP config if present
if (@file_exists('./config.inc.php')) {
    require_once realpath('./config.inc.php');
}

