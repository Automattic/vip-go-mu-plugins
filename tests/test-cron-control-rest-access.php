<?php

use Automattic\WP\Cron_Control\REST_API;

require_once dirname( __DIR__ ) . '/001-cron.php';
require_once dirname( __DIR__ ) . '/cron/cron-control/includes/class-singleton.php';
require_once dirname( __DIR__ ) . '/cron/cron-control/includes/class-rest-api.php';

class Cron_Control_REST_Access_Test extends WP_UnitTestCase {
	private $server_backup;
	private $get_backup;
	private $query_vars_backup;
	private $authentication_error;

	public function setUp(): void {
		parent::setUp();

		global $wp;

		$this->server_backup     = $_SERVER;
		$this->get_backup        = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test state backup.
		$this->query_vars_backup = $wp->query_vars;
		$_GET                    = [];

		add_filter( 'rest_authentication_errors', 'wpcom_vip_permit_cron_control_rest_access', 999 );
	}

	public function tearDown(): void {
		global $wp;

		remove_filter( 'rest_authentication_errors', 'wpcom_vip_permit_cron_control_rest_access', 999 );
		remove_filter( 'rest_authentication_errors', [ $this, 'return_authentication_error' ], 10 );

		$_SERVER        = $this->server_backup;
		$_GET           = $this->get_backup;
		$wp->query_vars = $this->query_vars_backup;

		parent::tearDown();
	}

	public function return_authentication_error( $authentication_error ) {
		return $this->authentication_error ?? $authentication_error;
	}

	public function test__does_not_bypass_authentication_for_a_non_cron_route_with_a_cron_looking_uri(): void {
		global $wp;

		$authentication_error         = new WP_Error( 'rest_cookie_invalid_nonce' );
		$this->authentication_error   = $authentication_error;
		$_SERVER['REQUEST_URI']       = '/wp-json/' . REST_API::API_NAMESPACE . '/' . REST_API::ENDPOINT_LIST;
		$_SERVER['REQUEST_METHOD']    = 'POST';
		$wp->query_vars['rest_route'] = '/wp/v2/users';
		add_filter( 'rest_authentication_errors', [ $this, 'return_authentication_error' ], 10 );

		$this->assertSame( $authentication_error, ( new WP_REST_Server() )->check_authentication() );
	}

	public function test__does_not_bypass_authentication_when_a_method_override_does_not_match_the_cron_route(): void {
		global $wp;

		$authentication_error         = new WP_Error( 'rest_cookie_invalid_nonce' );
		$_SERVER['REQUEST_METHOD']    = 'POST';
		$_GET['_method']              = 'PUT';
		$wp->query_vars['rest_route'] = '/' . REST_API::API_NAMESPACE . '/' . REST_API::ENDPOINT_LIST;

		$this->assertSame( $authentication_error, apply_filters( 'rest_authentication_errors', $authentication_error ) );
	}

	public function test__bypasses_authentication_for_the_canonical_events_route(): void {
		global $wp;

		$_SERVER['REQUEST_METHOD']    = 'POST';
		$wp->query_vars['rest_route'] = '/' . REST_API::API_NAMESPACE . '/' . REST_API::ENDPOINT_LIST;

		$this->assertTrue( apply_filters( 'rest_authentication_errors', new WP_Error( 'rest_cookie_invalid_nonce' ) ) );
	}

	public function test__bypasses_authentication_for_the_canonical_event_route_with_a_method_override(): void {
		global $wp;

		$_SERVER['REQUEST_METHOD']    = 'POST';
		$_GET['_method']              = 'put';
		$wp->query_vars['rest_route'] = '/' . REST_API::API_NAMESPACE . '/' . REST_API::ENDPOINT_RUN;

		$this->assertTrue( apply_filters( 'rest_authentication_errors', new WP_Error( 'rest_cookie_invalid_nonce' ) ) );
	}

	public function test__bypasses_authentication_for_the_canonical_event_route_with_a_lowercase_header_override(): void {
		global $wp;

		$_SERVER['REQUEST_METHOD']              = 'POST';
		$_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] = 'put';
		$wp->query_vars['rest_route']           = '/' . REST_API::API_NAMESPACE . '/' . REST_API::ENDPOINT_RUN;

		$this->assertTrue( apply_filters( 'rest_authentication_errors', new WP_Error( 'rest_cookie_invalid_nonce' ) ) );
	}
}
