<?php

// The application version
if (!defined('SXWEB_VERSION')) define('SXWEB_VERSION', '0.4.2beta');

// URL for version check
// The string SECS_SINCE_LAST_CHECK is replaced with the current timestamp
if (!defined('SXWEB_VERSION_CHECK_URL')) define('SXWEB_VERSION_CHECK_URL', 'http://cdn.skylable.com/check/sxweb-version?cur=' . urlencode(SXWEB_VERSION) . '&ts=SECS_SINCE_LAST_CHECK');

// URL for downloading the upgraded version
if (!defined('SXWEB_UPGRADE_URL'))  define('SXWEB_UPGRADE_URL', 'http://www.skylable.com/download/sxweb/#upgrade');

// URL of the SXWeb FAQ
if (!defined('SXWEB_FAQ_URL'))  define('SXWEB_FAQ_URL', 'http://www.skylable.com/docs/faq/sxweb');

// SEO keywords for SXWeb
if (!defined('SXWEB_SEO_KEYWORDS'))  define('SXWEB_SEO_KEYWORDS', 'sxweb, file sync and share, self hosted, sxdrive web');

// SXWeb SEO description: this will be automatically translated
if (!defined('SXWEB_SEO_DESCRIPTION'))  define('SXWEB_SEO_DESCRIPTION', 'SXWeb is a browser-based solution to access the data stored in your SX cluster through a user-friendly web interface.');

// URL for the SX CLI clients
if (!defined('SXWEB_SX_CLI_CLIENTS_URL')) define('SXWEB_SX_CLI_CLIENTS_URL', 'http://www.skylable.com/download/sx/');

// Try to fix the timezone
$timezone = @date_default_timezone_get();
if (empty($timezone)) {
    @date_default_timezone_set('UTC');
} else {
    @date_default_timezone_set($timezone);
}

/**
 * Check it there is some missing PHP extension needed to run SXWeb.
 *
 * Return FALSE is nothing is missing, otherwise an array with the names
 * of the missing extensions.
 *
 * @return array|bool
 */
function sxweb_get_missing_extensions() {

    $required_extensions = array(
        'Core',
        'date',
        'openssl',
        'pcre',
        // 'zlib',
        // 'bcmath',
        'ctype',
        'dom',
        'hash',
        'SPL',
        'iconv',
        'mbstring',
        'session',
        'posix',
        'standard',
        'SimpleXML',
        'sockets',
        'xml',
        'xmlreader',
        'xmlwriter',
        'PDO',
        'curl',
        'gd',
        'json',
        'mysqli',
        'pdo_mysql'
    );

    $missing = array_diff($required_extensions, get_loaded_extensions());
    if (empty($missing)) {
        return FALSE;
    }
    return $missing;
}

/**
 * Tells if the PHP version is ok to run SXWeb
 * @return bool
 */
function sxweb_php_version_is_ok() {
    return version_compare(PHP_VERSION, '5.3.9', '>=');
}


