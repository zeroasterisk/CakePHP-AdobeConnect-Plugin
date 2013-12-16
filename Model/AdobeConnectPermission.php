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
App::uses('AdobeConnectAppModel', 'AdobeConnect.Model');
App::uses('AdobeConnectSco', 'AdobeConnect.Model');
App::uses('AdobeConnectPrincipal', 'AdobeConnect.Model');
class AdobeConnectPermission extends AdobeConnectAppModel {

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
	public $findMethods = array(
		'permissions' => true,
	);

	/**
	 * permissions keys for any type of content: content, meetings, etc.
	 *
	 * @var array
	 */
	public $permissionsAny = array('view', 'publish');

	/**
	 * map for meeting permissions
	 * keys = meeting, values = non-meetings,
	 * basic access levels match
	 *
	 * @var array
	 */
	public $permissionsOptions = array('host' => 'manage', 'mini-host' => 'manage', 'remove' => 'denied');

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
			return $this->error("{$this->alias}::Save: Missing permission key ".json_encode($data));
		} elseif (!isset($data['principal-id'])) {
			return $this->error("{$this->alias}::Save: Missing principal-id key ".json_encode($data));
		} elseif (!isset($data['acl-id'])) {
			return $this->error("{$this->alias}::Save: Missing sco-id key ".json_encode($data));
		}
		// verify $data['permission-id'] is a valid permission, otherwise force to "view"
		if (!in_array($data['permission-id'], $this->permissionsAny) && !in_array($data['permission-id'], $this->permissionsOptions) && !in_array($data['permission-id'], array_keys($this->permissionsOptions))) {
			$data['permission-id'] = 'view';
		}
		if ((is_array($validate) && !empty($validate['validate'])) || !empty($validate)) {
			if (!$this->saveValidate($data)) {
				return false;
			}
		}
		$this->request = $data;
		$this->request['action'] = "permissions-update";
		$db = ConnectionManager::getDataSource($this->useDbConfig);
		$response = $db->request($this, $this->request);
		// the API response is on this->response (set there by the source)
		$result = $this->responseCleanAttr($this->response);
		if (isset($response['status']['code']) && $response['status']['code']=='ok') {
			return true;
		}
		return false;
	}

	/**
	 * Before we save a permission, we may want to validate it
	 * - checks that the Principal exists
	 * - checks that the Sco exists
	 * - checks that the permission is appropriate
	 *
	 * @param array $data
	 * @return boolean
	 * @throws AdobeConnectException
	 */
	public function saveValidate($data) {
		if (!isset($this->AdobeConnectPrincipal) || !isset($this->AdobeConnectSco)) {
			$this->AdobeConnectPrincipal = ClassRegistry::init('AdobeConnect.AdobeConnectPrincipal');
			$this->AdobeConnectSco = ClassRegistry::init('AdobeConnect.AdobeConnectSco');
		}
		// validate Principal exists
		$principal = $this->AdobeConnectPrincipal->read(null, $data['principal-id']);
		if (empty($principal) || !isset($principal['AdobeConnectPrincipal']['principal-id']) || $principal['AdobeConnectPrincipal']['principal-id']!=$data['principal-id']) {
			return $this->error("{$this->alias}::Save: Unable to verify that Principal (user) exists");
		}
		// validate SCO exists
		$sco = $this->AdobeConnectSco->read(null, $data['acl-id']);
		if (empty($sco) || !isset($sco['AdobeConnectSco']['sco-id']) || $sco['AdobeConnectSco']['sco-id']!=$data['acl-id']) {
			return $this->error("{$this->alias}::Save: Unable to verify that SCO exists");
		}
		// validate type of permission is appropriate & re-map permissions based on meeting or not
		$isMeeting = in_array($sco['AdobeConnectSco']['type'], array('meeting', 'session', 'event'));
		if (in_array($data['permission-id'], $this->permissionsAny) || ($isMeeting && in_array($data['permission-id'], array_keys($this->permissionsOptions))) || (!$isMeeting && in_array($data['permission-id'], $this->permissionsOptions))) {
			// no need to validate further
		} elseif ($isMeeting && in_array($data['permission-id'], $this->permissionsOptions) && !in_array($data['permission-id'], array_keys($this->permissionsOptions))) {
			$meetingOptions = array_flip($this->permissionsOptions);
			$data['permission-id'] = $meetingOptions[$data['permission-id']];
		} elseif (!$isMeeting && !in_array($data['permission-id'], $this->permissionsOptions) && in_array($data['permission-id'], array_keys($this->permissionsOptions))) {
			$data['permission-id'] = $this->permissionsOptions[$data['permission-id']];
		} else {
			return $this->error("{$this->alias}::Save: Unable to verify permission [{$data['permission-id']}] for type of SCO [{$sco['AdobeConnectSco']['type']}]");
		}
		return true;
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
	 * Deletes all permissions for a SCO + Principal
	 * @link http://help.adobe.com/en_US/AcrobatConnectPro/7.5/WebServices/WS26a970dc1da1c212717c4d5b12183254583-8000.html#WS5b3ccc516d4fbf351e63e3d11a171ddf77-7edb
	 * Resets all permissions any principals have on a SCO to the permissions of its parent SCO. If the parent has no permissions set, the child SCO will also have no permissions.
	 * note: hijacked the default functionality to access the datasource directly.
	 * @param int $scoId
	 * @param int $principalId (optional, if set, will only remove the permissions for this $scoId/$principalId)
	 * @return bool
	 */
	public function delete($scoId = 0, $principalId = 0) {
		if (empty($scoId) || empty($principalId)) {
			$scoId = $this->id;
		}
		if (empty($scoId)) {
			return $this->error('AdobeConnectPermission::delete() requires 2 arguments: $scoId, $principalId');
		}
		// basic functionaliy, remove for a single Principal
		if (!empty($principalId)) {
			return $this->assign($scoId, $principalId, "remove");
		}
		// blanket functionalty, remove all perms for all Principals - DANGEROUS
		$this->request = array(
			'action' => "permissions-reset",
			'acl-id' => $scoId,
		);
		$db = ConnectionManager::getDataSource($this->useDbConfig);
		$response = $db->request($this, $this->request);
		if (isset($response['status']['code']) && $this->response['status']['code']=="ok") {
			return true;
		} elseif (isset($response['status']['code']) && $this->response['status']['code']=="no-data") {
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
				return $this->error("AdobeConnectPermission::find() Missing principal-id key");
			} elseif (!isset($query['acl-id'])) {
				return $this->error("AdobeConnectPermission::find() Missing acl-id (sco-id) key");
			}
			$this->request['principal-id'] = $query['principal-id'];
			$this->request['acl-id'] = $query['acl-id'];
			$query = $this->_paginationParams($query);
			return $query;
		} else {
			if (empty($results)) {
				return false;
			}
			$results = $this->responseCleanAttr($results);
			if (isset($results['permission']['permission-id'])) {
				return $results['permission']['permission-id'];
			} elseif (isset($this->response['permission']['permission-id'])) {
				return $this->response['permission']['permission-id'];
			}
			return false;
		}
	}

	/**
	 * A shortcut function for: $this->find("permissions", array('acl-id' => $scoId, 'principal-id' => $principalId));
	 *
	 * $this->lookup($scoId, $principalId);
	 *
	 * @param string $state
	 * @param array $query
	 * @param array $results
	 */
	public function lookup($scoId, $principalId) {
		return $this->find("permissions", array('acl-id' => $scoId, 'principal-id' => $principalId));
	}

	/**
	 * This is a legacy alias for lookup()
	 *
	 * @param string $state
	 * @param array $query
	 * @param array $results
	 *-- enable this is you need it --/
	public function get($scoId, $principalId) {
		return $this->lookup($scoId, $principalId);
	}
	*/

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
