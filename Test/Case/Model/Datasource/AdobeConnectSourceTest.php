<?php
App::uses('AdobeConnectSource','AdobeConnect.Model/Datasource');
App::uses('AdobeConnectSco','AdobeConnect.Model');
App::uses('Set', 'Utility');
App::uses('HttpSocket', 'Network/Http');
App::uses('HttpSocketResponse', 'Network/Http');
App::uses('Xml', 'Utility');

class AdobeConnectSourceTest extends CakeTestCase {
	public $fixtures = array(
		'plugin.AdobeConnect.connect_api_log'
	);

	function setUp() {
		parent::setUp();
		// get the config from app/Config/
		Configure::load('adobe_connect');
		$config = Configure::read('AdobeConnectTest');
		if (empty($config)) {
			throw OutOfBoundsException('Unable to test.  Setup AdobeConnectTest Configuration');
		}
		$this->Connect = new AdobeConnectSource($config);
		$this->ConnectApiLog = ClassRegistry::init('ConnectApiLog');
		//$this->Connect->HttpSocket = $this->getMock('HttpSocket');
		$this->Model = ClassRegistry::init('AdobeConnect.AdobeConnectSco');
		$this->Model->useDbConfig = 'adobe_connect';
	}

	function test_getSessionLogin() {
		$result = $this->Connect->getSessionLogin(null, true);
		$this->assertTrue(!empty($result));

		$userData = $this->Connect->userConfig();
		$this->assertEqual($result, $userData);
		$this->assertTrue(!empty($result['sessionKey']));
		$this->assertTrue($userData['isLoggedIn']);
	}

	function test_getSessionCookie() {
		$result = $this->Connect->getSessionCookie();
		$this->assertTrue(!empty($result));

		//Test that we return false and errors.
		$this->Connect->config['url'] = 'broken';
		$this->assertEqual(0, count($this->Connect->errors));
		$result = $this->Connect->getSessionCookie();
		$this->assertEqual(2, count($this->Connect->errors));
		$this->assertFalse($result);
	}

	function test_getSessionKey() {
		$result = $this->Connect->getSessionKey();
		$this->assertTrue(!empty($result));
		$userData = $this->Connect->userConfig();
		$this->assertEqual($result, $userData['sessionKey']);
		$this->assertTrue($userData['isLoggedIn']);
	}

	private function setHttpResponse($xml = '', $method = 'get'){
		$this->Connect->HttpSocket = $this->getMock('HttpSocket');
		$return = new Object();
		$return->body = $xml;
		return $this->Connect->HttpSocket->expects($this->any())->method($method)->will($this->returnValue($return));
	}

	public function testRequest() {
		$this->ConnectApiLog->query("truncate {$this->ConnectApiLog->useTable}");
		$data = array(
			'action' => 'common-info'
		);
		$this->setHttpResponse('<?xml version="1.0" encoding="utf-8" ?>
<results>
    <status code="ok" />
    <common locale="en" time-zone-id="85">
        <cookie>breezbryf9ur23mbokzs8</cookie>
        <date>2008-03-13T01:21:13.190+00:00</date>
        <host>https://example.com</host>
        <local-host>abc123def789</local-host>


        <url>/api/xml?action=common-info</url>
        <version>connect_700_r641</version>
        <user-agent>
            Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1;
            .NET CLR 1.1.4322)
        </user-agent>
    </common>
</results>');
		$count = $this->ConnectApiLog->find('count');
		$this->assertTrue(empty($this->Connect->errors));
		$response = $this->Connect->request($this->Model, $data);
		$this->assertTrue(!empty($response));
		$this->assertEqual('ok', $response['status']['code']);
		$this->assertEqual($count + 1, $this->ConnectApiLog->find('count'));
		$this->assertTrue(empty($this->Connect->errors));
	}

	function testRequestInvalid() {
		$this->Connect->HttpSocket = $this->getMock('HttpSocket');
		$data = array(
			'action' => 'common-info'
		);
		$this->setHttpResponse('<?xml version="1.0" encoding="utf-8" ?>
<results>
    <status code="invalid">
        <invalid field="has-children" type="long" subcode="missing" />
    </status>
</results>');
		$this->assertTrue(empty($this->Connect->errors));
		$response = $this->Connect->request($this->Model, $data);
		$this->assertFalse($response);
		$this->assertFalse(empty($this->Connect->errors));
	}
}
