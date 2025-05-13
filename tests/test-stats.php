<?php

class Test_Stats extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();

		// Add the hooks we want to test
		add_filter( 'application_password_did_authenticate', 'Automattic\\VIP\\Stats\\application_password_did_authenticate', 30, 1 );
		add_action( 'xmlrpc_call', 'Automattic\\VIP\\Stats\\track_xml_rpc_password_type', 10, 1 );

		\Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$xmlrpc_password_type = 'user_pass';
		\Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$tracks_instance      = null;
	}

	public function tear_down() {
		// Remove the hooks
		remove_filter( 'application_password_did_authenticate', 'Automattic\\VIP\\Stats\\application_password_did_authenticate', 30, 1 );
		remove_action( 'xmlrpc_call', 'Automattic\\VIP\\Stats\\track_xml_rpc_password_type', 10, 1 );

		// Reset state again just in case
		\Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$xmlrpc_password_type = 'user_pass';
		\Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$tracks_instance      = null;

		parent::tear_down();
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_application_password_did_authenticate_non_xmlrpc_request() {
		// Ensure XMLRPC_REQUEST is not defined or false
		define( 'XMLRPC_REQUEST', false );

		// Add the hook we want to test
		add_filter( 'application_password_did_authenticate', 'Automattic\\VIP\\Stats\\application_password_did_authenticate', 30, 3 );

		// Create a test user
		$user = self::factory()->user->create_and_get();

		// Trigger the filter
		apply_filters( 'application_password_did_authenticate', $user );

		// Assert state was NOT changed from initial 'user_pass'
		$this->assertEquals( 'user_pass', \Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$xmlrpc_password_type );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_application_password_did_authenticate_xmlrpc_success() {
		// Define XMLRPC_REQUEST before any other code runs
		if ( ! defined( 'XMLRPC_REQUEST' ) ) {
			define( 'XMLRPC_REQUEST', true );
		}

		$username = 'testuser_app';
		$user_id  = self::factory()->user->create( [
			'user_login' => $username,
			'user_pass'  => 'a_regular_password',
		] );
		$user     = get_user_by( 'id', $user_id );

		// Simulate the action that would be triggered by wp_authenticate_application_password
		do_action( 'application_password_did_authenticate', $user, null );

		$this->assertEquals( 'app_pass', \Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$xmlrpc_password_type );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_application_password_did_authenticate_failure() {
		if ( ! defined( 'XMLRPC_REQUEST' ) ) {
			define( 'XMLRPC_REQUEST', true );
		}

		// Add the hook we want to test
		add_filter( 'application_password_did_authenticate', 'Automattic\\VIP\\Stats\\application_password_did_authenticate', 30, 3 );

		// Trigger the filter with a failed authentication result (WP_Error)
		$error  = new \WP_Error( 'authentication_failed', 'Authentication failed.' );
		$result = apply_filters( 'application_password_did_authenticate', $error );

		$this->assertEquals( 'user_pass', \Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$xmlrpc_password_type );
		$this->assertInstanceOf( \WP_Error::class, $result );

		// Test with null result
		apply_filters( 'application_password_did_authenticate', null );
		$this->assertEquals( 'user_pass', \Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$xmlrpc_password_type );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_record_xmlrpc_auth_telemetry_not_xmlrpc_request() {
		if ( ! defined( 'XMLRPC_REQUEST' ) ) {
			define( 'XMLRPC_REQUEST', false );
		}

		$mock_tracks = $this->getMockBuilder( 'Automattic\\VIP\\Telemetry\\Tracks' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'record_event' ] )
			->getMock();
		
		// Inject mock tracks instance
		\Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$tracks_instance = $mock_tracks;
		
		$mock_tracks->expects( $this->never() )->method( 'record_event' );

		do_action( 'xmlrpc_call', 'test.method' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_record_xmlrpc_auth_telemetry_authenticated() {
		if ( ! defined( 'XMLRPC_REQUEST' ) ) {
			define( 'XMLRPC_REQUEST', true );
		}

		// Create and log in a test user
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$mock_tracks = $this->getMockBuilder( 'Automattic\\VIP\\Telemetry\\Tracks' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'record_event' ] )
			->getMock();

		// Inject mock tracks instance
		\Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$tracks_instance = $mock_tracks;

		// Set the password type to app_pass
		\Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$xmlrpc_password_type = 'app_pass';

		// Expect the event to be recorded with correct data
		$mock_tracks->expects( $this->once() )
			->method( 'record_event' )
			->with(
				'xmlrpc_authentication',
				$this->callback( function ( $properties ) {
					return 'app_pass' === $properties['password_type'] &&
						'test.method' === $properties['method'];
				} )
			);

		do_action( 'xmlrpc_call', 'test.method' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_record_xmlrpc_auth_telemetry_unauthenticated() {
		if ( ! defined( 'XMLRPC_REQUEST' ) ) {
			define( 'XMLRPC_REQUEST', true );
		}

		// Ensure no user is logged in
		wp_set_current_user( 0 );

		$mock_tracks = $this->getMockBuilder( 'Automattic\\VIP\\Telemetry\\Tracks' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'record_event' ] )
			->getMock();

		// Inject mock tracks instance
		\Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$tracks_instance = $mock_tracks;

		// Expect no event to be recorded
		$mock_tracks->expects( $this->never() )->method( 'record_event' );

		do_action( 'xmlrpc_call', 'test.method' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_record_xmlrpc_auth_telemetry_different_methods() {
		if ( ! defined( 'XMLRPC_REQUEST' ) ) {
			define( 'XMLRPC_REQUEST', true );
		}

		// Create and log in a test user
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		// Set the password type to user_pass
		\Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$xmlrpc_password_type = 'user_pass';

		// Test different XML-RPC methods
		$methods = [
			'wp.getUsersBlogs',
			'wp.getProfile',
			'wp.getPost',
			'wp.newPost',
		];

		foreach ( $methods as $method ) {
			$mock_tracks = $this->getMockBuilder( 'Automattic\\VIP\\Telemetry\\Tracks' )
				->disableOriginalConstructor()
				->onlyMethods( [ 'record_event' ] )
				->getMock();

			// Inject mock tracks instance
			\Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$tracks_instance = $mock_tracks;

			$mock_tracks->expects( $this->once() )
				->method( 'record_event' )
				->with(
					'xmlrpc_authentication',
					$this->callback( function ( $properties ) use ( $method ) {
						return 'user_pass' === $properties['password_type'] &&
							$method === $properties['method'];
					} )
				);

			do_action( 'xmlrpc_call', $method );
		}
	}
}
