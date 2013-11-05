<?php
/**
* Plugin model for "Adobe Connect Report".
*
* Provides custom find types for the various calls on the web service, mapping
* familiar CakePHP methods and parameters to the http request params for
* issuing to the web service.
*
* @author Alan Blount <alan@zeroasterisk.com>
* @link http://zeroasterisk.com
* @copyright (c) 2011 Alan Blount
* @license MIT License - http://www.opensource.org/licenses/mit-license.php
*/
class AdobeConnectReport extends AdobeConnectAppModel {
	
	/**
	* The name of this model
	* @var name
	*/
	
	/**
	* default value of "send-email" for the welcome email on new-user-create
	* @var bool
	*/
	public $defaultSendEmailBool = false;
	
	/**
	* The fields and their types for the form helper
	* @var array
	*/
	public $_schema = array(
		);
	
	
	/**
	* Set the primaryKey
	* @var string
	*/
	public $primaryKey = 'acl-id';
	
	/**
	* The custom find methods (defined below)
	* @var array
	*/
	public $_findMethods = array(
		'active' => true,
		'coursestatus' => true,
		'bulkconsolidatedtransactions' => true,
		'meetingattendance' => true,
		'meetingconcurrentusers' => true,
		);
	
	/**
	* Custom Find: reports all currently active meetings
	* @link http://help.adobe.com/en_US/AcrobatConnectPro/7.5/WebServices/WS26a970dc1da1c212717c4d5b12183254583-8000.html#WS5b3ccc516d4fbf351e63e3d11a171dd627-7ec3
	* $this->find("active");
	* @param string $state
	* @param array $query
	* @param array $results
	*/
	protected function _findActive($state, $query = array(), $results = array()) {
		if ($state == 'before') {
			$this->request = array("action" => "report-active-meetings");
			return $query;
		} else {
			$return = Set::extract($results, "/report-active-meetings/sco/.");
			if (empty($return)) {
				$return = Set::extract($this->response, "/report-active-meetings/sco/.");
			}
			return $return;
		}
	}
	
	/**
	* Custom Find: BulkConsolidatedTransactions (shows attendance transactions/sessions, only for principals, but has more precise information)
	* Returns information about principal-to-SCO transactions on your Acrobat Connect Pro server or in your Acrobat Connect Pro hosted account.
	* A transaction is an instance of one principal visiting one SCO. The SCO can be a Acrobat Connect Pro meeting, course, document, or any content on the server.
	* These are all examples of transactions:
	* - If a principal attends a meeting twice, two transactions exist: one for each time the principal attended the meeting.
	* - If five people attend a meeting, five transactions exist: one for each user who attended the meeting.
	* - If a principal takes two courses three times each and passes each only on the third try, six transactions exist: one for each attempt on each course.
	* This call returns all transactions, so consider using a filter to reduce the volume of the response.
	* @link http://help.adobe.com/en_US/AcrobatConnectPro/7.5/WebServices/WS26a970dc1da1c212717c4d5b12183254583-8000.html#WS5b3ccc516d4fbf351e63e3d11a171ddf77-7f27
	* $this->find("bulkconsolidatedtransactions", array('conditions' => array('sco-id' => $scoId, 'principal-id' => $principalId), 'limit' => 100));
	* @param string $state
	* @param array $query
	* @param array $results
	*/
	protected function _findBulkconsolidatedtransactions($state, $query = array(), $results = array()) {
		if ($state == 'before') {
			$this->request = array(
				"action" => "report-bulk-consolidated-transactions",
				"sort1-principal-id" => "asc",
				"sort2-date-created" => "asc",
				);
			if (isset($query['sco-id']) && !empty($query['sco-id'])) {
				$query['conditions']['sco-id'] = $query['sco-id'];
			}
			if (isset($query['principal-id']) && !empty($query['principal-id'])) {
				$query['conditions']['principal-id'] = $query['principal-id'];
			}
			if (empty($query['limit'])) {
				$query['limit'] = 100;
			}
			$this->request = Set::merge($this->request, $this->parseFiltersFromQuery($query));
			return $query;
		} else {
			$return = Set::extract($results, "/report-bulk-consolidated-transactions/row/.");
			if (empty($return)) {
				$return = Set::extract($this->response, "/report-bulk-consolidated-transactions/row/.");
			}
			return $return;
		}
	}
	
