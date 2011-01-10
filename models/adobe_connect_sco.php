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
class AdobeConnectSco extends AdobeConnectAppModel {

	/**
	* The name of this model
	* @var name
	*/
	public $name ='AdobeConnectSco';

	/**
	* The datasource this model uses
	* @var string
	*/
	public $useDbConfig = 'adobeConnect';

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
	public $_findMethods = array(
		'search' => true,
		'contents' => true,
		'contents' => true,
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
			$this->errors[] = $error = "{$this->alias}::Save: Sorry, you must have either a folder-id or a sco-id to save a SCO";
			trigger_error(__d('adobe_connect', $error, true), E_USER_WARNING);
			$this->request = $initial;
			return false;
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
		if (isset($this->response['Status']['Invalid'])) {
			$this->errors[] = $error = "{$this->alias}::Save: Invalid Fields: ".json_encode($this->response['Status']['Invalid']);
			trigger_error(__d('adobe_connect', $error, true), E_USER_WARNING);
			$this->request = $initial;
			return false;
		}
		if (isset($result[$this->alias][$this->primaryKey])) {
			$this->id = $result[$this->alias][$this->primaryKey];
			$this->response[$this->alias][$this->primaryKey] = $this->id;
			$this->setInsertID($this->id);
			$this->request = $initial;
			return $result;
		}
		if (isset($this->response["Sco"]) && !isset($this->response[$this->alias])) {
			$this->response[$this->alias] = $this->response["Sco"];
		}
		if (isset($this->response[$this->alias][$this->primaryKey])) {
			$this->id = $this->response[$this->alias][$this->primaryKey];
			$this->response[$this->alias][$this->primaryKey] = $this->id;
			$result[$this->alias][$this->primaryKey] = $this->id;
			$this->setInsertID($this->id);
			$this->request = $initial;
			return $result;
		}
		$this->request = $initial;
		return $result;
	}

