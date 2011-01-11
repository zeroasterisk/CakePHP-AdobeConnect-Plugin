<?php
/**
* Testing is really difficult, because we have to assume you have certain details on your Adobe Connect server
* So we create the ones we need for testing, and remove them right away
*
* If you don't want to create Scos, don't run this test.
*
* NOTE: you should define the $rootScoIdForTesting as the folder you want to create your test content into 
*/

App::import('model', 'AdobeConnect.AdobeConnectSco');

class AdobeConnectScoTestCase extends CakeTestCase {
	var $deleteIds = array();
	var $rootScoIdForTesting = 10039;
	
	function startTest() {
		$this->AdobeConnectSco =& ClassRegistry::init('AdobeConnectSco');
	}
	function endTest() {
		if (!empty($this->deleteIds)) {
			foreach ( $this->deleteIds as $id ) { 
				$this->AdobeConnectSco->delete($id); 
			}
		}
		unset($this->AdobeConnectSco);
		ClassRegistry::flush();
	}
	function testBasics() {
		$this->assertTrue(is_object($this->AdobeConnectSco));
	}
	function testCreateAndUpdateSco() {
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
		$this->deleteIds[] = $scoId = $created[$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey];
		$this->assertTrue(isset($created[$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey]));
		$this->assertTrue(!empty($created[$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey]));
		$read = $this->AdobeConnectSco->read(null, $created[$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey]);
		$this->assertIdentical($Sco['type'], $read[$this->AdobeConnectSco->alias]['type']);
		$this->assertIdentical($Sco['name'], $read[$this->AdobeConnectSco->alias]['name']);
		$this->assertIdentical(strval($Sco['folder-id']), strval($read[$this->AdobeConnectSco->alias]['folder-id']));
		$this->assertIdentical(strtotime($Sco['date-begin']), strtotime($read[$this->AdobeConnectSco->alias]['date-begin']));
		$this->assertIdentical(strtotime($Sco['date-end']), strtotime($read[$this->AdobeConnectSco->alias]['date-end']));
		$Sco = array(
			'folder-id' => $this->rootScoIdForTesting, // will be unset in the save function
			'sco-id' => $scoId, // defined, which means we are updating...
			'name' => $name.' updated',
			'date-begin' => date("Y-m-d H:i:00", strtotime("+8 hours")),
			'date-end' => date("Y-m-d H:i:00", strtotime("+9 hours")),
			);
		$created = $this->AdobeConnectSco->save($Sco);
		$this->assertTrue(isset($created[$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey]));
		$this->assertTrue(!empty($created[$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey]));
		$this->assertIdentical($Sco['sco-id'], $created[$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey]);
		$read = $this->AdobeConnectSco->read(null, $created[$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey]);
		$this->assertIdentical($Sco['name'], $read[$this->AdobeConnectSco->alias]['name']);
		$this->assertIdentical(strval($Sco['folder-id']), strval($read[$this->AdobeConnectSco->alias]['folder-id']));
		$this->assertIdentical($ScoOrig['type'], $read[$this->AdobeConnectSco->alias]['type']);
		$this->assertIdentical(strtotime($Sco['date-begin']), strtotime($read[$this->AdobeConnectSco->alias]['date-begin']));
		$this->assertIdentical(strtotime($Sco['date-end']), strtotime($read[$this->AdobeConnectSco->alias]['date-end']));
		// set saving with alternate date input fields
		$Sco = array(
			'folder-id' => $this->rootScoIdForTesting, // will be unset in the save function
			'sco-id' => $scoId, // defined, which means we are updating...
			'name' => $name.' updated',
			'date' => date("Y-m-d H:i:00", strtotime("+2 hours")),
			'duration' => 1,
			);
		$created = $this->AdobeConnectSco->save($Sco);
		$read = $this->AdobeConnectSco->read(null, $created[$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey]);
		$this->assertIdentical(strtotime($Sco['date']), strtotime($read[$this->AdobeConnectSco->alias]['date-begin']));
		$this->assertIdentical(strtotime($Sco['date'])+3600, strtotime($read[$this->AdobeConnectSco->alias]['date-end']));
	}
	function testDeleteSco() {
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
		$this->deleteIds[] = $scoId = $created[$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey];
		$read = $this->AdobeConnectSco->read(null, $scoId);
		$this->assertTrue(!empty($read));
		$deleteResponse = $this->AdobeConnectSco->delete($scoId);
		$this->assertTrue($deleteResponse);
		$read = $this->AdobeConnectSco->read(null, $scoId);
		$this->assertTrue(empty($read));
	}
	function testFindReadSco() {
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
		$this->deleteIds[] = $scoId = $created[$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey];
		$read = $this->AdobeConnectSco->read(null, 123456789012345);
		$this->assertTrue(empty($read));
		$read = $this->AdobeConnectSco->read(null, $scoId);
		$this->assertTrue(count($read)==1);
		$this->assertTrue($read[$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey]==$scoId);
		$this->assertTrue($read[$this->AdobeConnectSco->alias]['name']==$Sco['name']);
	}
	function testFindSearchSco() {
		$time = time();
		$name = 'testcontent '.$time.' '.__function__;
		$Sco = array(
			'folder-id' => $this->rootScoIdForTesting,
			// 'sco-id' => 0, // not defined, which means we are creating...
			'type' => 'content',
			'name' => $name,
			);
		$created = $this->AdobeConnectSco->save($Sco);
		$this->deleteIds[] = $scoIdContent = $created[$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey];
		$name = 'testmeeting '.$time.' '.__function__;
		$Sco = array(
			'folder-id' => $this->rootScoIdForTesting,
			// 'sco-id' => 0, // not defined, which means we are creating...
			'type' => 'meeting',
			'name' => $name,
			'date-begin' => date("Y-m-d H:i:00", strtotime("+31 hours")),
			'date-end' => date("Y-m-d H:i:00", strtotime("+32 hours")),
			);
		$created = $this->AdobeConnectSco->save($Sco);
		$this->deleteIds[] = $scoId = $created[$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey];
		// search shortcut, contentsg name
		$found = $this->AdobeConnectSco->find("search", $name);
		$this->assertIdentical(count($found), 1);
		$this->assertTrue($found[0][$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey]==$scoId);
		// search full version, contentsg name (same as above)
		$found = $this->AdobeConnectSco->find("search", array('conditions' => array('name' => $name)));
		$this->assertIdentical(count($found), 1);
		$this->assertTrue($found[0][$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey]==$scoId);
		$found = $this->AdobeConnectSco->find("search", 'testmeeting * '.__function__);
		$this->assertIdentical(count($found), 1);
		$this->assertTrue($found[0][$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey]==$scoId);
		// seach should return two results (wildcard)
		$found = $this->AdobeConnectSco->find("search", 'test*'.$time.' '.__function__);
		$this->assertIdentical(count($found), 2);
		// seach should return one result, (two results, filtered down by type)  
		$found = $this->AdobeConnectSco->find("search", array('conditions' => array('name' => 'test*'.$time.' '.__function__, 'type' => 'content')));
		$this->assertIdentical(count($found), 1);
		$this->assertTrue($found[0][$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey]==$scoIdContent);
		// should not find anything, search fails, even with wildcard
		$found = $this->AdobeConnectSco->find("search", 'test*x'.__function__);
		$this->assertIdentical(count($found), 0);
	}
	function testFindContentsSco() {
		$time = time();
		$name = 'testmeeting '.$time.' '.__function__;
		$Sco = array(
			'folder-id' => $this->rootScoIdForTesting,
			// 'sco-id' => 0, // not defined, which means we are creating...
			'type' => 'meeting',
			'name' => $name,
			'date-begin' => date("Y-m-d H:i:00", strtotime("+31 hours")),
			'date-end' => date("Y-m-d H:i:00", strtotime("+32 hours")),
			);
		$created = $this->AdobeConnectSco->save($Sco);
		$this->deleteIds[] = $scoId = $created[$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey];
		$name = 'testcontent '.$time.' '.__function__;
		$Sco = array(
			'folder-id' => $scoId,
			// 'sco-id' => 0, // not defined, which means we are creating...
			'type' => 'content',
			'name' => $name,
			);
		$created = $this->AdobeConnectSco->save($Sco);
		$this->deleteIds[] = $scoIdContent = $created[$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey];
		
		// search shortcut, contentsg name
		$found = $this->AdobeConnectSco->find("contents", $scoId);
		$this->assertIdentical(count($found), 1);
		$this->assertTrue($found[0][$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey]==$scoIdContent);
		// search full version (same as above)
		$found = $this->AdobeConnectSco->find("contents", array('sco-id' => $scoId));
		$this->assertIdentical(count($found), 1);
		$this->assertTrue($found[0][$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey]==$scoIdContent);
		// search full version (same as above)
		$found = $this->AdobeConnectSco->find("contents", array('conditions' => array('sco-id' => $scoId)));
		$this->assertIdentical(count($found), 1);
		$this->assertTrue($found[0][$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey]==$scoIdContent);
		// search full version, add secondary filter
		$found = $this->AdobeConnectSco->find("contents", array('conditions' => array('sco-id' => $scoId, 'type' => 'content')));
		$this->assertIdentical(count($found), 1);
		$this->assertTrue($found[0][$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey]==$scoIdContent);
		// search full version, add secondary filter (failing)
		$found = $this->AdobeConnectSco->find("contents", array('conditions' => array('sco-id' => $scoId, 'icon' => 'archive')));
		$this->assertIdentical(count($found), 0);
		
		$name = 'testcontent archive '.$time.' '.__function__;
		$Sco = array(
			'folder-id' => $scoId,
			// 'sco-id' => 0, // not defined, which means we are creating...
			'type' => 'content',
			'icon' => 'archive',
			'name' => $name,
			);
		$created = $this->AdobeConnectSco->save($Sco);
		$this->deleteIds[] = $scoIdContentArchive = $created[$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey];
		// search full version, add secondary filter
		$found = $this->AdobeConnectSco->find("contents", array('conditions' => array('sco-id' => $scoId, 'icon' => 'archive')));
		$this->assertIdentical(count($found), 1);
		$this->assertTrue($found[0][$this->AdobeConnectSco->alias][$this->AdobeConnectSco->primaryKey]==$scoIdContentArchive);
		$found = $this->AdobeConnectSco->find("contents", $scoId);
		$this->assertIdentical(count($found), 2);
		
	}
	function testFindSearchcontentSco() {
		// don't really know how to test this one... not terribly important to me either.
	}
}
?>
