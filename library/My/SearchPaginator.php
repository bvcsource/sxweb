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
 * Paginator for Elasticsearch query results
 */
class My_SearchPaginator implements Zend_Paginator_Adapter_Interface {

    protected
     $_vol, $_q, $_es;

    public function __construct($volume, $query_string) {
        $this->_vol = $volume;
        $this->_q = $query_string;

        $config = array();
        $config["hosts"] = Zend_Registry::get('skylable')->get('elastic_hosts')->toArray();
        $config['guzzleOptions']['curl.options'][CURLOPT_CONNECTTIMEOUT] = 2.0;
        $config['guzzleOptions']['command.request_options']['connect_timeout'] = 2.0;
        $config['guzzleOptions']['command.request_options']['timeout'] = 2.0;

        $this->_es = new Elasticsearch\Client($config);
    }

    protected function getBaseQuery() {
        $params = array();

        $params['index'] = 'sx';
        $params['type'] = 'file';

        $params['body']['query']['bool']['must'][]['term']['volume'] = $this->_vol;
        $params['body']['query']['bool']['minimum_should_match'] = 1;

        // search by filename
        $params['body']['query']['bool']['should'][]['wildcard']['path'] = "*" . $this->_q . "*";

        // search by content
        $params['body']['query']['bool']['should'][]['match']['content'] = array(
            'query' => "*" . $this->_q . "*",
            'operator' => 'and'
        );

        $params['body']['query']['bool']['must_not'][]['wildcard']['path'] = '*'.Skylable_AccessSx::NEWDIR_FILENAME.'*';

        return $params;
    }

    /**
     * Returns a collection of items for a page.
     *
     * @param  integer $offset Page offset
     * @param  integer $itemCountPerPage Number of items per page
     * @return array
     */
    public function getItems($offset, $itemCountPerPage)
    {

        $query = $this->getBaseQuery();
        $query['body']['highlight']['fields']['content'] = new \stdClass;
        $query['body']['fields'] = 'path';

        $query['body']['size'] = $itemCountPerPage;
        $query['body']['from'] = $offset;

        $data = $this->_es->search($query);
        return $data['hits']['hits'];
    }

    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Count elements of an object
     * @link http://php.net/manual/en/countable.count.php
     * @return int The custom count as an integer.
     * </p>
     * <p>
     * The return value is cast to an integer.
     */
    public function count()
    {
        $query = $this->getBaseQuery();
        $cnt = $this->_es->count($query);
        return $cnt['count'];
    }
}