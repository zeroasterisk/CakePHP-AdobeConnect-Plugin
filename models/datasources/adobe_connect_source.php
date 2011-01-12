<?php
App::import('Core', 'HttpSocket');
/**
* CakePHP base datasource used by all AdobeConnect service data sources
*
* @author Alan Blount <alan@zeroasterisk.com>
* @link http://zeroasterisk.com
* @copyright (c) 2011 Alan Blount
* @license MIT License - http://www.opensource.org/licenses/mit-license.php
*/
class AdobeConnectSource extends DataSource {
	protected $_schema = array();
	/**
	* Default config
	* @var array
	*/
	public $config = array(
		'salt' => 'need-to-configure',
		'url' => 'need-to-configure',
		'username' => 'need-to-configure',
		'password' => 'need-to-configure',
		'apiUserKey' => 'APIUSER',
		// other potentially configurable settings
		'modelConnectApiLog' => 'ConnectApiLog',
		'secondsServerTimeTolerance' => 900, // 15 minutes
		'secondsGapPadding' => 180, // 3 minutes
		// extra configurable parameters
		'cacheEngine' => false, // specify a cache-engine to reduce API calls
		'loginPrefix' => null, // prefix for all created user accounts
		'AdobeConnect-Version' => '0.2',
		/*
		// Connect Server Timezone = GMT -6
		'connect-server-timezone-offset' => -6,
		// Connect Server Timezone = US/Central | http://www.php.net/manual/en/timezones.others.php
		'connect-server-timezone' => 'US/Central',
		*/
		'sco-ids' => array(
			'root' => "10000",
			'seminar-root' => "10005",
			'template-root' => "10045",
			'content-root' => "10000",
			// generic
			'default-folder' => "10011",
			'default-template' => "25178",
			)
		);
	
	
	/**
	* placeholder for users logged in with the API
	* @var array
	*/
	protected $users = array(
		'userKey' => array(
			'username' => null,
			'password' => null,
			'sessionKey' => null,
			'isLoggedIn' => false,
			),
		);
	
	/**
	* placeholder for users logged in with the API
	* @var array
	*/
	protected $userKey = null;
		
	/**
	* constants from Connect
	* @link http://help.adobe.com/en_US/AcrobatConnectPro/7.5/WebServices/WS8d7bb3e8da6fb92f73b3823d121e63182fe-8000.html#WS5b3ccc516d4fbf351e63e3d11a171ddf77-7f9c
	* @var array
	*/
	public $icons = array(
		'archive' => 'An archive of an Adobe Acrobat Acrobat Connect Pro meeting.',
		'attachment' => 'A piece of content uploaded as an attachment.',
		'authorware' => 'A piece of multimedia content created with Macromedia Authorware from Adobe.',
		'captivate' => 'A demo or movie created with Adobe Captivate.',
		'course' => 'A training course.',
		'curriculum' => 'A curriculum.',
		'external-event' => 'An external training that can be added to a curriculum.',
		'flv' => 'A media file in the FLV file format.',
		'html' => 'An HTML file.',
		'image' => 'An image.',
		'lms-plugin' => 'A piece of content from an external learning management system.',
		'logos' => 'A custom logo used in a meeting room or Acrobat Connect Pro Central.',
		'meeting-template' => 'A custom look and feel for a meeting.',
		'mp3' => 'An MP3 file.',
		'pdf' => 'An Adobe Portable Document Format file.',
		'pod' => 'A visual box that provides functionality in a meeting room layout.',
		'presentation' => 'A presentation created with an earlier version of Adobe Breeze software.',
		'producer' => 'A presentation created with Adobe Presenter.',
		'seminar' => 'A seminar created with Adobe Acrobat Connect Pro Seminars.',
		'session' => 'One occurrence of a recurring Acrobat Connect Pro meeting.',
		'swf' => 'A SWF file.',
		);
	
