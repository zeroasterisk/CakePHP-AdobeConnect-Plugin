<?php
/**
* Testing is really difficult, because we have to assume you have certain details on your Adobe Connect server
*/

App::uses('AdobeConnectAppModel', 'AdobeConnect.Model');
App::uses('AdobeConnectReport', 'AdobeConnect.Model');
App::uses('Set', 'Utility');
class AdobeConnectReportTest extends CakeTestCase {
	public $plugin = 'app';
	public $fixtures = array(
		'plugin.adobe_connect.connect_api_log',
	);
	protected $_testsToRun = array();

	public $deleteIds = array();
	public $rootScoIdForTesting = 10039;

	public function startTest($method) {
		parent::startTest($method);
		$this->AdobeConnectReport = ClassRegistry::init('AdobeConnect.AdobeConnectReport');
		$this->AdobeConnectSco = ClassRegistry::init('AdobeConnect.AdobeConnectSco');
		$this->AdobeConnectPrincipal = ClassRegistry::init('AdobeConnect.AdobeConnectPrincipal');
	}
	public function endTest($method) {
		parent::endTest($method);
		unset($this->AdobeConnectReport);
		unset($this->AdobeConnectSco);
		unset($this->AdobeConnectPrincipal);
		ClassRegistry::flush();
	}

	public function testBasics() {
		$this->assertTrue(is_object($this->AdobeConnectReport));
	}

	public function testForMeetings() {
		$scos = $this->AdobeConnectSco->find('search', array(
			'conditions' => array(
				'name' => 'test*',
				'type' => 'meeting',
			),
			'limit' => 50,
		));
		$this->assertFalse(empty($scos));
		foreach ($scos as $sco) {
			$scoId = $sco['AdobeConnectSco']['sco-id'];
			$report = $this->AdobeConnectReport->find("meetingattendance", $scoId);
			if (empty($report)) {
				continue;
			}
			$this->assertEqual($scoId, $report[0]['sco-id']);
			$this->assertEqual('test', strtolower(substr($report[0]['sco-name'], 0, 4)));
			$this->assertTrue(
				strtotime($report[0]['date-created']) <
				strtotime($report[0]['date-end'])
			);
			$principalId = $report[0]['principal-id'];
			$report = $this->AdobeConnectReport->find("bulkconsolidatedtransactions", array(
				'conditions' => array('sco-id' => $scoId, 'principal-id' => $principalId),
				'limit' => 100
			));
			$this->assertFalse(empty($report));
			foreach ($report as $transaction) {
				$this->assertEqual($scoId, $transaction['sco-id']);
				//$this->assertEqual($principalId, $transaction['principal-id']);
				$this->assertEqual('meeting', $transaction['type']);
				$this->assertFalse(empty($transaction['transaction-id']));
				$this->assertFalse(empty($transaction['user-name']));
				$this->assertFalse(empty($transaction['name']));
				$this->assertTrue(
					strtotime($transaction['date-created']) <
					strtotime($transaction['date-closed'])
				);
			}
		}
	}
}
