<?php
/**
 * Testing is really difficult, because we have to assume you have certain details on your Adobe Connect server
 *
 *
 */

App::uses('AdobeConnectAppModel', 'AdobeConnect.Model');
App::uses('AdobeConnectAppModel', 'AdobeConnect.Model');
App::uses('Set', 'Utility');
class AdobeConnectAppModelTest extends CakeTestCase {
	public $plugin = 'app';
	public $fixtures = array(
		'plugin.adobe_connect.connect_api_log',
	);
	protected $_testsToRun = array();

	public $deleteIds = array();
	public $rootScoIdForTesting = 10039;

	public function startTest($method) {
		parent::startTest($method);
		$this->AdobeConnectAppModel = ClassRegistry::init('AdobeConnect.AdobeConnectAppModel');
		$this->AdobeConnectAppModel->useDbConfig = 'adobe_connect';
	}
	public function endTest($method) {
		parent::endTest($method);
		if (!empty($this->deleteIds)) {
			foreach ( $this->deleteIds as $id ) {
				$this->AdobeConnectAppModel->delete($id);
			}
		}
		unset($this->AdobeConnectAppModel);
		ClassRegistry::flush();
	}

	public function testBasics() {
		$this->assertTrue(is_object($this->AdobeConnectAppModel));
	}

	public function testgetSessionKey() {
		$response = $this->AdobeConnectAppModel->getSessionKey();
		$this->assertTrue(!empty($response));
		$this->assertTrue(is_string($response));
		$this->assertTrue(strpos($response, "breez")!==false);
		$response2 = $this->AdobeConnectAppModel->getSessionKey();
		$this->assertEqual($response, $response2);
	}

	public function testgetConnectTimeOffset() {
		$response = $this->AdobeConnectAppModel->getConnectTimeOffset();
		$this->assertTrue(!empty($response));
		$this->assertTrue(is_numeric($response));
		$this->assertTrue((abs($response) > 0));
		$this->assertTrue((abs($response) < 3600 * 5));
	}

	public function testRequest() {
		$response = $quotas = $this->AdobeConnectAppModel->request(array('action' => 'quota-threshold-info'));
		$this->assertTrue(!empty($response['Quotas']['Quota']));
		$this->assertTrue(!empty($response['Trees']['Tree']));
		$response = $this->AdobeConnectAppModel->request(array('action' => 'quota-threshold-info'), "/Quotas/Quota/.");
		$this->assertIdentical($response, Set::extract($quotas, "/Quotas/Quota/."));
	}

	public function testResponseCleanAttr() {
		$input = array(
			'aaaaa' => 1,
			'a@aaa' => 2,
			'aaaa@' => 3,
			'@aaaa' => 4,
			'child' => array(
				'bbbbb' => 5,
				'@bbbb' => 6,
				'child' => array(
					'@cccc' => 7,
					'ccccc' => 8,
				),
			),
			'@nest' => array(
				'aaaaa' => 9,
				'@aaaa' => 10,
			),
			'dupe' => 11,
			'@dupe' => 12,
		);
		$expect = array(
			'aaaaa' => 1,
			'a@aaa' => 2,
			'aaaa@' => 3,
			'aaaa' => 4,
			'child' => array(
				'bbbbb' => 5,
				'bbbb' => 6,
				'child' => array(
					'cccc' => 7,
					'ccccc' => 8,
				),
			),
			'nest' => array(
				'aaaaa' => 9,
				'aaaa' => 10,
			),
			//'dupe' => 11, // overwritten
			'dupe' => 12,
		);
		$this->assertEqual($expect, $this->AdobeConnectAppModel->responseCleanAttr($input));
	}
}
