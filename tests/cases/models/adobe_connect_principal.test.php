<?php
/**
* Testing is really difficult, because we have to assume you have certain details on your Adobe Connect server
* So we create the ones we need for testing, and remove them right away
*
* If you don't want to create Principals, don't run this test.
*/

App::import('model', 'AdobeConnect.AdobeConnectPrincipal');

class AdobeConnectPrincipalTestCase extends CakeTestCase {
	var $deleteIds = array();
	
	function startTest() {
		$this->AdobeConnectPrincipal =& ClassRegistry::init('AdobeConnectPrincipal');
	}
	function endTest() {
		if (!empty($this->deleteIds)) {
			foreach ( $this->deleteIds as $id ) { 
				$this->AdobeConnectPrincipal->delete($id); 
			}
		}
		unset($this->AdobeConnectPrincipal);
		ClassRegistry::flush();
	}
	function testBasics() {
		$this->assertTrue(is_object($this->AdobeConnectPrincipal));
	}
	function testCreatePrincipal() {
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
		$this->deleteIds[] = $created[$this->AdobeConnectPrincipal->alias][$this->AdobeConnectPrincipal->primaryKey];
		$this->assertTrue(isset($created[$this->AdobeConnectPrincipal->alias][$this->AdobeConnectPrincipal->primaryKey]));
		$this->assertTrue(!empty($created[$this->AdobeConnectPrincipal->alias][$this->AdobeConnectPrincipal->primaryKey]));
		$read = $this->AdobeConnectPrincipal->read(null, $created[$this->AdobeConnectPrincipal->alias][$this->AdobeConnectPrincipal->primaryKey]);
		$this->assertIdentical($Principal['email'], $read[$this->AdobeConnectPrincipal->alias]['email']);
		$this->assertIdentical($Principal['login'], $read[$this->AdobeConnectPrincipal->alias]['login']);
		$this->assertIdentical($Principal['name'], $read[$this->AdobeConnectPrincipal->alias]['name']);
	}
	function testDeletePrincipal() {
		$login = 'testaccount_'.time().__function__.'@domain.com';
		$Principal = array(
			'login' => $login,
			'email' => $login,
			'first-name' => 'testaccount_first',
			'last-name' => 'testaccount_last',
			'name' => 'testaccount_first testaccount_last',
			); 
		$created = $this->AdobeConnectPrincipal->save($Principal);
		$this->deleteIds[] = $principalId = $created[$this->AdobeConnectPrincipal->alias][$this->AdobeConnectPrincipal->primaryKey];
		$read = $this->AdobeConnectPrincipal->read(null, $principalId);
		$this->assertTrue(!empty($read));
		$deleteResponse = $this->AdobeConnectPrincipal->delete($principalId);
		$this->assertTrue($deleteResponse);
		$read = $this->AdobeConnectPrincipal->read(null, $principalId);
		$this->assertTrue(empty($read));
	}
	function testFindReadPrincipal() {
		$login = 'testaccount_'.time().__function__.'@domain.com';
		$Principal = array(
			'login' => $login,
			'email' => $login,
			'first-name' => 'testaccount_first'.time().rand(0, 1000),
			'last-name' => 'testaccount_last'.time().rand(0, 1000),
			'name' => 'testaccount_first testaccount_last'.time().rand(0, 1000),
			);
		$created = $this->AdobeConnectPrincipal->save($Principal);
		$this->deleteIds[] = $principalId = $created[$this->AdobeConnectPrincipal->alias][$this->AdobeConnectPrincipal->primaryKey];
		$read = $this->AdobeConnectPrincipal->read(null, 123456789012345);
		$this->assertTrue(empty($read));
		$read = $this->AdobeConnectPrincipal->read(null, $principalId);
		$this->assertTrue(count($read)==1);
		$this->assertTrue($read[$this->AdobeConnectPrincipal->alias][$this->AdobeConnectPrincipal->primaryKey]==$principalId);
		$this->assertTrue($read[$this->AdobeConnectPrincipal->alias]['login']==$Principal['login']);
	}
	function testFindSearchPrincipal() {
		$login = 'testaccount_'.time().__function__.'@domain.com';
		$Principal = array(
			'login' => $login,
			'email' => $login,
			'first-name' => 'testaccount_first'.time().rand(0, 1000),
			'last-name' => 'testaccount_last'.time().rand(0, 1000),
			'name' => 'testaccount_first testaccount_last'.time().rand(0, 1000),
			);
		$created = $this->AdobeConnectPrincipal->save($Principal);
		$this->assertTrue($created);
		$this->deleteIds[] = $principalId = $created[$this->AdobeConnectPrincipal->alias][$this->AdobeConnectPrincipal->primaryKey];
		foreach ( array('email', 'login') as $key ) { 
			$found = $this->AdobeConnectPrincipal->find("search", array('conditions' => array($key => $Principal[$key])));
			$this->assertTrue(count($found)==1);
			$this->assertTrue($found[0][$this->AdobeConnectPrincipal->alias][$this->AdobeConnectPrincipal->primaryKey]==$principalId);
			
			$found = $this->AdobeConnectPrincipal->find("search", array('conditions' => array($key => substr($Principal[$key], 0, 20))));
			$this->assertTrue(count($found)==0);
			$found = $this->AdobeConnectPrincipal->find("search", array('conditions' => array($key.' like' => substr($Principal[$key], 0, 20).'*')));
			$this->assertTrue(count($found)==1);
			$this->assertTrue($found[0][$this->AdobeConnectPrincipal->alias][$this->AdobeConnectPrincipal->primaryKey]==$principalId);
			// auto-like with asterisk
			$found = $this->AdobeConnectPrincipal->find("search", array('conditions' => array($key => substr($Principal[$key], 0, 20).'*')));
			$this->assertTrue(count($found)==1);
			$this->assertTrue($found[0][$this->AdobeConnectPrincipal->alias][$this->AdobeConnectPrincipal->primaryKey]==$principalId);
			// and look, it wildecards the beginning and ending automatically
			$found = $this->AdobeConnectPrincipal->find("search", array('conditions' => array($key.' like' => substr($Principal[$key], 0, 20))));
			$this->assertTrue(count($found)==1);
			$this->assertTrue($found[0][$this->AdobeConnectPrincipal->alias][$this->AdobeConnectPrincipal->primaryKey]==$principalId);
			$found = $this->AdobeConnectPrincipal->find("search", array('conditions' => array($key.' like' => substr($Principal[$key], 2, 18))));
			$this->assertTrue(count($found)==1);
			$this->assertTrue($found[0][$this->AdobeConnectPrincipal->alias][$this->AdobeConnectPrincipal->primaryKey]==$principalId);
			
			$found = $this->AdobeConnectPrincipal->find("search", array('conditions' => array($key.' like' => 'x'.substr($Principal[$key], 2, 18))));
			$this->assertTrue(count($found)==0);
			$found = $this->AdobeConnectPrincipal->find("search", array('conditions' => array($key.' like' => substr($Principal[$key], 2, 18).'x')));
			$this->assertTrue(count($found)==0);
		}
	}
}
?>