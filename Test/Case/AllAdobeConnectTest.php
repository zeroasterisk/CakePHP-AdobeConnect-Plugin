<?php
/**
 * Convenience test for all Tests in AdobeConnect Plugin
 *
 * ./cake test AdobeConnect AllAdobeConnect
 *
 */
class AllAdobeConnectTest extends CakeTestSuite {
	public static $working = array(
		// these test require API
		'Model/Datasource/AdobeConnectSource',
		'Model/AdobeConnectAppModel',
		'Model/AdobeConnectSco',
		'Model/AdobeConnectPrincipal',
		'Model/AdobeConnectPermission',
		'Model/AdobeConnectReport',
	);

	public static function suite() {
		$suite = new CakeTestSuite('All Adobe Connect tests');
		$dir = dirname(__FILE__);
		foreach (self::$working as $file) {
			$suite->addTestFile($dir . DS . $file . 'Test.php');
		}
		return $suite;
	}
}
