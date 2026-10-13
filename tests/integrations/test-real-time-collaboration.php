<?php

/**
 * Test: Real-Time Collaboration Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;
use Automattic\Test\Constant_Mocker;

use function Automattic\Test\Utils\get_class_method_as_public;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing

class Real_Time_Collaboration_Integration_Test extends WP_UnitTestCase {
	private string $slug = 'real-time-collaboration';

	public function setUp(): void {
		parent::setUp();

		// Start without an active Gutenberg plugin; the transaction rollback restores both options.
		update_option( 'active_plugins', [] );
		update_site_option( 'active_sitewide_plugins', [] );
	}

	/**
	 * @dataProvider data_configure_constants
	 */
	public function test_configure_defines_websocket_constants( array $env_config, string $constant, $expected ): void {
		$integration = new RealTimeCollaborationIntegration( $this->slug );
		$integration->activate( [ 'config' => $env_config ] );

		$integration->configure();

		$this->assertTrue( defined( $constant ) );
		$this->assertSame( $expected, constant( $constant ) );
	}

	public static function data_configure_constants(): array {
		return [
			'websocket auth secret'           => [ [ 'web_socket_auth_secret' => 'test-secret-key' ], 'VIP_RTC_WS_AUTH_SECRET', 'test-secret-key' ],
			'websocket url'                   => [ [ 'web_socket_url' => 'wss://test.example.com/_ws' ], 'VIP_RTC_WS_URL', 'wss://test.example.com/_ws' ],
			'websocket multiplexing enabled'  => [ [ 'web_socket_multiplexing_enabled' => true ], 'VIP_RTC_WS_MULTIPLEXING_ENABLED', true ],
			'websocket multiplexing disabled' => [ [ 'web_socket_multiplexing_enabled' => false ], 'VIP_RTC_WS_MULTIPLEXING_ENABLED', false ],
		];
	}

	public function test_configure_does_not_redefine_existing_constants(): void {
		Constant_Mocker::define( 'VIP_RTC_WS_AUTH_SECRET', 'existing-secret' );
		Constant_Mocker::define( 'VIP_RTC_WS_URL', 'wss://existing.example.com/_ws' );
		Constant_Mocker::define( 'VIP_RTC_WS_MULTIPLEXING_ENABLED', false );

		$integration = new RealTimeCollaborationIntegration( $this->slug );
		$integration->activate( [
			'config' => [
				'web_socket_auth_secret'          => 'new-secret',
				'web_socket_url'                  => 'wss://new.example.com/_ws',
				'web_socket_multiplexing_enabled' => true,
			],
		] );

		$integration->configure();

		$this->assertEquals( 'existing-secret', constant( 'VIP_RTC_WS_AUTH_SECRET' ) );
		$this->assertEquals( 'wss://existing.example.com/_ws', constant( 'VIP_RTC_WS_URL' ) );
		$this->assertFalse( constant( 'VIP_RTC_WS_MULTIPLEXING_ENABLED' ) );
	}

	public function test_configure_handles_missing_config_values(): void {
		$integration = new RealTimeCollaborationIntegration( $this->slug );
		$integration->activate( [ 'config' => [] ] );

		$integration->configure();

		$this->assertFalse( defined( 'VIP_RTC_WS_AUTH_SECRET' ) );
		$this->assertFalse( defined( 'VIP_RTC_WS_URL' ) );
		$this->assertFalse( defined( 'VIP_RTC_WS_MULTIPLEXING_ENABLED' ) );
	}

	public function data_can_load_constants(): array {
		return [
			'all requirements met'               => [ [ 'VIP_RTC_WS_AUTH_SECRET', 'VIP_RTC_WS_URL' ], true ],
			'ws auth secret missing'             => [ [ 'VIP_RTC_WS_URL' ], false ],
			'ws url missing'                     => [ [ 'VIP_RTC_WS_AUTH_SECRET' ], false ],
			'both ws constants missing'          => [ [], false ],
			'gutenberg plugin constant'          => [ [ 'VIP_RTC_WS_AUTH_SECRET', 'VIP_RTC_WS_URL', 'IS_GUTENBERG_PLUGIN' ], false ],
			'gutenberg plugin activated'         => [ [ 'VIP_RTC_WS_AUTH_SECRET', 'VIP_RTC_WS_URL' ], false, [ 'gutenberg/gutenberg.php' ] ],
			'gutenberg plugin network activated' => [ [ 'VIP_RTC_WS_AUTH_SECRET', 'VIP_RTC_WS_URL' ], false, [], [ 'gutenberg/gutenberg.php' => 1 ] ],
		];
	}

	/**
	 * @dataProvider data_can_load_constants
	 */
	public function test_can_load_checks_required_constants( array $constants, bool $expected, array $active_plugins = [], array $network_active_plugins = [] ): void {
		if ( [] !== $network_active_plugins ) {
			// Network activation is only available in multisite.
			$this->skipWithoutMultisite();
		}

		$values = [
			'VIP_RTC_WS_AUTH_SECRET' => 'test-secret',
			'VIP_RTC_WS_URL'         => 'wss://test.example.com',
			'IS_GUTENBERG_PLUGIN'    => true,
		];
		foreach ( $constants as $constant ) {
			Constant_Mocker::define( $constant, $values[ $constant ] );
		}
		update_option( 'active_plugins', $active_plugins );
		update_site_option( 'active_sitewide_plugins', $network_active_plugins );

		$rtc_integration = new RealTimeCollaborationIntegration( $this->slug );
		$can_load        = get_class_method_as_public( RealTimeCollaborationIntegration::class, 'can_load' );

		$this->assertSame( $expected, $can_load->invoke( $rtc_integration ) );
	}

	/**
	 * Available plugin fixtures must load only when both required settings are present and the files exist.
	 *
	 * Use a native namespace constant so installed extension files cannot satisfy this fixture.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_required_configuration_guards_real_available_plugins(): void {
		// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.directory_mkdir, WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents -- Temporary plugin fixtures.
		$root = get_temp_dir() . 'vip-rtc-' . wp_generate_password( 12, false );
		\define( __NAMESPACE__ . '\\WPVIP_MU_PLUGIN_DIR', $root );
		foreach ( [ 'missing secret', 'missing url', 'missing files', 'valid' ] as $variant ) {
			Constant_Mocker::clear();
			$gutenberg = $root . '/vip-integrations/gutenberg-' . RealTimeCollaborationIntegration::VIP_RTC_GUTENBERG_VERSION;
			$rtc       = $root . '/vip-integrations/vip-real-time-collaboration-' . RealTimeCollaborationIntegration::VIP_RTC_PLUGIN_VERSION;
			mkdir( $gutenberg, 0700, true );
			mkdir( $rtc, 0700, true );
			if ( 'missing files' !== $variant ) {
				file_put_contents( $gutenberg . '/gutenberg.php', '<?php $GLOBALS["vip_rtc_fixture_loads"][] = "gutenberg";' );
				file_put_contents( $rtc . '/vip-real-time-collaboration.php', '<?php $GLOBALS["vip_rtc_fixture_loads"][] = "rtc";' );
			}
			if ( 'missing secret' !== $variant ) {
				Constant_Mocker::define( 'VIP_RTC_WS_AUTH_SECRET', 'test-secret' );
			}
			if ( 'missing url' !== $variant ) {
				Constant_Mocker::define( 'VIP_RTC_WS_URL', 'wss://example.com' );
			}
			$GLOBALS['vip_rtc_fixture_loads'] = [];
			$integration                      = new RealTimeCollaborationIntegration( $this->slug );
			$integration->activate();
			$this->assertTrue( $integration->is_active(), $variant );
			$hooks = isset( $GLOBALS['wp_filter']['plugins_loaded'] ) ? clone $GLOBALS['wp_filter']['plugins_loaded'] : null;
			try {
				$this->assertSame( 'missing secret' !== $variant && 'missing url' !== $variant, get_class_method_as_public( RealTimeCollaborationIntegration::class, 'can_load' )->invoke( $integration ), $variant );
				$integration->load();
				do_action( 'plugins_loaded' );
				$this->assertSame( 'valid' === $variant ? [ 'gutenberg', 'rtc' ] : [], $GLOBALS['vip_rtc_fixture_loads'], $variant );
				$this->assertSame( 'valid' === $variant, $integration->is_active(), $variant );
			} finally {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore exact hook state between fixture variants.
				if ( null === $hooks ) {
					unset( $GLOBALS['wp_filter']['plugins_loaded'] );
				} else {
					// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the cloned hook after the fixture.
					$GLOBALS['wp_filter']['plugins_loaded'] = $hooks;
				}
				unset( $GLOBALS['vip_rtc_fixture_loads'] );
				if ( 'missing files' !== $variant ) {
					wp_delete_file( $gutenberg . '/gutenberg.php' );
					wp_delete_file( $rtc . '/vip-real-time-collaboration.php' );
				}
				rmdir( $gutenberg );
				rmdir( $rtc );
				rmdir( $root . '/vip-integrations' );
				rmdir( $root );
			}
		}
		// phpcs:enable WordPressVIPMinimum.Functions.RestrictedFunctions.directory_mkdir, WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
	}
}
