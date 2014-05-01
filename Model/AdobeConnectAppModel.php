<?php
App::uses('Set','Utility');
class AdobeConnectAppModel extends AppModel {

	/**
	 * The models in the plugin get data from the web service, so they don't need a table.
	 * @var string
	 */
	public $useTable = false;

	/**
	 * The models in the plugin need a datasource
	 * @var string
	 */
	public $useDbConfig = 'adobe_connect';

	/**
	 * Methods in the models result in HTTP requests using the HttpSocket. So
	 * rather than do all the heavy lifting in the datasource, we set the various
	 * params of the request in the individual model methods. This ties the model
	 * to the data layer, but these models are especially for this datasource.
	 *
	 * @var array
	 */
	public $request = array();

	/**
	 * Since the webservice call returns the results in the current page of the
	 * result set and the total number of results in the whole results set, we
	 * need custom paginate and paginateCount methods, whereby the call to the
	 * web service is made in the paginateCount method, the results stored and the
	 * total results returned, then the actual results are returned from the
	 * paginate method. This way the call to the web service is only made once.
	 * However, in order to do this, we need to know the page and limit params in
	 * the paginateCount method. So these should be set in this Model::paginate
	 * property in the controller, before calling Controller::paginate().
	 *
	 * @var array
	 */
	public $paginate = array();

	/**
	 * Temporarily stores the results after being fetched during the paginateCount
	 * method, before returning in the paginate method.
	 *
	 * @var array
	 */
	protected $_results = null;

	/**
	 * Adds the datasource to the Connection Manager's list of sources if it is
	 * not already there. It would normally be there if you add the datasource
	 * details to your app/config/database.php file, but this code negates the
	 * need to do that. It adds the datasource for the current model being
	 * constructed with default basic configuration options, and extra options
	 * from the ADOBECONNECT_CONFIG->{$this->useDbConfig} class property from the file in
	 * plugins/gdata/config/gdata_config.php if it exists, and extra options from
	 * AdobeConnect.config key in the Configure class, if set. Options should include
	 * X-GData-Key as a minimum if required by the AdobeConnect API, and also optionally
	 * oauth_consumer_key, oauth_consumer_secret, oauth_token and
	 * oauth_token_secret keys
	 *
	 * @param mixed $id
	 * @param string $table
	 * @param mixed $ds
	 */
	public function __construct($id = false, $table = null, $ds = null) {
		$this->useDbConfig = 'adobe_connect';
		$config = $this->config();
		ConnectionManager::create($this->useDbConfig, $config);
		parent::__construct($id, $table, $ds);
	}

	/**
	 * Simple function to return the $config array
	 *
	 * @param array $config if set, merge with existing array
	 * @return array $config
	 */
	public function config($config = array()) {
		$loaded = Configure::read('AdobeConnectConfigLoaded');
		if (empty($loaded)) {
			Configure::load('adobe_connect');
			Configure::write('AdobeConnectConfigLoaded', 1);
		}
		if (!empty($config)) {
			$init = Configure::read('AdobeConnect');
			if (!is_array($init)) {
				$init = array();
			}
			$config = Hash::merge($init, $config);
			Configure::write(array('AdobeConnect' => $config));
		}
		$config = Configure::read('AdobeConnect');
		if (empty($config)) {
			throw new OutOfBoundsException('AdobeConnectAppModel::config() unable to load Configuration');
		}
		// double-check we have required keys
		foreach (array('url', 'username', 'password') as $key) {
			if (empty($config[$key])) {
				throw new OutOfBoundsException("AdobeConnectAppModel::config() missing [AdobeConnect.{$key}]");
			}
		}
		// return config
		return $config;
	}

	/**
	 * Error setting
	 *
	 * @param string error message
	 * @return false (use this as your return on error)
	 */
	public function __error($message) {
		$this->errors[] = $message;
		// TODO convert to exception
		trigger_error(__d('adobe_connect', $message), E_USER_WARNING);
		return false;
	}

