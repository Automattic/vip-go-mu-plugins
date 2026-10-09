<?php
/**
 * Test: loading behavior shared by the bundled-plugin integrations.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use Automattic\Test\Constant_Mocker;
use WP_UnitTestCase;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.MissingParamComment

class Integration_Loading_Test extends WP_UnitTestCase {
	/**
	 * Create a bundled plugin in vip-integrations/<slug>-<version>/<slug>.php that defines $loaded_constant as its version.
	 *
	 * @return string The plugin file path.
	 */
	private function create_bundled_plugin_fixture( string $slug, string $version, string $required_wp_version, string $loaded_constant ): string {
		$directory = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/' . $slug . '-' . $version;
		wp_mkdir_p( $directory );

		$plugin = <<<PHP
<?php
/**
 * Plugin Name: {$slug} test fixture
 * Requires at least: {$required_wp_version}
 */
\Automattic\VIP\Integrations\define( '{$loaded_constant}', '{$version}' );
PHP;

		$plugin_file = $directory . '/' . $slug . '.php';
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents -- Test-owned bundled plugin fixture.
		file_put_contents( $plugin_file, $plugin );

		return $plugin_file;
	}

	private function remove_bundled_plugin_fixture( string $plugin_file ): void {
		$directory = dirname( $plugin_file );
		if ( file_exists( $plugin_file ) ) {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink -- Remove the test-owned bundled plugin fixture.
			unlink( $plugin_file );
		}

		if ( is_dir( $directory ) ) {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir -- Remove the test-owned bundled plugin fixture directory.
			rmdir( $directory );
		}
	}

	/**
	 * Run load() and then only the plugins_loaded callbacks it registers, not every callback of the test bootstrap.
	 */
	private function load_on_plugins_loaded( Integration $integration ): void {
		remove_all_actions( 'plugins_loaded' );
		$integration->load();
		do_action( 'plugins_loaded' );
	}

	/**
	 * @dataProvider data_is_loaded
	 */
	public function test_is_loaded_reflects_the_plugin_constant( string $class_name, ?string $constant ): void {
		if ( null !== $constant ) {
			Constant_Mocker::define( $constant, true );
		}

		$this->assertSame( null !== $constant, ( new $class_name( 'test' ) )->is_loaded() );
	}

	public static function data_is_loaded(): array {
		return [
			'agentforce not loaded'                   => [ AgentforceIntegration::class, null ],
			'agentforce file constant'                => [ AgentforceIntegration::class, 'VIP_AGENTFORCE_FILE' ],
			'block data api not loaded'               => [ BlockDataApiIntegration::class, null ],
			'clipisode not loaded'                    => [ ClipisodeIntegration::class, null ],
			'clipisode loaded constant'               => [ ClipisodeIntegration::class, 'CLIPISODE_VERSION' ],
			'real-time collaboration not loaded'      => [ RealTimeCollaborationIntegration::class, null ],
			'real-time collaboration loaded constant' => [ RealTimeCollaborationIntegration::class, 'VIP_REAL_TIME_COLLABORATION__LOADED' ],
			'remote data blocks not loaded'           => [ RemoteDataBlocksIntegration::class, null ],
			'remote data blocks loaded constant'      => [ RemoteDataBlocksIntegration::class, 'REMOTE_DATA_BLOCKS__LOADED' ],
			'safe publish not loaded'                 => [ SafePublishIntegration::class, null ],
			'safe publish loaded constant'            => [ SafePublishIntegration::class, 'SAFE_PUBLISH_LOADED' ],
			'safe publish plugin file constant'       => [ SafePublishIntegration::class, 'SAFE_PUBLISH_PLUGIN_FILE' ],
			'security boost not loaded'               => [ SecurityBoostIntegration::class, null ],
			'share a draft not loaded'                => [ ShareadraftIntegration::class, null ],
			'share a draft loaded constant'           => [ ShareadraftIntegration::class, 'VIP_SHAREADRAFT_LOADED' ],
			'security boost loaded constant'          => [ SecurityBoostIntegration::class, 'VIP_SECURITY_BOOST__LOADED' ],
			'vip governance not loaded'               => [ VipGovernanceIntegration::class, null ],
			'vip workflows not loaded'                => [ VipWorkflowsIntegration::class, null ],
			'vip workflows loaded constant'           => [ VipWorkflowsIntegration::class, 'VIP_WORKFLOWS_LOADED' ],
			'vip workflows plugin file constant'      => [ VipWorkflowsIntegration::class, 'VIP_WORKFLOWS_PLUGIN_FILE' ],
			'wordpress mcp not loaded'                => [ WordPressMcpIntegration::class, null ],
		];
	}

	/**
	 * @dataProvider data_selected_version_folder
	 */
	public function test_get_selected_version_folder( string $class_name, ?string $version, array $versions, string $expected ): void {
		$integration = new $class_name( 'test' );
		if ( null !== $version ) {
			$integration->version = $version;
		}

		$this->assertSame( $expected, $integration->get_selected_version_folder( $versions ) );
	}

	public static function data_selected_version_folder(): array {
		$folders = static function ( string $prefix, array $versions ): array {
			return array_combine( array_map( static fn( $version ) => "{$prefix}-{$version}", $versions ), $versions );
		};

		$agentforce     = $folders( 'vip-agentforce', [ '2.5', '1.11', '1.2' ] );
		$safe_publish   = $folders( 'safe-publish', [ '2.5', '1.11', '1.2' ] );
		$security_boost = $folders( 'vip-security-boost', [ '2.5', '1.11', '1.2' ] );
		$vip_workflows  = $folders( 'vip-workflows', [ '0.1', '0.0' ] );

		return [
			'agentforce latest'                => [ AgentforceIntegration::class, 'latest', $agentforce, 'vip-agentforce-2.5' ],
			'agentforce specified version'     => [ AgentforceIntegration::class, '1.2', $agentforce, 'vip-agentforce-1.2' ],
			'agentforce unknown version'       => [ AgentforceIntegration::class, '9.9', $agentforce, 'vip-agentforce-2.5' ],
			'agentforce empty version'         => [ AgentforceIntegration::class, '', $agentforce, 'vip-agentforce-2.5' ],
			'safe publish latest'              => [ SafePublishIntegration::class, 'latest', $safe_publish, 'safe-publish-2.5' ],
			'safe publish specified version'   => [ SafePublishIntegration::class, '1.2', $safe_publish, 'safe-publish-1.2' ],
			'safe publish unknown version'     => [ SafePublishIntegration::class, '9.9', $safe_publish, 'safe-publish-2.5' ],
			'security boost latest'            => [ SecurityBoostIntegration::class, 'latest', $security_boost, 'vip-security-boost-2.5' ],
			'security boost specified version' => [ SecurityBoostIntegration::class, '1.2', $security_boost, 'vip-security-boost-1.2' ],
			'security boost empty version'     => [ SecurityBoostIntegration::class, '', $security_boost, 'vip-security-boost-2.5' ],
			'vip workflows default version'    => [ VipWorkflowsIntegration::class, null, $vip_workflows, 'vip-workflows-0.1' ],
			'vip workflows specified version'  => [ VipWorkflowsIntegration::class, '0.0', $vip_workflows, 'vip-workflows-0.0' ],
		];
	}

	/**
	 * @dataProvider data_configs_constant
	 */
	public function test_configure_defines_configs_constant_once( string $class_name, string $constant, ?array $existing, array $expected ): void {
		if ( null !== $existing ) {
			Constant_Mocker::define( $constant, $existing );
		}

		( new $class_name( 'test' ) )->configure();

		$this->assertTrue( defined( $constant ) );
		$this->assertSame( $expected, constant( $constant ) );
	}

	public static function data_configs_constant(): array {
		$existing = [ 'test' => 'value' ];

		return [
			'agentforce defines empty config'        => [ AgentforceIntegration::class, 'VIP_AGENTFORCE_CONFIGS', null, [] ],
			'agentforce keeps existing constant'     => [ AgentforceIntegration::class, 'VIP_AGENTFORCE_CONFIGS', $existing, $existing ],
			'remote data blocks defines no sources'  => [ RemoteDataBlocksIntegration::class, 'REMOTE_DATA_BLOCKS_CONFIGS', null, [] ],
			'remote data blocks keeps existing'      => [ RemoteDataBlocksIntegration::class, 'REMOTE_DATA_BLOCKS_CONFIGS', $existing, $existing ],
			'security boost defines empty config'    => [ SecurityBoostIntegration::class, 'VIP_SECURITY_BOOST_CONFIGS', null, [] ],
			'security boost keeps existing constant' => [ SecurityBoostIntegration::class, 'VIP_SECURITY_BOOST_CONFIGS', $existing, $existing ],
			'share a draft defines empty config'     => [ ShareadraftIntegration::class, 'VIP_SHAREADRAFT_CONFIG', null, [] ],
			'share a draft keeps existing constant'  => [ ShareadraftIntegration::class, 'VIP_SHAREADRAFT_CONFIG', $existing, $existing ],
		];
	}

	/**
	 * @dataProvider data_unavailable_plugin
	 */
	public function test_load_sets_inactive_when_the_plugin_is_unavailable( string $class_name, array $stubbed_returns ): void {
		$integration = $this->getMockBuilder( $class_name )
			->setConstructorArgs( [ 'test' ] )
			->onlyMethods( array_merge( [ 'is_loaded' ], array_keys( $stubbed_returns ) ) )
			->getMock();
		$integration->method( 'is_loaded' )->willReturn( false );
		foreach ( $stubbed_returns as $method => $value ) {
			// The version lookup must run, so the integration is deactivated by the missing plugin and not an earlier return.
			$expectation = in_array( $method, [ 'get_latest_version', 'get_versions' ], true ) ? $this->once() : $this->any();
			$integration->expects( $expectation )->method( $method )->willReturn( $value );
		}

		$integration->activate();
		$this->assertTrue( $integration->is_active(), 'Initial: Integration should be active.' );

		$this->load_on_plugins_loaded( $integration );

		$this->assertFalse( $integration->is_active() );
	}

	public static function data_unavailable_plugin(): array {
		return [
			'block data api without versions'       => [ BlockDataApiIntegration::class, [ 'get_latest_version' => null ] ],
			'clipisode without versions'            => [ ClipisodeIntegration::class, [ 'get_latest_version' => null ] ],
			'content for agents without versions'   => [ ContentForAgentsIntegration::class, [ 'get_latest_version' => null ] ],
			'content for agents missing entry file' => [ ContentForAgentsIntegration::class, [ 'get_latest_version' => 'content-for-agents-missing-test-plugin' ] ],
			'remote data blocks without versions'   => [
				RemoteDataBlocksIntegration::class,
				[
					'is_supported_wp_version' => true,
					'get_latest_version'      => null,
				],
			],
			'safe publish without versions'         => [ SafePublishIntegration::class, [ 'get_versions' => [] ] ],
			'share a draft without versions'        => [ ShareadraftIntegration::class, [ 'get_latest_version' => null ] ],
			'share a draft missing entry file'      => [ ShareadraftIntegration::class, [ 'get_latest_version' => 'shareadraft-missing-test-plugin' ] ],
			'vip governance without versions'       => [ VipGovernanceIntegration::class, [ 'get_latest_version' => null ] ],
			'vip workflows without versions'        => [ VipWorkflowsIntegration::class, [ 'get_versions' => [] ] ],
			'wordpress mcp without versions'        => [ WordPressMcpIntegration::class, [ 'get_versions' => [] ] ],
		];
	}

	/**
	 * A plugin loaded by customer code after activation keeps the integration active and skips version lookup.
	 *
	 * @dataProvider data_already_loaded
	 */
	public function test_load_returns_early_if_plugin_already_loaded( string $class_name, string $loaded_constant, array $skipped_methods ): void {
		$integration = $this->getMockBuilder( $class_name )
			->setConstructorArgs( [ 'test' ] )
			->onlyMethods( $skipped_methods )
			->getMock();
		foreach ( $skipped_methods as $method ) {
			$integration->expects( $this->never() )->method( $method );
		}

		// Activate first: activate() itself refuses to activate an already loaded plugin.
		$integration->activate( [ 'config' => [ 'preserved' => 'sentinel' ] ] );
		Constant_Mocker::define( $loaded_constant, true );

		$this->load_on_plugins_loaded( $integration );

		$this->assertTrue( $integration->is_active() );
		$this->assertSame( [ 'preserved' => 'sentinel' ], $integration->get_env_config() );
	}

	public static function data_already_loaded(): array {
		return [
			'clipisode'               => [ ClipisodeIntegration::class, 'CLIPISODE_VERSION', [ 'get_latest_version' ] ],
			'content for agents'      => [ ContentForAgentsIntegration::class, 'CONTENT_FOR_AGENTS_LOADED', [ 'get_latest_version' ] ],
			// Without the WebSocket constants, a load past the guard would deactivate the integration.
			'real-time collaboration' => [ RealTimeCollaborationIntegration::class, 'VIP_REAL_TIME_COLLABORATION__LOADED', [] ],
			'remote data blocks'      => [ RemoteDataBlocksIntegration::class, 'REMOTE_DATA_BLOCKS__LOADED', [ 'is_supported_wp_version', 'get_latest_version' ] ],
			'safe publish'            => [ SafePublishIntegration::class, 'SAFE_PUBLISH_LOADED', [ 'get_versions', 'get_selected_version_folder' ] ],
			'share a draft'           => [ ShareadraftIntegration::class, 'VIP_SHAREADRAFT_LOADED', [ 'get_latest_version' ] ],
			'vip workflows'           => [ VipWorkflowsIntegration::class, 'VIP_WORKFLOWS_LOADED', [ 'get_versions', 'get_selected_version_folder' ] ],
		];
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_clipisode_loads_the_latest_compatible_bundled_plugin(): void {
		global $wp_version;
		$wp_version = '6.6'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Exercise plugin header compatibility.

		$older_fixture  = $this->create_bundled_plugin_fixture( 'clipisode', '999.0', '6.6', 'CLIPISODE_VERSION' );
		$latest_fixture = $this->create_bundled_plugin_fixture( 'clipisode', '999.1', '6.6', 'CLIPISODE_VERSION' );

		try {
			$integration = new ClipisodeIntegration( 'test' );
			$integration->activate();

			$this->assertSame( 'clipisode-999.1', $integration->get_latest_version() );

			$this->load_on_plugins_loaded( $integration );

			$this->assertTrue( $integration->is_active() );
			$this->assertTrue( defined( 'CLIPISODE_VERSION' ) );
			$this->assertSame( '999.1', constant( 'CLIPISODE_VERSION' ) );
		} finally {
			$this->remove_bundled_plugin_fixture( $older_fixture );
			$this->remove_bundled_plugin_fixture( $latest_fixture );
		}
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_clipisode_stays_inactive_when_wordpress_is_unsupported(): void {
		global $wp_version;
		$wp_version = '6.5'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Exercise plugin header compatibility.

		$fixture = $this->create_bundled_plugin_fixture( 'clipisode', '999.2', '6.6', 'CLIPISODE_VERSION' );

		try {
			$integration = new ClipisodeIntegration( 'test' );
			$integration->activate();
			$this->load_on_plugins_loaded( $integration );

			$this->assertFalse( $integration->is_active() );
			$this->assertFalse( defined( 'CLIPISODE_VERSION' ) );
		} finally {
			$this->remove_bundled_plugin_fixture( $fixture );
		}
	}

	/**
	 * @dataProvider data_shareadraft_wordpress_requirement
	 */
	public function test_shareadraft_loads_only_when_wordpress_meets_the_bundled_requirement( string $version, string $required_wp_version, bool $expected ): void {
		$fixture = $this->create_bundled_plugin_fixture( 'shareadraft', $version, $required_wp_version, 'VIP_SHAREADRAFT_LOADED' );

		try {
			$integration = new ShareadraftIntegration( 'test' );
			$integration->activate();
			$this->load_on_plugins_loaded( $integration );

			$this->assertSame( $expected, $integration->is_active() );
			$this->assertSame( $expected, defined( 'VIP_SHAREADRAFT_LOADED' ) );
		} finally {
			$this->remove_bundled_plugin_fixture( $fixture );
		}
	}

	public static function data_shareadraft_wordpress_requirement(): array {
		// Each case needs its own plugin file, since require_once skips a file an earlier case loaded.
		return [
			'supported WordPress'   => [ '999.0', '5.0', true ],
			'unsupported WordPress' => [ '999.1', '99.0', false ],
		];
	}
}
