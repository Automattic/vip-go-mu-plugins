<?php

use Automattic\WP\Cron_Control\REST_API;

require_once dirname( __DIR__ ) . '/001-cron.php';
require_once dirname( __DIR__ ) . '/cron/cron-control/includes/class-singleton.php';
require_once dirname( __DIR__ ) . '/cron/cron-control/includes/class-rest-api.php';

class Cron_Control_REST_Access_Test extends WP_UnitTestCase {
	private $get_backup;
	private $query_vars_backup;

	public function setUp(): void {
		parent::setUp();

		global $wp;

		$this->get_backup        = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test state backup.
		$this->query_vars_backup = $wp->query_vars;
		$_GET                    = [];

		add_filter( 'rest_authentication_errors', 'wpcom_vip_permit_cron_control_rest_access', 999 );
	}

	public function tearDown(): void {
		global $wp;

		remove_filter( 'rest_authentication_errors', 'wpcom_vip_permit_cron_control_rest_access', 999 );

		$_GET           = $this->get_backup;
		$wp->query_vars = $this->query_vars_backup;

		parent::tearDown();
	}

	public function data_rest_access(): array {
		$events_route = '/' . REST_API::API_NAMESPACE . '/' . REST_API::ENDPOINT_LIST;
		$event_route  = '/' . REST_API::API_NAMESPACE . '/' . REST_API::ENDPOINT_RUN;

		// [ rest_route, $_SERVER values, $_GET values, whether authentication is bypassed ]
		return [
			'non-cron route with a cron-looking URI'       => [
				'/wp/v2/users',
				[
					'REQUEST_METHOD' => 'POST',
					'REQUEST_URI'    => '/wp-json' . $events_route,
				],
				[],
				false,
			],
			'method override not matching the cron route'  => [ $events_route, [ 'REQUEST_METHOD' => 'POST' ], [ '_method' => 'PUT' ], false ],
			'canonical events route'                       => [ $events_route, [ 'REQUEST_METHOD' => 'POST' ], [], true ],
			'canonical event route with a method override' => [ $event_route, [ 'REQUEST_METHOD' => 'POST' ], [ '_method' => 'put' ], true ],
			'canonical event route with a lowercase override' => [
				$event_route,
				[
					'REQUEST_METHOD'              => 'POST',
					'HTTP_X_HTTP_METHOD_OVERRIDE' => 'put',
				],
				[],
				true,
			],
		];
	}

	/**
	 * @dataProvider data_rest_access
	 */
	public function test__rest_access( string $route, array $server, array $get, bool $bypassed ): void {
		global $wp;

		$_SERVER                      = array_merge( $_SERVER, $server );
		$_GET                         = $get;
		$wp->query_vars['rest_route'] = $route;

		$authentication_error = new WP_Error( 'rest_cookie_invalid_nonce' );
		$expected             = $bypassed ? true : $authentication_error;

		$this->assertSame( $expected, apply_filters( 'rest_authentication_errors', $authentication_error ) );
	}
}
