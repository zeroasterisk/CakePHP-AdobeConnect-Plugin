<?php
/**
 * Plugin model for "Adobe Connect Sco".
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
App::uses('AdobeConnectAppModel', 'AdobeConnect.Model');
class AdobeConnectSco extends AdobeConnectAppModel {

	/**
	 * The models in the plugin get data from the web service, so they don't need a table.
	 * @var string
	 */
	public $useTable = false;

	/**
	 * The models in the plugin need a datasource
	 * @var string
	 */
	public $useDbConfig = 'adobe_connect';

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
		'sco-id' => array('type' => 'integer', 'length' => '11'),
		'name' => array('type' => 'string', 'length' => '255'), // The name of the SCO, with or without spaces. Required to create a SCO.
		'folder-id' => array('type' => 'integer', 'length' => '11'),
		'icon' => array('type' => 'string', 'length' => '255'), // The visual symbol used to identify a SCO in Acrobat Connect Pro Central; also provides information about the SCO in addition to its type.
		'type' => array('type' => 'string', 'length' => '255'), // The type of the new SCO (for allowed values, see type). The default value is content.
		'author-info-1' => array('type' => 'string', 'length' => '255'),
		'author-info-2' => array('type' => 'string', 'length' => '255'),
		'author-info-3' => array('type' => 'string', 'length' => '255'),
		'date-begin' => array('type' => 'datetime'),
		'date-end' => array('type' => 'datetime'),
		'description' => array('type' => 'text', 'length' => '1000'),
		'email' => array('type' => 'string', 'length' => '255'), // The e-mail address of the contact person for a presentation (used only with presentation SCOs).
		'first-name' => array('type' => 'string', 'length' => '255'), // The first name of the contact person for a presentation (used only with presentation SCOs).
		'lang' => array('type' => 'string', 'length' => '255'),
		'last-name' => array('type' => 'string', 'length' => '255'), // The last name of the contact person for a presentation (used only with presentation SCOs).
		'sco-tag' => array('type' => 'string', 'length' => '255'), // A label for any information you want to record about a course. Use only with courses.
		'source-sco-id' => array('type' => 'integer', 'length' => '11'), // The unique ID of a template you can use to create a meeting or a piece of content from which you can build a course.
		'url-path' => array('type' => 'string', 'length' => '255'), // The custom part of the URL to the meeting room that comes after the domain name. The url-path must be unique within the folder. If not specified, the server assigns a value.
	);


	/**
	 * Set the primaryKey
	 * @var string
	 */
	public $primaryKey = 'sco-id';

	/**
	 * constants from Connect
	 * @var array
	 */
	public $typesWithChildren = array("folder", "curriculum", "event", "meeting", "tree");

	/**
	 * The custom find methods (defined below)
	 * @var array
	 */
	public $findMethods = array(
		'search' => true,
		'contents' => true,
		'contents_recursive' => true,
		'contents_non_recursive' => true,
		'info' => true,
		'path' => true,
	);


	/**
	 * Creates/Saves a Sco
	 *
	 * @link http://help.adobe.com/en_US/AcrobatConnectPro/7.5/WebServices/WS26a970dc1da1c212717c4d5b12183254583-8000.html#WS5b3ccc516d4fbf351e63e3d11a171ddf77-7e45
	 *
	 * You must provide a folder-id or a sco-id, but not both. If you pass a folder-id, sco-update creates a new SCO and returns a sco-id. If the SCO already exists and you pass a sco-id, sco-update updates the metadata describing the SCO.
	 *
	 * @param array $data See Model::save()
	 * @param boolean $validate See Model::save() false
	 * @param array $fieldList See Model::save()
	 * @return boolean
	 */
	public function save($data = null, $validate = false, $fieldList = array()) {
		$initial = $this->request;
		$this->request = array();
		if (isset($data[$this->alias])) {
			// strip down to just this model's data
			$data = $data[$this->alias];
		}
		if (isset($data[$this->primaryKey]) && !empty($data[$this->primaryKey])) {
			if (isset($data['folder-id'])) {
				unset($data['folder-id']);
			}
		} elseif (!isset($data['folder-id']) || empty($data['folder-id'])) {
			return $this->error("AdobeConnectSco::save() Sorry, you must have either a folder-id or a sco-id to save a SCO");
		} elseif (isset($data[$this->primaryKey])) {
			unset($data[$this->primaryKey]);
		}
		// cleanup content, based on assumptions for various input fields: date, timezone, duration (hrs)
		if (isset($data['date']) && !empty($data['date'])) {
			if (isset($data['timezone']) && !empty($data['timezone'])) {
				$dateStartEpoch = strtotime($data['date'].' '.$data['timezone']);
			} else {
				$dateStartEpoch = strtotime($data['date']);
			}
			$data['date-begin'] = date('c', $dateStartEpoch);
			if (isset($data['duration']) && !empty($data['duration'])) {
				$data['date-end'] = date('c', $dateStartEpoch+($data['duration']*3600));
			}
		} else {
			if (isset($data['date-begin'])) {
				$data['date-begin'] = date('c', strtotime($data['date-begin']));
			}
			if (isset($data['date-end'])) {
				$data['date-end'] = date('c', strtotime($data['date-end']));
			}
		}
		// Add the content type in so OAuth won't use the body in the signature
		$this->request = $data;
		$this->request['action'] = "sco-update";
		$result = parent::save(array($this->alias => $data), $validate, $fieldList);
		// the API response is on this->response (set there by the source)
		$result = $this->responseCleanAttr($this->response);
		if (isset($result['status']['invalid'])) {
			return $this->error("AdobeConnectSco::save() Invalid Fields: ".json_encode($result['status']['invalid']));
		}
		if (empty($result['sco'][$this->primaryKey]) && !empty($data[$this->primaryKey])) {
			// on "update" we don't get back any data besides success...
			//   so we set the PK from the input $data
			$result['sco'][$this->primaryKey] = $data[$this->primaryKey];
		}
		if (empty($result['sco'][$this->primaryKey])) {
			$this->request = $initial;
			return false;
		}
		if (!empty($data['type']) && !empty($data['icon']) && $data['type'] == 'meeting' && $data['icon'] == 'seminar') {
			//Seminar Sessions
			if ($this->saveSeminarSession($result['sco'][$this->primaryKey], $data['date-begin'], $data['date-end']) === false) {
				return false;
			}
		}
		$this->id = $result['sco'][$this->primaryKey];
		$this->setInsertID($this->id);
		$result[$this->alias][$this->primaryKey] = $this->id;
		$result[$this->alias] = array_merge($result['sco'], $result[$this->alias]);
		unset($result['sco']);
		return $result;
	}

	/**
	 * Adobe Connect 9.1 introduced a new Seminar Room Session layer
	 * http://blogs.adobe.com/connectsupport/adobe-connect-9-1-seminar-session-creation-via-the-xml-api/
	 *
	 * Sessions now have to adhere to quotas:
	 *   You can obtain the Seminar License quotas for your different licenses by running this API call:
	 *   https://{myConnectURL}/api/xml?action=sco-seminar-licenses-list&sco-id=XXXXXXX
	 *   where sco-id = the sco-id of the SHARED SEMINARS folder (or the 'seminars' tree-id of the shortcut 'seminars').
	 *   see: $quota = $this->getRoomQuota($seminar_sco_id);
	 *
	 * @param int $seminar_sco_id Meeting's SCO ID, inside the Seminar Room
	 * @param string $date_begin
	 * @param string $date_end
	 */
	public function saveSeminarSession($seminar_sco_id, $date_begin, $date_end) {
		//Get quota for seminar room
		$config = $this->config();
		if (empty($config['connectVersion']) || (!empty($config['connectVersion']) && $config['connectVersion'] <= 9)) {
			return true;
		}
		$quota = $this->getRoomQuota($seminar_sco_id);
		if (empty($quota)) {
			return false;
		}
		//Allowing for only one session name per seminar room.
		$session_name = $seminar_sco_id."_session";

		//Check for existing session with this name
		$session_sco_id = $this->getSeminarSessionByName($seminar_sco_id, $session_name);
		if (time()-(60*60*24) < strtotime($date_begin)) {
			//if the event is a future event starting before 24 hours ago
			//Delete other sessions related to this seminar room to clear things out while excluding our current session
			$this->purgeSeminarSessions($seminar_sco_id, $session_sco_id);
		}

		//Create SCO for session: action=sco-update&type=5&name=MySeminarSession&folder-id=30009
		if (empty($session_sco_id)) {
			$data = array('type' => 'seminarsession', 'name' => $session_name, 'folder-id' => $seminar_sco_id);
			$this->request = $data;
			$this->request['action'] = "sco-update";
			$result = parent::save(array($this->alias => $data), false, array());
			// the API response is on this->response (set there by the source)
			$result = $this->responseCleanAttr($this->response);
			$session_sco_id = (!empty($result['sco']['sco-id']) ? $result['sco']['sco-id'] : 0);
			if (empty($session_sco_id)) {
				return false;
			}
		}

		//Set seminar session time
		//action=seminar-session-sco-update&sco-id=30010&source-sco-id=30009&parent-acl-id=30009&date-begin=2013-08-30T14:00:00.000-07:00&date-end=2013-08-30T15:00:10.000-07:00
		$data = array('sco-id' => $session_sco_id, 'source-sco-id' => $seminar_sco_id, 'parent-acl-id' => $seminar_sco_id, 'date-begin' => $date_begin, 'date-end' => $date_end);
		$this->request = $data;
		$this->request['action'] = "seminar-session-sco-update";
		$result = parent::save(array($this->alias => $data), false, array());
		$result = $this->responseCleanAttr($this->response);
		if (empty($result['status']['code']) && $result['status']['code'] != 'ok') {
			return false;
		}

		//Set session load
		//action=acl-field-update&acl-id=30010&field-id=311&value=25
		$result = $this->request(array('action' => 'acl-field-update', 'acl-id' => $session_sco_id, 'field-id' => 311, 'value' => $quota));
		if (isset($result['status']['code']) && $result['status']['code'] != 'ok') {
			return false;
		}


		// Don't verify next seminar session if event has already occurred.
		if (time() > strtotime($date_begin)) {
			return true;
		}

		//Verify Next Seminar Session is the one we just worked with
		$seminar_session = $this->getNextSeminarSession($seminar_sco_id);
		if (!empty($seminar_session['nextsession'])
			&& ($seminar_session['nextsession']['session-name'] == $session_name)
			&& (strtotime($seminar_session['nextsession']['datebegin']) === strtotime($date_begin))) {
				return true;
		} else {
				return false;
		}
	}

	public function getSeminarSessions($seminar_sco_id) {
		//https://sample.com/api/xml?action=sco-expanded-content&sco-id=11903
		return $this->request(array('action' => 'sco-expanded-contents', 'sco-id' => $seminar_sco_id));
	}

	public function getSeminarSessionByName($seminar_sco_id, $session_name) {
		$sessions = $this->getSeminarSessions($seminar_sco_id);
		if (empty($sessions['expanded-scos']['sco'])) {
			return false;
		} else {
			foreach ($sessions['expanded-scos']['sco'] as $session) {
				if (!empty($session['name']) && ($session['name'] == $session_name)) {
					return $session['sco-id'];
				}
			}
		}
		return false;
	}

	public function getNextSeminarSession($seminar_sco_id) {
		//https://sample.com/api/xml?action=get-next-seminar-event-session&sco-id=11903
		return $this->request(array('action' => 'get-next-seminar-event-session', 'sco-id' => $seminar_sco_id));
	}

	public function purgeSeminarSessions($seminar_sco_id, $exclude_sco_id=false) {
		$sessions = $this->getSeminarSessions($seminar_sco_id);
		if (empty($sessions['expanded-scos']['sco'])) {
			return false;
		} else {
			foreach ($sessions['expanded-scos']['sco'] as $session) {
				if (!empty($session['type']) && !empty($session['sco-id']) && $session['type'] == 'seminarsession' && $session['sco-id'] !== $exclude_sco_id) {
					//Delete other sessions to keep them from blocking any time updates
					$data = array('sco-id' => $session['sco-id']);
					$this->request = $data;
					$this->request['action'] = "sco-delete";
					$result = parent::save(array($this->alias => $data), false, array());
				}
			}
		}
		return true;
	}

	public function getRoomQuota($seminar_sco_id) {
		$sco_info = $this->find('info', $seminar_sco_id);
		$config = $this->config();
		$seminar_root_sco_id = $config['sco-ids']['seminar-root'];
		$cacheKey = "AdobeConnect_quotas_$seminar_root_sco_id";
		if (($seminar_licenses = Cache::read($cacheKey)) == false) {
			$seminar_licenses = $this->request(array('action' => 'sco-seminar-licenses-list', 'sco-id' => $seminar_root_sco_id,));
			$named_licenses = $this->request(array('action' => 'sco-seminar-licenses-list', 'sco-id' => $seminar_root_sco_id, 'user-webinar-selected' => 'true'));
			if (!empty($named_licenses['user-webinar-licenses'])) {
				$seminar_licenses['user-webinar-licenses'] = $named_licenses['user-webinar-licenses'];
			}
			Cache::write($cacheKey, $seminar_licenses);
		}
		if (!empty($seminar_licenses['seminar-licenses']['sco'])) {
			foreach ($seminar_licenses['seminar-licenses']['sco'] as $license) {
				if ($license['sco-id'] == $sco_info['AdobeConnectSco']['folder-id']) {
					return $license['quota'];
				}
			}
		}
		if (!empty($seminar_licenses['user-webinar-licenses']['sco'])) {
			foreach ($seminar_licenses['user-webinar-licenses']['sco'] as $license) {
				if ($license['sco-id'] == $sco_info['AdobeConnectSco']['folder-id']) {
					return $license['quota'];
				}
			}
		}

		//Not found in cached version.  Pull fresh
		$seminar_licenses = $this->request(array('action' => 'sco-seminar-licenses-list', 'sco-id' => $seminar_root_sco_id,));
		$named_licenses = $this->request(array('action' => 'sco-seminar-licenses-list', 'sco-id' => $seminar_root_sco_id, 'user-webinar-selected' => 'true'));
		if (!empty($named_licenses['user-webinar-licenses'])) {
			$seminar_licenses['user-webinar-licenses'] = $named_licenses['user-webinar-licenses'];
		}
		Cache::write($cacheKey, $seminar_licenses);
		if (!empty($seminar_licenses['seminar-licenses']['sco'])) {
			foreach ($seminar_licenses['seminar-licenses']['sco'] as $license) {
				if ($license['sco-id'] == $sco_info['AdobeConnectSco']['folder-id']) {
					return $license['quota'];
				}
			}
		}
		if (!empty($seminar_licenses['user-webinar-licenses']['sco'])) {
			foreach ($seminar_licenses['user-webinar-licenses']['sco'] as $license) {
				if ($license['sco-id'] == $sco_info['AdobeConnectSco']['folder-id']) {
					return $license['quota'];
				}
			}
		}
		return 0;
	}

	/**
	 * Ovverwrite of the exists() function, to facilitate saves
	 * (assume this is called within the save() function, so existance has already been established)
	 */
	public function exists($id = null) {
		if (isset($this->data[$this->primaryKey]) && !empty($this->data[$this->primaryKey])) {
			return true;
		}
		if (isset($this->data[$this->alias][$this->primaryKey]) && !empty($this->data[$this->alias][$this->primaryKey])) {
			return true;
		}
		if (isset($this->id) && !empty($this->id)) {
			$initial = $this->request;
			$this->request = array();
			$read = $this->read(null, $this->id);
			$this->request = $initial;
			if (isset($read[$this->alias][$this->primaryKey]) && !empty($read[$this->alias][$this->primaryKey])) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Reads a single Sco
	 *
	 * @param array $fields See Model::read() (ignored)
	 * @param boolean $id See Model::read()
	 * @return array
	 */
	public function read($fields = null, $id = true) {
		if (empty($id) || !is_numeric($id)) {
			return $this->error("AdobeConnectSco::read() invalid ID");
		}
		return $this->find('info', $id);
	}

	/**
	 * Deletes a single Sco
	 * note: hijacked the default functionality to access the datasource directly.
	 * @param boolean $id
	 * @return bool
	 */
	public function delete($id = 0, $cascade = true) {
		if (empty($id)) {
			$id = $this->id;
		}
		if (empty($id)) {
			return false;
		}
		$this->request['action'] = "sco-delete";
		$this->request['sco-id'] = $id;
		$db = ConnectionManager::getDataSource($this->useDbConfig);
		$response = $db->delete($this, $id);
		if (!empty($response)) {
			return true;
		}
		if (isset($this->response['status']['code']) && $this->response['status']['code']=="no-data") {
			return true;
		}
		return false;
	}
	/**
	 * Moves an SCO $sco_id to $folder_id
	 **/
	public function move($sco_id, $folder_id) {
		if (empty($sco_id)) {
			$sco_id = $this->id;
		}
		if (empty($sco_id) || empty($folder_id)) {
			return false;
		}
		$this->request['action'] = "sco-move";
		$this->request['sco-id'] = $sco_id;
		$this->request['folder-id'] = $folder_id;
		$db = ConnectionManager::getDataSource($this->useDbConfig);
		$response = $db->request($this);
		if (!empty($response)) {
			return true;
		}
		if (isset($this->response['status']['code']) && $this->response['status']['code']=="no-data") {
			return true;
		}
		return false;
	}

	/**
	 * Custom Find: akin to 'first', requires ID for input. see read()
	 * $this->AdobeConnectSco->find('info', 12345);
	 * @param string $state
	 * @param array $query
	 * @param array $results
	 */
	protected function _findInfo($state, $query = array(), $results = array()) {
		if ($state == 'before') {
			$this->request = array("action" => "sco-info");
			$id = 0;
			if (isset($query[0]) && !empty($query[0])) {
				$id = $query[0];
			} elseif (isset($query['conditions']['id']) && !empty($query['conditions']['id'])) {
				$id = $query['conditions']['id'];
			} elseif (isset($query['conditions'][$this->primaryKey]) && !empty($query['conditions'][$this->primaryKey])) {
				$id = $query['conditions'][$this->primaryKey];
			} elseif (isset($query['conditions'][$this->alias.'.id']) && !empty($query['conditions'][$this->alias.'.id'])) {
				$id = $query['conditions'][$this->alias.'.id'];
			} elseif (isset($query['conditions'][$this->alias.'.'.$this->primaryKey]) && !empty($query['conditions'][$this->alias.'.'.$this->primaryKey])) {
				$id = $query['conditions'][$this->alias.'.'.$this->primaryKey];
			}
			if (empty($id)) {
				return false;
			}
			$this->request['sco-id'] = $id;
			$query = Set::merge(array('order' => null, 'recursive' => null, 'conditions' => null, 'fields' => null,), $query);
			$query = $this->_paginationParams($query);
			return $query;
		} else {
			if (isset($results['sco'])) {
				$return =  array($this->alias => $results['sco']);
				if (isset($query['recursive']) && $query['recursive'] > -1 && in_array($return[$this->alias]['type'], $this->typesWithChildren)) {
					$children = $this->find("contents", $return[$this->alias][$this->primaryKey]);
					$return['Contents'] = array();
					foreach ( $children as $child ) {
						$return['Contents'][] = $child[$this->alias];
					}
				}
				return $return;
			}
			return array();
		}
	}

	/**
	 * Custom Find: akin to 'all', allows a simple search, globally.
	 *
	 * The sco-search-by-field action searches the content of some types of SCOs for the query string. The search includes folders, training courses, curriculums, meetings, content, and archives.
	 * To search for multi-word terms with spaces between the words, search only on the first word in the term and use a wildcard at the end.
	 *
	 * $this->AdobeConnectSco->find('search', 'my meeting');
	 * $this->AdobeConnectSco->find('search', array('conditions' => array('name' => 'my meeting')));
	 * // you can specify other filters as secondary conditions (NOTE: SEACH CONDITONS MUST BE FIRST)
	 * $this->AdobeConnectSco->find('search', array('conditions' => array('name' => 'my meeting', 'type' => 'meeting')));
	 * // you can use wildcards (*,?) but you can not start a query with them
	 * $this->AdobeConnectSco->find('search', array('conditions' => array('name' => 'my*meeting', 'type' => 'meeting')));
	 * @param string $state
	 * @param array $query
	 * @param array $results
	 */
	protected function _findSearch($state, $query = array(), $results = array()) {
		if ($state == 'before') {
			$this->request = array('action' => 'sco-search-by-field');
			if (isset($query[0]) && !empty($query[0])) {
				$this->request['field'] = 'name';
				$this->request['query'] = $query[0];
				$query['conditions'] = array();
			} elseif (isset($query['conditions']) && !empty($query['conditions']) && is_string($query['conditions'])) {
				$this->request['field'] = 'name';
				$this->request['query'] = $query['conditions'];
				$query['conditions'] = array();
			} elseif (isset($query['conditions']) && !empty($query['conditions']) && is_array($query['conditions'])) {
				if (isset($query['conditions']['name']) && !empty($query['conditions']['name'])) {
					$this->request['field'] = 'name';
					$this->request['query'] = $query['conditions']['name'];
					unset($query['conditions']['name']);
				} else {
					$this->request['field'] = key($query['conditions']);
					$this->request['query'] = current($query['conditions']);
				}
			} else {
				return $this->error("AdobeConnectSco::findSearch() you must specify a search term... either as simple string for name, or as the first key => value of the conditions array");
			}
			// 'sco-search-by-field' doesn't support multi-term inputs
			if (strpos($this->request['query'], ' ')!==false) {
				$this->request['query'] = preg_replace('#\s\s+#', ' ', $this->request['query']);
				$this->request['query'] = preg_replace('#\b([a-zA-Z0-9\.,;:-\_]{0,3})\b#', '', $this->request['query']);
				$this->request['query'] = trim($this->request['query']);
			}
			// 'sco-search-by-field' doesn't support multi-term inputs
			if (strpos($this->request['query'], ' ')!==false) {
				$this->request['action'] = 'sco-search';
				unset($this->request['field']);
			}
			// defaults --> settings
			$query = Set::merge(array('recursive' => 0, 'order' => 'date-modified desc'), $query);
			$this->request = Set::merge($this->request, $this->parseFiltersFromQuery($query));
			$query = $this->_paginationParams($query);
			return $query;
		} else {
			$unformatted = Set::extract($results, '/sco-search-by-field-info/sco/.');
			$results = array();
			foreach ( $unformatted as $node ) {
				if (isset($query['recursive']) && $query['recursive'] > -1 && in_array($return[$this->alias]['type'], $this->typesWithChildren)) {
					$returnNode = array($this->alias => $node);
					$children = $this->find('contents', $returnNode[$this->alias][$this->primaryKey]);
					$returnNode['Contents'] = array();
					foreach ( $children as $child ) {
						$returnNode['Contents'][] = $child[$this->alias];
					}
					$results[] = $returnNode;
				} else {
					$results[] = array($this->alias => $node);
				}
			}
			return $results;
		}
	}

	/**
	 * Custom Find: akin to 'all', searches within a sco, optionally filter with conditions
	 * routes to recursive or non-recursive
	 * $this->AdobeConnectSco->find('contents', 12345);
	 * $this->AdobeConnectSco->find('contents', array('sco-id' => 12345, 'conditions' => array('icon' => 'archive')));
	 * @param string $state
	 * @param array $query
	 * @param array $results
	 */
	protected function _findContents($state, $query = array(), $results = array()) {
		if (isset($query['recursive']) && !empty($query['recursive'])) {
			return $this->_findContentsRecursive($state, $query, $results);
		}
		return $this->_findContentsNonRecursive($state, $query, $results);
	}
	/**
	 * Custom Find: akin to 'all', searches within a sco, optionally filter with conditions Recursive
	 * $this->AdobeConnectSco->find('contents', 12345);
	 * $this->AdobeConnectSco->find('contents', array('sco-id' => 12345, 'conditions' => array('icon' => 'archive')));
	 * @param string $state
	 * @param array $query
	 * @param array $results
	 */
	protected function _findContentsRecursive($state, $query = array(), $results = array()) {
		if ($state == 'before') {
			$this->request = array("action" => "sco-expanded-contents");
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
				return $this->error("AdobeConnectSco::findContentsRecursive() you must include a value for to find('contents', \$options['sco-id'])");
			}
			$query['conditions']['sco-id not'] = $this->request["sco-id"];
			$this->request = Set::merge($this->request, $this->parseFiltersFromQuery($query));
			$query = $this->_paginationParams($query);
			return $query;
		} else {
			$unformatted = Set::extract($results, "/expanded-scos/sco/.");
			$results = array();
			foreach ( $unformatted as $node ) {
				$results[] = array($this->alias => $node);
			}
			return $results;
		}
	}

	/**
	 * Custom Find: akin to 'all', searches within a sco, optionally filter with conditions Non Recursive
	 * $this->AdobeConnectSco->find('contents', 12345);
	 * $this->AdobeConnectSco->find('contents', array('sco-id' => 12345, 'conditions' => array('icon' => 'archive')));
	 * @param string $state
	 * @param array $query
	 * @param array $results
	 */
	protected function _findContentsNonRecursive($state, $query = array(), $results = array()) {
		if ($state == 'before') {
			$this->request = array("action" => "sco-contents");
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
				return $this->error("AdobeConnectSco::findContentsNonRecursive() you must include a value for to find('contents', \$options['sco-id'])");
			}
			$this->request = Set::merge($this->request, $this->parseFiltersFromQuery($query));
			$query = $this->_paginationParams($query);
			return $query;
		} else {
			$unformatted = Set::extract($results, "/scos/sco/.");
			$results = array();
			foreach ( $unformatted as $node ) {
				$results[] = array($this->alias => $node);
			}
			return $results;
		}
	}

	/**
	 * Custom Find: akin to 'all', allows a simple search, globally.  Seems to require two search parameters in the query, optionally filter with conditions
	 *
	 * The sco-search action searches the content of some types of SCOs for the query string. The types of SCOs searched include presentation archives, meeting archives, and the presentation components of a course or curriculum. A presentation that is included in a course returns two sets of results, one for the actual presentation and one for the course. The search does not include the SCO name or any metadata about the SCO stored in the database.
	 * @link http://help.adobe.com/en_US/AcrobatConnectPro/7.5/WebServices/WS26a970dc1da1c212717c4d5b12183254583-8000.html#WS5b3ccc516d4fbf351e63e3d11a171dd627-7d5e
	 *
	 * $this->AdobeConnectSco->find('searchcontent', 'welcome training');
	 * $this->AdobeConnectSco->find('searchcontent', array('query' => 'welcome training', 'conditions' => array('type' => 'content')));
	 * @param string $state
	 * @param array $query
	 * @param array $results
	 */
	protected function _findSearchcontent($state, $query = array(), $results = array()) {
		if ($state == 'before') {
			$this->request = array("action" => "sco-search");
			if (isset($query['query']) && !empty($query['query']) && is_string($query['query'])) {
				$this->request["query"] = $this->escapeString($query['query']);
			} elseif (isset($query['conditions']['query']) && !empty($query['conditions']['query']) && is_string($query['conditions']['query'])) {
				$this->request["query"] = $this->escapeString($query['conditions']['query']);
			} elseif (isset($query[0]) && !empty($query[0]) && is_string($query[0])) {
				$this->request["query"] = $this->escapeString($query[0]);
			}
			if (!isset($this->request["query"]) || empty($this->request["query"])) {
				return $this->error("AdobeConnectSco::findSearchcontent() you must include a value for to find('search', \$options['query'])");
			}
			$query = Set::merge(array('recursive' => 0, 'order' => 'date-modified desc'), $query);
			$this->request = Set::merge($this->request, $this->parseFiltersFromQuery($query));
			$query = $this->_paginationParams($query);
			return $query;
		} else {
			$unformatted = Set::extract($results, "/sco-search-info/sco/.");
			$results = array();
			foreach ( $unformatted as $node ) {
				$results[] = array($this->alias => $node);
				if (isset($query['recursive']) && $query['recursive'] > 0) {
					die("//todo: if container, get contents, get path, (append)");
				}
			}
			return $results;
		}
	}

	/**
	 * Custom Find: path (folder heirarchy)
	 *
	 * $this->AdobeConnectSco->find('path', $sco_id);
	 * @param string $state
	 * @param array $query
	 * @param array $results
	 */
	protected function _findPath($state, $query = array(), $results = array()) {
		if ($state == 'before') {
			return $this->_findInfo($state, $query, $results);
		}
		$return = $this->_findInfo($state, $query, $results);
		if (isset($return[$this->alias]['folder-id'])) {
			$path = array();
			$node = $return;
			while (!empty($node)) {
				$path[($node[$this->alias]['sco-id'])] = $node[$this->alias]['name'];
				if (isset($node[$this->alias]['folder-id']) && !empty($node[$this->alias]['folder-id'])) {
					$node = $this->find('info', $node[$this->alias]['folder-id']);
				} else {
					$node = false;
				}
			}
			return array_reverse($path, true);
		}
		return $return;
	}


	/**
	 * A jankity overwrite of the _findCount method
	 * Needed to clean saves
	 *
	 * @param mixed $conditions
	 * @param mixed $fields
	 * @param mixed $order
	 * @param integer $limit
	 * @param integer $page
	 * @param integer $recursive
	 * @param array $extra
	 * @return array The results of the call to the web service
	 */
	public function _findCount($state, $query = array(), $results = array()) {
		$initial = $this->request;
		$return = $this->_findInfo($state, $query, $results);
		$this->request = $initial;
		return $return;
	}
}
