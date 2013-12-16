<?php
/**
* Testing is really difficult, because we have to assume you have certain details on your Adobe Connect server
* So we create the ones we need for testing, and remove them right away
*
* If you don't want to create Scos, don't run this test.
*
* NOTE: you should define the $rootScoIdForTesting as the folder you want to create your test content into
*/

App::uses('AdobeConnectAppModel', 'AdobeConnect.Model');
App::uses('AdobeConnectSco', 'AdobeConnect.Model');
App::uses('Set', 'Utility');
class AdobeConnectScoTest extends CakeTestCase {
	public $plugin = 'app';
	public $fixtures = array(
		'plugin.adobe_connect.connect_api_log',
	);
	protected $_testsToRun = array();

	public $deleteIds = array();
	public $rootScoIdForTesting = 10039;

	public function startTest($method) {
		parent::startTest($method);
		$this->AdobeConnectSco = ClassRegistry::init('AdobeConnect.AdobeConnectSco');
		$this->AdobeConnectSco->useDbConfig = 'adobe_connect';
	}
	public function endTest($method) {
		parent::endTest($method);
		if (!empty($this->deleteIds)) {
			foreach ( $this->deleteIds as $id ) {
				$this->AdobeConnectSco->delete($id);
			}
		}
		unset($this->AdobeConnectSco);
		ClassRegistry::flush();
	}