	/**
	* constants from Connect
	* @link http://help.adobe.com/en_US/AcrobatConnectPro/7.5/WebServices/WS8d7bb3e8da6fb92f73b3823d121e63182fe-8000.html#WS5b3ccc516d4fbf351e63e3d11a171ddf77-7f9c
	* @var array
	*/
	public $types = array(
		'content' => 'A viewable file uploaded to the server, for example, an FLV file, an HTML file, an image, a pod, and so on.',
		'curriculum' => 'A curriculum.',
		'event' => 'A event.',
		'folder' => 'A folder on the server\'s hard disk that contains content.',
		'link' => 'A reference to another SCO. These links are used by curriculums to link to other SCOs. When content is added to a curriculum, a link is created from the curriculum to the content.',
		'meeting' => 'An Acrobat Connect Pro meeting.',
		'session' => 'One occurrence of a recurring Acrobat Connect Pro meeting.',
		'tree' => 'The root of a folder hierarchy. A tree\'s root is treated as an independent hierarchy; you can\'t determine the parent folder of a tree from inside the tree.',
		);
	
	/**
	* constants from Connect
	* @link http://help.adobe.com/en_US/AcrobatConnectPro/7.5/WebServices/WS8d7bb3e8da6fb92f73b3823d121e63182fe-8000.html#WS5b3ccc516d4fbf351e63e3d11a171ddf77-7f9c
	* @var array
	*/
	public $typesReturned = array(
		'archive' => 'An archived copy of a live Acrobat Connect Pro meeting or presentation.',
		'attachment' => 'A piece of content uploaded as an attachment.',
		'authorware' => 'A piece of multimedia content created with Macromedia Authorware from Adobe.',
		'captivate' => 'A demo or movie authored in Adobe Captivate.',
		'curriculum' => 'A curriculum, including courses, presentations, and other content.',
		'external-event' => 'An external training that can be added to a curriculum.',
		'flv' => 'A media file in the FLV file format.',
		'image' => 'An image, for example, in GIF or JPEG format.',
		'meeting' => 'An Acrobat Connect Pro meeting.',
		'presentation' => 'A presentation.',
		'swf' => 'A SWF file.',
		);
	
	
	/**
	* constants from Connect
	* @link http://help.adobe.com/en_US/AcrobatConnectPro/7.5/WebServices/WS8d7bb3e8da6fb92f73b3823d121e63182fe-8000.html#WS5b3ccc516d4fbf351e63e3d11a171ddf77-7f9c
	* @var array
	*/
	public $typesPrincipal = array(
		'admins' => 'The built-in group Administrators, for Acrobat Connect Pro server Administrators.',
		'authors' => 'The built-in group Authors, for authors.',
		'course-admins' => 'The built-in group Training Managers, for training managers.',
		'event-admins' => 'The built-in group Event Managers, for anyone who can create an Acrobat Connect Pro meeting.',
		'event-group' => 'The group of users invited to an event.',
		'everyone' => 'All Acrobat Connect Pro users.',
		'external-group' => 'A group authenticated from an external network.',
		'external-user' => 'A user authenticated from an external network.',
		'group' => 'A group that a user or Administrator creates.',
		'guest' => 'A non-registered user who enters an Acrobat Connect Pro meeting room.',
		'learners' => 'The built-in group learners, for users who take courses.',
		'live-admins' => 'The built-in group Meeting Hosts, for Acrobat Connect Pro meeting hosts.',
		'seminar-admins' => 'The built-in group Seminar Hosts, for seminar hosts.',
		'user' => 'A registered user on the server.',
		);
	
