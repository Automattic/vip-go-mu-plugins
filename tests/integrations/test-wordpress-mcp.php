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
use Org_Integration_Status;
use Env_Integration_Status;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing

class WordPress_Mcp_Integration_Test extends WP_UnitTestCase {
	private string $slug          = 'wordpress-mcp';
	private array $server_backup  = [];
	private array $get_backup     = [];
	private array $vip_config_map = [];

	public function setUp(): void {
		parent::setUp();

		$this->server_backup = $_SERVER;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test state backup.
		$this->get_backup = $_GET;
	}

	public function tearDown(): void {
		remove_filter( 'vip_integrations_pre_load_config', [ $this, 'filter_vip_config' ], 10 );

		$_SERVER              = $this->server_backup;
		$_GET                 = $this->get_backup;
		$this->vip_config_map = [];

		parent::tearDown();

		Constant_Mocker::clear();
	}

	public function filter_vip_config( $config, $path, $slug ) {
		if ( array_key_exists( $slug, $this->vip_config_map ) ) {
			return $this->vip_config_map[ $slug ];
		}

		return $config;
	}

	private function set_vip_config_map( array $vip_config_map ): void {
		$this->vip_config_map = $vip_config_map;

		remove_filter( 'vip_integrations_pre_load_config', [ $this, 'filter_vip_config' ], 10 );
		add_filter( 'vip_integrations_pre_load_config', [ $this, 'filter_vip_config' ], 10, 3 );
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

		remove_filter( 'mcp_adapter_default_server_config', [ $wordpress_mcp_integration, 'filter_default_server_config' ], PHP_INT_MAX );
		remove_filter( 'determine_current_user', [ $wordpress_mcp_integration, 'authenticate_mcp_request' ], 19 );
		remove_filter( 'rest_authentication_errors', [ $wordpress_mcp_integration, 'report_auth_error' ] );
	}

	public function test_load_registers_exposed_abilities_args_filter_when_configured(): void {
		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );
		$wordpress_mcp_integration->activate( [ 'config' => [ 'exposed_abilities' => [ 'core/get-site-info' ] ] ] );
		$wordpress_mcp_integration->configure();

		$wordpress_mcp_integration->load();

		$this->assertSame(
			PHP_INT_MAX,
			has_filter( 'wp_register_ability_args', [ $wordpress_mcp_integration, 'filter_exposed_abilities_args' ] )
		);

