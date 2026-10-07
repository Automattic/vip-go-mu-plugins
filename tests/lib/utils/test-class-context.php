<?php

namespace Automattic\VIP\Utils;

use Automattic\Test\Constant_Mocker;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../lib/utils/class-context.php';

class Context_Test extends TestCase {
	public function get_test_data__is_healthcheck() {
		return [
			'other path'        => [ '/not-healthcheck-path', false ],
			'cache healthcheck' => [ '/cache-healthcheck?', true ],
		];
	}

	/**
	 * @dataProvider get_test_data__is_healthcheck
	 */
	public function test__is_healthcheck( $request_uri, $expected ) {
		$_SERVER['REQUEST_URI'] = $request_uri;

		$this->assertSame( $expected, Context::is_healthcheck() );
	}

	public function get_test_data__constant_contexts() {
		return [
			'is_maintenance_mode' => [ 'is_maintenance_mode', 'WPCOM_VIP_SITE_MAINTENANCE_MODE' ],
			'is_overdue_locked'   => [ 'is_overdue_locked', 'VIP_OVERDUE_LOCKOUT' ],
			'is_wp_cli'           => [ 'is_wp_cli', 'WP_CLI' ],
			'is_rest_api'         => [ 'is_rest_api', 'REST_REQUEST' ],
			'is_cron'             => [ 'is_cron', 'DOING_CRON' ],
			'is_xmlrpc_api'       => [ 'is_xmlrpc_api', 'XMLRPC_REQUEST' ],
			'is_ajax'             => [ 'is_ajax', 'DOING_AJAX' ],
			'is_installing'       => [ 'is_installing', 'WP_INSTALLING' ],
		];
	}

	/**
	 * Each context is off until its constant is set to `true`.
	 *
	 * @dataProvider get_test_data__constant_contexts
	 */
	public function test__constant_context( $method, $constant ) {
		$this->assertFalse( defined( $constant ), "`$constant` should not be defined for this test" );
		$this->assertFalse( Context::$method() );

		Constant_Mocker::define( $constant, true );

		$this->assertTrue( Context::$method() );
	}

	public function get_test_data__is_web_request__nope() {
		return [
			'is_wp_cli'     => [
				'WP_CLI',
			],
			'is_ajax'       => [
				'DOING_AJAX',
			],
			'is_installing' => [
				'WP_INSTALLING',
			],
			'is_rest_api'   => [
				'REST_REQUEST',
			],
			'is_xmlrpc_api' => [
				'XMLRPC_REQUEST',
			],
			'is_cron'       => [
				'DOING_CRON',
			],
		];
	}

	/**
	 * @dataProvider get_test_data__is_web_request__nope
	 */
	public function test__is_web_request__nope( $constant_to_define ) {
		Constant_Mocker::define( $constant_to_define, true );

		$actual_result = Context::is_web_request();

		$this->assertFalse( $actual_result );
	}

	public function test__is_web_request__yep() {
		// Note: none of the constants should be defined here

		$actual_result = Context::is_web_request();

		$this->assertTrue( $actual_result, 'Test failed; either something is actually broken or one of the constants being checked is being unintentionally defined in our test environment.' );
	}

	public function get_test_data__app_values() {
		return [
			'get_app_slug'    => [ 'get_app_slug', 'VIP_GO_APP_SLUG', 'example-app' ],
			'get_environment' => [ 'get_environment', 'VIP_GO_APP_ENVIRONMENT', 'develop' ],
		];
	}

	/**
	 * @dataProvider get_test_data__app_values
	 */
	public function test__app_value( $method, $constant, $value ) {
		$this->assertSame( '', Context::$method() );

		Constant_Mocker::define( $constant, $value );

		$this->assertSame( $value, Context::$method() );
	}
}
