<?php

class Test_Stats extends WP_UnitTestCase {
	private $send_pixel_url_queries = [];
	private $pre_http_request_callback;
	private $force_app_passwords_available_callback;

	/**
	 * Store user IDs created during tests that might have app passwords.
	 * @var array
	 */
	private $test_user_ids_with_app_passwords = [];

	public function set_up() {
		parent::set_up();
		$this->send_pixel_url_queries = [];

		// Filter to enable app passwords
		$this->force_app_passwords_available_callback = '__return_true';
		add_filter( 'wp_is_application_passwords_available', $this->force_app_passwords_available_callback );

		// Mock wp_remote_get calls to the pixel server
		$this->pre_http_request_callback = function ( $preempt, $request_args, $url ) {
			if ( strpos( $url, 'pixel.wp.com/b.gif' ) !== false ) {
				parse_str( wp_parse_url( $url, PHP_URL_QUERY ), $query_args );
				$this->send_pixel_url_queries[] = $query_args;
				return array( 'response' => array( 'code' => 200 ) ); // Prevent actual HTTP request and return a dummy successful response
			}
			return $preempt;
		};
		add_filter( 'pre_http_request', $this->pre_http_request_callback, 10, 3 );

		// Ensure the hook isn't already added from another test
		remove_filter( 'authenticate', 'Automattic\VIP\Stats\track_vip_xmlrpc_auth_type', 30 );
	}