	/**
	 * Simple function to return an activated sessionKey for a user/pass
	 *
	 * @param string $username
	 * @param string $password
	 * @param string $userKey (optional) unique to this user (username used if empty)
	 * @param boolean force a refresh from the API (default false)
	 * @return string $sessionKey
	 */
	public function getSessionKeyForUser($username=null, $password=null, $userKey=null, $refresh=false) {
		$db = ConnectionManager::getDataSource($this->useDbConfig);
		if (empty($userKey)) {
			$userKey = $username;
		}
		// when we do this, we don't want to change all future API calls to
		//   work from the logged in userKey, we usually are just grabbing the
		//   newly logged in session and redirecting.
		$initUserKey = $db->userKey;
		// setup for login
		$db->userConfig($userKey, compact('username', 'password'));
		// login
		$sessionKey = $db->getSessionKey($userKey, $refresh);
		// reset back to default API userKey
		$db->userKey = $initUserKey;
		// return $sessionKey
		return $sessionKey;
	}

	/**
	 * Alias to the AdobeConnectSource getSessionKey()
	 *
	 * @param string userKey
	 * @param boolean force a refresh from the API (default false)
	 * @return mixed string $sessionKey or false if failure
	 */
	public function getSessionKey($userKey = null, $refresh = false) {
		$db = ConnectionManager::getDataSource($this->useDbConfig);
		return $db->getSessionKey($userKey, $refresh);
	}

	/**
	 * simple function to determin the time diff from the connect server/timestamps and this server's
	 * @return int $secondsToAdd to Connect timestamps to end up with this server's (negative numbers are fine, they just subtract)
	 */
	public function getConnectTimeOffset() {
		$db = ConnectionManager::getDataSource($this->useDbConfig);
		if (isset($db->config['server-time-offset']) && !empty($db->config['server-time-offset'])) {
			return $db->config['server-time-offset'];
		}
		$common = $db->request($this, array('action' => 'common-info'));
		if (!isset($common['common']['date'])) {
			$this->errors[] = $error = "{$this->alias}::getConnectTimeOffset: Unable to get the Common Information";
			trigger_error(__d('adobe_connect', $error), E_USER_WARNING);
			return false;
		}
		$serverTimeEpoch = strtotime($common['common']['date']);
		$selfTime = time();
		$secondsToAdd = $selfTime-$serverTimeEpoch;
		$db->config['server-time-offset'] = $secondsToAdd;
		return $secondsToAdd;
	}

	/**
	 * Special request action... allows custom API calls to happen (make sure you've got your $data array correct)
	 *
	 * @param array $data
	 * @param array $setExtractPath (optional, if set, it runs Set::extract() on the response)
	 */
	public function request($data, $setExtractPath = null) {
		if (is_string($data)) {
			$data['action'] = $data;
		}
		if (!isset($data['action'])) {
			$this->errors[] = $error = "{$this->alias}::Request: Missing action key ".json_encode($data);
			trigger_error(__d('adobe_connect', $error), E_USER_WARNING);
			return false;
		}
		if (!isset($data['data']) && !empty($data['data'])) {
			$data = Set::merge($data, $this->parseFiltersFromQuery($data['data']));
			unset($data['data']);
		}
		$db = ConnectionManager::getDataSource($this->useDbConfig);
		$response = $db->request($this, $data);
		if (empty($response)) {
			$response = $db->response;
		}
		if (empty($response)) {
			return false;
		}
		$response = $this->responseCleanAttr($response);
		if (is_string($setExtractPath) && !empty($setExtractPath)) {
			return Set::extract($response, $setExtractPath);
		}
		return $response;
	}

