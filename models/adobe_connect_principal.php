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
	* The name of this model
	* @var name
	*/
	public $name ='AdobeConnectPrincipal';
	
	/**
	* Tell the model that we don't use a table
	* @var name
	*/
	public $useTable = false;
	
	/**
	* The datasource this model uses
	* @var name
	*/
	public $useDbConfig = 'adobeConnect';
	
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
	public $_findMethods = array(
		'search' => true,
		'info' => true,
		);
	
	
	/**
	* Creates/Saves a Principal
	* @param array $data See Model::save()
	* @param boolean $validate See Model::save() false
	* @param array $fieldList See Model::save()
	* @return boolean
	*/
	public function save($data = null, $validate = false, $fieldList = array()) {
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
		if ($data['type'] == 'user') {
			$data['has-children'] = false;
			$requiredKeys = array('last-name', 'first-name', 'name', 'login', 'email');
			$missingFields = array_diff_key(array_flip($requiredKeys), $data);
			if (!empty($missingFields)) {
				$this->errors[] = "ERROR {$this->alias} ".__function__." missing required field: ".implode(', ',array_keys($missingFields));
				return false;
			}
		}
		// Add the content type in so OAuth won't use the body in the signature
		$this->request = $data;
		$this->request['action'] = "principal-update";
		$result = parent::save(array($this->alias => $data), $validate, $fieldList);
		if (isset($this->response['Principal']['principal-id'])) {
			$this->id = $this->response['Principal']['principal-id'];
			$this->response[$this->alias][$this->primaryKey] = $this->id;
			$result[$this->alias][$this->primaryKey] = $this->id;
		} else {
			return false;
		}
		if ($result) {
			$this->setInsertID($this->response[$this->alias][$this->primaryKey]);
		}
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
			echo dumpthis($this->request);
			$read = $this->read(null, $this->id);
			echo dumpthis($this->request);
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
		return $this->find('info', $id);
	}
	
	/**
	* Deletes a single Principal
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
		$this->request['action'] = "principals-delete";
		$this->request['principal-id'] = $id;
		$db =& ConnectionManager::getDataSource($this->useDbConfig);
		$response = $db->delete($this, $id);
		if (!empty($response)) {
			return true;
		}
		if (isset($this->response['Status']['code']) && $this->response['Status']['code']=="no-data") {
    		return true;
    	}
		echo dumpthis($response);
		echo dumpthis($this->response);
		return false;
		
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
	
	/**
	* Custom Find: akin to 'first', requires ID for input. see read()
	* // NOTE: might move this to SCO model instead?
	* @param string $state
	* @param array $query
	* @param array $results
	*/
	protected function _findPermissions($state, $query = array(), $results = array()) {
		if ($state == 'before') {
			$this->request["action"] = "permissions-info";
			$pricipalId = 0;
			if (isset($query[0]) && !empty($query[0])) {
				$pricipalId = $query[0];
			} elseif (isset($query['conditions']['id']) && !empty($query['conditions']['id'])) {
				$pricipalId = $query['conditions']['id'];
			} elseif (isset($query['conditions'][$this->primaryKey]) && !empty($query['conditions'][$this->primaryKey])) {
				$pricipalId = $query['conditions'][$this->primaryKey];
			} elseif (isset($query['conditions'][$this->alias.'.id']) && !empty($query['conditions'][$this->alias.'.id'])) {
				$pricipalId = $query['conditions'][$this->alias.'.id'];
			} elseif (isset($query['conditions'][$this->alias.'.'.$this->primaryKey]) && !empty($query['conditions'][$this->alias.'.'.$this->primaryKey])) {
				$pricipalId = $query['conditions'][$this->alias.'.'.$this->primaryKey];
			}
			$scoId = 0;
			if (isset($query[1]) && !empty($query[1])) {
				$scoId = $query[1];
			} elseif (isset($query['conditions']['sco-id']) && !empty($query['conditions']['sco-id'])) {
				$scoId = $query['conditions']['sco-id'];
			} elseif (isset($query['conditions'][$this->alias.'.sco-id']) && !empty($query['conditions'][$this->alias.'.sco-id'])) {
				$scoId = $query['conditions'][$this->alias.'.sco-id'];
			}
			if (empty($pricipalId) || empty($scoId)) {
				return false;
			}
			$this->request['principal-id'] = $pricipalId;
			$this->request['acl-id'] = $scoId;
			$query = $this->_paginationParams($query);
			return $query;
		} else {
			echo dumpthis($results);
			$unformatted = array();
			if (isset($results['Contact'])) {
				$unformatted = array_merge($unformatted, $results['Contact']);
			}
			if (isset($results['Principal'])) {
				$unformatted = array_merge($unformatted, $results['Principal']);
			}
			if (!empty($unformatted)) {
				return array($this->alias => $unformatted);
			}
			return array();
		}
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
			if (isset($results['Contact'])) {
				$unformatted = array_merge($unformatted, $results['Contact']);
			}
			if (isset($results['Principal'])) {
				$unformatted = array_merge($unformatted, $results['Principal']);
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
			if (!empty($query['conditions'])) {
				foreach ( $query['conditions'] as $key => $val ) {
					if (strpos($key, '.')!==false) {
						$keyParts = explode('.', $key);
						$key = array_pop($keyParts);
					}
					if ($key == 'id') {
						$key = $this->primaryKey;
					}
					$this->request["filter-like-{$key}"] = str_replace('*', '%', $val);
				}
			}
			$query = $this->_paginationParams($query);
			return $query;
		} else {
			$unformatted = set::extract($results, "/Principal-list/Principal/.");
			$results = array();
			foreach ( $unformatted as $node ) { 
				$results[] = array($this->alias => $node); 
			}
			return $results;
		}
	}
	
}

?>