	public function testBasics() {
		$this->assertTrue(is_object($this->AdobeConnectSco));
	}
	public function testRead(){
		// this is a "test" SCO which should exist on Adobe Connect
		//   if not, cusotmize this SCO to something which does exist.
		$parent_sco_id = '11637';
		$parentScoInfo = $this->AdobeConnectSco->read(null, $parent_sco_id);
		$this->assertFalse(empty($parentScoInfo['AdobeConnectSco']['sco-id']));
		$this->assertEqual('11637', $parentScoInfo['AdobeConnectSco']['sco-id']);
	}
	public function testCreateAndUpdateSco() {
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
		$this->deleteIds[] = $scoId = $created['AdobeConnectSco'][$this->AdobeConnectSco->primaryKey];
		$this->assertTrue(isset($created['AdobeConnectSco'][$this->AdobeConnectSco->primaryKey]));
		$this->assertTrue(!empty($created['AdobeConnectSco'][$this->AdobeConnectSco->primaryKey]));
		$read = $this->AdobeConnectSco->read(null, $created['AdobeConnectSco'][$this->AdobeConnectSco->primaryKey]);
		$this->assertIdentical($Sco['type'], $read['AdobeConnectSco']['type']);
		$this->assertIdentical($Sco['name'], $read['AdobeConnectSco']['name']);
		$this->assertIdentical(strval($Sco['folder-id']), strval($read['AdobeConnectSco']['folder-id']));
		$this->assertIdentical(strtotime($Sco['date-begin']), strtotime($read['AdobeConnectSco']['date-begin']));
		$this->assertIdentical(strtotime($Sco['date-end']), strtotime($read['AdobeConnectSco']['date-end']));
		$Sco = array(
			'folder-id' => $this->rootScoIdForTesting, // will be unset in the save function
			'sco-id' => $scoId, // defined, which means we are updating...
			'name' => $name.' updated',
			'date-begin' => date("Y-m-d H:i:00", strtotime("+8 hours")),
			'date-end' => date("Y-m-d H:i:00", strtotime("+9 hours")),
			);
		$created = $this->AdobeConnectSco->save($Sco);
		$this->assertTrue(isset($created['AdobeConnectSco'][$this->AdobeConnectSco->primaryKey]));
		$this->assertTrue(!empty($created['AdobeConnectSco'][$this->AdobeConnectSco->primaryKey]));
		$this->assertIdentical($Sco['sco-id'], $created['AdobeConnectSco'][$this->AdobeConnectSco->primaryKey]);
		$read = $this->AdobeConnectSco->read(null, $created['AdobeConnectSco'][$this->AdobeConnectSco->primaryKey]);
		$this->assertIdentical($Sco['name'], $read['AdobeConnectSco']['name']);
		$this->assertIdentical(strval($Sco['folder-id']), strval($read['AdobeConnectSco']['folder-id']));
		$this->assertIdentical($ScoOrig['type'], $read['AdobeConnectSco']['type']);
		$this->assertIdentical(strtotime($Sco['date-begin']), strtotime($read['AdobeConnectSco']['date-begin']));
		$this->assertIdentical(strtotime($Sco['date-end']), strtotime($read['AdobeConnectSco']['date-end']));
		// set saving with alternate date input fields
		$Sco = array(
			'folder-id' => $this->rootScoIdForTesting, // will be unset in the save function
			'sco-id' => $scoId, // defined, which means we are updating...
			'name' => $name.' updated',
			'date' => date("Y-m-d H:i:00", strtotime("+2 hours")),
			'duration' => 1,
			);
		$created = $this->AdobeConnectSco->save($Sco);
		$read = $this->AdobeConnectSco->read(null, $created['AdobeConnectSco'][$this->AdobeConnectSco->primaryKey]);
		$this->assertIdentical(strtotime($Sco['date']), strtotime($read['AdobeConnectSco']['date-begin']));
		$this->assertIdentical(strtotime($Sco['date'])+3600, strtotime($read['AdobeConnectSco']['date-end']));
	}
	public function testDeleteSco() {
		$name = 'testmeeting_'.time().__function__;
		$Sco = array(
			'folder-id' => $this->rootScoIdForTesting,
			// 'sco-id' => 0, // not defined, which means we are creating...
			'type' => 'meeting',
			'name' => $name,
			'date-begin' => date("Y-m-d H:i:00", strtotime("+30 hours")),
			'date-end' => date("Y-m-d H:i:00", strtotime("+31 hours")),
			);
		$created = $this->AdobeConnectSco->save($Sco);
		$this->deleteIds[] = $scoId = $created['AdobeConnectSco'][$this->AdobeConnectSco->primaryKey];
		$read = $this->AdobeConnectSco->read(null, $scoId);
		$this->assertTrue(!empty($read));
		$deleteResponse = $this->AdobeConnectSco->delete($scoId);
		$this->assertTrue($deleteResponse);
		$read = $this->AdobeConnectSco->read(null, $scoId);
		$this->assertTrue(empty($read));
	}
	public function testFindReadSco() {
		$name = 'testmeeting_'.time().__function__;
		$Sco = array(
			'folder-id' => $this->rootScoIdForTesting,
			// 'sco-id' => 0, // not defined, which means we are creating...
			'type' => 'meeting',
			'name' => $name,
			'date-begin' => date("Y-m-d H:i:00", strtotime("+31 hours")),
			'date-end' => date("Y-m-d H:i:00", strtotime("+32 hours")),
			);
		$created = $this->AdobeConnectSco->save($Sco);
		$this->deleteIds[] = $scoId = $created['AdobeConnectSco'][$this->AdobeConnectSco->primaryKey];
		$read = $this->AdobeConnectSco->read(null, 123456789012345);
		$this->assertTrue(empty($read));
		$read = $this->AdobeConnectSco->read(null, $scoId);
		$this->assertTrue(count($read)==1);
		$this->assertTrue($read['AdobeConnectSco'][$this->AdobeConnectSco->primaryKey]==$scoId);
		$this->assertTrue($read['AdobeConnectSco']['name']==$Sco['name']);
	}
	public function testFindSearchSco() {
		$tests = $this->AdobeConnectSco->find('search', array(
			'conditions' => array('name' => 'test*'),
			'limit' => 20,
		));
		$this->assertFalse(empty($tests));
		$this->assertFalse(count($tests) > 20);
		// simple value = query shortcut
		$tests = $this->AdobeConnectSco->find('search', 'test*');
		$this->assertFalse(empty($tests));
		$this->assertTrue(count($tests) > 20);
		$this->assertFalse(count($tests) > 200);
		/*
		$this->assertTrue($found[0]['AdobeConnectSco'][$this->AdobeConnectSco->primaryKey]==$scoId);
		$found = $this->AdobeConnectSco->find("search", 'testmeeting * '.__function__);
		$this->assertIdentical(count($found), 1);
		$this->assertTrue($found[0]['AdobeConnectSco'][$this->AdobeConnectSco->primaryKey]==$scoId);
		// seach should return two results (wildcard)
		$found = $this->AdobeConnectSco->find("search", 'test*'.$time.' '.__function__);
		$this->assertIdentical(count($found), 2);
		// seach should return one result, (two results, filtered down by type)
		$found = $this->AdobeConnectSco->find("search", array('conditions' => array('name' => 'test*'.$time.' '.__function__, 'type' => 'content')));
		$this->assertIdentical(count($found), 1);
		$this->assertTrue($found[0]['AdobeConnectSco'][$this->AdobeConnectSco->primaryKey]==$scoIdContent);
		// should not find anything, search fails, even with wildcard
		$found = $this->AdobeConnectSco->find("search", 'test*x'.__function__);
		$this->assertIdentical(count($found), 0);
		*/
	}
	public function testFindContentsSco() {
		$tests = $this->AdobeConnectSco->find('search', array(
			'conditions' => array(
				'name' => 'test*',
				'type' => 'folder',
			),
			'limit' => 20,
		));
		shuffle($tests);
		foreach ($tests as $sco) {
			$found = $this->AdobeConnectSco->find("contents", $sco['AdobeConnectSco']['folder-id']);
			$this->assertFalse(empty($found));
			// expect a find('all') result set
			$this->assertFalse(array_key_exists('AdobeConnectSco', $found));
			// it should contain the $sco (as we looked up the parent folder's contents
			$this->assertTrue(in_array($sco['AdobeConnectSco']['sco-id'], Set::extract($found, '/AdobeConnectSco/sco-id')));
			// re-do with type filter
			$found = $this->AdobeConnectSco->find("contents", array('conditions' => array('sco-id' => $sco['AdobeConnectSco']['folder-id'], 'type' => $sco['AdobeConnectSco']['type'])));
			$this->assertTrue(in_array($sco['AdobeConnectSco']['sco-id'], Set::extract($found, '/AdobeConnectSco/sco-id')));
			$this->assertEqual(1, count(array_unique(Set::extract($found, '/AdobeConnectSco/type'))));
			// re-do with icon filter
			$found = $this->AdobeConnectSco->find("contents", array('conditions' => array('sco-id' => $sco['AdobeConnectSco']['folder-id'], 'icon' => $sco['AdobeConnectSco']['icon'])));
			$this->assertTrue(in_array($sco['AdobeConnectSco']['sco-id'], Set::extract($found, '/AdobeConnectSco/sco-id')));
			$this->assertEqual(1, count(array_unique(Set::extract($found, '/AdobeConnectSco/icon'))));
		}
	}
	public function testFindSearchcontentSco() {
		// don't really know how to test this one... not terribly important to me either.
	}
	public function testMoveSco() {
		// create sco, move it, look for it in the new location, look for it in the old location
	}
	public function testFindPath() {
		$tests = $this->AdobeConnectSco->find('search', array(
			'conditions' => array(
				'name' => 'test*',
			),
			'limit' => 20,
		));
		shuffle($tests);
		foreach (array_slice($tests, 0, 5) as $sco) {
			$path = $this->AdobeConnectSco->find("path", $sco['AdobeConnectSco']['sco-id']);
			$this->assertTrue(is_array($path));
			$this->assertTrue(count($path) > 1);
			$this->assertEqual(Hash::dimensions($path), 1);
			$this->assertTrue(Hash::numeric(array_keys($path)));
			$this->assertFalse(Hash::numeric(array_values($path)));
		}
	}
}
?>
