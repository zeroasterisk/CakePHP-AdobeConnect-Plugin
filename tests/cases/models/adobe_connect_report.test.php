<?php
/**
* Testing is really difficult, because we have to assume you have certain details on your Adobe Connect server
* So we create the ones we need for testing, and remove them right away
*
* If you don't want to create Scos, don't run this test.
*
* NOTE: you should define the $meetingScoIdForTesting as the meeting you want to report on (it should have some attendance data) 
* NOTE: you should define the $meetingPrincipalIdForTesting as the user you want to report on (it should have some attendance data) 
*/

App::import('model', 'AdobeConnect.AdobeConnectReport');

class AdobeConnectScoTestCase extends CakeTestCase {
	var $meetingScoIdForTesting = 3515893;
	var $meetingPrincipalIdForTesting = 3442962;
	var $areThereActiveMeetings = false;
	
	function startTest() {
		$this->AdobeConnectReport =& ClassRegistry::init('AdobeConnectReport');
	}
	function endTest() {
		if (!empty($this->deleteIds)) {
			foreach ( $this->deleteIds as $i => $id ) { 
				$this->AdobeConnectReport->delete($id);
				unset($this->deleteIds[$i]);
			}
		}
		unset($this->AdobeConnectSco);
		ClassRegistry::flush();
	}
	function testBasics() {
		$this->assertTrue(is_object($this->AdobeConnectReport));
	}
	function testCreateAndUpdateSco() {
		$response = $this->AdobeConnectReport->find("bulkconsolidatedtransactions", array('conditions' => array('sco-id' => $this->meetingScoIdForTesting, 'principal-id' => $this->meetingPrincipalIdForTesting), 'limit' => 100));
		$this->assertTrue(!empty($response));
		$this->assertIdentical(set::countDim($response), 2);
		$response = $this->AdobeConnectReport->find("coursestatus", array('conditions' => array('sco-id' => $this->meetingScoIdForTesting)));
		$this->assertTrue(!empty($response));
		$this->assertIdentical(set::countDim($response), 2);
		$response = $this->AdobeConnectReport->find("meetingattendance", array('conditions' => array('sco-id' => $this->meetingScoIdForTesting)));
		$this->assertTrue(!empty($response));
		$this->assertIdentical(set::countDim($response), 2);
		$response = $this->AdobeConnectReport->find("meetingconcurrentusers", array('conditions' => array('sco-id' => $this->meetingScoIdForTesting)));
		$this->assertTrue(!empty($response));
		$this->assertIdentical(set::countDim($response), 1);
		
		
		if ($this->areThereActiveMeetings) {
			$response = $this->AdobeConnectReport->find("active", array('conditions' => array('sco-id' => $this->meetingScoIdForTesting, 'principal-id' => $this->meetingPrincipalIdForTesting), 'limit' => 100));
			$this->assertTrue(!empty($response));
			$this->assertIdentical(set::countDim($response), 2);
		}
	}
}
?>
