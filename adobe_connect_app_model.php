<?php

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
		
		// Get the list of datasource that the ConnectionManager is aware of
		$sources = ConnectionManager::sourceList();
		// If this model's datasource isn't in it, add it
		if (!in_array($this->useDbConfig, $sources)) {
			// Default minimum config
			$config = array(
				'datasource' => 'AdobeConnect.AdobeConnectSource',
				'salt' => 'AdobeConnect123',
				'url' => null,
				'username' => null,
				'password' => null,
				);
			// Try an import the plugins/adobe_connect/config/adobe_connect_config.php file and merge
			// any default and datasource specific config with the defaults above
			if (App::import(array('type' => 'File', 'name' => 'AdobeConnect.ADOBECONNECT_CONFIG', 'file' => APP.'config'.DS.'adobe_connect_config.php'))) {
				$ADOBECONNECT_CONFIG = new ADOBECONNECT_CONFIG();
				if (isset($ADOBECONNECT_CONFIG->default)) {
					$config = set::merge($config, $ADOBECONNECT_CONFIG->default);
				}
				if (isset($ADOBECONNECT_CONFIG->{$this->useDbConfig})) {
					$config = set::merge($config, $ADOBECONNECT_CONFIG->{$this->useDbConfig});
				}
			} elseif (App::import(array('type' => 'File', 'name' => 'AdobeConnect.ADOBECONNECT_CONFIG', 'file' => 'config'.DS.'adobe_connect_config.php'))) {
				$ADOBECONNECT_CONFIG = new ADOBECONNECT_CONFIG();
				if (isset($ADOBECONNECT_CONFIG->default)) {
					$config = set::merge($config, $ADOBECONNECT_CONFIG->default);
				}
				if (isset($ADOBECONNECT_CONFIG->{$this->useDbConfig})) {
					$config = set::merge($config, $ADOBECONNECT_CONFIG->{$this->useDbConfig});
				}
			}
			// Add any config from Configure class that you might have added at any
			// point before the model is instantiated.
			if (($configureConfig = Configure::read('AdobeConnect.config')) != false) {
				$config = set::merge($config, $configureConfig);
			}
			// double-check we have required keys
			if (empty($config['url'])) {
				trigger_error(__d('adobe_connect', "Invalid AdobeConnectSource Configuration, missing 'url' key. useDbConfig = [{$this->useDbConfig}]", true), E_USER_WARNING);
				die();
			} elseif (empty($config['username']) || empty($config['password'])) {
				trigger_error(__d('adobe_connect', "Invalid AdobeConnectSource Configuration, missing 'username' or 'password' key.  This should be the API user account you want to use. useDbConfig = [{$this->useDbConfig}]", true), E_USER_WARNING);
				die();
			}
			if ($this->useDbConfig=='default' || $this->useDbConfig=='test' || $this->useDbConfig=='test_suite') {
				$this->useDbConfig = 'adobe_connect_config';
			}
			// Add the datasource, with it's new config, to the ConnectionManager
			ConnectionManager::create($this->useDbConfig, $config);
		}
		$useDbConfig = $this->useDbConfig;
		parent::__construct($id, $table, $ds);
		$this->useDbConfig = $useDbConfig;
	}
	
	/**
    * Simple function to return the $config array
    * @param array $config if set, merge with existing array
    * @return array $config
    */
	public function config($config = array()) {
		$db =& ConnectionManager::getDataSource($this->useDbConfig);
		if (!empty($config) && is_array($config)) {
			$db->config = set::merge($db->config, $config);
		}
		return $db->config;
	}
	
	/**
    * Simple function to return an activated sessionKey 
    * NOTE: if you want to initialize a new sessionKey, use initUser() or reset() and then this function
    * @param string $userKey (optional)
    * @param string $username (optional)
    * @param string $password (optional)
    * @return string $sessionKey
    */
	public function getSessionKey($userKey=null, $username=null, $password=null) {
		$db =& ConnectionManager::getDataSource($this->useDbConfig);
		return $db->getSessionKey(array('action' => 'custom'), $userKey, $username, $password);
	}
	
	/**
    * simple function to determin the time diff from the connect server/timestamps and this server's 
    * @return int $secondsToAdd to Connect timestamps to end up with this server's (negative numbers are fine, they just subtract)
    */
    public function getConnectTimeOffset() {
    	$db =& ConnectionManager::getDataSource($this->useDbConfig);
    	if (isset($db->config['server-time-offset']) && !empty($db->config['server-time-offset'])) {
    		return $db->config['server-time-offset'];
    	}
    	$common = $db->request($this, array('action' => 'common-info'));
    	if (!isset($common['Common']['date'])) {
    		$this->errors[] = $error = "{$this->alias}::getConnectTimeOffset: Unable to get the Common Information";
			trigger_error(__d('adobe_connect', $error, true), E_USER_WARNING);
			return false;
    	}
    	$serverTimeEpoch = strtotime($common['Common']['date']);
    	$selfTime = time();
    	$secondsToAdd = $selfTime-$serverTimeEpoch;
    	$db->config['server-time-offset'] = $secondsToAdd;
    	return $secondsToAdd;
    }
	
    /**
	* Special request action... allows custom API calls to happen (make sure you've got your $data array correct) 
	*
	* @param array $data
	* @param array $setExtractPath (optional, if set, it runs set::extract() on the response)
	*/
	public function request($data, $setExtractPath = null) {
		if (is_string($data)) {
			$data['action'] = $data;
		}
		if (!isset($data['action'])) {
			$this->errors[] = $error = "{$this->alias}::Request: Missing action key ".json_encode($data);
    		trigger_error(__d('adobe_connect', $error, true), E_USER_WARNING);
			return false;
		}
		if (!isset($data['data']) && !empty($data['data'])) {
			$data = set::merge($data, $this->parseFiltersFromQuery($data['data']));
			unset($data['data']);
		}
		$db =& ConnectionManager::getDataSource($this->useDbConfig);
		$response = $db->request($this, $data);
		if (empty($response)) {
			$response = $db->response;
		}
		if (empty($response)) {
			return false;
		} 
		if (is_string($setExtractPath) && !empty($setExtractPath)) {
			return set::extract($response, $setExtractPath);
		}
    	return $response;
	}
	
	/**
	* Overloads the Model::find() method. 
	* Resets request array in between finds, caches initial request array and resets on complete.
	*
	* @param string $type
	* @param array $options
	*/
	public function find($type, $options = array()) {
		$initial = $this->request;
		$this->request = array();
		$options = set::merge(array('order' => null, 'recursive' => null, 'conditions' => null, 'fields' => null,), $options); 
		$return = parent::find($type, $options);
		$this->request = $initial;
		return $return;
	}
	
	/**
	* Overloads the Model::delete() method. 
	* Resets request array in between finds, caches initial request array and resets on complete.
	*
	* @param int $id
	*/
	public function delete($id) {
		$initial = $this->request;
		$this->request = array();
		$return = parent::delete($id);
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
	* @return array $request ($this->request = set::merge($this->request, $this->parseFiltersFromQuery($query)))
	*/
	public function parseFiltersFromQuery($query) {
		$request = array();
		if (isset($query['recursive'])) {
			$request["filter-depth"] = $query['recursive'];
			if (empty($request['filter-depth'])) {
				unset($request['filter-depth']);
			}
		}
		if (isset($query['limit'])) {
			$request["filter-rows"] = $query['limit'];
		} else {
			$request["filter-rows"] = 200;
		}
		if (isset($query['page'])) {
			$request["filter-start"] = (($query['page'] -1) * $request["filter-rows"]) + 1;
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
	function escapeString($string) {
		return str_replace(
			array('+', '-', '&&', '||', '!', '(', ')', '{', '}', '[', ']', '^', '"', '~', '*', '?', ':', '\\'),
			array('\\+', '\\-', '\\&&', '\\||', '\\!', '\\(', '\\)', '\\{', '\\}', '\\[', '\\]', '\\^', '\\"', '\\~', '%', '\\?', '\\:', '\\\\'),
			trim($string));
	}
}

?>