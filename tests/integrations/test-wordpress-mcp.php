<?php
/**
 * Test: WordPress MCP Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;
use Org_Integration_Status;
use Env_Integration_Status;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing, Squiz.Commenting.FunctionComment.MissingParamComment

class WordPress_Mcp_Integration_Test extends WP_UnitTestCase {
	private string $slug          = 'wordpress-mcp';
	private array $vip_config_map = [];

	public function filter_vip_config( $config, $path, $slug ) {
		if ( array_key_exists( $slug, $this->vip_config_map ) ) {
			return $this->vip_config_map[ $slug ];
		}

		return $config;
	}

	private function set_vip_config_map( array $vip_config_map ): void {
		$this->vip_config_map = $vip_config_map;

		add_filter( 'vip_integrations_pre_load_config', [ $this, 'filter_vip_config' ], 10, 3 );
	}

	/**
	 * @dataProvider data_default_server_config
	 */
	public function test_filter_default_server_config_applies_only_configured_values( array $integration_config, string $expected_namespace, string $expected_route ): void {
		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );
		$wordpress_mcp_integration->activate( [ 'config' => $integration_config ] );
		$wordpress_mcp_integration->configure();

		$config = $wordpress_mcp_integration->filter_default_server_config(
			[
				'server_id'              => 'mcp-adapter-default-server',
				'server_route_namespace' => 'mcp',
				'server_route'           => 'mcp-adapter-default-server',
			]
		);

		$this->assertSame( 'mcp-adapter-default-server', $config['server_id'] );
		$this->assertSame( $expected_namespace, $config['server_route_namespace'] );
		$this->assertSame( $expected_route, $config['server_route'] );
	}

	public static function data_default_server_config(): array {
		return [
			'namespace and route' => [
				[
					'server_namespace' => 'vip-mcp/v1',
					'server_route'     => 'vip-mcp-server',
				],
				'vip-mcp/v1',
				'vip-mcp-server',
			],
			'namespace only'      => [ [ 'server_namespace' => 'vip-mcp/v1' ], 'vip-mcp/v1', 'mcp-adapter-default-server' ],
			'route only'          => [ [ 'server_route' => 'vip-mcp-server' ], 'mcp', 'vip-mcp-server' ],
		];
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
	}

	public function test_load_does_not_register_default_server_config_filter_without_server_config(): void {
		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );

		$wordpress_mcp_integration->load();

		$this->assertFalse(
			has_filter( 'mcp_adapter_default_server_config', [ $wordpress_mcp_integration, 'filter_default_server_config' ] )
		);
	}

	public function test_load_registers_exposed_abilities_args_filter_without_config(): void {
		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );

		$wordpress_mcp_integration->load();

		$this->assertSame(
			PHP_INT_MAX,
			has_filter( 'wp_register_ability_args', [ $wordpress_mcp_integration, 'filter_exposed_abilities_args' ] )
		);
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

	public function test_filter_exposed_abilities_args_inherits_public_and_preserves_explicit_mcp_opt_out(): void {
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

		$args = [ 'meta' => [ 'public' => true ] ];

		$this->assertTrue(
			$wordpress_mcp_integration->filter_exposed_abilities_args( $args, 'jetpack/get-stats' )['meta']['mcp']['public']
		);
	}

	public function test_filter_exposed_abilities_args_preserves_configured_ability_mcp_opt_out(): void {
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
			$wordpress_mcp_integration->filter_exposed_abilities_args( $args, 'core/get-site-info' )
		);
	}

	/**
	 * @dataProvider data_provider_selected_version_folder
	 */
	public function test_get_selected_version_folder( string $current_wp_version, array $versions, ?string $expected ): void {
		global $wp_version;
		$original_wp_version = $wp_version;
		$wp_version          = $current_wp_version; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$selected = ( new WordPressMcpIntegration( $this->slug ) )->get_selected_version_folder( $versions );

		$wp_version = $original_wp_version; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->assertSame( $expected, $selected );
	}

	public static function data_provider_selected_version_folder(): array {
		$versions = [
			'wordpress-mcp-0.7' => '0.7',
			'wordpress-mcp-0.6' => '0.6',
			'wordpress-mcp-0.5' => '0.5',
		];

		return [
			'pinned version on supported WordPress'     => [ '6.9', $versions, 'wordpress-mcp-0.6' ],
			'latest version when pinned one is missing' => [
				'6.9',
				[
					'wordpress-mcp-0.7' => '0.7',
					'wordpress-mcp-0.5' => '0.5',
				],
				'wordpress-mcp-0.7',
			],
			'legacy version on older WordPress'         => [ '6.8', $versions, 'wordpress-mcp-0.5' ],
			'no legacy version on older WordPress'      => [ '6.8', [ 'wordpress-mcp-0.6' => '0.6' ], null ],
		];
	}

	/**
	 * The child config only activates WordPress MCP when the secure-mcp parent is enabled for the env and org.
	 *
	 * @dataProvider data_secure_mcp_parent
	 */
	public function test_platform_activation_uses_secure_mcp_child_config_gated_by_parent( string $org_status, string $env_status, array $child_env, bool $expected_active ): void {
		$this->set_vip_config_map(
			[
				'secure-mcp' => [
					'org'      => [
						'status' => $org_status,
					],
					'env'      => [
						'status' => $env_status,
					],
					'children' => [
						'wordpress-mcp' => [
							'env' => $child_env,
						],
					],
				],
			]
		);

		$integrations = new Integrations();
		$integration  = new WordPressMcpIntegration( $this->slug );

		$integrations->register( $integration );
		$integrations->activate_platform_integrations();

		$this->assertSame( $expected_active, $integration->is_active() );
		if ( $expected_active ) {
			$this->assertSame( $child_env['config'], $integration->get_env_config() );
		}
	}

	public static function data_secure_mcp_parent(): array {
		$child_env = [ 'status' => Env_Integration_Status::ENABLED ];

		return [
			'enabled parent'               => [
				Org_Integration_Status::ENABLED,
				Env_Integration_Status::ENABLED,
				array_merge( $child_env, [ 'config' => [ 'server_route' => 'vip-mcp-server' ] ] ),
				true,
			],
			'disabled parent environment'  => [ Org_Integration_Status::ENABLED, Env_Integration_Status::DISABLED, $child_env, false ],
			'disabled parent env and org'  => [ Org_Integration_Status::DISABLED, Env_Integration_Status::DISABLED, $child_env, false ],
			'disabled parent organization' => [ Org_Integration_Status::DISABLED, Env_Integration_Status::ENABLED, $child_env, false ],
		];
	}

	/**
	 * @dataProvider data_mcp_adapter_rest_request
	 */
	public function test_is_mcp_adapter_rest_request( string $request_uri, ?string $rest_route, array $integration_config, bool $expected ): void {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_SERVER['REQUEST_URI']    = $request_uri;
		if ( null !== $rest_route ) {
			$_GET['rest_route'] = $rest_route;
		}

		$wordpress_mcp_integration = new WordPressMcpIntegration( $this->slug );
		if ( [] !== $integration_config ) {
			$wordpress_mcp_integration->activate( [ 'config' => $integration_config ] );
			$wordpress_mcp_integration->configure();
		}

		$this->assertSame( $expected, $wordpress_mcp_integration->is_mcp_adapter_rest_request() );
	}

	public static function data_mcp_adapter_rest_request(): array {
		return [
			'pretty default REST url'                 => [ '/wp-json/mcp/mcp-adapter-default-server', null, [], true ],
			'configured REST url'                     => [
				'/wp-json/vip-mcp/v1/vip-mcp-server',
				null,
				[
					'server_namespace' => 'vip-mcp/v1',
					'server_route'     => 'vip-mcp-server',
				],
				true,
			],
			'default namespace with configured route' => [ '/wp-json/mcp/vip-mcp-server', null, [ 'server_route' => 'vip-mcp-server' ], true ],
			'default route with configured namespace' => [ '/wp-json/vip-mcp/v1/mcp-adapter-default-server', null, [ 'server_namespace' => 'vip-mcp/v1' ], true ],
			'rest_route query arg'                    => [ '/index.php?rest_route=/mcp/mcp-adapter-default-server', '/mcp/mcp-adapter-default-server', [], true ],
			'other REST url'                          => [ '/wp-json/wp/v2/posts', null, [], false ],
		];
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

	/**
	 * Only a current, correctly signed request scoped to the bridge resolves a user.
	 *
	 * @dataProvider mcp_authentication_cases
	 */
	public function test_mcp_authentication_rejects_invalid_requests( string $variant ): void {
		$auth_key = 'test-auth-key';
		$email    = 'request-user@example.com';
		$user_id  = $this->factory()->user->create( [ 'user_email' => $email ] );
		$this->sign_mcp_request( $email, $auth_key );
		if ( 'wrong signature' === $variant ) {
			// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.BasicAuthentication -- Deliberate invalid bridge credential fixture.
			$_SERVER['PHP_AUTH_PW'] = str_repeat( '0', 64 );
		} elseif ( 'wrong key' === $variant ) {
			$this->sign_mcp_request( $email, 'different-key' );
		} elseif ( 'old timestamp' === $variant || 'future timestamp' === $variant ) {
			$timestamp                                = (string) ( time() + ( 'old timestamp' === $variant ? -3600 : 3600 ) );
			$_SERVER['HTTP_X_VIP_MCP_AUTH_TIMESTAMP'] = $timestamp;
			// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.BasicAuthentication -- Deliberate invalid bridge credential fixture.
			$_SERVER['PHP_AUTH_PW'] = hash_hmac( 'sha256', $email . $timestamp, $auth_key );
		} elseif ( 'missing timestamp' === $variant ) {
			unset( $_SERVER['HTTP_X_VIP_MCP_AUTH_TIMESTAMP'] );
		} elseif ( 'malformed timestamp' === $variant ) {
			$_SERVER['HTTP_X_VIP_MCP_AUTH_TIMESTAMP'] = 'not-a-timestamp';
		} elseif ( 'disabled bridge' === $variant ) {
			$_SERVER['HTTP_X_VIP_MCP_AUTH'] = 'false';
		} elseif ( 'other route' === $variant ) {
			$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts';
		}
		$integration = new WordPressMcpIntegration( $this->slug );
		$integration->activate( [ 'config' => [ 'auth_key' => $auth_key ] ] );
		$this->assertSame( 'valid' === $variant ? $user_id : false, $integration->authenticate_mcp_request( false ) );
	}

	/**
	 * Authentication cases change one request property from a valid signed control.
	 */
	public function mcp_authentication_cases(): array {
		$variants = [ 'valid', 'wrong signature', 'wrong key', 'old timestamp', 'future timestamp', 'missing timestamp', 'malformed timestamp', 'disabled bridge', 'other route' ];
		return array_combine( $variants, array_map( static fn( $variant ) => [ $variant ], $variants ) );
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

	/**
	 * Registered callbacks must resolve signed users and propagate unknown-user errors.
	 */
	public function test_registered_authentication_resolves_users_and_rest_errors(): void {
		$auth_key    = 'hook-test-key';
		$email       = 'hook-user@example.com';
		$user_id     = $this->factory()->user->create( [ 'user_email' => $email ] );
		$integration = new WordPressMcpIntegration( $this->slug );
		$integration->activate( [ 'config' => [ 'auth_key' => $auth_key ] ] );

		$integration->load();
		$this->assertSame( 19, has_filter( 'determine_current_user', [ $integration, 'authenticate_mcp_request' ] ) );
		$this->assertSame( 10, has_filter( 'rest_authentication_errors', [ $integration, 'report_auth_error' ] ) );
		$this->sign_mcp_request( $email, $auth_key );
		unset( $GLOBALS['current_user'] );
		$this->assertSame( $user_id, get_current_user_id() );
		$server = new \WP_REST_Server();
		$this->assertContains( $server->check_authentication(), [ null, true ], 'WordPress accepts either null or true for successful REST authentication.' );

		$this->sign_mcp_request( 'unknown-hook-user@example.com', $auth_key );
		unset( $GLOBALS['current_user'] );
		$this->assertSame( 0, get_current_user_id() );
		$error = $server->check_authentication();
		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertSame( 'vip_mcp_user_not_found', $error->get_error_code() );
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
