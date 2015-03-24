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
 * FIXME: WORK IN PROGRESS
 * TODO: fix content-type header parsing
 *
 * Access the SX cluster using the REST interface.
 *
 */
class Skylable_AccessSxNG {

    protected
        $_headers,
        $_body,
        $_error,
        $_error_no,
        $_response,
        $_node_list = array(),

        /**
         * Connect using SSL 
         * @var bool
         */
        $_use_ssl = TRUE,

        /**
         * Alternative server port.
         * If NULL use the default one.
         * @var null
         */
        $_port = NULL,
        $_secret_key = NULL,
        $_cluster = NULL;

    const
        // Verbs used for REST calls
        REQUEST_VERB_GET = 'GET',
        REQUEST_VERB_POST = 'POST',
        REQUEST_VERB_DELETE = 'DELETE',
        REQUEST_VERB_PUT = 'PUT';


    /**
     * Initialize the object to use.
     *
     * Mandatory parameters:
     * 'secret_key' - the user secret key (the authorization token)
     * 'cluster' - the cluster on which operate (can be an IP address for non-DNS clusters)
     * 
     * Other parameters:
     * 'port' - the port number of the cluster
     * 'use_ssl' - boolean, TRUE use SSL (the default), FALSE otherwise
     *
     * @param array $options
     */
    public function __construct(array $options = array()) {
        if (array_key_exists('secret_key', $options)) {
            $this->_secret_key = $options['secret_key'];
        }

        if (array_key_exists('cluster', $options)) {
            $this->_cluster = $options['cluster'];
            if (strncmp($this->_cluster, 'sx://', 5) == 0) {
                $this->_cluster = substr($this->_cluster, 5);
            }
        }

        if (array_key_exists('port', $options)) {
            if (is_numeric($options['port'])) {
                $this->_port = $options['port'];
            }
        }

        if (array_key_exists('use_ssl', $options)) {
            $this->_use_ssl = (bool)$options['use_ssl'];
        } else {
            $this->_use_ssl = TRUE;
        }
        
        $this->clear();
    }

    /**
     * Return a base URL to use in cURL calls.
     * 
     * @param string $host the host (IP or domain name) to call
     * @return string
     */
    private function getBaseURL($host) {
        return 'http' . ($this->_use_ssl ? 's' : '') . '://'.$host;
    }

    /**
     * Returns the global logger.
     *
     * @return Zend_Log
     */
    public function getLogger() {
        return Zend_Controller_Front::getInstance()->getParam('bootstrap')->getResource('log');
    }

    /**
     * Breaks the auth token into his parts.
     *
     * IMPORTANT: the user id and user key are returned as strings of hexadecimal bytes (2 char per byte).
     *
     * @param string $token base64 encoded auth token
     * @param string $user_id the resulting user id
     * @param string $user_key the resulting user key
     * @return boolean
     */
    public function processAuthToken($token, &$user_id, &$user_key) {
        $b = base64_decode($token);
        $id = unpack('H40', substr($b, 0, 20));
        $user_id = $id[1];
        $key = unpack('H40', substr($b, 20, 40));
        $user_key = $key[1];
        return TRUE;
    }

    /**
     * Returns the request signature.
     *
     * @param string $token base64 encoded auth token
     * @param string $verb
     * @param string $path
     * @param string $date
     * @param string $sha1_body SHA1 of the request body
     * @return string the request signature
     */
    public function getRequestSignature($token, $verb, $path, $date, $sha1_body) {
        $user_id = '';
        $user_key = '';
        $this->processAuthToken($token, $user_id, $user_key);
        $req_str = $verb."\n".$path."\n".$date."\n".$sha1_body."\n";

        $hmac = hash_hmac('sha1', $req_str, pack('H*',$user_key) );
        $raw_auth = $user_id.$hmac."0000";
        $pack_raw = pack('H*', $raw_auth);
        return base64_encode($pack_raw);
    }

    /**
     * Returns the RFC 1123 date for requests.
     *
     * @return string
     */
    public function getRequestDate() {
        return gmdate('D, d M Y H:i:s T');
    }

