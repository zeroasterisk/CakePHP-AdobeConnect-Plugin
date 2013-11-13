<?php
/**
 * AdobeConnect Plugin Configuration
 *
 * Used by Configure::load('adobe_connect');
 *
 * Accessible by Configure::read('AdobeConnect.$key');
 * Or as a whole by AdobeConnectAppModel->config();
 */
// -----------------
// Production Data (default to prod)
$config = array(
	'AdobeConnect' => array(
		'datasource' => 'AdobeConnect.AdobeConnectSource',
		'username' => 'apiuser@example.com',
		'password' => 'xxxxxxxxx',
		'salt' => 'some-custom-salt',
		'url' => 'http://connect.example.com/api/xml',
		// specify a cache-engine to reduce API calls (false to disable)
		'cacheEngine' => 'default',
		// model we will log all API interactions to (false to disable)
		'modelConnectApiLog' => 'ConnectApiLog',
		// setting a login prefix will allow you to prefix principal/user logins in AdobeConnect
		//   this helps alleviate the possibility of account username conflicts
		'loginPrefix' => '',
		// root level SCO-IDs you want to know about...
		'sco-ids' => array(
			'root' => "10000",
			'seminar-root' => "10005",
			'template-root' => "10045",
			'content-root' => "10000",
			'default-folder' => "10039",
			'default-template' => "49848",
		),
		// timing offsets/padding (between app server and connect server)
		'secondsServerTimeTolerance' => 900, // 15 minutes
		'secondsGapPadding' => 180, // 3 minutes
		// Connect Server Timezone = GMT -6
		//'connect-server-timezone-offset' => -6,
		// Connect Server Timezone = US/Central | http://www.php.net/manual/en/timezones.others.php
		//'connect-server-timezone' => 'US/Central',
		// -------------------------
		// should not need to edit below here
		// -------------------------
		// the base apiUserKey (which is our normal API user)
		'apiUserKey' => 'APIUSER',
	)
);
/*
// customizations based on environment...
if (Configure::read('env') != 'prod') {
	$config['AdobeConnect']['url'] = 'http://dev.connect.audiologyonline.com/api/xml';
	$config['AdobeConnect']['loginPrefix'] = 'ENV_' . Configure::read('env') . '_';
}
*/
