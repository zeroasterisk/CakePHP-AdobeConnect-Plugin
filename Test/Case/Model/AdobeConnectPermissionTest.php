<?php
/**
* Testing is really difficult, because we have to assume you have certain details on your Adobe Connect server
*/

App::uses('AdobeConnectAppModel', 'AdobeConnect.Model');
App::uses('AdobeConnectPermission', 'AdobeConnect.Model');
App::uses('Set', 'Utility');
class AdobeConnectPermissionTest extends CakeTestCase {
	public $plugin = 'app';
	public $fixtures = array(
		'plugin.adobe_connect.connect_api_log',
	);
	protected $_testsToRun = array();

	public $deleteIds = array();
	public $rootScoIdForTesting = 10039;

	public function startTest($method) {
		parent::startTest($method);
		$this->AdobeConnectPermission = ClassRegistry::init('AdobeConnect.AdobeConnectPermission');
		$this->AdobeConnectSco = ClassRegistry::init('AdobeConnect.AdobeConnectSco');
		$this->AdobeConnectPrincipal = ClassRegistry::init('AdobeConnect.AdobeConnectPrincipal');
	}
	public function endTest($method) {
		parent::endTest($method);
		if (!empty($this->deleteIds)) {
			foreach ( $this->deleteIds as $id ) {
				$this->AdobeConnectPrincipal->delete($id);
			}
		}
		unset($this->AdobeConnectPermission);
		unset($this->AdobeConnectSco);
		unset($this->AdobeConnectPrincipal);
		ClassRegistry::flush();
	}

	public function testBasics() {
		$this->assertTrue(is_object($this->AdobeConnectPermission));
	}

	public function testForFolder(){
		$permissionOptions = array_values($this->AdobeConnectPermission->permissionsOptions);
		$scos = $this->AdobeConnectSco->find('search', array(
			'conditions' => array(
				'name' => 'test*',
				'type' => 'folder',
			),
			'limit' => 5,
		));
		$this->assertFalse(empty($scos));
		$login = 'testaccount_'.time().__function__.'@domain.com';
		$principal = array(
			'login' => $login,
			'email' => $login,
			'first-name' => 'testaccount_first'.time().rand(0, 1000),
			'last-name' => 'testaccount_last'.time().rand(0, 1000),
			'name' => 'testaccount_first testaccount_last'.time().rand(0, 1000),
		);
		$created = $this->AdobeConnectPrincipal->save($principal);
		$this->assertFalse(empty($created));
		$this->deleteIds[] = $principalId = $created[$this->AdobeConnectPrincipal->alias][$this->AdobeConnectPrincipal->primaryKey];
		foreach ($scos as $sco) {
			// initially the perms should be false
			$perms = $this->AdobeConnectPermission->get($sco['AdobeConnectSco']['sco-id'], $principalId);
			$this->assertFalse($perms);
			// now save the perms
			$saved = $this->AdobeConnectPermission->assign($sco['AdobeConnectSco']['sco-id'], $principalId, 'view');
			$this->assertTrue($saved);
			// now it should be "read"
			$perms = $this->AdobeConnectPermission->get($sco['AdobeConnectSco']['sco-id'], $principalId);
			$this->assertEqual('view', $perms);
			// now attempt to assign permissions for all Content values
			foreach ($permissionOptions as $_perm) {
				$saved = $this->AdobeConnectPermission->assign($sco['AdobeConnectSco']['sco-id'], $principalId, $_perm);
				$this->assertTrue($saved);
				$perms = $this->AdobeConnectPermission->get($sco['AdobeConnectSco']['sco-id'], $principalId);
				$this->assertEqual($_perm, $perms);
			}
		}
	}

	public function testForMeeting(){
		$permissionOptions = array_keys($this->AdobeConnectPermission->permissionsOptions);
		$scos = $this->AdobeConnectSco->find('search', array(
			'conditions' => array(
				'name' => 'test*',
				'type' => 'meeting',
			),
			'limit' => 5,
		));
		$this->assertFalse(empty($scos));
		$login = 'testaccount_'.time().__function__.'@domain.com';
		$principal = array(
			'login' => $login,
			'email' => $login,
			'first-name' => 'testaccount_first'.time().rand(0, 1000),
			'last-name' => 'testaccount_last'.time().rand(0, 1000),
			'name' => 'testaccount_first testaccount_last'.time().rand(0, 1000),
		);
		$created = $this->AdobeConnectPrincipal->save($principal);
		$this->assertFalse(empty($created));
		$this->deleteIds[] = $principalId = $created[$this->AdobeConnectPrincipal->alias][$this->AdobeConnectPrincipal->primaryKey];
		foreach ($scos as $sco) {
			// initially the perms should be false
			$perms = $this->AdobeConnectPermission->get($sco['AdobeConnectSco']['sco-id'], $principalId);
			$this->assertFalse($perms);
			// now save the perms
			$saved = $this->AdobeConnectPermission->assign($sco['AdobeConnectSco']['sco-id'], $principalId, 'view');
			$this->assertTrue($saved);
			// now it should be "read"
			$perms = $this->AdobeConnectPermission->get($sco['AdobeConnectSco']['sco-id'], $principalId);
			$this->assertEqual('view', $perms);
			// now attempt to assign permissions for all Content values
			foreach ($permissionOptions as $_perm) {
				$saved = $this->AdobeConnectPermission->assign($sco['AdobeConnectSco']['sco-id'], $principalId, $_perm);
				$this->assertTrue($saved);
				$perms = $this->AdobeConnectPermission->get($sco['AdobeConnectSco']['sco-id'], $principalId);
				if ($_perm == 'remove') {
					$this->assertFalse($perms);
				} else {
					$this->assertEqual($_perm, $perms);
				}
			}
		}
	}
}
