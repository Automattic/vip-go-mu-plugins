<?php
/**
 * Test: WordPress MCP Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;
use Automattic\Test\Constant_Mocker;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing

class WordPress_Mcp_Integration_Test extends WP_UnitTestCase {
	private string $slug         = 'wordpress-mcp';
	private array $server_backup = [];
	private array $get_backup    = [];

	public function setUp(): void {
		parent::setUp();

		$this->server_backup = $_SERVER;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test state backup.
		$this->get_backup = $_GET;
	}

	public function tearDown(): void {
		$_SERVER = $this->server_backup;
		$_GET    = $this->get_backup;

		parent::tearDown();

		Constant_Mocker::clear();
	}

	public function test_is_loaded_returns_false_when_not_loaded(): void {
		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );

		$this->assertFalse( $wordpress_mcp_integration->is_loaded() );
	}

	public function test_filter_default_server_config_applies_configured_values(): void {
		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );
		$wordpress_mcp_integration->activate(
			[
				'config' => [
					'server_namespace' => 'vip-mcp/v1',
					'server_route'     => 'vip-mcp-server',
				],
			]
		);
		$wordpress_mcp_integration->configure();

		$config = $wordpress_mcp_integration->filter_default_server_config(
			[
				'server_id'              => 'mcp-adapter-default-server',
				'server_route_namespace' => 'mcp',
				'server_route'           => 'mcp-adapter-default-server',
			]
		);

		$this->assertSame( 'mcp-adapter-default-server', $config['server_id'] );
		$this->assertSame( 'vip-mcp/v1', $config['server_route_namespace'] );
		$this->assertSame( 'vip-mcp-server', $config['server_route'] );
	}

	public function test_filter_default_server_config_only_applies_configured_server_namespace(): void {
		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );
		$wordpress_mcp_integration->activate( [ 'config' => [ 'server_namespace' => 'vip-mcp/v1' ] ] );
		$wordpress_mcp_integration->configure();

		$config = $wordpress_mcp_integration->filter_default_server_config(
			[
				'server_id'              => 'mcp-adapter-default-server',
				'server_route_namespace' => 'mcp',
				'server_route'           => 'mcp-adapter-default-server',
			]
		);

		$this->assertSame( 'mcp-adapter-default-server', $config['server_id'] );
		$this->assertSame( 'vip-mcp/v1', $config['server_route_namespace'] );
		$this->assertSame( 'mcp-adapter-default-server', $config['server_route'] );
	}

	public function test_filter_default_server_config_only_applies_configured_server_route(): void {
		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );
		$wordpress_mcp_integration->activate( [ 'config' => [ 'server_route' => 'vip-mcp-server' ] ] );
		$wordpress_mcp_integration->configure();

		$config = $wordpress_mcp_integration->filter_default_server_config(
			[
				'server_id'              => 'mcp-adapter-default-server',
				'server_route_namespace' => 'mcp',
				'server_route'           => 'mcp-adapter-default-server',
			]
		);

		$this->assertSame( 'mcp-adapter-default-server', $config['server_id'] );
		$this->assertSame( 'mcp', $config['server_route_namespace'] );
		$this->assertSame( 'vip-mcp-server', $config['server_route'] );
	}

	public function test_load_registers_default_server_config_filter_at_max_priority(): void {
		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );
		$wordpress_mcp_integration->activate( [ 'config' => [ 'server_namespace' => 'vip-mcp/v1' ] ] );
		$wordpress_mcp_integration->configure();

		$wordpress_mcp_integration->load();

		$this->assertSame(
			PHP_INT_MAX,
			has_filter( 'mcp_adapter_default_server_config', [ $wordpress_mcp_integration, 'filter_default_server_config' ] )
		);
		$this->assertSame(
			10,
			has_filter( 'mcp_adapter_target_ability_permission_denied_message', [ $wordpress_mcp_integration, 'filter_target_ability_permission_denied_message' ] )
		);

		remove_filter( 'mcp_adapter_default_server_config', [ $wordpress_mcp_integration, 'filter_default_server_config' ], PHP_INT_MAX );
		remove_filter( 'determine_current_user', [ $wordpress_mcp_integration, 'authenticate_mcp_request' ], 19 );
		remove_filter( 'mcp_adapter_target_ability_permission_denied_message', [ $wordpress_mcp_integration, 'filter_target_ability_permission_denied_message' ], 10 );
	}

	public function test_load_does_not_register_default_server_config_filter_without_server_config(): void {
		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );

		$wordpress_mcp_integration->load();

		$this->assertFalse(
			has_filter( 'mcp_adapter_default_server_config', [ $wordpress_mcp_integration, 'filter_default_server_config' ] )
		);

		remove_filter( 'determine_current_user', [ $wordpress_mcp_integration, 'authenticate_mcp_request' ], 19 );
		remove_filter( 'mcp_adapter_target_ability_permission_denied_message', [ $wordpress_mcp_integration, 'filter_target_ability_permission_denied_message' ], 10 );
	}

	public function test_load_sets_inactive_if_no_versions_found(): void {
		/** @var MockObject&WordPressMcpIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( WordPressMcpIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'is_loaded', 'get_versions' ] )
			->getMock();

		$integration_mock->activate();
		$integration_mock->method( 'is_loaded' )->willReturn( false );
		$integration_mock->method( 'get_versions' )->willReturn( [] );

		$integration_mock->load();
		do_action( 'plugins_loaded' );
		remove_filter( 'mcp_adapter_default_server_config', [ $integration_mock, 'filter_default_server_config' ], PHP_INT_MAX );
		remove_filter( 'determine_current_user', [ $integration_mock, 'authenticate_mcp_request' ], 19 );
		remove_filter( 'mcp_adapter_target_ability_permission_denied_message', [ $integration_mock, 'filter_target_ability_permission_denied_message' ], 10 );

		$this->assertFalse( $integration_mock->is_active() );
	}

	public function test_filter_target_ability_permission_denied_message_keeps_default_without_two_factor_block(): void {
		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );

		$this->assertSame(
			'Default permission denied.',
			$wordpress_mcp_integration->filter_target_ability_permission_denied_message(
				'Default permission denied.',
				'core/get-environment-info',
				[]
			)
		);
	}

	public function test_filter_target_ability_permission_denied_message_adds_two_factor_context(): void {
		$wordpress_mcp_integration = new class( $this->slug ) extends WordPressMcpIntegration {
			protected function is_current_user_blocked_by_required_two_factor(): bool {
				return true;
			}
		};

		$this->assertSame(
			'Unable to run ability "core/get-environment-info" because two-factor authentication is not enabled for the current user.',
			$wordpress_mcp_integration->filter_target_ability_permission_denied_message(
				'Default permission denied.',
				'core/get-environment-info',
				[]
			)
		);
	}

	public function test_is_mcp_adapter_rest_request_returns_true_for_pretty_rest_url(): void {
		Constant_Mocker::define( 'REST_REQUEST', true );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/wp-json/mcp/mcp-adapter-default-server';

		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );

		$this->assertTrue( $wordpress_mcp_integration->is_mcp_adapter_rest_request() );
	}

	public function test_is_mcp_adapter_rest_request_returns_true_for_configured_rest_url(): void {
		Constant_Mocker::define( 'REST_REQUEST', true );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/wp-json/vip-mcp/v1/vip-mcp-server';

		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );
		$wordpress_mcp_integration->activate(
			[
				'config' => [
					'server_namespace' => 'vip-mcp/v1',
					'server_route'     => 'vip-mcp-server',
				],
			]
		);
		$wordpress_mcp_integration->configure();

		$this->assertTrue( $wordpress_mcp_integration->is_mcp_adapter_rest_request() );
	}

	public function test_is_mcp_adapter_rest_request_uses_default_namespace_with_configured_server_route(): void {
		Constant_Mocker::define( 'REST_REQUEST', true );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/wp-json/mcp/vip-mcp-server';

		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );
		$wordpress_mcp_integration->activate( [ 'config' => [ 'server_route' => 'vip-mcp-server' ] ] );
		$wordpress_mcp_integration->configure();

		$this->assertTrue( $wordpress_mcp_integration->is_mcp_adapter_rest_request() );
	}

	public function test_is_mcp_adapter_rest_request_uses_default_route_with_configured_namespace(): void {
		Constant_Mocker::define( 'REST_REQUEST', true );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/wp-json/vip-mcp/v1/mcp-adapter-default-server';

		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );
		$wordpress_mcp_integration->activate( [ 'config' => [ 'server_namespace' => 'vip-mcp/v1' ] ] );
		$wordpress_mcp_integration->configure();

		$this->assertTrue( $wordpress_mcp_integration->is_mcp_adapter_rest_request() );
	}

	public function test_is_mcp_adapter_rest_request_returns_true_for_rest_route_query_arg(): void {
		Constant_Mocker::define( 'REST_REQUEST', true );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/index.php?rest_route=/mcp/mcp-adapter-default-server';
		$_GET['rest_route']        = '/mcp/mcp-adapter-default-server';

		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );

		$this->assertTrue( $wordpress_mcp_integration->is_mcp_adapter_rest_request() );
	}

	public function test_is_mcp_adapter_rest_request_returns_false_for_other_rest_url(): void {
		Constant_Mocker::define( 'REST_REQUEST', true );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/wp-json/wp/v2/posts';

		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );

		$this->assertFalse( $wordpress_mcp_integration->is_mcp_adapter_rest_request() );
	}

	public function test_authenticate_mcp_request_preserves_existing_user(): void {
		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );

		$this->assertSame( 123, $wordpress_mcp_integration->authenticate_mcp_request( 123 ) );
	}

	public function test_authenticate_mcp_request_maps_valid_hmac_to_user(): void {
		$auth_key  = 'test-auth-key';
		$email     = 'mcp-user-' . wp_generate_password( 8, false ) . '@example.com';
		$timestamp = (string) time();
		$user_id   = $this->factory()->user->create( [ 'user_email' => $email ] );

		Constant_Mocker::define( 'AUTH_KEY', $auth_key );
		Constant_Mocker::define( 'REST_REQUEST', true );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/wp-json/mcp/mcp-adapter-default-server';
		// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.BasicAuthentication -- Test fixture for MCP Basic auth bridge.
		$_SERVER['PHP_AUTH_USER'] = $email;
		// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.BasicAuthentication -- Test fixture for MCP Basic auth bridge.
		$_SERVER['PHP_AUTH_PW']                   = hash_hmac( 'sha256', $email . $timestamp, $auth_key );
		$_SERVER['HTTP_X_VIP_MCP_AUTH']           = 'true';
		$_SERVER['HTTP_X_VIP_MCP_AUTH_TIMESTAMP'] = $timestamp;

		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );

		$this->assertSame( $user_id, $wordpress_mcp_integration->authenticate_mcp_request( false ) );
	}

	public function test_authenticate_mcp_request_ignores_missing_hmac_headers(): void {
		Constant_Mocker::define( 'AUTH_KEY', 'test-auth-key' );
		Constant_Mocker::define( 'REST_REQUEST', true );

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/wp-json/mcp/mcp-adapter-default-server';

		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );

		$this->assertFalse( $wordpress_mcp_integration->authenticate_mcp_request( false ) );
	}
}