	public function tear_down() {
		// Remove the wp_remote_get mock
		if ( $this->pre_http_request_callback ) {
			remove_filter( 'pre_http_request', $this->pre_http_request_callback, 10 );
		}

		// Remove the app password availability filter
		if ( $this->force_app_passwords_available_callback ) {
			remove_filter( 'wp_is_application_passwords_available', $this->force_app_passwords_available_callback );
		}

		// Remove the hook
		remove_filter( 'authenticate', 'Automattic\VIP\Stats\track_vip_xmlrpc_auth_type', 30 );

		// Clean up any application passwords created for test users
		foreach ( $this->test_user_ids_with_app_passwords as $user_id ) {
			\WP_Application_Passwords::delete_all_application_passwords( $user_id );
		}
		$this->test_user_ids_with_app_passwords = []; // Reset for next test

		parent::tear_down();
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_track_vip_xmlrpc_auth_type_non_xmlrpc_request() {
		// Ensure XMLRPC_REQUEST is not defined or false
		define( 'XMLRPC_REQUEST', false );

		$user_id = self::factory()->user->create();
		$user    = get_user_by( 'id', $user_id );

		// Add the hook we want to test
		add_filter( 'authenticate', 'Automattic\VIP\Stats\track_vip_xmlrpc_auth_type', 30, 3 );

		// Trigger the filter
		$result = apply_filters( 'authenticate', null, 'testuser', 'password' );

		// Assert send_pixel was NOT called
		$this->assertEmpty( $this->send_pixel_url_queries );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertArrayHasKey( 'invalid_username', $result->errors );
	}

	public function test_track_vip_xmlrpc_auth_type_user_pass_success() {
		// Define required constants for this test
		if ( ! defined( 'XMLRPC_REQUEST' ) ) {
			define( 'XMLRPC_REQUEST', true );
		}

		$username = 'testuser';
		$password = 'password';
		$user_id  = self::factory()->user->create( [
			'user_login' => $username,
			'user_pass'  => $password,
		] );
		$user     = get_user_by( 'id', $user_id );

		// Add the hook we want to test
		add_filter( 'authenticate', 'Automattic\VIP\Stats\track_vip_xmlrpc_auth_type', 30, 3 );

		// Trigger the filter with a successful authentication result (pass the user object)
		// In a real scenario, core's authenticate filter (priority 20) would return the $user object first.
		$result = apply_filters( 'authenticate', $user, $username, $password );

		// Assert send_pixel was called (via wp_remote_get mock)
		$this->assertCount( 1, $this->send_pixel_url_queries );

		// Assert the stats arguments are correct
		$expected_stats = [
			'v'                         => 'wpcom-no-pv', // Added by send_pixel
			'x_vip-go-xmlrpc-auth-type' => 'user_pass',
			'x_vip-go-xmlrpc-site-id'   => (string) FILES_CLIENT_SITE_ID, // Defined in bootstrap as 123
		];
		$this->assertEquals( $expected_stats, $this->send_pixel_url_queries[0] );

		// Assert the filter returned the user object unchanged
		$this->assertSame( $user, $result );
	}

	public function test_track_vip_xmlrpc_auth_type_cookie_success() {
		// Define required constants for this test
		if ( ! defined( 'XMLRPC_REQUEST' ) ) {
			define( 'XMLRPC_REQUEST', true );
		}

		$username = 'testuser_cookie';
		$user_id  = self::factory()->user->create( [ 'user_login' => $username ] );
		$user     = get_user_by( 'id', $user_id );

		// Add the hook we want to test
		add_filter( 'authenticate', 'Automattic\VIP\Stats\track_vip_xmlrpc_auth_type', 30, 3 );

		// Trigger the filter with a successful authentication result ($user) and an empty password
		$result = apply_filters( 'authenticate', $user, $username, '' ); // Empty password indicates cookie auth

		// Assert send_pixel was called
		$this->assertCount( 1, $this->send_pixel_url_queries );

		// Assert the stats arguments are correct
		$expected_stats = [
			'v'                         => 'wpcom-no-pv',
			'x_vip-go-xmlrpc-auth-type' => 'cookie',
			'x_vip-go-xmlrpc-site-id'   => (string) FILES_CLIENT_SITE_ID,
		];
		$this->assertEquals( $expected_stats, $this->send_pixel_url_queries[0] );

		// Assert the filter returned the user object unchanged
		$this->assertSame( $user, $result );
	}

	public function test_track_vip_xmlrpc_auth_type_failure() {
		// Define required constants for this test
		if ( ! defined( 'XMLRPC_REQUEST' ) ) {
			define( 'XMLRPC_REQUEST', true );
		}

		$username = 'testuser_fail';
		$password = 'wrongpassword';
		// No user created or user is irrelevant as auth fails before our hook

		// Add the hook we want to test
		add_filter( 'authenticate', 'Automattic\VIP\Stats\track_vip_xmlrpc_auth_type', 30, 3 );

		// Trigger the filter with a failed authentication result (WP_Error)
		$error  = new \WP_Error( 'authentication_failed', 'Authentication failed.' );
		$result = apply_filters( 'authenticate', $error, $username, $password );

		// Assert send_pixel was NOT called
		$this->assertEmpty( $this->send_pixel_url_queries );
		$this->assertInstanceOf( \WP_Error::class, $result );

		$this->send_pixel_url_queries = []; // Reset for next check
		$result_null                  = apply_filters( 'authenticate', null, $username, $password );
		$this->assertEmpty( $this->send_pixel_url_queries );
		$this->assertInstanceOf( \WP_Error::class, $result_null );
	}

	public function test_track_vip_xmlrpc_auth_type_app_password_success() {
		if ( ! defined( 'XMLRPC_REQUEST' ) ) {
			define( 'XMLRPC_REQUEST', true );
		}

		$username                                 = 'testuser_app';
		$user_id                                  = self::factory()->user->create( [
			'user_login' => $username, 
			'user_pass'  => 'a_regular_password',
		] );
		$user                                     = get_user_by( 'id', $user_id );
		$this->test_user_ids_with_app_passwords[] = $user_id;

		// Create a real application password for the user
		$new_password_data = \WP_Application_Passwords::create_new_application_password( $user_id, [ 'name' => 'Test App Password' ] );

		// Check if creation was successful and get the plain-text password
		if ( is_wp_error( $new_password_data ) ) {
			$this->fail( 'Failed to create application password: ' . $new_password_data->get_error_message() );
		}
		if ( ! is_array( $new_password_data ) || ! isset( $new_password_data[0] ) ) {
			$this->fail( 'create_new_application_password did not return the expected array structure.' );
		}
		$app_password_plain = $new_password_data[0]; // Plain-text password is at index 0

		// Add the hook we want to test
		add_filter( 'authenticate', 'Automattic\VIP\Stats\track_vip_xmlrpc_auth_type', 30, 3 );

		// Trigger the filter with the real application password
		$result = apply_filters( 'authenticate', $user, $username, $app_password_plain );

		// Assert send_pixel was called
		$this->assertCount( 1, $this->send_pixel_url_queries );

		// Assert the stats arguments are correct
		$expected_stats = [
			'v'                         => 'wpcom-no-pv',
			'x_vip-go-xmlrpc-auth-type' => 'app_pass',
			'x_vip-go-xmlrpc-site-id'   => (string) FILES_CLIENT_SITE_ID,
		];
		$this->assertEquals( $expected_stats, $this->send_pixel_url_queries[0] );

		// Assert the filter returned the user object unchanged
		$this->assertSame( $user, $result );
	}
}