	/**
	* Custom Find: reports course status
	* Returns summary information about a course, including the number of users who have passed, failed, and completed the course, as well as the current number of enrollees. The request requires the sco-id of a course.
	* @link http://help.adobe.com/en_US/AcrobatConnectPro/7.5/WebServices/WS26a970dc1da1c212717c4d5b12183254583-8000.html#WS5b3ccc516d4fbf351e63e3d11a171dd627-7e82
	* $this->find("coursestatus", $scoId);
	* @param string $state
	* @param array $query
	* @param array $results
	*/
	protected function _findCoursestatus($state, $query = array(), $results = array()) {
		if ($state == 'before') {
			$this->request = array("action" => "report-course-status");
			if (isset($query['sco-id']) && !empty($query['sco-id']) && is_numeric($query['sco-id'])) {
				$this->request["sco-id"] = $query['sco-id'];
				unset($query['sco-id']);
			} elseif (isset($query['conditions']['sco-id']) && !empty($query['conditions']['sco-id']) && is_numeric($query['conditions']['sco-id'])) {
				$this->request["sco-id"] = $query['conditions']['sco-id'];
				unset($query['conditions']['sco-id']);
			} elseif (isset($query[0]) && !empty($query[0]) && is_numeric($query[0])) {
				$this->request["sco-id"] = $query[0];
				unset($query[0]);
			}
			if (!isset($this->request["sco-id"]) || empty($this->request["sco-id"])) {
				die("ERROR: you must include a value for to find('coursestatus', \$options['sco-id'])");
			}
			$this->request = Set::merge($this->request, $this->parseFiltersFromQuery($query));
			return $query;
		} else {
			$return = Set::extract($results, "/report-course-status/.");
			if (empty($return)) {
				$return = Set::extract($this->response, "/report-course-status/.");
			}
			return $return;
		}
	}
	
	/**
	* Custom Find: reports meeting attendance (shows attendance transactions/sessions, works for non-principals)
	* Returns a list of users who attended a Acrobat Connect Pro meeting. The data is returned in row elements, one for each person who attended. If the meeting hasn’t started or had no attendees, the response contains no rows.The response does not include meeting hosts or users who were invited but did not attend.
	* @link http://help.adobe.com/en_US/AcrobatConnectPro/7.5/WebServices/WS26a970dc1da1c212717c4d5b12183254583-8000.html#WS5b3ccc516d4fbf351e63e3d11a171dd627-7e6e
	* $this->find("meetingattendance", $scoId);
	* @param string $state
	* @param array $query
	* @param array $results
	*/
	protected function _findMeetingattendance($state, $query = array(), $results = array()) {
		if ($state == 'before') {
			$this->request = array("action" => "report-meeting-attendance");
			if (isset($query['sco-id']) && !empty($query['sco-id']) && is_numeric($query['sco-id'])) {
				$this->request["sco-id"] = $query['sco-id'];
				unset($query['sco-id']);
			} elseif (isset($query['conditions']['sco-id']) && !empty($query['conditions']['sco-id']) && is_numeric($query['conditions']['sco-id'])) {
				$this->request["sco-id"] = $query['conditions']['sco-id'];
				unset($query['conditions']['sco-id']);
			} elseif (isset($query[0]) && !empty($query[0]) && is_numeric($query[0])) {
				$this->request["sco-id"] = $query[0];
				unset($query[0]);
			}
			if (!isset($this->request["sco-id"]) || empty($this->request["sco-id"])) {
				die("ERROR: you must include a value for to find('meetingattendance', \$options['sco-id'])");
			}
			$this->request = Set::merge($this->request, $this->parseFiltersFromQuery($query));
			$query = $this->_paginationParams($query);
			return $query;
		} else {
			$return = Set::extract($results, "/report-meeting-attendance/row/.");
			if (empty($return)) {
				$return = Set::extract($this->response, "/report-meeting-attendance/row/.");
			}
			return $return;
		}
	}
	
	/**
	* Custom Find: reports meeting concurrent users
	* Returns the maximum number of users in Acrobat Connect Pro meetings concurrently in the last 30 days, and the number of times the maximum has been reached. The maximum is the peak number of users in any meetings at a single moment, whether one meeting, multiple concurrent meetings, or multiple overlapping meetings.
	* You can change the time period to a period greater than 30 days by adding a length parameter, for example, length=120.
	* The maximum number of users (max-users) is determined by the account license and applies to the server overall, not to a specific meeting. This action also returns the number of times in the current month the maximum has been reached (max-participants-freq).
	* @link http://help.adobe.com/en_US/AcrobatConnectPro/7.5/WebServices/WS26a970dc1da1c212717c4d5b12183254583-8000.html#WS5b3ccc516d4fbf351e63e3d11a171dd627-7e64
	* $this->find("meetingconcurrentusers", $scoId);
	* @param string $state
	* @param array $query
	* @param array $results
	*/
	protected function _findMeetingconcurrentusers($state, $query = array(), $results = array()) {
		if ($state == 'before') {
			$this->request = array("action" => "report-meeting-concurrent-users");
			if (isset($query['length'])) {
				$this->request['length'] = $query['length'];
			}
			return $query;
		} else {
			$return = Set::extract($results, "/report-meeting-concurrent-users/.");
			if (empty($return)) {
				$return = Set::extract($this->response, "/report-meeting-concurrent-users/.");
			}
			if (isset($return[0])) {
				return $return[0];
			}
			return $return;
		}
	}
}