	/**
	* constants from Connect
	* @link http://help.adobe.com/en_US/AcrobatConnectPro/7.5/WebServices/WS8d7bb3e8da6fb92f73b3823d121e63182fe-8000.html#WS5b3ccc516d4fbf351e63e3d11a171ddf77-7f9c
	* @var array
	*/
	public $connectTimezoneIds = array(
		0 => "(GMT-12:00) International Date Line West",
		1 => "(GMT-11:00) Midway Island, Samoa",
		2 => "(GMT-10:00) Hawaii",
		3 => "(GMT-09:00) Alaska",
		4 => "(GMT-08:00) Pacific Time (US and Canada); Tijuana",
		10 => "(GMT-07:00) Mountain Time (US and Canada)",
		13 => "(GMT-07:00) Chihuahua, La Paz, Mazatlan",
		15 => "(GMT-07:00) Arizona",
		20 => "(GMT-06:00) Central Time (US and Canada)",
		25 => "(GMT-06:00) Saskatchewan",
		30 => "(GMT-06:00) Guadalajara, Mexico City, Monterrey",
		33 => "(GMT-06:00) Central America",
		35 => "(GMT-05:00) Eastern Time (US and Canada)",
		40 => "(GMT-05:00) Indiana (East)",
		45 => "(GMT-05:00) Bogota, Lima, Quito",
		47 => "(GMT-04:30) Caracas",
		50 => "(GMT-04:00) Atlantic Time (Canada)",
		55 => "(GMT-04:00) La Paz",
		56 => "(GMT-04:00) Santiago",
		60 => "(GMT-03:30) Newfoundland",
		65 => "(GMT-03:00) Brasilia",
		70 => "(GMT-03:00) Buenos Aires, Georgetown",
		73 => "(GMT-03:00) Greenland",
		75 => "(GMT-02:00) Mid-Atlantic",
		80 => "(GMT-01:00) Azores",
		83 => "(GMT-01:00) Cape Verde Islands",
		85 => "(GMT) Greenwich Mean Time : Dublin, Edinburgh, Lisbon, London",
		90 => "(GMT) Casablanca, Monrovia",
		95 => "(GMT+01:00) Belgrade, Bratislava, Budapest, Ljubljana, Prague",
		100 => "(GMT+01:00) Sarajevo, Skopje, Warsaw, Zagreb",
		105 => "(GMT+01:00) Brussels, Copenhagen, Madrid, Paris",
		110 => "(GMT+01:00) Amsterdam, Berlin, Bern, Rome, Stockholm, Vienna",
		113 => "(GMT+01:00) West Central Africa",
		115 => "(GMT+02:00) Bucharest",
		120 => "(GMT+02:00) Cairo",
		125 => "(GMT+02:00) Helsinki, Kyiv, Riga, Sofia, Tallinn, Vilnius",
		130 => "(GMT+02:00) Athens, Istanbul, Minsk",
		135 => "(GMT+02:00) Jerusalem",
		140 => "(GMT+02:00) Harare, Pretoria",
		145 => "(GMT+03:00) Moscow, St. Petersburg, Volgograd",
		150 => "(GMT+03:00) Kuwait, Riyadh",
		155 => "(GMT+03:00) Nairobi",
		158 => "(GMT+03:00) Baghdad",
		160 => "(GMT+03:30) Tehran",
		165 => "(GMT+04:00) Abu Dhabi, Muscat",
		170 => "(GMT+04:00) Baku, Tbilisi, Yerevan",
		175 => "(GMT+04:30) Kabul",
		180 => "(GMT+05:00) Ekaterinburg",
		185 => "(GMT+05:00) Islamabad, Karachi, Tashkent",
		190 => "(GMT+05:30) Chennai, Kolkata, Mumbai, New Delhi",
		193 => "(GMT+05:45) Kathmandu",
		195 => "(GMT+06:00) Astana, Dhaka",
		200 => "(GMT+06:00) Sri Jayawardenepura",
		201 => "(GMT+06:00) Almaty, Novosibirsk",
		203 => "(GMT+06:30) Rangoon",
		205 => "(GMT+07:00) Bangkok, Hanoi, Jakarta",
		207 => "(GMT+07:00) Krasnoyarsk",
		210 => "(GMT+08:00) Beijing, Chongqing, Hong Kong SAR, Urumqi",
		215 => "(GMT+08:00) Kuala Lumpur, Singapore",
		220 => "(GMT+08:00) Taipei",
		225 => "(GMT+08:00) Perth",
		227 => "(GMT+08:00) Irkutsk, Ulaan Bataar",
		230 => "(GMT+09:00) Seoul",
		235 => "(GMT+09:00) Osaka, Sapporo, Tokyo",
		240 => "(GMT+09:00) Yakutsk",
		245 => "(GMT+09:30) Darwin",
		250 => "(GMT+09:30) Adelaide",
		255 => "(GMT+10:00) Canberra, Melbourne, Sydney",
		260 => "(GMT+10:00) Brisbane",
		265 => "(GMT+10:00) Hobart",
		270 => "(GMT+10:00) Vladivostok",
		275 => "(GMT+10:00) Guam, Port Moresby",
		280 => "(GMT+11:00) Magadan, Solomon Islands, New Caledonia",
		285 => "(GMT+12:00) Fiji Islands, Kamchatka, Marshall Islands",
		290 => "(GMT+12:00) Auckland, Wellington",
		300 => "(GMT+13:00) Nuku'alofa",
		);
	
	
	
