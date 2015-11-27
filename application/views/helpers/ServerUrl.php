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
 * Custom ServerUrl implementation.
 * 
 * If you have defined the 'url' configuration parameter into the 
 * skylable.ini file, it will be used to generate all of the site URLs.
 * Otherwise the {@link MyUtils::serverUrl} method (which is a rip off of the Zend 
 * Framework ServerUrl View helper) will be used.
 * 
 * @see MyUtils::serverUrl
 */
class Zend_View_Helper_ServerUrl extends Zend_View_Helper_Abstract
{   
    private static $url = NULL;
    protected
        $_host = '',
        $_scheme = '';
        
    
    public function serverUrl($requestUri = NULL) {
        if (is_null(self::$url)) {
            self::$url = (Zend_Registry::isRegistered('skylable') ? Zend_Registry::get('skylable')->get('url', '') : '' );
        }

        if (strlen(self::$url) > 0) {
            return (is_null($requestUri) ? self::$url : self::$url.$requestUri );
        } 
        return My_Utils::serverUrl($requestUri);
    }
    
    /*
     * Methods copied from Zend_View_Helper_ServerUrl for compatibility.
     * Can't extend original class because of view helpers paths.
     */
    public function __construct()
    {
        switch (true) {
            case (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] === true)):
            case (isset($_SERVER['HTTP_SCHEME']) && ($_SERVER['HTTP_SCHEME'] == 'https')):
            case (isset($_SERVER['SERVER_PORT']) && ($_SERVER['SERVER_PORT'] == 443)):
                $scheme = 'https';
                break;
            default:
                $scheme = 'http';
        }
        $this->setScheme($scheme);

        if (isset($_SERVER['HTTP_HOST']) && !empty($_SERVER['HTTP_HOST'])) {
            $this->setHost($_SERVER['HTTP_HOST']);
        } else if (isset($_SERVER['SERVER_NAME'], $_SERVER['SERVER_PORT'])) {
            $name = $_SERVER['SERVER_NAME'];
            $port = $_SERVER['SERVER_PORT'];

            if (($scheme == 'http' && $port == 80) ||
                ($scheme == 'https' && $port == 443)) {
                $this->setHost($name);
            } else {
                $this->setHost($name . ':' . $port);
            }
        }
    }

    /**
     * Returns host
     *
     * @return string  host
     */
    public function getHost()
    {
        return $this->_host;
    }

    /**
     * Sets host
     *
     * @param  string $host                new host
     * @return Zend_View_Helper_ServerUrl  fluent interface, returns self
     */
    public function setHost($host)
    {
        $this->_host = $host;
        return $this;
    }

    /**
     * Returns scheme (typically http or https)
     *
     * @return string  scheme (typically http or https)
     */
    public function getScheme()
    {
        return $this->_scheme;
    }

    /**
     * Sets scheme (typically http or https)
     *
     * @param  string $scheme              new scheme (typically http or https)
     * @return Zend_View_Helper_ServerUrl  fluent interface, returns self
     */
    public function setScheme($scheme)
    {
        $this->_scheme = $scheme;
        return $this;
    }

}