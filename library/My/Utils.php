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
 * Collection of utility functions
 *
 */
class My_Utils {
    
    /**
     * Generates a randomized string of the given length
     * 
     * @param integer $len the generated string length
     * @return string
     */
    public static function rndStr($len = 20) {
		$rnd='';
		for($i=0;$i<$len;$i++) {
			do {
				$byte = openssl_random_pseudo_bytes(1);
				$asc = chr(base_convert(substr(bin2hex($byte),0,2),16,10));
			} while(!ctype_alnum($asc));
			$rnd .= $asc;
		}
		return $rnd;
	}

    /**
     * Skips parts of a path.
     *
     * Works like the -p parameter of the "patch" command.
     *
     * Returns an empty path if can't skip the required paths.
     *
     * @param string $path the path
     * @param int $skip parts to skip
     * @return bool|string FALSE on error, the remaining part of the path
     */
    public static function skipPath($path, $skip) {
        if (strlen($path) == 0) {
            return '';
        }
        if ($skip <= 0) {
            return FALSE;
        }
        $path = self::removeSlashes($path);

        $p = 0;
        do {
            $p = strpos($path, '/', $p);
            $skip--;
        } while ($skip > 0 && $p !== FALSE);

        return $p === FALSE ? '' : substr($path, $p);
    }


    /**
     * Adds beginning and trailing slashes to a path.
     *
     * Don't check if the path has a file part.
     *
     * @param string $path
     * @return string
     */
    public static function slashPath($path) {
        if (strlen($path) == 0) {
            return '/';
        }
        $p1 = strpos($path, '/', 0);
        if ($p1 === FALSE || $p1 != 0) {
            $path = '/' . $path;
        }

        $p1 = strrpos($path, '/');
        if ($p1 === FALSE || $p1 < (strlen($path) - 1)) {
            $path .= '/';
        }

        return $path;
    }

    /**
     * Removes beginning and trailing slashes from a path.
     *
     * Also removes duplicate slashes into the path.
     *
     * @param string $path
     * @param bool $beginning_only TRUE remove only the trailing slash
     * @return string
     */
    public static function removeSlashes($path, $beginning_only = FALSE) {
        if (strlen($path) == 0) {
            return '';
        }
        $path = preg_replace('#/+#', '/', $path);

        $p = strpos($path, '/');
        if ($p !== FALSE) {
            if ($p == 0) {
                $path = substr($path, 1);
                if ($path === FALSE) {
                    return '';
                }
            }
        }

        if ($beginning_only) {
            return $path;
        }

        $p = strrpos($path, '/');
        if ($p !== FALSE) {
            if ($p == strlen($path) - 1) {
                $path = substr($path, 0, strlen($path) - 1);
            }
        }
        return $path;
    }

    /**
     * Removes the trailing slash from a path
     * @param string $path the path
     * @return mixed|string
     */
    public static function removeTrailingSlash($path) {
        if (strlen($path) == 0) {
            return '';
        }
        $path = preg_replace('#/+$#', '/', $path);
        $l = strlen($path);
        if ($l == 1) {
            return '';
        }
        $p = strrpos($path, '/');
        if ($p !== FALSE) {
            if ($p == $l - 1) {
                return substr($path, 0, $l - 1);
            }
        }
        return $path;
    }

    /**
     * Split a path into parts.
     *
     * @param string $path a path
     * @return array|bool an array of strings or FALSE if can't operate
     */
    public static function splitPath($path) {
        if (strlen($path) == 0) {
            return FALSE;
        }
        $path = self::removeSlashes($path);
        if (strlen($path) == 0) {
            return FALSE;
        }
        return  explode('/', $path);
    }

    /**
     * Join some dirs into a complete path.
     *
     * The final path will start and end with a slash.
     *
     * @param array $dirs
     * @return string
     */
    public static function joinDirectories($dirs) {
        $out = '';
        foreach($dirs as $d) {
            $out .= self::slashPath($d);
        }
        return str_replace('//', '/', $out);
    }