	/**
	* keys which require boolean values
	* @var array
	*/
	public $keysForceBoolValues = array("disabled", "has-children", );
	public $keysDataRestricted = array("uri", );
	
	/**
	* Container for HttpSocket object
	* @var object
	*/
	public $HttpSocket = null;
	
	/**
	* Container for modelConnectApiLog object
	* @var object
	*/
	public $modelConnectApiLog = null;
	
	/**
	* Container for a log of errrors
	* @var array
	*/
	public $errors = array();
	
	/**
	* Container for a log of actions
	* @var array
	*/
	public $log = array();
	
	/**
	* Shows status of if we have auto-failed on a session, changing cache and other details (so we only autofail once, and bypass cache when doing so)
	* @var bool
	*/
	public $autoFailedSession = false;
	
	/**
	* setup the config
	* setup the HttpSocket class to issue the requests
	* @param array $config
	*/
	public function  __construct($config) {
		$this->config = set::merge($this->config, $config);
		App::import('Core', array('Xml', 'HttpSocket'));
		$this->HttpSocket = new HttpSocket();
		
		App::import('Model', $this->config['modelConnectApiLog']);
		$this->modelConnectApiLog =& ClassRegistry::init($this->config['modelConnectApiLog']);
		if (!is_object($this->modelConnectApiLog)) {
			return $this->cakeError('missingModel', 'Missing "modelConnectApiLog" model: '.$this->config['modelConnectApiLog']);
		}
		return parent::__construct($config);
	}
	
	
	/**
    * resets all interactions and details 
    * @param string $reason
    * @param bool $alsoClearErrors true
    * @param bool $alsoClearLog false
    */
    function reset($reason='unknown', $alsoClearErrors=true, $alsoClearLog=false) {
    	foreach( array('series_id', 'course_id', 'event_id', 'member_id') as $key) {
    		$this->$key = 0;
    	}
    	$this->users = array();
		$this->config['autoreset'] = false;
		$this->config['autologin'] = false;
		if ($this->config['cacheEngine']) {
			Cache::delete('adobe_connect_session_data_'.$this->userKey, $this->config['cacheEngine']);
		}
    	if ($alsoClearErrors) {
    		$this->errors = array();
    		$this->lastError = null;
    	}
    	if ($alsoClearLog) {
    		$this->log = array();
    	}
    	$this->log[] = array("reset" => $reason);
    	return true;
    }
    
