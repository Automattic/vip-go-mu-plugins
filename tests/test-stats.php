<?php

class Test_Stats extends WP_UnitTestCase {
	private $force_app_passwords_available_callback;

	/**
	 * Store user IDs created during tests that might have app passwords.
	 * @var array
	 */
	private $test_user_ids_with_app_passwords = [];

	public function set_up() {
		parent::set_up();

		// Filter to enable app passwords
		$this->force_app_passwords_available_callback = '__return_true';
		add_filter( 'wp_is_application_passwords_available', $this->force_app_passwords_available_callback );

		// Ensure the hook isn't already added from another test and reset state
		remove_filter( 'authenticate', 'Automattic\VIP\Stats\determine_xmlrpc_password_type', 30 );
		Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$xmlrpc_password_type = 'none';
		Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$tracks_instance      = null;
	}

	public function tear_down() {
		// Remove the app password availability filter
		if ( $this->force_app_passwords_available_callback ) {
			remove_filter( 'wp_is_application_passwords_available', $this->force_app_passwords_available_callback );
		}

		// Remove the hook
		remove_filter( 'authenticate', 'Automattic\VIP\Stats\determine_xmlrpc_password_type', 30 );

		// Clean up any application passwords created for test users
		foreach ( $this->test_user_ids_with_app_passwords as $user_id ) {
			\WP_Application_Passwords::delete_all_application_passwords( $user_id );
		}
		$this->test_user_ids_with_app_passwords = []; // Reset for next test

		// Reset state again just in case
		Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$xmlrpc_password_type = 'none';
		Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$tracks_instance      = null;

		parent::tear_down();
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_determine_xmlrpc_password_type_non_xmlrpc_request() {
		// Ensure XMLRPC_REQUEST is not defined or false
		define( 'XMLRPC_REQUEST', false );

		// Add the hook we want to test
		add_filter( 'authenticate', 'Automattic\VIP\Stats\determine_xmlrpc_password_type', 30, 3 );

		// Trigger the filter
		$result = apply_filters( 'authenticate', null, 'testuser', 'password' );

		// Assert state was NOT changed from initial 'none'
		$this->assertEquals( 'none', Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$xmlrpc_password_type );
		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_determine_xmlrpc_password_type_user_pass_success() {
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
		add_filter( 'authenticate', 'Automattic\VIP\Stats\determine_xmlrpc_password_type', 30, 3 );

		// Trigger the filter with a successful authentication result
		$result = apply_filters( 'authenticate', $user, $username, $password );

		$this->assertEquals( 'user_pass', Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$xmlrpc_password_type );
		$this->assertSame( $user, $result );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_determine_xmlrpc_password_type_cookie_success() {
		if ( ! defined( 'XMLRPC_REQUEST' ) ) {
			define( 'XMLRPC_REQUEST', true );
		}

		$username = 'testuser_cookie';
		$user_id  = self::factory()->user->create( [ 'user_login' => $username ] );
		$user     = get_user_by( 'id', $user_id );

		// Add the hook we want to test
		add_filter( 'authenticate', 'Automattic\VIP\Stats\determine_xmlrpc_password_type', 30, 3 );

		// Trigger the filter with a successful authentication result
		$result = apply_filters( 'authenticate', $user, $username, '' ); // Empty password indicates cookie auth

		$this->assertEquals( 'cookie', Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$xmlrpc_password_type );
		$this->assertSame( $user, $result );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_determine_xmlrpc_password_type_failure() {
		if ( ! defined( 'XMLRPC_REQUEST' ) ) {
			define( 'XMLRPC_REQUEST', true );
		}

		$username = 'testuser_fail';
		$password = 'wrongpassword';

		// Add the hook we want to test
		add_filter( 'authenticate', 'Automattic\VIP\Stats\determine_xmlrpc_password_type', 30, 3 );

		// Trigger the filter with a failed authentication result (WP_Error)
		$error  = new \WP_Error( 'authentication_failed', 'Authentication failed.' );
		$result = apply_filters( 'authenticate', $error, $username, $password );

		$this->assertEquals( 'none', Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$xmlrpc_password_type );
		$this->assertInstanceOf( \WP_Error::class, $result );

		$this->assertEquals( 'none', Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$xmlrpc_password_type );
		$result_null = apply_filters( 'authenticate', null, $username, $password );
		$this->assertEquals( 'none', Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$xmlrpc_password_type );
		$this->assertInstanceOf( \WP_Error::class, $result_null );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_determine_xmlrpc_password_type_app_password_success() {
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
		add_filter( 'authenticate', 'Automattic\VIP\Stats\determine_xmlrpc_password_type', 30, 3 );

		// Trigger the filter with the real application password
		$result = apply_filters( 'authenticate', $user, $username, $app_password_plain );

		$this->assertEquals( 'app_pass', Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$xmlrpc_password_type );
		$this->assertSame( $user, $result );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_record_xmlrpc_auth_telemetry_not_xmlrpc_request() {
		if ( ! defined( 'XMLRPC_REQUEST' ) ) {
			define( 'XMLRPC_REQUEST', false );
		}

		$mock_tracks = $this->getMockBuilder( 'Automattic\VIP\Telemetry\Tracks' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'record_event' ] )
			->getMock();
		
		// Inject mock tracks instance
		Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$tracks_instance = $mock_tracks;
		
		$mock_tracks->expects( $this->never() )->method( 'record_event' );

		do_action( 'xmlrpc_call', 'test.method' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_record_xmlrpc_auth_telemetry_user_not_logged_in() {
		if ( ! defined( 'XMLRPC_REQUEST' ) ) {
			define( 'XMLRPC_REQUEST', true );
		}

		add_action( 'xmlrpc_call', 'Automattic\VIP\Stats\record_xmlrpc_auth_telemetry_on_xmlrpc_call' );

		wp_logout(); // Ensure no user is logged in

		$mock_tracks = $this->getMockBuilder( 'Automattic\VIP\Telemetry\Tracks' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'record_event' ] )
			->getMock();    

		// Inject mock tracks instance
		Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$tracks_instance = $mock_tracks;

		$mock_tracks->expects( $this->never() )->method( 'record_event' );

		do_action( 'xmlrpc_call', 'test.method' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_record_xmlrpc_auth_telemetry_sends_event() {
		if ( ! defined( 'XMLRPC_REQUEST' ) ) {
			define( 'XMLRPC_REQUEST', true );
		}

		add_action( 'xmlrpc_call', 'Automattic\VIP\Stats\record_xmlrpc_auth_telemetry_on_xmlrpc_call' );

		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$xmlrpc_method_name = 'test.methodName';

		$mock_tracks = $this->getMockBuilder( 'Automattic\VIP\Telemetry\Tracks' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'record_event' ] )
			->getMock();

		// Inject the mock instance BEFORE the action is triggered
		Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$xmlrpc_password_type = 'user_pass';
		Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$tracks_instance      = $mock_tracks;

		$expected_site_id = defined( 'FILES_CLIENT_SITE_ID' ) ? FILES_CLIENT_SITE_ID : 0;
		$mock_tracks->expects( $this->once() )->method( 'record_event' )->with(
			'xmlrpc_authentication', 
			[
				'password_type' => 'user_pass',
				'method'        => $xmlrpc_method_name,
				'site_id'       => $expected_site_id,
			]
		);

		do_action( 'xmlrpc_call', $xmlrpc_method_name );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_track_method_bails_if_password_type_is_none() {
		if ( ! defined( 'XMLRPC_REQUEST' ) ) {
			define( 'XMLRPC_REQUEST', true );
		}
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		$mock_tracks = $this->getMockBuilder( 'Automattic\VIP\Telemetry\Tracks' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'record_event' ] )
			->getMock();

		// Inject mock tracks instance
		Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$xmlrpc_password_type = 'none'; // Ensure type is none
		Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$tracks_instance      = $mock_tracks;

		$mock_tracks->expects( $this->never() )->method( 'record_event' );

		do_action( 'xmlrpc_call', 'test.method' );
	}
}
