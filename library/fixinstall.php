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

require_once realpath('./defines.inc.php');
    
function serverUrl($requestUri = NULL) {

    // Taken from the serverUrl Zend Framework view helper
    switch (true) {
        case (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] === true)):
        case (isset($_SERVER['HTTP_SCHEME']) && ($_SERVER['HTTP_SCHEME'] == 'https')):
        case (isset($_SERVER['SERVER_PORT']) && ($_SERVER['SERVER_PORT'] == 443)):
            $scheme = 'https';
            break;
        default:
            $scheme = 'http';
    }

    if (isset($_SERVER['HTTP_HOST']) && !empty($_SERVER['HTTP_HOST'])) {
        $host = $_SERVER['HTTP_HOST'];
    } else if (isset($_SERVER['SERVER_NAME'], $_SERVER['SERVER_PORT'])) {
        $name = $_SERVER['SERVER_NAME'];
        $port = $_SERVER['SERVER_PORT'];

        if (($scheme == 'http' && $port == 80) ||
            ($scheme == 'https' && $port == 443)) {
            $host = $name;
        } else {
            $host = $name . ':' . $port;
        }
    }

    if ($requestUri === true) {
        $path = $_SERVER['REQUEST_URI'];
    } else if (is_string($requestUri)) {
        $path = $requestUri;
    } else {
        $path = '';
    }
    
    return $scheme . '://'.$host.$path;
}
  
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title>SXWeb Fatal Error!</title>

    <!-- Bootstrap -->
    <link href="<?php echo serverUrl(); ?>/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo serverUrl(); ?>/css/bootstrap-theme.min.css" rel="stylesheet">
    <link href="<?php echo serverUrl(); ?>/css/installer.css" rel="stylesheet">

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->

    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="<?php echo serverUrl(); ?>/js/jquery.min.js"></script>
    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <script src="<?php echo serverUrl(); ?>/js/bootstrap.min.js"></script>
</head>
<body>

<div class="container">
    <h1 class="text-hide text-center"><a href="http://www.skylable.com/sxweb" tabindex="-1"><img src="<?php echo serverUrl(); ?>/img/logo.png">Skylable SXWeb</a></h1>
    <div class="jumbotron">
        <h1>Security risk alert!</h1>
        <p>There is a problem with your SXWeb installation!</p>
    </div>
    <p>Before continuing, please delete, rename or make unreadable by the web server the file <code>install.php</code>.</p>
    <hr>
    <p class="copyright text-center">Copyright &copy; 2012-2015 Skylable Ltd. All Rights Reserved. Version: <?php echo SXWEB_VERSION; ?></p>
</div>

</body>
</html>