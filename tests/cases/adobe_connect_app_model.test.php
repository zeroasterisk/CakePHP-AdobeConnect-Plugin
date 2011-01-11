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
	function testgetSessionKey() {
		$response = $this->AdobeConnectSco->getSessionKey();
		$this->assertTrue(!empty($response));
		$this->assertTrue(is_string($response));
		$this->assertTrue(strpos($response, "breez")!==false);
	}
	function testgetConnectTimeOffset() {
		$response = $this->AdobeConnectSco->getConnectTimeOffset();
		$this->assertTrue(!empty($response));
		$this->assertTrue(is_numeric($response));
		$this->assertTrue(($response > 0));
	}
	function testRequest() {
		$response = $quotas = $this->AdobeConnectSco->request(array('action' => 'quota-threshold-info'));
		$this->assertTrue(!empty($response['Quotas']['Quota']));
		$this->assertTrue(!empty($response['Trees']['Tree']));
		$response = $this->AdobeConnectSco->request(array('action' => 'quota-threshold-info'), "/Quotas/Quota/.");
		$this->assertIdentical($response, set::extract($quotas, "/Quotas/Quota/."));
	}
}
?>
