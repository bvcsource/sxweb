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
 * Manages searches
 */
class SearchController extends My_BaseAction {


    /**
     * Check the search engine configuration.
     * 
     * @return bool TRUE if cfg is ok, FALSE otherwise
     * @throws Zend_Exception
     */
    public function checkSearchEngineConfig() {
        $hosts_cfg = Zend_Registry::get('skylable')->get('elastic_hosts');
        if (!is_object($hosts_cfg)) {
            return FALSE;
        }
        $hosts = $hosts_cfg->toArray();
        foreach($hosts as $k => $v) {
            if (strlen(trim($v)) == 0) {
                unset($hosts[$k]);
            }
        }
        if (empty($hosts)) {
            return FALSE;
        }
        return TRUE;
    }
    
	/**
	 * Search files and contents.
	 *
	 * Parameters:
	 * 'vol' - string, volume to search
	 * 'q' - string, query string
	 * 'page' - numeric, results page to show
	 */
	public function indexAction() {
        
        // Always check the configuration
        if ($this->checkSearchEngineConfig() == FALSE) {
            $this->getInvokeArg('bootstrap')->getResource('Log')->debug(__METHOD__ . ': Search engine is not configured properly');
            $this->view->error_msg = 'Upgrade to SX Enterprise Edition to enable full-text search in SXWeb: <a href="http://www.skylable.com/company/#contact-us" onclick="window.open(this.href); return false;">http://www.skylable.com/company/#contact-us</a>.';
            return FALSE;
        }

		$volume = $this->getRequest()->getParam('vol', '');
		$query = $this->getRequest()->getParam('q', '');

		// validate a bit...
		$validate_query = new Zend_Validate_StringLength(array( 'min' => 1, 'max' => 255 ));

		$access_sx = new Skylable_AccessSxNG(array(
			'cluster' => Zend_Registry::get('skylable')->get('cluster'),
			'secret_key' => Zend_Auth::getInstance()->getIdentity()->getSecretKey()
		));
		$validate_volume = new My_ValidateSxPath($access_sx, My_ValidateSxPath::FILE_TYPE_VOLUME);

		try {

			if ($validate_query->isValid($query) && $validate_volume->isValid($volume)) {
				$this->getInvokeArg('bootstrap')->getResource('Log')->debug('Search on '.$volume.' for: '.$query);

				// Get view configuration from user
				$page_size = 20;
				$user_page_size = Zend_Auth::getInstance()->getIdentity()->getPreferences()->get(My_User::PREF_PAGE_SIZE, -1);
				if (is_numeric($user_page_size)) {
					if ($user_page_size > 0) {
						$page_size = $user_page_size;
					}
				}

                // Sets vars here, because the following code can raise exceptions
                $this->view->url = My_Utils::slashPath($volume); // Needed to let autocomplete show again
                $this->view->volume = $volume;
                $this->view->query_str = $query;

				$paginator = new Zend_Paginator(new My_SearchPaginator($volume, $query));
				$paginator->setItemCountPerPage( $page_size );
				$paginator->setPageRange( 9 );
				$current_page = $this->_getParam('page');
				if (preg_match('/^\d+$/', $current_page) == 1) {
					$current_page = abs(intval($current_page));
				} else {
					$current_page = 1;
				}
				$paginator->setCurrentPageNumber( $current_page );

                $this->getInvokeArg('bootstrap')->getResource('Log')->debug('Search #2 on '.$volume.' for: '.$query);

				$this->view->list = $paginator->getCurrentItems();
				$this->view->paginator = $paginator;

			} else {
				$this->getInvokeArg('bootstrap')->getResource('Log')->debug('Invalid search parameters: volume: '.var_export($volume, TRUE).' query: '.var_export($query, TRUE));
				$this->view->error_msg = 'Invalid search parameters, please retry.';
			}
		}
		catch(Exception $e) {
			$this->getInvokeArg('bootstrap')->getResource('Log')->err(__METHOD__.': exception: '.$e->getMessage().' Code:'.$e->getCode() );
			$this->view->error_msg = 'Search engine is unavailable. Contact your sysadmin.';
		}

	}