	/**
	* Adds in common elements to the request such as AdobeConnect version and Developer
	* key headers and the config parameters if not set in the request already
	*
	* @param AppModel $model The model the operation is called on. Should have a request property in the format described in HttpSocket::request
	* @return mixed Depending on what is returned from HttpSocket::request()
	*/
	public function request(&$model, $data = array(), $requestOptions = array()) {
		if (!is_array($data)) { $data = array(); }
		if (!is_array($requestOptions)) { $requestOptions = array(); }
		if (isset($model->request) && is_array($model->request)) {
			$data = set::merge($model->request, $data);
		}
		if (isset($model->requestOptions)) {
			$requestOptions = set::merge($model->requestOptions);
		}
		$data = set::merge(array(
			'method' => (count($data) > 6 ? 'post' : 'get'),
			'action' => 'unknown',
			), $data);
		extract($data);
		$errors = array();
		if ($data['action'] == "unknown") {
			$this->errors[] = $error = "{$model->alias}::request: missing action: ".json_encode($data);
			trigger_error(__d('adobe_connect', $error, true), E_USER_WARNING);
			return false;
		}
		if ($data['action'] != "common-info") {
			$data['session'] = $this->getSessionKey($data, $this->userKey);
			if (empty($data['session']) && !empty($this->users[$this->userKey]['sessionKey'])) {
				$data['session'] = $this->users[$this->userKey]['sessionKey'];
			}
		}
		// clean data for request
		foreach ( $data as $key => $val ) { 
			if (in_array($key, $this->keysDataRestricted)) {
				unset($data[$key]);
			} elseif (is_bool($val) || in_array($key, $this->keysForceBoolValues)) {
				$data[$key] = (empty($val) ? 0 : 1);
			} elseif (is_array($val)) {
				$data[$key] = json_encode($val);
			} elseif ($val===null || $val=="") {
				unset($data[$key]);
			}
		}
		$dataCleaned = array_diff_key($data, array('method' => 0, 'conditions' => 0, 'order' => 0, 'limit' => 0, 'recursive' => 0, 'joins' => 0, 'offset' => 0, 'page' => 0, 'group' => 0, 'callbacks' => 0, 'fields' => 0));
		// setup request
    	$requestOptions = set::merge(array(
    		'header' => array(
    			'Connection' => 'close',
    			'User-Agent' => 'CakePHP AdobeConnect Plugin v.'.$this->config['AdobeConnect-Version'],
				)
			), $requestOptions);
		$this->HttpSocket->reset();
		// do request
    	if ($data['method'] == 'post') {
    		$requestOptions['header']['Content-Type'] = 'text/xml';
    		$dataCleaned = array_diff_key($dataCleaned, array('session' => 0));
    		$dataAsXMLArray = array('<params>');
			foreach ( $dataCleaned as $key => $val ) { 
				$dataAsXMLArray[] = '<param name="'.$key.'"><![CDATA['.$val.']]></param>';
			}
			$dataAsXMLArray[] = '</params>';
			$dataAsXML = implode("\n", $dataAsXMLArray);
    		$response = $this->HttpSocket->request(set::merge(array(
    			'method' => strtoupper($method),
    			'uri' => $this->config['url'].'?action='.$action.'&session='.$data['session'],
    			'body' => $dataAsXML,
    			), $requestOptions));
    	} else {
    		$response = $this->HttpSocket->get($this->config['url'], $dataCleaned, $requestOptions);
    		$url = trim(str_replace(array('GET', 'HTTP/1.1'), '', $this->HttpSocket->request['line']));
    		$url = $this->HttpSocket->request['uri']['scheme'].'://'.$this->HttpSocket->request['uri']['host'].$url;
    	}
    	//debug($response);
    	# parse response
    	$xml = new Xml($response);
    	$responseArray = $xml->toArray();
    	# extract status
    	$responseArray = (isset($responseArray['Results']) && is_array($responseArray['Results']) ? $responseArray['Results'] : $responseArray);
    	# extract status
    	$good = (isset($responseArray['Status']['code']) && $responseArray['Status']['code']=="ok");
    	# extract errors
    	if (!$good) {
    		# look for no-login error (session expired?) login again and retry
    		if (isset($responseArray['Status']['subcode']) && $responseArray['Status']['subcode']=="no-login" && !$this->autoFailedSession) {
    			$this->autoFailedSession = true;
    			$this->reset("No Login after action - automatic reset and re-attempt");
    			return $this->request($model, $data, $requestOptions);
    		}
    		# general errors
    		$invalid = set::extract($responseArray, "/Status/Invalid");
    		if (!empty($invalid)) {
				foreach ( $invalid as $_invalid ) {
					$errors[] =  "INVALID: {$_invalid['Invalid']['field']}: {$_invalid['Invalid']['subcode']}";
				}
			} else {
				$statusCodes = set::extract($responseArray, "/Status");
				foreach ( $statusCodes as $statusCode ) {
					if (isset($statusCode['Status']['subcode'])) {
						$errors[] =  "{$statusCode['Status']['code']}: {$statusCode['Status']['subcode']}";
					} elseif ($statusCode['Status']['code']!='no-data') {
						$errors[] =  "CODE: {$statusCode['Status']['code']}";
					}
				}
			}
    	}
    	# log request
    	if (!$this->log(compact('url', 'action', 'data', 'dataCleaned', 'dataAsXML', 'responseArray', 'response', 'errors'))) {
    		$errors[] = 'Unable to save log';
    	}
    	# flesh out logged errors
    	if (!empty($errors)) {
    		$this->lastError = implode(' | ', $errors);
    		$errors[] = array('log' => current($this->log));
    		array_unshift($errors, "Errors with Action: [{$action}]");
    		$this->errors[] = $errors;
    	}
    	# add response/log/errors to model object, if exists
    	if (is_object($model)) {
    		$model->response = $responseArray;
			$model->log = $this->log;
			$model->errors = $this->errors;
		}
    	# return
    	if ($good) {
    		return $responseArray;
    	}
    	return false;
	}
	
