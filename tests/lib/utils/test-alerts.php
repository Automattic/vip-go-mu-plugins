<?php

namespace Automattic\VIP\Utils;

 class Alerts_Test extends \WP_UnitTestCase {
	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once( __DIR__ . '/../../../lib/utils/class-alerts.php' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__instance() {
		define( 'ALERT_SERVICE_ADDRESS', 'test.host' );
		define( 'ALERT_SERVICE_PORT', 9999 );

		$alerts = Alerts::instance();

		$this->assertTrue( $alerts instanceof Alerts );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__instance__missing_config() {
		$alerts = Alerts::instance();

		$this->assertWPError( $alerts );
		$this->assertEquals( 'missing-service-address', $alerts->get_error_code(), 'Wrong error message' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__instance__missing_port() {
		define( 'ALERT_SERVICE_ADDRESS', 'test.host' );

		$alerts = Alerts::instance();

		$this->assertWPError( $alerts );
		$this->assertEquals( 'missing-service-port', $alerts->get_error_code(), 'Wrong error message' );
	}
}
