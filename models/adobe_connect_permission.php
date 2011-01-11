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
		'permissions' => true,
		);


	/**
	* Creates/Saves a Permission
	*
	* // TODO: implement custom field updating too: http://help.adobe.com/en_US/connect/8.0/webservices/WS5b3ccc516d4fbf351e63e3d11a171dd0f3-7fe8_SP1.html
	*
	* @param array $data See array('permission-id' => $permission, 'acl-id' => $scoId, 'principal-id' => $principalId)
	* @param boolean $validate true (if true, verifies the type of permission to assign)
	* @param array $fieldList (unused)
	* @return boolean
	*/
	public function save($data = null, $validate = true, $fieldList = array()) {
		$initial = $this->request;
		$this->request = array();
		if (isset($data[$this->alias])) {
			$data = $data[$this->alias];
		}
		// verify that we have valid inputs
		if (isset($data['permission']) && !isset($data['permission-id'])) {
			$data['permission-id'] = $data['permission'];
		}
		if (isset($data['sco-id']) && !isset($data['acl-id'])) {
			$data['acl-id'] = $data['sco-id'];
		}
		if (!isset($data['permission-id'])) {
			$this->errors[] = $error = "{$this->alias}::Save: Missing permission key ".json_encode($data);
    		trigger_error(__d('adobe_connect', $error, true), E_USER_WARNING);
    		$this->request = $initial;
    		return false;
    	} elseif (!isset($data['principal-id'])) {
			$this->errors[] = $error = "{$this->alias}::Save: Missing principal-id key ".json_encode($data);
			trigger_error(__d('adobe_connect', $error, true), E_USER_WARNING);
			$this->request = $initial;
    		return false;
    	} elseif (!isset($data['acl-id'])) {
			$this->errors[] = $error = "{$this->alias}::Save: Missing sco-id key ".json_encode($data);
    		trigger_error(__d('adobe_connect', $error, true), E_USER_WARNING);
    		$this->request = $initial;
    		return false;
    	}
		// here are some permissions keys, keys = meeting, values = non-meetings, basic access levels match
		$permissionsAny = array('view', 'publish');
    	$permissionsOptions = array('host' => 'manage', 'mini-host' => 'manage', 'remove' => 'denied');
    	// verify $data['permission-id'] is a valid permission, otherwise force to "view"
    	if (!in_array($data['permission-id'], $permissionsAny) && !in_array($data['permission-id'], $permissionsOptions) && !in_array($data['permission-id'], array_keys($permissionsOptions))) {
			$data['permission-id'] = 'view';
    	}
    	if ((is_array($validate) && !empty($validate['validate'])) || !empty($validate)) {
    		if (!isset($this->AdobeConnectPrincipal) || !isset($this->AdobeConnectSco)) {
				App::import('model', 'AdobeConnect.AdobeConnectPrincipal');
				$this->AdobeConnectPrincipal = ClassRegistry::init('AdobeConnectPrincipal');
				App::import('model', 'AdobeConnect.AdobeConnectSco');
				$this->AdobeConnectSco = ClassRegistry::init('AdobeConnectSco');
			}
    		// validate Principal exists
    		$principal = $this->AdobeConnectPrincipal->read(null, $data['principal-id']);
    		if (empty($principal) || !isset($principal[$this->AdobeConnectPrincipal->alias][$this->AdobeConnectPrincipal->primaryKey]) || $principal[$this->AdobeConnectPrincipal->alias][$this->AdobeConnectPrincipal->primaryKey]!=$data['principal-id']) {
    			$this->errors[] = $error = "{$this->alias}::Save: Unable to verify that Principal (user) exists";
    			trigger_error(__d('adobe_connect', $error, true), E_USER_WARNING);
    			$this->request = $initial;
    			return false;
    		}
    		// validate SCO exists
    		$sco = $this->AdobeConnectSco->read(null, $data['acl-id']);
    		if (empty($sco) || !isset($sco[$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey]) || $sco[$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey]!=$data['acl-id']) {
    			$this->errors[] = $error = "{$this->alias}::Save: Unable to verify that SCO exists";
    			trigger_error(__d('adobe_connect', $error, true), E_USER_WARNING);
    			$this->request = $initial;
    			return false;
    		}
    		// validate type of permission is appropriate & re-map permissions based on meeting or not
    		$isMeeting = in_array($sco[$this->AdobeConnectSco->alias]['type'], array('meeting', 'session', 'event'));
			if (in_array($data['permission-id'], $permissionsAny) || ($isMeeting && in_array($data['permission-id'], array_keys($permissionsOptions))) || (!$isMeeting && in_array($data['permission-id'], $permissionsOptions))) {
				// no need to validate further
			} elseif ($isMeeting && in_array($data['permission-id'], $permissionsOptions) && !in_array($data['permission-id'], array_keys($permissionsOptions))) {
				$meetingOptions = array_flip($permissionsOptions);
				$data['permission-id'] = $meetingOptions[$data['permission-id']];
			} elseif (!$isMeeting && !in_array($data['permission-id'], $permissionsNonMeetings) && !in_array($data['permission-id'], array_keys($permissionsOptions))) {
				$data['permission-id'] = $permissionsOptions[$data['permission-id']];
			} else {
				$this->errors[] = $error = "{$this->alias}::Save: Unable to verify permission [{$data['permission-id']}] for type of SCO [{$sco[$this->AdobeConnectSco->alias]['type']}]";
    			trigger_error(__d('adobe_connect', $error, true), E_USER_WARNING);
    			$this->request = $initial;
    			return false;
			}
		}
		$this->request = $data;
		$this->request['action'] = "permissions-update";
		$db =& ConnectionManager::getDataSource($this->useDbConfig);
		$response = $db->request($this, $this->request);
		$this->request = $initial;
		if (isset($response['Status']['code']) && $response['Status']['code']=='ok') {
			return true;
		}
		return false;
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
	* Deletes all permissions for a SCO
	* @link http://help.adobe.com/en_US/AcrobatConnectPro/7.5/WebServices/WS26a970dc1da1c212717c4d5b12183254583-8000.html#WS5b3ccc516d4fbf351e63e3d11a171ddf77-7edb
	* Resets all permissions any principals have on a SCO to the permissions of its parent SCO. If the parent has no permissions set, the child SCO will also have no permissions.
	* note: hijacked the default functionality to access the datasource directly.
	* @param int $scoId
	* @param int $principalId (optional, if set, will only remove the permissions for this $scoId/$principalId)
	* @return bool
	*/
	public function delete($scoId = 0, $principalId = 0) {
		if (empty($scoId)) {
			$scoId = $this->id;
		}
		if (empty($scoId)) {
			return false;
		}
		if (!empty($principalId)) {
			return $this->assign($scoId, $principalId, "remove");
		}
		$this->request = array(
			'action' => "permissions-reset",
			'acl-id' => $scoId,
			);
		$db =& ConnectionManager::getDataSource($this->useDbConfig);
		$response = $db->request($this, $this->request);
		if (isset($response['Status']['code']) && $this->response['Status']['code']=="ok") {
			return true;
		} elseif (isset($response['Status']['code']) && $this->response['Status']['code']=="no-data") {
    		return true;
    	}
		return false;
	}


	/**
	* Custom Find: akin to 'first', requires 'acl-id' AND 'principal-id'
	*
	* $this->find("permissions", array('acl-id' => $scoId, 'principal-id' => $principalId));
	*
	* @param string $state
	* @param array $query
	* @param array $results
	*/
	protected function _findPermissions($state, $query = array(), $results = array()) {
		if ($state == 'before') {
			$this->request["action"] = "permissions-info";
			if (isset($query['sco-id']) && !isset($query['acl-id'])) {
				$query['acl-id'] = $query['sco-id'];
			}
			if (!isset($query['principal-id'])) {
				$this->errors[] = $error = "Find: Missing principal-id key";
				trigger_error(__d('adobe_connect', $error, true), E_USER_WARNING);
				return false;
			} elseif (!isset($query['acl-id'])) {
				$this->errors[] = $error = "Find: Missing acl-id (sco-id) key";
				trigger_error(__d('adobe_connect', $error, true), E_USER_WARNING);
				return false;
			}
			$this->request['principal-id'] = $query['principal-id'];
			$this->request['acl-id'] = $query['acl-id'];
			$query = $this->_paginationParams($query);
			return $query;
		} else {
			if (isset($results['Permission']['permission-id'])) {
    			return $results['Permission']['permission-id'];
    		} elseif (isset($this->response['Permission']['permission-id'])) {
    			return $this->response['Permission']['permission-id'];
    		}
			return false;
		}
	}

	/**
	* A shortcut function for: $this->find("permissions", array('acl-id' => $scoId, 'principal-id' => $principalId));
	*
	* $this->get($scoId, $principalId);
	*
	* @param string $state
	* @param array $query
	* @param array $results
	*/
	public function get($scoId, $principalId) {
		return $this->find("permissions", array('acl-id' => $scoId, 'principal-id' => $principalId));
	}

	/**
	* A shortcut function for: $this->save();
	*
	* $this->assign($scoId, $principalId, "read");
	*
	* @param string $state
	* @param array $query
	* @param array $results
	*/
	public function assign($scoId, $principalId, $permissionId) {
		return $this->save(array(
			'acl-id' => $scoId,
			'principal-id' => $principalId,
			'permission-id' => $permissionId,
			));
	}

}

?>