		remove_filter( 'wp_register_ability_args', [ $wordpress_mcp_integration, 'filter_exposed_abilities_args' ], PHP_INT_MAX );
		remove_filter( 'determine_current_user', [ $wordpress_mcp_integration, 'authenticate_mcp_request' ], 19 );
		remove_filter( 'rest_authentication_errors', [ $wordpress_mcp_integration, 'report_auth_error' ] );
	}

	public function test_load_does_not_register_default_server_config_filter_without_server_config(): void {
		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );

		$wordpress_mcp_integration->load();

		$this->assertFalse(
			has_filter( 'mcp_adapter_default_server_config', [ $wordpress_mcp_integration, 'filter_default_server_config' ] )
		);

		remove_filter( 'determine_current_user', [ $wordpress_mcp_integration, 'authenticate_mcp_request' ], 19 );
		remove_filter( 'rest_authentication_errors', [ $wordpress_mcp_integration, 'report_auth_error' ] );
	}

	public function test_load_does_not_register_exposed_abilities_args_filter_without_config(): void {
		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );

		$wordpress_mcp_integration->load();

		$this->assertFalse(
			has_filter( 'wp_register_ability_args', [ $wordpress_mcp_integration, 'filter_exposed_abilities_args' ] )
		);

		remove_filter( 'determine_current_user', [ $wordpress_mcp_integration, 'authenticate_mcp_request' ], 19 );
		remove_filter( 'rest_authentication_errors', [ $wordpress_mcp_integration, 'report_auth_error' ] );
	}

	public function test_filter_exposed_abilities_args_marks_configured_ability_public(): void {
		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );
		$wordpress_mcp_integration->activate( [ 'config' => [ 'exposed_abilities' => [ 'core/get-site-info' ] ] ] );
		$wordpress_mcp_integration->configure();

		$args = $wordpress_mcp_integration->filter_exposed_abilities_args(
			[
				'meta' => [
					'mcp'  => [
						'description' => 'Existing MCP metadata',
					],
					'data' => 'preserved',
				],
			],
			'core/get-site-info'
		);

		$this->assertTrue( $args['meta']['mcp']['public'] );
		$this->assertSame( 'Existing MCP metadata', $args['meta']['mcp']['description'] );
		$this->assertSame( 'preserved', $args['meta']['data'] );
	}

	public function test_filter_exposed_abilities_args_handles_invalid_meta_shape(): void {
		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );
		$wordpress_mcp_integration->activate( [ 'config' => [ 'exposed_abilities' => [ 'core/get-site-info' ] ] ] );
		$wordpress_mcp_integration->configure();

		$args = $wordpress_mcp_integration->filter_exposed_abilities_args(
			[ 'meta' => 'invalid' ],
			'core/get-site-info'
		);

		$this->assertTrue( $args['meta']['mcp']['public'] );

		$args = $wordpress_mcp_integration->filter_exposed_abilities_args(
			[ 'meta' => [ 'mcp' => 'invalid' ] ],
			'core/get-site-info'
		);

		$this->assertTrue( $args['meta']['mcp']['public'] );
	}

	public function test_filter_exposed_abilities_args_matches_wildcards(): void {
		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );
		$wordpress_mcp_integration->activate( [ 'config' => [ 'exposed_abilities' => [ 'core/get*', 'core/*-info', 'vip/*' ] ] ] );
		$wordpress_mcp_integration->configure();

		$this->assertTrue( $wordpress_mcp_integration->filter_exposed_abilities_args( [], 'core/get-site-info' )['meta']['mcp']['public'] );
		$this->assertTrue( $wordpress_mcp_integration->filter_exposed_abilities_args( [], 'core/site-info' )['meta']['mcp']['public'] );
		$this->assertTrue( $wordpress_mcp_integration->filter_exposed_abilities_args( [], 'vip/update-site' )['meta']['mcp']['public'] );
		$this->assertSame( [], $wordpress_mcp_integration->filter_exposed_abilities_args( [], 'other/delete-site' ) );

		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );
		$wordpress_mcp_integration->activate( [ 'config' => [ 'exposed_abilities' => [ '*', '*/*', '*/delete-site' ] ] ] );
		$wordpress_mcp_integration->configure();

		$this->assertSame( [], $wordpress_mcp_integration->filter_exposed_abilities_args( [], 'vip/delete-site' ) );
	}

	public function test_filter_exposed_abilities_args_preserves_unconfigured_ability(): void {
		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );
		$wordpress_mcp_integration->activate( [ 'config' => [ 'exposed_abilities' => [ 'core/get-site-info' ] ] ] );
		$wordpress_mcp_integration->configure();

		$args = [
			'meta' => [
				'mcp' => [
					'public' => false,
				],
			],
		];

		$this->assertSame(
			$args,
			$wordpress_mcp_integration->filter_exposed_abilities_args( $args, 'core/update-site' )
		);
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

		$this->assertFalse( $integration_mock->is_active() );
	}

	public function test_platform_activation_uses_secure_mcp_child_config(): void {
		$this->set_vip_config_map(
			[
				'secure-mcp' => [
					'org'      => [
						'status' => Org_Integration_Status::ENABLED,
					],
					'env'      => [
						'status' => Env_Integration_Status::ENABLED,
					],
					'children' => [
						'wordpress-mcp' => [
							'env' => [
								'status' => Env_Integration_Status::ENABLED,
								'config' => [
									'server_route' => 'vip-mcp-server',
								],
							],
						],
					],
				],
			]
		);

		$integrations = new Integrations();
		$integration  = new WordPressMcpIntegration( $this->slug );

		$integrations->register( $integration );
		$integrations->activate_platform_integrations();

		$this->assertTrue( $integration->is_active() );
		$this->assertSame( [ 'server_route' => 'vip-mcp-server' ], $integration->get_env_config() );
	}

	public function test_secure_mcp_parent_gates_wordpress_mcp_child_config(): void {
		$this->set_vip_config_map(
			[
				'secure-mcp' => [
					'org'      => [
						'status' => Org_Integration_Status::ENABLED,
					],
					'env'      => [
						'status' => Env_Integration_Status::DISABLED,
					],
					'children' => [
						'wordpress-mcp' => [
							'env' => [
								'status' => Env_Integration_Status::ENABLED,
							],
						],
					],
				],
			]
		);

		$integrations = new Integrations();
		$integration  = new WordPressMcpIntegration( $this->slug );

		$integrations->register( $integration );
		$integrations->activate_platform_integrations();

		$this->assertFalse( $integration->is_active() );
	}

	public function test_secure_mcp_disabled_parent_gates_wordpress_mcp_child_config(): void {
		$this->set_vip_config_map(
			[
				'secure-mcp' => [
					'org'      => [
						'status' => Org_Integration_Status::DISABLED,
					],
					'env'      => [
						'status' => Env_Integration_Status::DISABLED,
					],
					'children' => [
						'wordpress-mcp' => [
							'env' => [
								'status' => Env_Integration_Status::ENABLED,
							],
						],
					],
				],
			]
		);

		$integrations = new Integrations();
		$integration  = new WordPressMcpIntegration( $this->slug );

		$integrations->register( $integration );
		$integrations->activate_platform_integrations();

		$this->assertFalse( $integration->is_active() );
	}

	public function test_secure_mcp_org_disabled_gates_wordpress_mcp_child_config(): void {
		$this->set_vip_config_map(
			[
				'secure-mcp' => [
					'org'      => [
						'status' => Org_Integration_Status::DISABLED,
					],
					'env'      => [
						'status' => Env_Integration_Status::ENABLED,
					],
					'children' => [
						'wordpress-mcp' => [
							'env' => [
								'status' => Env_Integration_Status::ENABLED,
							],
						],
					],
				],
			]
		);

		$integrations = new Integrations();
		$integration  = new WordPressMcpIntegration( $this->slug );

		$integrations->register( $integration );
		$integrations->activate_platform_integrations();

		$this->assertFalse( $integration->is_active() );
	}

	public function test_is_mcp_adapter_rest_request_returns_true_for_pretty_rest_url(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/wp-json/mcp/mcp-adapter-default-server';

		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );

		$this->assertTrue( $wordpress_mcp_integration->is_mcp_adapter_rest_request() );
	}

	public function test_is_mcp_adapter_rest_request_returns_true_for_configured_rest_url(): void {
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
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/wp-json/mcp/vip-mcp-server';

		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );
		$wordpress_mcp_integration->activate( [ 'config' => [ 'server_route' => 'vip-mcp-server' ] ] );
		$wordpress_mcp_integration->configure();

		$this->assertTrue( $wordpress_mcp_integration->is_mcp_adapter_rest_request() );
	}

	public function test_is_mcp_adapter_rest_request_uses_default_route_with_configured_namespace(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/wp-json/vip-mcp/v1/mcp-adapter-default-server';

		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );
		$wordpress_mcp_integration->activate( [ 'config' => [ 'server_namespace' => 'vip-mcp/v1' ] ] );
		$wordpress_mcp_integration->configure();

		$this->assertTrue( $wordpress_mcp_integration->is_mcp_adapter_rest_request() );
	}

	public function test_is_mcp_adapter_rest_request_returns_true_for_rest_route_query_arg(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/index.php?rest_route=/mcp/mcp-adapter-default-server';
		$_GET['rest_route']        = '/mcp/mcp-adapter-default-server';

		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );

		$this->assertTrue( $wordpress_mcp_integration->is_mcp_adapter_rest_request() );
	}

	public function test_is_mcp_adapter_rest_request_returns_false_for_other_rest_url(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/wp-json/wp/v2/posts';

		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );

		$this->assertFalse( $wordpress_mcp_integration->is_mcp_adapter_rest_request() );
	}

	public function test_authenticate_mcp_request_preserves_existing_user(): void {
		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );

		$this->assertSame( 123, $wordpress_mcp_integration->authenticate_mcp_request( 123 ) );
	}

	public function test_authenticate_mcp_request_ignores_missing_hmac_headers(): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/wp-json/mcp/mcp-adapter-default-server';

		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );

		$this->assertFalse( $wordpress_mcp_integration->authenticate_mcp_request( false ) );
	}

	/**
	 * Populate $_SERVER with a valid, HMAC-signed MCP request for the given email.
	 */
	private function sign_mcp_request( string $email, string $auth_key ): void {
		$timestamp = (string) time();

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = '/wp-json/mcp/mcp-adapter-default-server';
		// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.BasicAuthentication -- Test fixture for MCP Basic auth bridge.
		$_SERVER['PHP_AUTH_USER'] = $email;
		// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.BasicAuthentication -- Test fixture for MCP Basic auth bridge.
		$_SERVER['PHP_AUTH_PW']                   = hash_hmac( 'sha256', $email . $timestamp, $auth_key );
		$_SERVER['HTTP_X_VIP_MCP_AUTH']           = 'true';
		$_SERVER['HTTP_X_VIP_MCP_AUTH_TIMESTAMP'] = $timestamp;

		wp_set_current_user( 0 );
	}

	public function test_authenticate_mcp_request_maps_valid_hmac_to_user(): void {
		$auth_key = 'test-auth-key';
		$email    = 'mcp-user-' . wp_generate_password( 8, false ) . '@example.com';
		$user_id  = $this->factory()->user->create( [ 'user_email' => $email ] );

		$this->sign_mcp_request( $email, $auth_key );

		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );
		$wordpress_mcp_integration->activate( [ 'config' => [ 'auth_key' => $auth_key ] ] );

		$this->assertSame( $user_id, $wordpress_mcp_integration->authenticate_mcp_request( false ) );
	}

	public function test_report_auth_error_surfaces_rest_error_when_user_not_found(): void {
		$auth_key = 'test-auth-key';
		$email    = 'missing-' . wp_generate_password( 8, false ) . '@example.com';

		$this->sign_mcp_request( $email, $auth_key );

		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );
		$wordpress_mcp_integration->activate( [ 'config' => [ 'auth_key' => $auth_key ] ] );

		// A valid signature for an unknown user must not resolve to a user ID; the
		// input is preserved and the hard failure is recorded for the REST layer.
		$this->assertFalse( $wordpress_mcp_integration->authenticate_mcp_request( false ) );

		$error = $wordpress_mcp_integration->report_auth_error( null );

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'vip_mcp_user_not_found', $error->get_error_code() );
		$this->assertStringContainsString( $email, $error->get_error_message() );
		$this->assertSame( 401, $error->get_error_data()['status'] );
	}

	public function test_report_auth_error_preserves_existing_result(): void {
		$auth_key = 'test-auth-key';
		$email    = 'missing-' . wp_generate_password( 8, false ) . '@example.com';

		$this->sign_mcp_request( $email, $auth_key );

		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );
		$wordpress_mcp_integration->activate( [ 'config' => [ 'auth_key' => $auth_key ] ] );

		$wordpress_mcp_integration->authenticate_mcp_request( false );

		// A prior successful authentication (true) must pass through untouched.
		$this->assertTrue( $wordpress_mcp_integration->report_auth_error( true ) );

		// An error set by another handler must not be overridden.
		$existing = new \WP_Error( 'existing_error', 'Existing error' );
		$this->assertSame( $existing, $wordpress_mcp_integration->report_auth_error( $existing ) );
	}

	public function test_report_auth_error_returns_null_for_valid_user(): void {
		$auth_key = 'test-auth-key';
		$email    = 'mcp-user-' . wp_generate_password( 8, false ) . '@example.com';
		$user_id  = $this->factory()->user->create( [ 'user_email' => $email ] );

		$this->sign_mcp_request( $email, $auth_key );

		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );
		$wordpress_mcp_integration->activate( [ 'config' => [ 'auth_key' => $auth_key ] ] );

		$this->assertSame( $user_id, $wordpress_mcp_integration->authenticate_mcp_request( false ) );

		// A successful auth must not surface a hard error for the REST layer.
		$this->assertNull( $wordpress_mcp_integration->report_auth_error( null ) );
	}

	public function test_report_auth_error_returns_null_without_recorded_error(): void {
		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );

		$this->assertNull( $wordpress_mcp_integration->report_auth_error( null ) );
	}
}