	/**
	* Prep and log data to inline array and also to the modelConnectApiLog
	* @param array $data
	*/
	public function log($data) {
		$data['microtimestamp'] = getMicrotime();
		if (isset($data['dataAsXML']) && !empty($data['dataAsXML'])) {
			$data['sent'] = $data['dataAsXML'];
			$data['sent_raw'] = json_encode($data['dataCleaned']);
		} else {
			$data['sent'] = json_encode($data['dataCleaned']);
		}
    	$data['received'] = (isset($data['responseArray']) && !empty($data['responseArray']) ? json_encode($data['responseArray']) : $data['response']);
    	$data['received_raw'] = $data['response'];
    	$data['data'] = json_encode($data['data']);
    	$data['dataCleaned'] = json_encode($data['dataCleaned']);
    	$data['userKey'] = $this->userKey;
    	$data['userData'] = (isset($this->users[$this->userKey]) ? $this->users[$this->userKey] : 'missing');
    	$data['errors'] = array_unique($this->errors + $data['errors']);
		$this->log[] = $data;
		$data['errors'] = json_encode($data['errors']);
		if (is_object($this->modelConnectApiLog)) {
			$this->modelConnectApiLog->create(false);
			return $this->modelConnectApiLog->save($data);
		}
		return true;
	}
	