	/**
	* Ovverwrite of the exists() function, to facilitate saves
	* (assume this is called within the save() function, so existance has already been established)
	*/
	public function exists() {
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
			$this->errors[] = "Error: Read: invalid ID";
			return false;
		}
		return $this->find('info', $id);
	}

	/**
	* Deletes a single Sco
	* note: hijacked the default functionality to access the datasource directly.
	* @param boolean $id
	* @return bool
	*/
	public function delete($id = 0) {
		if (empty($id)) {
			$id = $this->id;
		}
		if (empty($id)) {
			return false;
		}
		$this->request['action'] = "sco-delete";
		$this->request['sco-id'] = $id;
		$db =& ConnectionManager::getDataSource($this->useDbConfig);
		$response = $db->delete($this, $id);
		if (!empty($response)) {
			return true;
		}
		if (isset($this->response['Status']['code']) && $this->response['Status']['code']=="no-data") {
    		return true;
    	}
		return false;

	}

	/**
	* Custom Find: akin to 'first', requires ID for input. see read()
	* $this->Sco->find('info', 12345);
	* @param string $state
	* @param array $query
	* @param array $results
	*/
	protected function _findInfo($state, $query = array(), $results = array()) {
		if ($state == 'before') {
			$this->request["action"] = "sco-info";
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
			$query = set::merge(array('order' => null, 'recursive' => null, 'conditions' => null, 'fields' => null,), $query);
			$query = $this->_paginationParams($query);
			return $query;
		} else {
			if (isset($results['Sco'])) {
				$return =  array($this->alias => $results['Sco']);
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
	* $this->Sco->find('search', 'my meeting');
	* $this->Sco->find('search', array('conditions' => array('name' => 'my meeting')));
	* // you can specify other filters as secondary conditions (NOTE: SEACH CONDITONS MUST BE FIRST)
	* $this->Sco->find('search', array('conditions' => array('name' => 'my meeting', 'type' => 'meeting')));
	* // you can use wildcards (*,?) but you can not start a query with them
	* $this->Sco->find('search', array('conditions' => array('name' => 'my*meeting', 'type' => 'meeting')));
	* @param string $state
	* @param array $query
	* @param array $results
	*/
	protected function _findSearch($state, $query = array(), $results = array()) {
		if ($state == 'before') {
			$this->request["action"] = "sco-search-by-field";
			if (isset($query[0]) && !empty($query[0])) {
				$this->request['field'] = 'name';
				$this->request['query'] = $query[0];
				$query['conditions'] = array();
			} elseif (isset($query['conditions']) && !empty($query['conditions']) && is_string($query['conditions'])) {
				$this->request['field'] = 'name';
				$this->request['query'] = $query['conditions'];
				$query['conditions'] = array();
			} elseif (isset($query['conditions']) && !empty($query['conditions']) && is_array($query['conditions'])) {
				$this->request['field'] = key($query['conditions']);
				$this->request['query'] = current($query['conditions']);
			} else {
				die("Error: ".__function__." you must specify a search term... either as simple string for name, or as the first key => value of the conditions array");
			}
			$query = set::merge(array('recursive' => 0, 'order' => 'date-modified desc'), $query);
			$this->request = set::merge($this->request, $this->parseFiltersFromQuery($query));
			$query = $this->_paginationParams($query);
			return $query;
		} else {
			$unformatted = set::extract($results, "/Sco-search-by-field-info/Sco/.");
			$results = array();
			foreach ( $unformatted as $node ) {
				if (isset($query['recursive']) && $query['recursive'] > -1 && in_array($return[$this->alias]['type'], $this->typesWithChildren)) {
					$returnNode = array($this->alias => $node);
					$children = $this->find("contents", $returnNode[$this->alias][$this->primaryKey]);
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
	* $this->Sco->find('contents', 12345);
	* $this->Sco->find('contents', array('sco-id' => 12345, 'conditions' => array('icon' => 'archive')));
	* @param string $state
	* @param array $query
	* @param array $results
	*/
	protected function _findContents($state, $query = array(), $results = array()) {
		if ($state == 'before') {
			$this->request["action"] = "sco-expanded-contents";
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
				die("ERROR: you must include a value for to find('contents', \$options['sco-id'])");
			}
			$query['conditions']['sco-id not'] = $this->request["sco-id"];
			$this->request = set::merge($this->request, $this->parseFiltersFromQuery($query));
			$query = $this->_paginationParams($query);
			return $query;
		} else {
			$unformatted = set::extract($results, "/Expanded-scos/Sco/.");
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
	* $this->Sco->find('searchcontent', 'welcome training');
	* $this->Sco->find('searchcontent', array('query' => 'welcome training', 'conditions' => array('type' => 'content')));
	* @param string $state
	* @param array $query
	* @param array $results
	*/
	protected function _findSearchcontent($state, $query = array(), $results = array()) {
		if ($state == 'before') {
			$this->request["action"] = "sco-search";
			if (isset($query['query']) && !empty($query['query']) && is_string($query['query'])) {
				$this->request["query"] = $this->escapeString($query['query']);
			} elseif (isset($query['conditions']['query']) && !empty($query['conditions']['query']) && is_string($query['conditions']['query'])) {
				$this->request["query"] = $this->escapeString($query['conditions']['query']);
			} elseif (isset($query[0]) && !empty($query[0]) && is_string($query[0])) {
				$this->request["query"] = $this->escapeString($query[0]);
			}
			if (!isset($this->request["query"]) || empty($this->request["query"])) {
				die("ERROR: you must include a value for to find('search', \$options['query'])");
			}
			$query = set::merge(array('recursive' => 0, 'order' => 'date-modified desc'), $query);
			$this->request = set::merge($this->request, $this->parseFiltersFromQuery($query));
			$query = $this->_paginationParams($query);
			return $query;
		} else {
			$unformatted = set::extract($results, "/Sco-search-info/Sco/.");
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

?>
