<?php
/**
* Plugin model for "Adobe Connect Permission".
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
class AdobeConnectPermission extends AdobeConnectAppModel {
	
	/**
	* The name of this model
	* @var name
	*/
	public $name ='AdobeConnectPermission';
	
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
		'search' => true,
		'info' => true,
		);
	
	
	/**
	* Creates/Saves a Permission
	*
	* // TODO: implement custom field updating too: http://help.adobe.com/en_US/connect/8.0/webservices/WS5b3ccc516d4fbf351e63e3d11a171dd0f3-7fe8_SP1.html
	*
	* @param array $data See Model::save()
	* @param boolean $validate See Model::save() false
	* @param array $fieldList See Model::save()
	* @return boolean
	*/
	public function save($data = null, $validate = false, $fieldList = array()) {
		$this->request = $data;
		$this->request['action'] = "report-update";
		$result = parent::save(array($this->alias => $data), $validate, $fieldList);
		if (isset($this->response['Report'][$this->primaryKey])) {
			$this->id = $this->response['Report'][$this->primaryKey];
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
	* Reads a single Permission
	*
	* @param array $fields See Model::read() (ignored)
	* @param boolean $id See Model::read()
	* @return array
	*/
	public function read($fields = null, $id = true) {
		return $this->find('info', $id);
	}
	
	/**
	* Deletes a single Permission
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
		$this->request['action'] = "pemission-delete";
		$this->request['pemission-id'] = $id;
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
			$this->request['pemission-id'] = $pricipalId;
			$this->request['acl-id'] = $scoId;
			$query = $this->_paginationParams($query);
			return $query;
		} else {
			echo dumpthis($results);
			$unformatted = array();
			if (isset($results['Contact'])) {
				$unformatted = array_merge($unformatted, $results['Contact']);
			}
			if (isset($results['Permission'])) {
				$unformatted = array_merge($unformatted, $results['Permission']);
			}
			if (!empty($unformatted)) {
				return array($this->alias => $unformatted);
			}
			return array();
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
	
}

?>
