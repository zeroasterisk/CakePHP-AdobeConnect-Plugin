<?php
/**
* Testing is really difficult, because we have to assume you have certain details on your Adobe Connect server
* So we create the ones we need for testing, and remove them right away
*
* If you don't want to create Scos, don't run this test.
*
* NOTE: you should define the $rootScoIdForTesting as the folder you want to create your test content into 
*/

App::import('model', 'AdobeConnect.AdobeConnectPermission');
App::import('model', 'AdobeConnect.AdobeConnectSco');
App::import('model', 'AdobeConnect.AdobeConnectPrincipal');

class AdobeConnectScoTestCase extends CakeTestCase {
	var $deleteIdsSco = array();
	var $deleteIdsPrincipal = array();
	var $rootScoIdForTesting = 10039;
	
	function startTest() {
		$this->AdobeConnectPermission =& ClassRegistry::init('AdobeConnectPermission');
		$this->AdobeConnectSco =& ClassRegistry::init('AdobeConnectSco');
		$this->AdobeConnectPrincipal =& ClassRegistry::init('AdobeConnectPrincipal');
	}
	function endTest() {
		if (!empty($this->deleteIdsSco)) {
			foreach ( $this->deleteIdsSco as $i => $id ) { 
				$this->AdobeConnectSco->delete($id);
				unset($this->deleteIdsSco[$i]);
			}
		}
		unset($this->AdobeConnectSco);
		if (!empty($this->deleteIdsPrincipal)) {
			foreach ( $this->deleteIdsPrincipal as $i => $id ) { 
				$this->AdobeConnectPrincipal->delete($id);
				unset($this->deleteIdsPrincipal[$i]);
			}
		}
		unset($this->AdobeConnectPrincipal);
		unset($this->AdobeConnectPermission);
		ClassRegistry::flush();
	}
	function testBasics() {
		$this->assertTrue(is_object($this->AdobeConnectSco));
	}
	function testSavePermissions() {
		$name = 'testmeeting_'.time().__function__;
		$Sco = $ScoOrig = array(
			'folder-id' => $this->rootScoIdForTesting,
			// 'sco-id' => 0, // not defined, which means we are creating...
			'type' => 'meeting',
			'name' => $name,
			'date-begin' => date("Y-m-d H:i:00", strtotime("+5 hours")),
			'date-end' => date("Y-m-d H:i:00", strtotime("+6 hours")),
			);
		$created = $this->AdobeConnectSco->save($Sco);
		$this->assertTrue($created);
		$this->assertTrue(!empty($created[$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey]));
		$this->deleteIdsSco[] = $scoId = $created[$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey];
		$login = 'testaccount_'.time().__function__.'@domain.com';
		$Principal = array(
			'login' => $login,
			'email' => $login,
			'first-name' => 'testaccount_first',
			'last-name' => 'testaccount_last',
			'name' => 'testaccount_first testaccount_last',
			);
		$created = $this->AdobeConnectPrincipal->save($Principal);
		$this->assertTrue($created);
		$this->assertTrue(!empty($created[$this->AdobeConnectPrincipal->alias][$this->AdobeConnectPrincipal->primaryKey]));
		$this->deleteIdsPrincipal[] = $principalId = $created[$this->AdobeConnectPrincipal->alias][$this->AdobeConnectPrincipal->primaryKey];
		// verify that we start without permissions
		$permission = $this->AdobeConnectPermission->get($scoId, $principalId);
		$this->assertIdentical($permission, false);
		// assign permissions as read
		$response = $this->AdobeConnectPermission->save(array(
			'acl-id' => $scoId,
			'principal-id' => $principalId,
			'permission-id' => 'view',
			));
		$this->assertTrue($response);
		$permission = $this->AdobeConnectPermission->get($scoId, $principalId);
		$this->assertIdentical($permission, "view");
		// assign permissions as host, using assign() shortcut
		$permission = $this->AdobeConnectPermission->assign($scoId, $principalId, "host");
		$permission = $this->AdobeConnectPermission->get($scoId, $principalId);
		$this->assertIdentical($permission, "host");
		// assign permissions as "invalid", which should translate to "view", using assign() shortcut
		$permission = $this->AdobeConnectPermission->assign($scoId, $principalId, "invalid");
		$permission = $this->AdobeConnectPermission->get($scoId, $principalId);
		$this->assertIdentical($permission, "view");
		// assign permissions as "manage", which should translate to "host-mini", using assign() shortcut
		$permission = $this->AdobeConnectPermission->assign($scoId, $principalId, "manage");
		$permission = $this->AdobeConnectPermission->get($scoId, $principalId);
		$this->assertIdentical($permission, "mini-host");
		// removes permissions for this sco/principal
		$permission = $this->AdobeConnectPermission->assign($scoId, $principalId, "remove");
		$permission = $this->AdobeConnectPermission->get($scoId, $principalId);
		$this->assertIdentical($permission, false);
	}
	function testDeletePermissions() {
		$name = 'testmeeting_'.time().__function__;
		$Sco = $ScoOrig = array(
			'folder-id' => $this->rootScoIdForTesting,
			// 'sco-id' => 0, // not defined, which means we are creating...
			'type' => 'meeting',
			'name' => $name,
			'date-begin' => date("Y-m-d H:i:00", strtotime("+5 hours")),
			'date-end' => date("Y-m-d H:i:00", strtotime("+6 hours")),
			);
		$created = $this->AdobeConnectSco->save($Sco);
		$this->assertTrue($created);
		$this->assertTrue(!empty($created[$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey]));
		$this->deleteIdsSco[] = $scoId = $created[$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey];
		$login = 'testaccount_'.time().__function__.'@domain.com';
		$Principal = array(
			'login' => $login,
			'email' => $login,
			'first-name' => 'testaccount_first',
			'last-name' => 'testaccount_last',
			'name' => 'testaccount_first testaccount_last',
			);
		$created = $this->AdobeConnectPrincipal->save($Principal);
		$this->assertTrue($created);
		$this->assertTrue(!empty($created[$this->AdobeConnectPrincipal->alias][$this->AdobeConnectPrincipal->primaryKey]));
		$this->deleteIdsPrincipal[] = $principalId = $created[$this->AdobeConnectPrincipal->alias][$this->AdobeConnectPrincipal->primaryKey];
		$login = 'testaccount_'.time().__function__.'2@domain.com';
		$Principal = array(
			'login' => $login,
			'email' => $login,
			'first-name' => 'testaccount_first2',
			'last-name' => 'testaccount_last2',
			'name' => 'testaccount_first2 testaccount_last2',
			);
		$created = $this->AdobeConnectPrincipal->save($Principal);
		$this->assertTrue($created);
		$this->assertTrue(!empty($created[$this->AdobeConnectPrincipal->alias][$this->AdobeConnectPrincipal->primaryKey]));
		$this->deleteIdsPrincipal[] = $principalId2 = $created[$this->AdobeConnectPrincipal->alias][$this->AdobeConnectPrincipal->primaryKey];
		// verify that we start without permissions
		$permission = $this->AdobeConnectPermission->get($scoId, $principalId);
		$this->assertIdentical($permission, false);
		$permission = $this->AdobeConnectPermission->get($scoId, $principalId2);
		$this->assertIdentical($permission, false);
		// assign permissions as host
		$permission = $this->AdobeConnectPermission->assign($scoId, $principalId, "host");
		$permission = $this->AdobeConnectPermission->get($scoId, $principalId);
		$this->assertIdentical($permission, "host");
		// assign permissions as view
		$permission = $this->AdobeConnectPermission->assign($scoId, $principalId2, "view");
		$permission = $this->AdobeConnectPermission->get($scoId, $principalId2);
		$this->assertIdentical($permission, "view");
		// delete all permissions for this SCO
		$permission = $this->AdobeConnectPermission->delete($scoId);
		$permission = $this->AdobeConnectPermission->get($scoId, $principalId);
		$this->assertIdentical($permission, false);
		$permission = $this->AdobeConnectPermission->get($scoId, $principalId2);
		$this->assertIdentical($permission, false);
		// assign permissions as host
		$permission = $this->AdobeConnectPermission->assign($scoId, $principalId, "host");
		$permission = $this->AdobeConnectPermission->get($scoId, $principalId);
		$this->assertIdentical($permission, "host");
		// assign permissions as view
		$permission = $this->AdobeConnectPermission->assign($scoId, $principalId2, "view");
		$permission = $this->AdobeConnectPermission->get($scoId, $principalId2);
		$this->assertIdentical($permission, "view");
		// delete all permissions for this SCO, for $principalId
		$permission = $this->AdobeConnectPermission->delete($scoId, $principalId);
		$permission = $this->AdobeConnectPermission->get($scoId, $principalId);
		$this->assertIdentical($permission, false);
		$permission = $this->AdobeConnectPermission->get($scoId, $principalId2);
		$this->assertIdentical($permission, "view");
		// assign permissions as host
		$permission = $this->AdobeConnectPermission->assign($scoId, $principalId, "host");
		$permission = $this->AdobeConnectPermission->get($scoId, $principalId);
		$this->assertIdentical($permission, "host");
		// delete all permissions for this SCO, for $principalId2
		$permission = $this->AdobeConnectPermission->delete($scoId, $principalId2);
		$permission = $this->AdobeConnectPermission->get($scoId, $principalId);
		$this->assertIdentical($permission, "host");
		$permission = $this->AdobeConnectPermission->get($scoId, $principalId2);
		$this->assertIdentical($permission, false);
	}
}
?>
