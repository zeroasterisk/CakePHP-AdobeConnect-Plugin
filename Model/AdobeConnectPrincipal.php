<?php
/**
* Plugin model for "Adobe Connect Principal".
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
class AdobeConnectPrincipal extends AdobeConnectAppModel {

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
		'principal-id' => array('type' => 'integer'),
		'email' => array('type' => 'string', 'length' => '255'),
		'first-name' => array('type' => 'string', 'length' => '255'),
		'last-name' => array('type' => 'string', 'length' => '255'),
		'description' => array('type' => 'text'),
		'has-children' => array('type' => 'boolean'),
		'login' => array('type' => 'string', 'length' => '255'),
		'name' => array('type' => 'string', 'length' => '255'),
		'password' => array('type' => 'string', 'length' => '255'),
		'principal-id' => array('type' => 'string', 'length' => '255'),
		'send-email' => array('type' => 'boolean'),
		'type' => array('type' => 'string', 'length' => '255'),
		'ext-login' => array('type' => 'string', 'length' => '255'),
		'account-id' => array('type' => 'integer'),
		'disabled' => array('type' => 'datetime'),
		'is-hidden' => array('type' => 'boolean'),
		'is-primary' => array('type' => 'boolean'),
		'preferences' => array('type' => 'string', 'length' => '255'),
		'acl-id' => array('type' => 'integer'),
		'lang' => array('type' => 'string', 'length' => '255'),
		'time-zone-id' => array('type' => 'string', 'length' => '255'),
		);


	/**
	* Set the primaryKey
	* @var string
	*/
	public $primaryKey = 'principal-id';

	/**
	* The custom find methods (defined below)
	* @var array
	*/
	public $findMethods = array(
		'search' => true,
		'info' => true,
		);


	/**
	* Creates/Saves a Principal
	*
	* // TODO: implement custom field updating too: http://help.adobe.com/en_US/connect/8.0/webservices/WS5b3ccc516d4fbf351e63e3d11a171dd0f3-7fe8_SP1.html
	*
	* @param array $data See Model::save()
	* @param boolean $validate See Model::save() false
	* @param array $fieldList See Model::save()
	* @return array $data saved
	*/
	public function save($data = null, $validate = false, $fieldList = array()) {
		$initial = $this->request;
		$this->request = array();
		if (isset($data[$this->alias])) {
			// strip down to just this model's data
			$data = $data[$this->alias];
		}
		// merge with existing records (needed so we can update vs. create & so we have all the needed fields)
		if (isset($data[$this->primaryKey]) && !empty($data[$this->primaryKey])) {
			$existing = $this->read(null, $data[$this->primaryKey]);
		} elseif (isset($data['login']) && !empty($data['login'])) {
			$existing = $this->find('search', array('conditions' => array('login' => $data['login'])));
		} elseif (isset($data['email']) && !empty($data['email'])) {
			$existing = $this->find('search', array('conditions' => array('email' => $data['email'])));
		} elseif (isset($data['name']) && !empty($data['name'])) {
			$existing = $this->find('search', array('conditions' => array('name' => $data['name'])));
		}
		if (isset($existing) && !empty($existing)) {
			if (isset($existing[0])) {
				$existing = $existing[0];
			}
			if (isset($existing[$this->alias][$this->primaryKey]) && !empty($existing[$this->alias][$this->primaryKey])) {
				$data = array_merge($existing[$this->alias], $data);
				unset($data['disabled']);
			} elseif (isset($data[$this->primaryKey])) {
				unset($data[$this->primaryKey]);
			}
		}

		$principalAllowedTypes = array('admins', 'authors', 'course-admins', 'event-admins', 'event-group', 'everyone', 'external-group', 'external-user', 'group', 'guest', 'learners', 'live-admins', 'seminar-admins', 'user', );
		if (!isset($data['type']) || !in_array($data['type'], $principalAllowedTypes)) {
			$data['type'] = 'user';
		}
		if (!isset($data['has-children'])) {
			$data['has-children'] = (strpos($data['type'], "group")!==false);
		}
		if (!isset($data['send-email']) && $data['type'] == 'user') {
			$data['send-email'] = $this->defaultSendEmailBool;
		}

		if ($data['type'] == 'user') {
			$data['has-children'] = false;
			$requiredKeys = array('last-name', 'first-name', 'name', 'login', 'email');
			$missingFields = array_diff_key(array_flip($requiredKeys), $data);
			if (!empty($missingFields)) {
				return $this->error("{$this->alias}::Save: missing required field: ".implode(', ',array_keys($missingFields)));
			}
		}
		// Add the content type in so OAuth won't use the body in the signature
		$this->request = $data;
		$this->request['action'] = "principal-update";
		$result = parent::save(array($this->alias => $data), $validate, $fieldList);
		// the API response is on this->response (set there by the source)
		$result = $this->responseCleanAttr($this->response);
		if (isset($result['status']['invalid'])) {
			return $this->error("{$this->alias}::Save: Invalid Fields: ".json_encode($result['status']['invalid']));
		}
		if (empty($result['principal'][$this->primaryKey]) && !empty($data[$this->primaryKey])) {
			// on "update" we don't get back any data besides success...
			//   so we set the PK from the input $data
			$result['principal'][$this->primaryKey] = $data[$this->primaryKey];
		}
		if (empty($result['principal'][$this->primaryKey])) {
			$this->request = $initial;
			return false;
		}
		$this->id = $result['principal'][$this->primaryKey];
		$this->setInsertID($this->id);
		$result[$this->alias][$this->primaryKey] = $this->id;
		$result[$this->alias] = array_merge($result['principal'], $result[$this->alias]);
		unset($result['principal']);

		// Did we initially pass in a 'password' to save?
		if (isset($data['password']) && !empty($data['password'])) {
			/*
			 * run a secondary API call to update the user password
			 * @link http://help.adobe.com/en_US/AcrobatConnectPro/7.5/WebServices/WS26a970dc1da1c212717c4d5b12183254583-8000.html#WS5b3ccc516d4fbf351e63e3d11a171dd627-7d12
			 */
			$db = ConnectionManager::getDataSource($this->useDbConfig);
			$this->request = array();
			$passwordData = array('action' => "user-update-pwd", "user-id" => $this->id, "password" => $data['password'], "password-verify" => $data['password']);
			$passwordResult = $db->request($this, $passwordData);
			if (isset($passwordResult['status']['invalid'])) {
				return $this->error("{$this->alias}::Save: Unable to update password: ".json_encode($passwordResult['status']['invalid']));
			}
		}
		return $result;
	}

	/**
	* Ovverwrite of the exists() function, to facilitate saves
	* (assume this is called within the save() function, so existance has already been established)
	*/
	public function exists($id = null) {
		if (!empty($id)) {
			return true;
		}
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
	* Reads a single Principal
	*
	* @param array $fields See Model::read() (ignored)
	* @param boolean $id See Model::read()
	* @return array
	*/
	public function read($fields = null, $id = true) {
		if (empty($id) || !is_numeric($id)) {
			return $this->error("Error: Read: invalid ID");
		}
		return $this->find('info', $id);
	}

	/**
	* Deletes a single Principal
	* note: hijacked the default functionality to access the datasource directly.
	*
	* @param int $id
	* @param boolean $cascade (ignored)
	* @return bool
	*/
	public function delete($id = null, $cascade = true) {
		if (empty($id)) {
			$id = $this->id;
		}
		if (empty($id)) {
			return false;
		}
		$this->request['action'] = "principals-delete";
		$this->request['principal-id'] = $id;
		$db = ConnectionManager::getDataSource($this->useDbConfig);
		$response = $db->delete($this, $id);
		if (!empty($response)) {
			return true;
		}
		if (isset($this->response['status']['@code']) && $this->response['status']['@code']=="no-data") {
			return true;
		}
		return false;
	}

	/**
	* Custom Find: akin to 'first', requires ID for input. see read()
	* @param string $state
	* @param array $query
	* @param array $results
	*/
	protected function _findInfo($state, $query = array(), $results = array()) {
		if ($state == 'before') {
			$this->request["action"] = "principal-info";
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
			$this->request['principal-id'] = $id;
			$query = $this->_paginationParams($query);
			return $query;
		} else {
			$unformatted = array();
			if (isset($results['contact'])) {
				$unformatted = array_merge($unformatted, $results['contact']);
			}
			if (isset($results['principal'])) {
				$unformatted = array_merge($unformatted, $results['principal']);
			}
			if (!empty($unformatted)) {
				return array($this->alias => $unformatted);
			}
			return array();
		}
	}

	/**
	* Custom Find: akin to 'all', allows a simple search based on core fields
	* $this->Principal->find('search', array('conditions' => array('email' => 'sp_devadmin%')));
	* @param string $state
	* @param array $query
	* @param array $results
	*/
	protected function _findSearch($state, $query = array(), $results = array()) {
		if ($state == 'before') {
			$this->request["action"] = "principal-list";
			$this->request["method"] = "get";
			$this->request = Set::merge($this->request, $this->parseFiltersFromQuery($query));
			$query = $this->_paginationParams($query);
			return $query;
		} else {
			$unformatted = Set::extract($results, "/principal-list/principal/.");
			$results = array();
			foreach ( $unformatted as $node ) {
				$results[] = array($this->alias => $node);
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
