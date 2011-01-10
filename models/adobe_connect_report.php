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
	public $name ='AdobeConnectReport';
	
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
	* Creates/Saves a Report
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
	* Reads a single Report
	*
	* @param array $fields See Model::read() (ignored)
	* @param boolean $id See Model::read()
	* @return array
	*/
	public function read($fields = null, $id = true) {
		return $this->find('info', $id);
	}
	
	/**
	* Deletes a single Report
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
		$this->request['action'] = "report-delete";
		$this->request['report-id'] = $id;
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
	
}

?>