	/**
	 * Response arrays may have fields/keys with a '@' prefix -- remove those
	 *
	 * @param array $array
	 * @return array $array
	 */
	public function responseCleanAttr($array) {
		if (!is_array($array)) {
			return $array;
		}
		foreach (array_keys($array) as $key) {
			if (is_array($array[$key])) {
				$array[$key] = $this->responseCleanAttr($array[$key]);
			}
			if (substr(trim($key), 0, 1) == '@') {
				$array[str_replace('@', '', trim($key))] = $array[$key];
				unset($array[$key]);
			}
		}
		return $array;
	}

	/**
	 * Overloads the Model::find() method.
	 * Resets request array in between finds, caches initial request array and resets on complete.
	 *
	 * NOTE: this strips out all '@' keys from results, all keys are text only fields
	 *
	 * @param string $type
	 * @param array $options
	 * @return mixed array or false
	 */
	public function find($type = 'first', $options = array()) {
		$initial = $this->request;
		$this->request = array();
		$options = Set::merge(array('order' => null, 'recursive' => null, 'conditions' => null, 'fields' => null,), $options);
		$return = parent::find($type, $options);
		$return = $this->responseCleanAttr($return);
		$this->request = $initial;
		return $return;
	}

	/**
	 * Overwrite of the query() function
	 */
	public function query($sql) {
		debug(compact('sql'));
		throw new OutOfBoundsException("Should not have attempted {$this->alias}->query()");
	}

	/**
	 * Overwrite of the exists() function
	 * means everything is a create() / new
	 *
	 * @param mixed $id (ignored)
	 * @return boolean [false]
	 */
	public function exists($id = null) {
		return true;
	}

	/**
	 * Overloads the Model::delete() method.
	 * Resets request array in between finds, caches initial request array and resets on complete.
	 *
	 * @param int $id
	 */
	public function delete($id = null, $cascade = true) {
		$initial = $this->request;
		$this->request = array();
		$return = parent::delete($id, $cascade);
		$this->request = $initial;
		return $return;
	}

	/**
	 * Add pagination params from the $query to the $request
	 *
	 * @param array $query Query array sent as options to a find call
	 * @return array
	 */
	protected function _paginationParams($query) {
		/*
		if (!empty($query['limit'])) {
			$this->request['uri']['query']['max-results'] = $query['limit'];
		} else {
			$this->request['uri']['query']['max-results'] = $query['limit'] = 10;
		}
		if (!empty($query['page'])) {
			$this->request['uri']['query']['start-index'] = ($query['page'] - 1) * $query['limit'] + 1;
		} else {
			$this->request['uri']['query']['start-index'] = $query['page'] = 1;
		}
		 */
		return $query;
	}


	/**
	 * Called by Controller::paginate(). Calls the custom find type. Stores the
	 * results for later returning in the paginate() method. Returns the total
	 * number of results from the full result set.
	 *
	 * @param array $conditions
	 * @param integer $recursive
	 * @param array $extra
	 * @return integer The number of items in the full result set
	 */
	public function paginateCount($conditions, $recursive = 1, $extra = array()) {
		$response = $this->find($this->paginate[0], $this->paginate);
		$this->_results = $response;
		return $response['feed']['totalResults'];
	}

	/**
	 * Returns the results of the call to the web service fetched in the
	 * self::paginateCount() method above.
	 *
	 * @param mixed $conditions
	 * @param mixed $fields
	 * @param mixed $order
	 * @param integer $limit
	 * @param integer $page
	 * @param integer $recursive
	 * @param array $extra
	 * @return array The results of the call to the web service
	 */
	public function paginate($conditions, $fields = null, $order = null, $limit = null, $page = 1, $recursive = null, $extra = array()) {
		return $this->_results;
	}