	/**
	 * Return results for auto complete functionality.
	 *
	 * Parameters:
	 * 'volume' - string the volume to search
	 * 'q' - string the query string
	 *
	 * Returns:
	 * A JSON object with the results
	 */
	public function suggestAction() {

		$this->disableView();
		$this->getResponse()->setHeader('Content-Type', 'application/json; encoding=UTF-8');

        if ($this->checkSearchEngineConfig() == FALSE) {
            $this->getInvokeArg('bootstrap')->getResource('Log')->debug(__METHOD__ . ': Search engine is not configured properly');
            echo json_encode(
                array(
                    'status' => FALSE,
                    'error' => 'Search engine is unavailable. Contact your sysadmin.'
                )
            );
            return FALSE;
        }

		$volume = $this->getRequest()->getParam('volume', '');
		$query = $this->getRequest()->getParam('q', '');

		// validate a bit...
		$validate_query = new Zend_Validate_StringLength(array( 'min' => 1, 'max' => 255 ));

		$access_sx = new Skylable_AccessSxNG(array(
			'cluster' => Zend_Registry::get('skylable')->get('cluster'),
			'secret_key' => Zend_Auth::getInstance()->getIdentity()->getSecretKey()
		));
		$validate_volume = new My_ValidateSxPath($access_sx, My_ValidateSxPath::FILE_TYPE_VOLUME);

		if ($validate_query->isValid($query) && $validate_volume->isValid($volume)) {
			$this->getInvokeArg('bootstrap')->getResource('Log')->debug(__METHOD__.': Search on ' . $volume . ' for: ' . $query);

			try {
				$config = array();
				$config["hosts"] = Zend_Registry::get('skylable')->get('elastic_hosts')->toArray();
				$config['guzzleOptions']['curl.options'][CURLOPT_CONNECTTIMEOUT] = 2.0;
				$config['guzzleOptions']['command.request_options']['connect_timeout'] = 2.0;
				$config['guzzleOptions']['command.request_options']['timeout'] = 2.0;

				$es = new Elasticsearch\Client($config);

				$params = array();

				$params['index'] = 'sx';
				$params['type'] = 'file';

				$params['body']['query']['bool']['must'][]['term']['volume'] = $volume;
				$params['body']['query']['bool']['minimum_should_match'] = 1;

				// search by filename
				$params['body']['query']['bool']['should'][]['wildcard']['path'] = "*" . $query. "*";

				// search by content
				$params['body']['query']['bool']['should'][]['match']['content'] = array(
					'query' => "*" . $query . "*",
					'operator' => 'and'
				);

				$params['body']['query']['bool']['must_not'][]['wildcard']['path'] = '*'.Skylable_AccessSxNew::NEWDIR_FILENAME.'*';

				$params['body']['fields'] = 'path';

				$params['body']['size'] = 10;
				$params['body']['from'] = 0;

				$data = $es->search($params);
				$out = array(
					'status' => TRUE,
					'data' => array()
				);
				foreach($data['hits']['hits'] as $file) {
					$out['data'][] = $file['fields']['path'][0];
				}
				echo json_encode($out);
			}
			catch(Exception $e) {
				$this->getInvokeArg('bootstrap')->getResource('Log')->err(__METHOD__.': exception: '.$e->getMessage() );
				echo json_encode(
					array(
						'status' => FALSE,
						'error' => 'Search engine is unavailable. Contact your sysadmin.'
					)
				);
			}
		} else {
			$this->getInvokeArg('bootstrap')->getResource('Log')->debug('Invalid search parameters: volume: '.var_export($volume, TRUE).' query: '.var_export($query, TRUE));
			echo json_encode(
				array(
					'status' => FALSE,
					'error' => 'Invalid search parameters.'
				)
			);
		}

	}
}