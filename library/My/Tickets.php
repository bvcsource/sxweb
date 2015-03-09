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


/*
 * Tickets used to ensure download limits
 */
class My_Tickets extends Zend_Db_Table_Abstract {
	protected $_name = 'tickets';
	protected $_primary = 'ticket_id';

	/**
	 * Register a ticket for a download.
	 *
	 * Every user has a concurrent downloads limit.
	 *
	 * Every parameter is into the main config file, the key to check are:
	 * 'downloads' - number of concurrent downloads per logged user
	 * 'downloads_ip' - number of concurrent downloads per IP address
	 * 'downloads_time_window' - window in seconds where downloads gets counted
	 *
	 * Returns TRUE if you can allow the download, FALSE otherwise
	 *
	 * @param integer $user_id NULL or the user ID
	 * @param string $user_ip NULL or the string with the IP address of the user
	 * @return bool
	 * @throws Exception
	 */
	public function registerTicket($user_id, $user_ip) {

		$logger = Zend_Controller_Front::getInstance()->getParam('bootstrap')->getResource('log');

		$user_id_limit = Zend_Registry::get('skylable')->get('downloads');
		$user_ip_limit = Zend_Registry::get('skylable')->get('downloads_ip');
		$time_limit = Zend_Registry::get('skylable')->get('downloads_time_window');
		$time_limit_ip = Zend_Registry::get('skylable')->get('downloads_time_window_ip');

		if (!is_null($user_id)) {
			if (!is_numeric($user_id)) {
				$logger->debug(__METHOD__.': Invalid user ID: '.var_export($user_id, TRUE));
				return FALSE;
			}
			if (!is_numeric($user_id_limit)) {
				$logger->debug(__METHOD__.': Invalid user ID limit, check the config!');
				return FALSE;
			}
		}
		if (!is_null($user_ip)) {
			if (!is_string($user_ip)) {
				$logger->debug(__METHOD__.': Invalid IP address: '.var_export($user_ip, TRUE));
				return FALSE;
			}
			if (!is_numeric($user_ip_limit)) {
				$logger->debug(__METHOD__.': Invalid IP limit, check the config!');
				return FALSE;
			}
		}

		if (!is_numeric($time_limit) || !is_numeric($time_limit_ip)) {
			$logger->debug(__METHOD__.': Invalid time limits, check the config!');
			return FALSE;
		}

		$this->getAdapter()->beginTransaction();
		try {

			// Count ticket for user
			if (is_numeric($user_id)) {
				// Expire older tickets and count remaining
				$this->getAdapter()->query('DELETE FROM tickets WHERE TIMESTAMPDIFF(SECOND, ticket_time, NOW()) > ?', array($time_limit) );

				$stm = $this->getAdapter()->query('SELECT COUNT(*) as cnt FROM tickets WHERE uid = ?', array($user_id) );
				$cnt = $stm->fetchColumn(0);
				$logger->debug(__METHOD__.': user ID: '.strval($user_id).', tickets found: '.var_export($cnt, TRUE));

				if ($cnt === FALSE) {
					$this->getAdapter()->commit();
					return FALSE;
				}

				$ret = FALSE;
				if ($cnt < $user_id_limit) {
					$ticket_id = $this->insert(array( 'uid' => $user_id));
					$ret = TRUE;
				}
				$this->getAdapter()->commit();
				return $ret;
			} elseif(strlen($user_ip) > 0) {

				// Expire older tickets and count remaining
				$this->getAdapter()->query('DELETE FROM tickets WHERE TIMESTAMPDIFF(SECOND, ticket_time, NOW()) > ?', array($time_limit_ip) );

				$stm = $this->getAdapter()->query('SELECT COUNT(*) as cnt FROM tickets WHERE ip_addr = ?', array($user_ip) );
				$cnt = $stm->fetchColumn();
				if ($cnt === FALSE) {
					$this->getAdapter()->commit();
					return FALSE;
				}

				$ret = FALSE;
				if ($cnt < $user_ip_limit) {
					$ticket_id = $this->insert(array( 'ip_addr' => $user_ip));
					$ret = TRUE;
				}
				$this->getAdapter()->commit();
				return $ret;
			} else {
				$this->getAdapter()->rollBack();
				return FALSE;
			}
		}
		catch(Exception $e) {
			$this->getAdapter()->rollBack();
			throw $e;
		}
	}

}