    /**
     * Returns the root directory from a path.
     *
     * Given a path in the form:
     * '/foo/'
     * 'foo/bar/baz'
     * '/foo/bar'
     * '/foo'
     * returns always 'foo'
     *
     * @param string $path
     * @return string
     */
    public static function getRootFromPath($path) {
        $path = self::removeSlashes($path);
        if (strlen($path) == 0) {
            return '';
        }

        $p = strpos($path, '/');
        if ($p !== FALSE) {
            return substr($path, 0, $p);
        }
        return $path;
    }

    /**
     * Tells if two paths are the same, ignoring slashes.
     *
     * Given path1 = 'the/path/you/want/to/check' and path2 = '/the/path/you/want/to/check/',
     * this method returns TRUE.
     *
     * @param string $path1 the first path
     * @param string $path2 the second path
     * @return bool TRUE on success, FALSE on failure
     */
    public static function isSamePath($path1, $path2) {
        $path1 = self::removeTrailingSlash( self::slashPath($path1) );
        $path2 = self::removeTrailingSlash( self::slashPath($path2) );
        return (strcmp($path1, $path2) == 0);
    }

    /**
     * FIXME: not the best implementation
     *
     * Creates a temporary directory.
     *
     * @param string $dir destination dir
     * @param string $prefix file prefix
     * @param integer $perm file permissions
     * @return bool|string
     */
    public static function mktempdir($dir, $prefix, $perm = 0755) {
        $file = tempnam($dir, $prefix);
        if ($file !== FALSE) {
            @unlink($file);
            if (@mkdir($file, $perm)) {
                return $file;
            }
        }
        return FALSE;
    }

    /**
     * FIXME: not the best implementation, fails on non Unix platform or on lack of rm command
     * Recursively delete a directory
     * @param string $dirname the path to remove
     * @return bool TRUE on success FALSE on failure
     */
    public static function deleteDir($dirname) {
        if (empty($dirname)) {
            return FALSE;
        }
        exec('rm -rf '.escapeshellarg($dirname), $out, $ret_val);
        return TRUE;
    }


    /**
     * Try to guess the file type from a file name.
     *
     * Don't use a MIME database or direct file inspection, only the name.
     *
     * Returns a string describing the type:
     * 'pdf' - for a PDF file
     * 'source' - for a source code file
     * 'text' -  for a text file
     * 'image' - for a web browser viewable image
     * 'data' - for an unknown or unguessable type
     *
     * @param string $filename the file name
     * @return string
     */
    public static function getFileType($filename) {
        $type = 'data';
        $name = basename(strtolower($filename));
        $p = strrpos($name, '.');
        if($p !== FALSE) {
            $ext = substr($name, $p+1);
            switch($ext) {
                case 'pdf': $type = 'pdf'; break;
                case 'phtml':
                case 'php':
                case 'html':
                case 'xhtml':
                case 'py':
                case 'js':
                case 'c':
                case 'cc':
                case 'c++':
                case 'cpp':
                case 'h':
                case 'hh':
                case 'json':
                case 'bat':
                case 'sh':
                case 'asp':
                case 'xml':
                case 'rb':
                case 'sql': $type = 'source'; break;
                case 'txt' :
                case 'srt':
                case 'md' : $type = 'text'; break;
                case 'png':
                case 'gif':
                case 'jpeg':
                case 'jpg': $type = 'image'; break;
                default:
                    $type = 'data';
            }
        }
        return $type;
    }

    /**
     * Return the file extension (if any).
     *
     * Note: can return an empty string or FALSE.
     *
     * @param string $filename the file path
     * @return string|bool FALSE on error or a string with the extension
     */
    public static function getFileExt($filename) {
        $name = basename(strtolower($filename));
        $p = strrpos($name, '.');
        if($p !== FALSE) {
            return substr($name, $p + 1);
        }
        return '';
    }

