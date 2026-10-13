<?php

use Automattic\Test\Constant_Mocker;

use function Automattic\Test\Utils\run_php;

class Test_Stats extends WP_UnitTestCase {
	public function set_up() {
		parent::set_up();

		// Tracking is skipped for Jetpack requests, so don't depend on what earlier tests left in $_SERVER
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

		parent::tear_down();
	}

	/**
	 * Verify the production stats bootstrap registers the XML-RPC telemetry hook.
	 *
	 * The script runs in its own PHP process, so the test doesn't need process isolation.
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
		$script = str_replace( '__STATS_PATH__', wp_json_encode( dirname( __DIR__ ) . '/stats.php', JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ), $script );
		$result = run_php( [], $script );

		$this->assertSame( 0, $result['exit'], $result['stderr'] );
		$events = json_decode( $result['stdout'], true );
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

	private function inject_mock_tracks(): \PHPUnit\Framework\MockObject\MockObject {
		$mock_tracks = $this->getMockBuilder( 'Automattic\\VIP\\Telemetry\\Tracks' )
			->disableOriginalConstructor()
			->onlyMethods( [ 'record_event' ] )
			->getMock();

		\Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$tracks_instance = $mock_tracks;

		return $mock_tracks;
	}

	public function test_record_xmlrpc_auth_telemetry_not_xmlrpc_request() {
		Constant_Mocker::define( 'XMLRPC_REQUEST', false );

		// Log in, so only the XML-RPC check can prevent tracking
		wp_set_current_user( self::factory()->user->create() );

		$this->inject_mock_tracks()->expects( $this->never() )->method( 'record_event' );

		do_action( 'xmlrpc_call', 'test.method' );
	}

	public function data_record_xmlrpc_auth_telemetry_authenticated(): array {
		return [
			'application password' => [ 'app_pass', 'test.method' ],
			'user password'        => [ 'user_pass', 'wp.getUsersBlogs' ],
		];
	}

	/**
	 * @dataProvider data_record_xmlrpc_auth_telemetry_authenticated
	 */
	public function test_record_xmlrpc_auth_telemetry_authenticated( string $password_type, string $method ) {
		Constant_Mocker::define( 'XMLRPC_REQUEST', true );

		// Create and log in a test user
		wp_set_current_user( self::factory()->user->create() );

		\Automattic\VIP\Stats\XML_RPC_Auth_Tracker::$xmlrpc_password_type = $password_type;

		// Expect the event to be recorded with correct data
		$this->inject_mock_tracks()->expects( $this->once() )
			->method( 'record_event' )
			->with(
				'xmlrpc_authentication',
				$this->callback( function ( $properties ) use ( $password_type, $method ) {
					return $password_type === $properties['password_type'] &&
						$method === $properties['method'];
				} )
			);

		do_action( 'xmlrpc_call', $method );
	}

	public function test_record_xmlrpc_auth_telemetry_unauthenticated() {
		Constant_Mocker::define( 'XMLRPC_REQUEST', true );

		// Ensure no user is logged in
		wp_set_current_user( 0 );

		$this->inject_mock_tracks()->expects( $this->never() )->method( 'record_event' );

		do_action( 'xmlrpc_call', 'test.method' );
	}
}