    /**
     *
     * Mandatory params:
     * 'url' -> string - url to call
     * 'date' -> string - RFC1123 date
     * 'authorization' -> string - SX call authorization key
     *
     * Extra:
     * 'verb' - the verb to use
     * 'headers' - array of string with the additional headers
     * 'content-length' - unsigned integer specifies content length
     * 'post-fields' - string of data for POST verb
     * 'curl-options' -  associative array of curl options: array( 'curl_option' => 'value')
     *
     * @param array $params
     * @param boolean $return_transfer TRUE return request data, FALSE otherwise
     * @return bool|mixed
     */
    protected function RESTCall(array $params, $return_transfer = FALSE) {
        $this->clear();

        $res = curl_init();
        if ($res !== FALSE) {
            curl_setopt($res, CURLOPT_URL, $params['url']);
            
            if (!empty($this->_port)) {
                curl_setopt($res, CURLOPT_PORT, $this->_port);
            }

            if (array_key_exists('verb', $params)) {
                switch($params['verb']) {
                    case self::REQUEST_VERB_POST:
                        curl_setopt($res, CURLOPT_POST, TRUE);
                        if (array_key_exists('post-fields', $params)) {
                            curl_setopt($res, CURLOPT_POSTFIELDS, $params['post-fields']);
                        }
                        break;
                    case self::REQUEST_VERB_PUT: curl_setopt($res, CURLOPT_PUT, TRUE); break;
                    case self::REQUEST_VERB_DELETE: curl_setopt($res, CURLOPT_CUSTOMREQUEST, 'DELETE'); break;
                    default:
                        curl_setopt($res, CURLOPT_HTTPGET, TRUE);
                }
            } else {
                curl_setopt($res, CURLOPT_HTTPGET, TRUE);
            }

            curl_setopt($res, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($res, CURLOPT_HEADER, FALSE);

            $headers = array(
                'Date: '.$params['date'],
                'Authorization: SKY '.$params['authorization']
            );
            if (array_key_exists('content-length', $params)) {
                $headers[] = 'Content-Length: '.strval($params['content-length']);
            }

            if (array_key_exists('headers', $params)) {
                $headers = array_merge($headers, $params['headers']);
            }

            curl_setopt($res, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($res, CURLOPT_RETURNTRANSFER, $return_transfer);
            curl_setopt($res, CURLOPT_HEADERFUNCTION, array($this, 'writeHeader') );
            curl_setopt($res, CURLOPT_WRITEFUNCTION, array($this, 'writeBody') );

            curl_setopt($res, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($res, CURLOPT_SSL_VERIFYHOST, FALSE);

            if (array_key_exists('curl-options', $params)) {
                foreach($params['curl-options'] as $curlopt => $value) {
                    curl_setopt($res, $curlopt, $value);
                }
            }

            if (curl_exec($res) === FALSE) {
                $this->_error = curl_error($res);
                $this->_error_no = curl_errno($res);
            }

            curl_close($res);
            return ($this->_error_no == 0);
        }
        return FALSE;
    }

    public function parseHeaders() {
        /**
         * FIXME: This approach will fail will folded lines, but... who cares! 8-)
         */

        /*
         * From: http://www.faqs.org/rfcs/rfc2616.html
         *
         * HTTP/1.1 header field values can be folded onto multiple lines if the
         * continuation line begins with a space or horizontal tab. All linear
         * white space, including folding, has the same semantics as SP. A
         * recipient MAY replace any linear white space with a single SP before
         * interpreting the field value or forwarding the message downstream.
         *
         * LWS            = [CRLF] 1*( SP | HT )
         *
        */

        $this->_response = array();

        if (preg_match('#^(?P<proto>HTTP/\d\.\d)\s(?P<httpcode>\d{3})\s(?P<httpmessage>[^\r]*)#', $this->_headers, $matches) == 1) {
            $this->_response['http_code'] = intval($matches['httpcode']);
            $this->_response['http_message'] = $matches['httpmessage'];
            $this->_response['headers'] = array();

            if (preg_match_all('#^(?P<header>[^:]+):\s(?P<values>[^\r]+)?#m', $this->_headers, $matches, PREG_PATTERN_ORDER, strpos($this->_headers, "\r\n"))) {
                foreach($matches['header'] as $idx => $header) {
                    $this->_response['headers'][strtolower($header)] = $matches['values'][$idx];
                }
                return TRUE;
            }
        }
        return FALSE;
    }


    protected function parseBody() {

    }

    public function writeHeader($res, $data) {
        $this->_headers .= $data;
        return strlen($data);
    }

    public function writeBody($res, $data) {
        $this->_body .= $data;
        return strlen($data);
    }

    /**
     * Tells if the last REST call has errors. Only reports cURL library errors.
     * @return bool
     */
    public function hasRESTError() {
        return ($this->_error_no != 0);
    }

    /**
     * Checks if a JSON reply is an error and throws an exception
     * @param array $reply the parsed JSON reply
     * @throws Skylable_AccessSxException
     */
    protected function replyIsError($reply) {
        if (is_array($reply)) {
            if (array_key_exists('ErrorId', $reply)) {
                throw new Skylable_AccessSxException( (array_key_exists('ErrorMessage', $reply) ? $reply['ErrorMessage'] : 'An error occurred.'), $reply['ErrorId'] );
            }
        }
    }

    /**
     * Returns the nodes that take part in the SX cluster.
     *
     * See: https://wiki.skylable.com/wiki/REST_API_List_Nodes
     *
     * @return array|bool
     */
    public function nodeList() {
        $date = $this->getRequestDate();

        if ($this->RESTCall(
            array(
                'url' => $this->getBaseURL($this->_cluster).'/?nodeList',
                'date' => $date,
                'authorization' => $this->getRequestSignature($this->_secret_key, 'GET', '?nodeList', $date, sha1(''))
            )
        )) {
            // Parse the reply and saves internally for caching
            if ($this->parseHeaders()) {
                if ($this->_response['http_code'] == 200 && $this->isJSON()) {
                    $data = json_decode($this->_body, TRUE);
                    $this->replyIsError($data);
                    if (!is_null($data)) {
                        if (array_key_exists('nodeList', $data)) {
                            $this->_node_list = $data['nodeList'];
                            return $this->_node_list;
                        }
                    }
                }
            }
        }
        return FALSE;
    }

    /**
     * List nodes responsible for a volume.
     *
     * See: https://wiki.skylable.com/wiki/REST_API_Locate_Volume
     *
     * @param $volume
     * @return array|bool
     */
    public function volumeNodeList($volume) {
        $date = $this->getRequestDate();

        if ($this->RESTCall(
            array(
                'url' => $this->getBaseURL($this->_cluster).'/'.$volume.'?o=locate',
                'date' => $date,
                'authorization' => $this->getRequestSignature($this->_secret_key, 'GET', $volume.'?o=locate', $date, sha1(''))
            )
        )) {

            if ($this->parseHeaders()) {
                if ($this->_response['http_code'] == 200 && $this->isJSON()) {
                    $data = json_decode($this->_body, TRUE);
                    $this->replyIsError($data);
                    if (!is_null($data)) {
                        if (array_key_exists('nodeList', $data)) {
                            return $data['nodeList'];
                        }
                    }
                }
            }
        }
        return FALSE;
    }

    /**
     * Returns a list of users having access to a volume and the type of grants they have.
     *
     * See: https://wiki.skylable.com/wiki/REST_API_Get_Volume_ACL
     *
     * @param $volume
     * @return bool|mixed
     */
    public function getACL($volume) {
        $date = $this->getRequestDate();

        if ($this->RESTCall(
            array(
                'url' => $this->getBaseURL($this->_cluster).'/'.$volume.'?o=acl',
                'date' => $date,
                'authorization' => $this->getRequestSignature($this->_secret_key, 'GET', $volume.'?o=acl', $date, sha1(''))
            )
        )) {

            if ($this->parseHeaders()) {
                if ($this->_response['http_code'] == 200 && $this->isJSON()) {
                    $data = json_decode($this->_body, TRUE);
                    $this->replyIsError($data);
                    if (!is_null($data)) {
                        return $data;
                    }
                }
            }
        }
        return FALSE;
    }

    /**
     * Returns all the accessible volumes in the SX cluster.
     *
     * See: https://wiki.skylable.com/wiki/REST_API_List_Volumes
     *
     * @return bool
     */
    public function volumeList() {
        $date = $this->getRequestDate();
        if ($this->RESTCall(
            array(
                'url' => $this->getBaseURL($this->_cluster).'/?volumeList',
                'date' => $date,
                'authorization' => $this->getRequestSignature($this->_secret_key, 'GET', '?volumeList', $date, sha1(''))
            )
        )) {

            if ($this->parseHeaders()) {
                if ($this->_response['http_code'] == 200 && $this->isJSON()) {
                    $volumelist = json_decode($this->_body, TRUE);
                    $this->replyIsError($volumelist);
                    if (!is_null($volumelist)) {
                        if (array_key_exists('volumeList', $volumelist)) {
                            return $volumelist['volumeList'];
                        }
                    }
                }
            }
        }
        return FALSE;
    }

    /**
     * Reset the internal values.
     */
    public function clear() {
        $this->_headers = '';
        $this->_body = '';
        $this->_error = '';
        $this->_error_no = 0;
        $this->_response = array();
    }

    /**
     * List files in an SX volume.
     *
     * See: https://wiki.skylable.com/wiki/REST_API_List_Files
     *
     * @param string $volume the name of the volume to locate
     * @param string $pattern globbing pattern to limit the results returned to only those matching it
     * @param bool $recursive TRUE list files recursively, FALSE otherwise
     * @return bool|mixed
     */
    public function ls($volume, $pattern = '', $recursive = FALSE) {
        // Gets the volume node list
        $nodes = $this->volumeNodeList($volume);
        if ($nodes !== FALSE) {
            $req = $volume.'?o=list';
            if(!empty($pattern)) {
                $req .= '&filter='.rawurlencode($pattern);
            }
            if ($recursive) $req .= '&recursive';

            foreach($nodes as $node) {
                $date = $this->getRequestDate();

                if ($this->RESTCall(
                    array(
                        'url' => $this->getBaseURL($node).'/'.$req,
                        'date' => $date,
                        'authorization' => $this->getRequestSignature($this->_secret_key, 'GET', $req, $date, sha1(''))
                    )
                )) {

                    if ($this->parseHeaders()) {
                        if ($this->_response['http_code'] == 200 && $this->isJSON()) {
                            $data = json_decode($this->_body, TRUE);
                            $this->replyIsError($data);
                            if (!is_null($data)) {
                                return $data;
                            }
                        }
                    }
                }

            }
        }
        return FALSE;
    }

    const
        FILE_TYPE_NONE = 0,
        FILE_TYPE_DIR = 1,
        FILE_TYPE_FILE = 2,
        FILE_TYPE_VOLUME = 3,
        FILE_TYPE_ANY = 4;

    /**
     * Checks if a file exists on the cluster.
     *
     * IMPORTANT:
     * The file type check is strict, so:
     * 'my-vol' -> returns FILE_TYPE_VOLUME
     * 'my-vol/' -> returns FILE_TYPE_DIR (the dir is '/')
     *
     * The path must be complete: 'vol-name/file/path'.
     * On success set the $file_type parameter with the file type, which is
     * one of the FILE_TYPE_* class constants.
     *
     * @param string $path the path to check
     * @param integer $file_type the file type
     * @return bool TRUE if the file exists, FALSE otherwise
     * @throws Skylable_AccessSxException
     */
    public function fileExists($path, &$file_type) {
        $this->getLogger()->debug(__METHOD__.' Checking: '.$path);
        $file_type = self::FILE_TYPE_NONE;
        $volume = My_Utils::getRootFromPath($path);
        $file = My_Utils::skipPath($path, 2);

        if (strlen($file) == 0 || $file == '/') {
            $this->getLogger()->debug(__METHOD__.' file is empty or /');
            if ($this->volumeExists($path)) {
                if ($path[strlen($path) - 1] == '/') {
                    $file_type = self::FILE_TYPE_DIR;
                } else {
                    $file_type = self::FILE_TYPE_VOLUME;
                }
                return TRUE;
            }
            return FALSE;
        }

        $file_list = $this->ls($volume, $file);
        if ($file_list === FALSE) {
            return FALSE;
        }
        foreach($file_list['fileList'] as $f_name => $f_data) {
            if (My_Utils::isSamePath($f_name, $file)) {
                $file_type = empty($f_data) ? self::FILE_TYPE_DIR : self::FILE_TYPE_FILE;
                return TRUE;
            }
        }
        return FALSE;
    }

    /**
     * Tells if a volume exists.
     *
     * @param string $path the volume name (can be a complete path)
     * @return bool TRUE if the volume exists, FALSE if not or on errors
     *
     */
    public function volumeExists($path) {
        $volume = My_Utils::getRootFromPath($path);
        if ($volume === FALSE || strlen($volume) == 0) {
            return FALSE;
        }
        $volumes = $this->volumeList();
        if ($volumes === FALSE) {
            return FALSE;
        }

        return array_key_exists($volume, $volumes);
    }

    /**
     * Delete an existing SX file object.
     *
     * See: https://wiki.skylable.com/wiki/REST_API_Delete_File
     *
     * @param $volume
     * @param $path
     * @return bool|mixed
     */
    public function delete($volume, $path) {
        // Gets the volume node list
        $nodes = $this->volumeNodeList($volume);
        if ($nodes !== FALSE) {
            $req = $volume.'/'.$path;

            foreach($nodes as $node) {
                $date = $this->getRequestDate();

                if ($this->RESTCall(
                    array(
                        'url' => $this->getBaseURL($node).'/'.$req,
                        'date' => $date,
                        'authorization' => $this->getRequestSignature($this->_secret_key, 'DELETE', $req, $date, sha1('')),
                        'verb' => self::REQUEST_VERB_DELETE
                    )
                )) {

                    if ($this->parseHeaders()) {
                        if ($this->_response['http_code'] == 200 && $this->isJSON()) {
                            $data = json_decode($this->_body, TRUE);
                            $this->replyIsError($data);
                            if (!is_null($data)) {
                                return $data;
                            }
                        }
                    }
                }
            }
        }
        return FALSE;
    }

    /**
     * Retrieves the list of blocks of which a file is comprised of and the nodes where those blocks are available.
     *
     * See: https://wiki.skylable.com/wiki/REST_API_Get_File
     *
     * @param $volume
     * @param $path
     * @return bool|mixed
     */
    public function getFile($volume, $path) {
        // Gets the volume node list
        $nodes = $this->volumeNodeList($volume);
        if ($nodes !== FALSE) {
            $req = $volume.'/'.$path;

            foreach($nodes as $node) {
                $date = $this->getRequestDate();

                if ($this->RESTCall(
                    array(
                        'url' => $this->getBaseURL($node).'/'.$req,
                        'date' => $date,
                        'authorization' => $this->getRequestSignature($this->_secret_key, 'GET', $req, $date, sha1(''))
                    )
                )) {

                    if ($this->parseHeaders()) {
                        if ($this->_response['http_code'] == 200 && $this->isJSON()) {
                            $data = json_decode($this->_body, TRUE);
                            $this->replyIsError($data);
                            if (!is_null($data)) {
                                return $data;
                            }
                        }
                    }
                }
            }
        }
        return FALSE;
    }

    /**
     * Retrieves the metadata associated with an existing SX file.
     *
     * See: https://wiki.skylable.com/wiki/REST_API_Get_File_Metadata
     *
     * @param $volume
     * @param $path
     * @return bool|mixed
     */
    public function getFileMetadata($volume, $path) {
        return $this->getFile($volume, $path.'?fileMeta');
    }

    /**
     * Tells if the response is a JSON
     *
     * @return bool
     */
    protected function isJSON() {
        return (strncmp($this->_response['headers']['content-type'], 'application/json', strlen('application/json')) == 0);
    }
}