    /**
     * Try to guess the MIME type from a file name.
     *
     * Don't use a MIME database or direct file inspection, only the name.
     *
     * If uncertain, returns 'application/octet-stream'
     *
     * @param string $filename the file name
     * @return string the MIME type
     */
    public static function getFileMIME($filename) {
        $mime = 'application/octet-stream';
        $ext = self::getFileExt($filename);
        if (empty($ext)) {
            return $mime;
        }

        switch($ext) {

            case 'ps':
            case 'eps':
            case 'ai':
                $mime = 'application/postscript';
                break;
            case 'aif':
            case 'aifc':
            case 'aiff':
            case 'asf':
            case 'asr':
            case 'asx':
                $mime = 'video/x-ms-asf';
                break;
            case 'au':
                $mime = 'audio/basic';
                break;
            case 'avi':
                $mime = 'video/x-msvideo';
                break;

            case 'bcpio':
                $mime = 'application/x-bcpio';
                break;
            case 'bmp':
                $mime = 'image/bmp';
                break;
            case 'c':
                $mime = 'text/plain';
                break;
            case 'cpio':
                $mime = 'application/x-cpio';
                break;

            case 'csh':
                $mime = 'application/x-csh';
                break;
            case 'css':
                $mime = 'text/css';
                break;
            case 'dxr':
            case 'dcr':
                $mime = 'application/x-director';
                break;
            case 'doc':
            case 'dot':
                $mime = 'application/msword';
                break;
            case 'dvi':
                $mime = 'application/x-dvi';
                break;
            case 'evy':
                $mime = 'application/envoy';
                break;

            case 'gif':
                $mime = 'image/gif';
                break;
            case 'gtar':
                $mime = 'application/x-gtar';
                break;
            case 'gz':
                $mime = 'application/x-gzip';
                break;
            case 'h':
                $mime = 'text/plain';
                break;
            case 'hdf':
                $mime = 'application/x-hdf';
                break;
            case 'hlp':
                $mime = 'application/winhlp';
                break;
            case 'hqx':
                $mime = 'application/mac-binhex40';
                break;
            case 'hta':
                $mime = 'application/hta';
                break;
            case 'htc':
                $mime = 'text/x-component';
                break;
            case 'htm':
            case 'html':
                $mime = 'text/html';
                break;
            case 'htt':
                $mime = 'text/webviewhtml';
                break;
            case 'ico':
                $mime = 'image/x-icon';
                break;
            case 'ief':
                $mime = 'image/ief';
                break;

            case 'jfif':
                $mime = 'image/pipeg';
                break;
            case 'jpe':
            case 'jpeg':
            case 'jpg':
                $mime = 'image/jpeg';
                break;
            case 'js':
                $mime = 'application/x-javascript';
                break;
            case 'latex':
                $mime = 'application/x-latex';
                break;

            case 'lsf':
            case 'lsx':
                $mime = 'video/x-la-asf';
                break;

            case 'man':
                $mime = 'application/x-troff-man';
                break;
            case 'mdb':
                $mime = 'application/x-msaccess';
                break;
            case 'me':
                $mime = 'application/x-troff-me';
                break;
            case 'mht':
                $mime = 'message/rfc822';
                break;
            case 'mhtml':
                $mime = 'message/rfc822';
                break;
            case 'mid':
                $mime = 'audio/mid';
                break;
            case 'mny':
                $mime = 'application/x-msmoney';
                break;
            case 'mov':
                $mime = 'video/quicktime';
                break;
            case 'movie':
                $mime = 'video/x-sgi-movie';
                break;

            case 'mp3':
                $mime = 'audio/mpeg';
                break;
            case 'mp2':
            case 'mpa':
            case 'mpe':
            case 'mpeg':
            case 'mpv2':
            case 'mpg':
                $mime = 'video/mpeg';
                break;

            case 'mpp':
                $mime = 'application/vnd.ms-project';
                break;

            case 'ms':
                $mime = 'application/x-troff-ms';
                break;
            case 'msg':
                $mime = 'application/vnd.ms-outlook';
                break;
            case 'mvb':
                $mime = 'application/x-msmediaview';
                break;
            case 'nc':
                $mime = 'application/x-netcdf';
                break;

            case 'oda':
                $mime = 'application/oda';
                break;

            case 'pbm':
                $mime = 'image/x-portable-bitmap';
                break;
            case 'pdf':
                $mime = 'application/pdf';
                break;
            case 'pgm':
                $mime = 'image/x-portable-graymap';
                break;

            case 'ppm':
                $mime = 'image/x-portable-pixmap';
                break;
            case 'pnm':
                $mime = 'image/x-portable-anymap';
                break;
            case 'pot':
            case 'pps':
            case 'ppt':
                $mime = 'application/vnd.ms-powerpoint';
                break;


            case 'pub':
                $mime = 'application/x-mspublisher';
                break;
            case 'qt':
                $mime = 'video/quicktime';
                break;
            case 'ra':
            case 'ram':
                $mime = 'audio/x-pn-realaudio';
                break;
            case 'ras':
                $mime = 'image/x-cmu-raster';
                break;
            case 'rgb':
                $mime = 'image/x-rgb';
                break;
            case 'rmi':
                $mime = 'audio/mid';
                break;
            case 'roff':
                $mime = 'application/x-troff';
                break;
            case 'rtf':
                $mime = 'application/rtf';
                break;
            case 'rtx':
                $mime = 'text/richtext';
                break;
            case 'scd':
                $mime = 'application/x-msschedule';
                break;
            case 'sct':
                $mime = 'text/scriptlet';
                break;

            case 'sh':
                $mime = 'application/x-sh';
                break;
            case 'shar':
                $mime = 'application/x-shar';
                break;
            case 'sit':
                $mime = 'application/x-stuffit';
                break;
            case 'snd':
                $mime = 'audio/basic';
                break;

            case 'spl':
                $mime = 'application/futuresplash';
                break;
            case 'src':
                $mime = 'application/x-wais-source';
                break;
            case 'stl':
                $mime = 'application/vnd.ms-pkistl';
                break;
            case 'stm':
                $mime = 'text/html';
                break;

            case 'svg':
                $mime = 'image/svg+xml';
                break;
            case 'swf':
                $mime = 'application/x-shockwave-flash';
                break;

            case 'tar':
                $mime = 'application/x-tar';
                break;
            case 'tcl':
                $mime = 'application/x-tcl';
                break;
            case 'tex':
                $mime = 'application/x-tex';
                break;
            case 'texi':
                $mime = 'application/x-texinfo';
                break;
            case 'texinfo':
                $mime = 'application/x-texinfo';
                break;
            case 'tgz':
                $mime = 'application/x-compressed';
                break;
            case 'tif':
            case 'tiff':
                $mime = 'image/tiff';
                break;
            case 'tr':
                $mime = 'application/x-troff';
                break;

            case 'txt':
                $mime = 'text/plain';
                break;
            case 'uls':
                $mime = 'text/iuls';
                break;
            case 'ustar':
                $mime = 'application/x-ustar';
                break;
            case 'vcf':
                $mime = 'text/x-vcard';
                break;
            case 'vrml':
                $mime = 'x-world/x-vrml';
                break;
            case 'wav':
                $mime = 'audio/x-wav';
                break;

            case 'wcm':
            case 'wdb':
            case 'wks':
            case 'wps':
                $mime = 'application/vnd.ms-works';
                break;

            case 'wmf':
                $mime = 'application/x-msmetafile';
                break;

            case 'wri':
                $mime = 'application/x-mswrite';
                break;
            case 'xbm':
                $mime = 'image/x-xbitmap';
                break;
            case 'xla':
            case 'xlc':
            case 'xlm':
            case 'xls':
            case 'xlt':
            case 'xlw':
                $mime = 'application/vnd.ms-excel';
                break;
            case 'xof':
                $mime = 'x-world/x-vrml';
                break;
            case 'xpm':
                $mime = 'image/x-xpixmap';
                break;
            case 'xwd':
                $mime = 'image/x-xwindowdump';
                break;

            case 'zip':
                $mime = 'application/zip';
                break;
        }

        return $mime;
    }

    /**
     * Checks if a string is base64 encoded
     * 
     * @param string $s the base64 string
     * @return bool TRUE on success, FALSE on failure
     */
    public function isBase64Key($s){
        // Check if there are valid base64 characters
        if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $s)) return FALSE;

        // Decode the string in strict mode and check the results
        $decoded = base64_decode($s, TRUE);
        if(FALSE === $decoded) return FALSE;

        // Encode the string again
        if(base64_encode($decoded) != $s) return FALSE;
        if(strlen($s)!=56) return FALSE;

        return true;
    }
}
