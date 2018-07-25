<?php

namespace Automattic\VIP\Utils;

class Context_Test extends \PHPUnit_Framework_TestCase {
	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once( __DIR__ . '/../../../lib/utils/context.php' );
	}

	function test__is_cache_healthcheck__nope() {
		$_SERVER['REQUEST_URI'] = '/not-healthcheck-path';

		$actual_result = Context::is_healthcheck();

		$this->assertFalse( $actual_result );
	}

	function test__is_cache_healthcheck__yep() {
		$_SERVER['REQUEST_URI'] = '/cache-healthcheck?';

		$actual_result = Context::is_healthcheck();

		$this->assertTrue( $actual_result );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	function test__is_maintenance_mode__nope() {
		// Note: `WPCOM_VIP_SITE_MAINTENANCE_MODE` not defined

		$actual_result = Context::is_maintenance_mode();

		$this->assertFalse( $actual_result, '`WPCOM_VIP_SITE_MAINTENANCE_MODE` constant should not be defined for this test' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_maintenance_mode__yep() {
		define( 'WPCOM_VIP_SITE_MAINTENANCE_MODE', true );

		$actual_result = Context::is_maintenance_mode();

		$this->assertTrue( $actual_result );
	}

	public function get_test_data__is_web_request__nope() {
		return [
			'is_wp_cli' => [
				'WP_CLI',
			],
			'is_ajax' => [
				'DOING_AJAX',
			],
			'is_installing' => [
				'WP_INSTALLING',
			],
			'is_rest_api' => [
				'REST_REQUEST',
			],
			'is_xmlrpc_api' => [
				'XMLRPC_REQUEST',
			],
			'is_cron' => [
				'DOING_CRON',
			],
		];
	}

	/**
	 * @dataProvider get_test_data__is_web_request__nope
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_web_request__nope( $constant_to_define ) {
		define( $constant_to_define, true );

		$actual_result = Context::is_web_request();

		$this->assertFalse( $actual_result );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_web_request__yep() {
		// Note: none of the constants should be defined here

		$actual_result = Context::is_web_request();

		$this->assertTrue( $actual_result, 'Test failed; either something is actually broken or one of the constants being checked is being unintentionally defined in our test environment.' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_wp_cli__nope() {
		// Note: constant should not be defined here

		$actual_result = Context::is_wp_cli();

		$this->assertFalse( $actual_result );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_wp_cli__yep() {
		define( 'WP_CLI', true );

		$actual_result = Context::is_wp_cli();

		$this->assertTrue( $actual_result );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_rest_api__nope() {
		// Note: constant should not be defined here

		$actual_result = Context::is_rest_api();

		$this->assertFalse( $actual_result );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_rest_api__yep() {
		define( 'REST_REQUEST', true );

		$actual_result = Context::is_rest_api();

		$this->assertTrue( $actual_result );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_cron__nope() {
		// Note: constant should not be defined here

		$actual_result = Context::is_cron();

		$this->assertFalse( $actual_result );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_cron__yep() {
		define( 'DOING_CRON', true );

		$actual_result = Context::is_cron();

		$this->assertTrue( $actual_result );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_xmlrpc_api__nope() {
		// Note: constant should not be defined here

		$actual_result = Context::is_xmlrpc_api();

		$this->assertFalse( $actual_result );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_xmlrpc_api__yep() {
		define( 'XMLRPC_REQUEST', true );

		$actual_result = Context::is_xmlrpc_api();

		$this->assertTrue( $actual_result );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_ajax__nope() {
		// Note: constant should not be defined here

		$actual_result = Context::is_ajax();

		$this->assertFalse( $actual_result );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_ajax__yep() {
		define( 'DOING_AJAX', true );

		$actual_result = Context::is_ajax();

		$this->assertTrue( $actual_result );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_installing__nope() {
		// Note: constant should not be defined here

		$actual_result = Context::is_installing();

		$this->assertFalse( $actual_result );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__is_installing__yep() {
		define( 'WP_INSTALLING', true );

		$actual_result = Context::is_installing();

		$this->assertTrue( $actual_result );
	}
}
