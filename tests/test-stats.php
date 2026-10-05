<?php

use Automattic\Test\Constant_Mocker;

class Test_Stats extends WP_UnitTestCase {
	private $server_backup;

	public function set_up() {
		parent::set_up();

		Constant_Mocker::clear();

		// Tracking is skipped for Jetpack requests, so don't depend on what earlier tests left in $_SERVER
		$this->server_backup = $_SERVER;
		// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__, WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
		unset( $_SERVER['HTTP_USER_AGENT'], $_SERVER['HTTP_X_FORWARDED_FOR'] );

		// Add the hooks we want to test
		add_action( 'application_password_did_authenticate', 'Automattic\\VIP\\Stats\\maybe_set_xml_rpc_auth_tracker_type', 30, 1 );
		add_action( 'xmlrpc_call', 'Automattic\\VIP\\Stats\\track_xml_rpc_password_type', 10, 1 );

		\Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$xmlrpc_password_type = 'user_pass';
		\Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$tracks_instance      = null;
	}

	public function tear_down() {
		// Remove the hooks
		remove_action( 'application_password_did_authenticate', 'Automattic\\VIP\\Stats\\maybe_set_xml_rpc_auth_tracker_type', 30, 1 );
		remove_action( 'xmlrpc_call', 'Automattic\\VIP\\Stats\\track_xml_rpc_password_type', 10, 1 );

		// Reset state again just in case
		\Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$xmlrpc_password_type = 'user_pass';
		\Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$tracks_instance      = null;

		$_SERVER = $this->server_backup;

		Constant_Mocker::clear();
		parent::tear_down();
	}


	/**
	 * Verify the production stats bootstrap registers the XML-RPC telemetry hook.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_production_bootstrap_registers_xmlrpc_telemetry_hook() {
		$script = <<<'PHP'
<?php
namespace {
	define( 'WPCOM_IS_VIP_ENV', true );
	define( 'WPCOM_SANDBOXED', false );
	define( 'XMLRPC_REQUEST', true );
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['hooks'][ $hook ] = $callback; }
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['hooks'][ $hook ] = $callback; }
	function is_user_logged_in() { return true; }
	function vip_is_jetpack_request() { return false; }
	function do_action( $hook, ...$args ) { call_user_func_array( $GLOBALS['hooks'][ $hook ], $args ); }
}
namespace Automattic\VIP\Telemetry {
	class Tracks {
		public function record_event( $name, $properties ) { $GLOBALS['events'][] = [ $name, $properties ]; }
	}
}
namespace {
	require __STATS_PATH__;
	do_action( 'xmlrpc_call', 'wp.getUsersBlogs' );
	echo json_encode( $GLOBALS['events'] ?? [] );
}
PHP;
		$script = str_replace( '__STATS_PATH__', var_export( dirname( __DIR__ ) . '/stats.php', true ), $script );
		// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_tempnam, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink -- The test writes only a disposable subprocess fixture in the system temp directory.
		$path = tempnam( get_temp_dir(), 'vip-stats-bootstrap-' );
		file_put_contents( $path, $script );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- A subprocess isolates constants and exercises production bootstrap registration.
		$process = proc_open(
			[ PHP_BINARY, $path ],
			[
				0 => [ 'pipe', 'r' ],
				1 => [ 'pipe', 'w' ],
				2 => [ 'pipe', 'w' ],
			],
			$pipes
		);
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] );
		$error  = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$exit_code = proc_close( $process );
		unlink( $path );
		// phpcs:enable

		$this->assertSame( 0, $exit_code, $error );
		$events = json_decode( $output, true );
		$this->assertSame( 'xmlrpc_authentication', $events[0][0] ?? null );
		$this->assertSame( 'wp.getUsersBlogs', $events[0][1]['method'] ?? null );
	}

	public function test_application_password_did_authenticate_non_xmlrpc_request() {
		// Ensure XMLRPC_REQUEST is not defined or false
		Constant_Mocker::define( 'XMLRPC_REQUEST', false );

		// Create a test user
		$user = self::factory()->user->create_and_get();

		// Trigger the action
		do_action( 'application_password_did_authenticate', $user, null );

		// Assert state was NOT changed from initial 'user_pass'
		$this->assertEquals( 'user_pass', \Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$xmlrpc_password_type );
	}

	public function test_application_password_did_authenticate_xmlrpc_success() {
		// Define XMLRPC_REQUEST before any other code runs
		Constant_Mocker::define( 'XMLRPC_REQUEST', true );

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

	public function test_application_password_did_authenticate_failure() {
		Constant_Mocker::define( 'XMLRPC_REQUEST', true );

		// Trigger the filter with a failed authentication result (WP_Error)
		$error = new \WP_Error( 'authentication_failed', 'Authentication failed.' );

		do_action( 'application_password_did_authenticate', $error, null );
		$this->assertEquals( 'user_pass', \Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$xmlrpc_password_type );

		// Test with null result
		do_action( 'application_password_did_authenticate', null, null );
		$this->assertEquals( 'user_pass', \Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$xmlrpc_password_type );
	}

	public function test_record_xmlrpc_auth_telemetry_not_xmlrpc_request() {
		Constant_Mocker::define( 'XMLRPC_REQUEST', false );

		// Log in, so only the XML-RPC check can prevent tracking
		wp_set_current_user( self::factory()->user->create() );

		$mock_tracks = $this->getMockBuilder( 'Automattic\\VIP\\Telemetry\\Tracks' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'record_event' ] )
			->getMock();
		
		// Inject mock tracks instance
		\Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$tracks_instance = $mock_tracks;
		
		$mock_tracks->expects( $this->never() )->method( 'record_event' );

		do_action( 'xmlrpc_call', 'test.method' );
	}

	public function test_record_xmlrpc_auth_telemetry_authenticated() {
		Constant_Mocker::define( 'XMLRPC_REQUEST', true );

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

	public function test_record_xmlrpc_auth_telemetry_unauthenticated() {
		Constant_Mocker::define( 'XMLRPC_REQUEST', true );

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

	public function test_record_xmlrpc_auth_telemetry_different_methods() {
		Constant_Mocker::define( 'XMLRPC_REQUEST', true );

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