	/**
    * Simple function to return an activated sessionKey 
    * NOTE: if you want to initialize a new sessionKey, use initUser() or reset() and then this function
    * @param array $data (optional)
    * @param string $userKey (optional)
    * @param string $username (optional)
    * @param string $password (optional)
    * @return string $sessionKey
    */
    public function getSessionKey($data, $userKey=null, $username=null, $password=null) {
    	if (empty($userKey)) {
    		$userKey = $this->config['apiUserKey'];
    	}
    	$this->userKey = $userKey;
    	if (!isset($this->users[$this->userKey]['sessionKey'])) {
			if (empty($username) && $this->userKey == $this->config['apiUserKey']) {
				$username = $this->config['username'];
			}
			if (empty($password) && $this->userKey == $this->config['apiUserKey']) {
				$password = $this->config['password'];
			}
    		$this->users[$this->userKey] = array(
    			'principle-id' => null,
    			'username' => $username,
    			'password' => $password,
    			'sessionKey' => null,
    			'isLoggedIn' => false,
    			);
    	}
    	// return if we've got values
    	if (!empty($this->users[$this->userKey]['sessionKey']) && !empty($this->config[$this->userKey]['isLoggedIn'])) {
    		return $this->users[$this->userKey]['sessionKey'];
    	} elseif (!empty($this->users[$this->userKey]['sessionKey']) && $data['action'] == "login") {
    		return $this->users[$this->userKey]['sessionKey'];
    	}
    	// check in with the cache, if we have cache (if autoFailed, we ignore cache)
    	$cacheKey = false;
    	if ($this->config['cacheEngine'] && $this->userKey == $this->config['apiUserKey']) {
    		$cacheKey = 'adobe_connect_session_'.date('h').strtolower($this->userKey);
    	}
    	if ($cacheKey && empty($this->users[$this->userKey]['sessionKey']) && !$this->autoFailedSession) {
			$cachedUserData = Cache::read($cacheKey, $this->config['cacheEngine']);
			if (!empty($cachedUserData)) {
				$this->users[$this->userKey] = $cachedUserData;
			}
		}
		// return if we've got values
    	if (!empty($this->users[$this->userKey]['sessionKey']) && !empty($this->config[$this->userKey]['isLoggedIn'])) {
    		return $this->users[$this->userKey]['sessionKey'];
    	} elseif (!empty($this->users[$this->userKey]['sessionKey']) && $data['action'] == "login") {
    		return $this->users[$this->userKey]['sessionKey'];
    	}
    	// get values
		if (empty($this->users[$this->userKey]['sessionKey'])) {
			// no session, create it
			$response = $this->request($this->userKey, array('action' => "common-info"));
			if (isset($response['Common']['cookie'])) {
				$this->users[$this->userKey]['sessionKey'] = $response['Common']['cookie'];
			} else {
				$this->errors[] = "Unable to get sessionKey"; 
				return false;
			}
		}
		if (empty($this->users[$this->userKey]['isLoggedIn']) && $data['action'] != "login") {
			// has a session, but we need to login user
			$response = $this->request($this->userKey, array('action' => "login", 'login' => $this->users[$this->userKey]['username'], 'password' => $this->users[$this->userKey]['password']));
			if (isset($response['Status']['code']) && $response['Status']['code'] == "ok") {
				$this->users[$this->userKey]['isLoggedIn'] = true;
				if ($cacheKey) {
					Cache::write($cacheKey, $this->users[$this->userKey], $this->config['cacheEngine']);
				}
				return $this->users[$this->userKey]['sessionKey'];
			}
		}
    	return false;
    }
	
    /**
    * Simple Login functionality 
    * NOTE: if you want to initialize a new sessionKey, use initUser() instead of login()
    * @param string $user
    * @param string $pass
    * @return bool $loggedIn
    */
    public function login($user=null, $pass=null) {
    	$loggedIn = $this->request("login", array('login' => $user, 'password' => $pass, ));
    	if (!empty($loggedIn)) {
			return true;
		}
    	return false;
    }
    
	/**
	* Sets method = POST in request if not already set
	* @param AppModel $model
	* @param array $fields Unused
	* @param array $values Unused
	*/
	public function create(&$model, $fields = null, $values = null) {
		return $this->request($model);
	}
	
	/**
	* Sets method = GET in request if not already set
	* @param AppModel $model
	* @param array $queryData Unused
	*/
	public function read(&$model, $queryData = array()) {
		return $this->request($model, $queryData);
	}
	
	/**
	* Sets method = PUT in request if not already set
	*
	* @param AppModel $model
	* @param array $fields Unused
	* @param array $values Unused
	*/
	public function update(&$model, $fields = null, $values = null) {
		return $this->request($model);
	}
	
	/**
	* Delete
	* @param AppModel $model
	* @param mixed $id Unused
	*/
	public function delete(&$model, $id = null) {
		return $this->request($model);
	}
	public function listSources() {
		return array('tweets');
	}
	public function describe($model) {
		return $this->_schema['tweets'];
	}
}
?>