	/**
	 * In many cases we need to translate CakePHP $query/$findOptions to AdobeConnect Filters
	 * @param array $query
	 * @return array $request ($this->request = Set::merge($this->request, $this->parseFiltersFromQuery($query)))
	 */
	public function parseFiltersFromQuery($query) {
		$request = array();
		if (isset($query['recursive'])) {
			// no longer supported... oh well
			unset($query['recursive']);
		}
		if (isset($query['limit'])) {
			$request["filter-rows"] = $query['limit'];
		} else {
			// default limit = 200
			$request["filter-rows"] = 200;
		}
		if (isset($query['page']) && $query['page'] != 1) {
			$request["filter-start"] = (($query['page'] -1) * $request["filter-rows"]);
		}
		if (isset($query['order'])) {
			if (is_string($query['order']) && strpos($query['order'], ',')) {
				$query['order'] = explode(',', $query['order']);
			}
			if (is_array($query['order'])) {
				foreach ( $query['order'] as $field_dir ) {
					$field_dir = strtolower(trim($field_dir));
					$dir = (substr($field_dir, -4) == 'desc' ? 'desc' : 'asc');
					$field_dir_parts = explode(' ', $field_dir);
					$field = array_shift($field_dir_parts);
					$field_parts = explode('.', $field);
					$field = array_pop($field_parts);
					$request["sort-{$field}"] = $dir;
				}
			} else {
				$field_dir = strtolower(trim($query['order']));
				$dir = (strtolower(substr($field_dir, -4)) == 'desc' ? 'desc' : 'asc');
				$field_dir_parts = explode(' ', $field_dir);
				$field = array_shift($field_dir_parts);
				$field_parts = explode('.', $field);
				$field = array_pop($field_parts);
				$request["sort-{$field}"] = $dir;
			}
		}
		if (!empty($query['conditions'])) {
			foreach ( $query['conditions'] as $key => $val ) {
				if (strpos($key, '.')!==false) {
					$keyParts = explode('.', $key);
					$key = array_pop($keyParts);
				}
				if ($key == 'id') {
					$key = $this->primaryKey;
				}
				$val = $this->escapeString(trim(str_replace('*', '%', $val)));
				if (substr($key, -1)=='>') {
					$key = trim(substr($key, 0, strlen($key)-1));
					$request["filter-gt-{$key}"] = $val;
				} elseif (substr($key, -1)=='<') {
					$key = trim(substr($key, 0, strlen($key)-1));
					$request["filter-lt-{$key}"] = $val;
				} elseif (substr($key, -2)=='>=') {
					$key = trim(substr($key, 0, strlen($key)-2));
					$request["filter-gte-{$key}"] = $val;
				} elseif (substr($key, -2)=='<=') {
					$key = trim(substr($key, 0, strlen($key)-2));
					$request["filter-lte-{$key}"] = $val;
				} elseif (strtolower(substr($key, -3))=='not') {
					$key = trim(substr($key, 0, strlen($key)-3));
					$request["filter-out-{$key}"] = $val;
				} elseif (strtolower(substr($key, -4))=='like') {
					$key = trim(substr($key, 0, strlen($key)-4));
					$request["filter-like-{$key}"] = $val;
				} elseif (strpos($val, '%')!==false) {
					$request["filter-like-{$key}"] = $val;
				} else {
					$request["filter-{$key}"] = $val;
				}
			}
		}
		return $request;
	}

	/**
	 * A string to search for. To use any of these special characters in the query string, escape them with a backslash before the character:
	 *    + - && || ! ( ) { } [ ] ^ " ~ * ? : \
	 *    The query string is not case-sensitive and allows wildcard characters * and ? at the end of the query string.
	 * @link http://help.adobe.com/en_US/AcrobatConnectPro/7.5/WebServices/WS26a970dc1da1c212717c4d5b12183254583-8000.html
	 * @param string $string
	 * @return string $string
	 */
	public function escapeString($string) {
		return str_replace(
			array('+', '-', '&&', '||', '!', '(', ')', '{', '}', '[', ']', '^', '"', '~', '*', '?', ':', '\\'),
			array('\\+', '-', '\\&&', '\\||', '\\!', '\\(', '\\)', '\\{', '\\}', '\\[', '\\]', '\\^', '\\"', '\\~', '%', '\\?', '\\:', '\\\\'),
			trim($string));
	}

}

