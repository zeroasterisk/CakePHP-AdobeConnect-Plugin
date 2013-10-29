<?php
App::uses('AdobeConnectSource','AdobeConnect.Model/Datasource');
App::uses('AdobeConnectSco','AdobeConnect.Model');
App::uses('Set', 'Utility');
App::uses('HttpSocket', 'Network/Http');
App::uses('HttpSocketResponse', 'Network/Http');
App::uses('Xml', 'Utility');

class AdobeConnectSourceTest extends CakeTestCase {
	public $config = array(
		'username' => 'admin@audiologyonline.com',
		'password' => '~br33z3!',
		'salt' => 'connectSALT$$$',
		'url' => 'http://dev9.connect.audiologyonline.com/api/xml',
		'cacheEngine' => 'fast',
		'loginPrefix' => 'HIJACKED_temp_',
		'sco-ids' => array(
			'root' => "10000",
			'seminar-root' => "10005",
			'template-root' => "10045",
			'content-root' => "10000",
			'default-folder' => "10039",
			'default-template' => "49848",
		)
	);
	
	public $fixtures = array(
		'plugin.AdobeConnect.connect_api_log'
	);
	
	function setUp() {
		parent::setUp();
		$this->Connect = new AdobeConnectSource($this->config);
		$this->ConnectApiLog = ClassRegistry::init('ConnectApiLog');
		$this->Connect->HttpSocket = $this->getMock('HttpSocket');
		$this->Model = ClassRegistry::init('AdobeConnect.AdobeConnectSco');
	}
	
	private function setHttpResponse($xml = '', $method = 'get'){
		$return = new Object();
		$return->body = $xml;
		return $this->Connect->HttpSocket->expects($this->any())->method($method)->will($this->returnValue($return));
	}
	
	public function testRequest() {
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
		$this->assertEqual('ok', $response['status']['@code']);
		$this->assertEqual($count + 1, $this->ConnectApiLog->find('count'));
		$this->assertTrue(empty($this->Connect->errors));
	}
	
	function testRequestInvalid() {
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
