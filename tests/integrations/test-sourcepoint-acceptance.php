<?php
/**
 * Acceptance: SourcePoint CMP integration, site side.
 *
 * Story criteria 4 and 5 as the site observes them. Unlike the unit tests, nothing in the
 * loader is mocked: a real `sourcepoint-config.php` is written to disk in the exact format GOOP's
 * ConfigMap pipeline produces (`<?php return array(...)`), the real `Integrations` registry reads it
 * through `IntegrationVipConfig`, and the assertions are on what site code can observe:
 * `VIP_SOURCEPOINT_CONFIGS`, `wpvip_is_integration_enabled()` and `wpvip_get_integration_info()`.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use Automattic\Test\Constant_Mocker;
use WP_UnitTestCase;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing
// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.directory_mkdir, WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents, WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Test-only temporary config file standing in for the integrations ConfigMap.

require_once __DIR__ . '/trait-secondary-blog.php';

class Sourcepoint_Acceptance_Test extends WP_UnitTestCase {
	use Secondary_Blog;

	private const SLUG = 'sourcepoint';

	/** Values saved through the Dashboard in the GOOP acceptance run. */
	private const ENV_CONFIG = [
		'account_id'         => '1234',
		'privacy_manager_id' => '9012',
		'property_id'        => '5678',
	];

	private const NETWORK_SITE_CONFIG = [
		'account_id'         => '4321',
		'privacy_manager_id' => '2109',
		'property_id'        => '8765',
	];

	/**
	 * Captured from GOOP `IntegrationsConfigMapService.getIntegrationsConfigMap()` for an enabled
	 * single-site environment (see sourcepoint-integration.acceptance.e2e.spec.ts, AC4).
	 */
	private const GOOP_SINGLE_SITE_FILE = "<?php return array('type' => 'sourcepoint', 'org' => array('status' => 'enabled'), 'env' => array('status' => 'enabled', 'config' => array('account_id' => '1234', 'privacy_manager_id' => '9012', 'property_id' => '5678')));";

	private string $config_dir;

	public function setUp(): void {
		parent::setUp();

		$this->config_dir = sys_get_temp_dir() . '/sourcepoint-acceptance-' . uniqid();
		mkdir( $this->config_dir, 0700, true );
		Constant_Mocker::define( 'WPVIP_INTEGRATIONS_CONFIG_DIR', $this->config_dir );
	}

	public function tearDown(): void {
		array_map( 'unlink', glob( $this->config_dir . '/*' ) ?: [] );
		rmdir( $this->config_dir );

		parent::tearDown();
	}

	/**
	 * Write the integration config file the way the integrations ConfigMap delivers it.
	 */
	private function deliver_config_file( string $php_source ): void {
		file_put_contents( $this->config_dir . '/' . self::SLUG . '-config.php', $php_source );
	}

	/**
	 * Build the file body for a structured config in GOOP's `<?php return array(...)` format.
	 */
	private function php_file_for( array $config ): string {
		return '<?php return ' . var_export( $config, true ) . ';';
	}

	/**
	 * Boot the platform integrations exactly as vip-integrations.php does on `muplugins_loaded`.
	 *
	 * @return array{0: Integrations, 1: SourcepointIntegration}
	 */
	private function boot_platform_integrations(): array {
		$integrations = new Integrations();
		$integration  = new SourcepointIntegration( self::SLUG );
		$integrations->register( $integration );

		$integrations->activate_platform_integrations();
		$integrations->load_active();

		return [ $integrations, $integration ];
	}

	/*
	|--------------------------------------------------------------------------
	| AC4: an enabled integration's settings are available to site code
	|--------------------------------------------------------------------------
	*/

	public function test_ac4_goop_delivered_file_exposes_the_settings_to_site_code(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single-site payload; multisite is covered by the network-site test.' );
		}

		$this->deliver_config_file( self::GOOP_SINGLE_SITE_FILE );

		[ $integrations, $integration ] = $this->boot_platform_integrations();

		$this->assertTrue( $integration->is_active() );
		$this->assertTrue( $integrations->is_integration_enabled( self::SLUG ) );
		$this->assertTrue( defined( 'VIP_SOURCEPOINT_CONFIGS' ) );
		$this->assertSame( self::ENV_CONFIG, constant( 'VIP_SOURCEPOINT_CONFIGS' ) );
		$this->assertSame( self::ENV_CONFIG, $integrations->get_integration_info( self::SLUG )['env_config'] );
	}

	public function test_ac4_each_network_site_reads_its_own_settings_on_multisite(): void {
		$blog_2_id = $this->switch_to_secondary_blog();

		// Shape produced by GOOP for a multisite with network_wide_enable 'false' and blog 2 enabled
		// individually (sourcepoint-integration.acceptance.e2e.spec.ts, AC4 multisite).
		$this->deliver_config_file( $this->php_file_for( [
			'type'          => self::SLUG,
			'org'           => [ 'status' => 'enabled' ],
			'env'           => [
				'status' => 'enabled',
				'config' => self::ENV_CONFIG + [ 'network_wide_enable' => 'false' ],
			],
			'network_sites' => [
				$blog_2_id => [
					'status' => 'enabled',
					'config' => self::NETWORK_SITE_CONFIG,
				],
			],
		] ) );

		[ $integrations, $integration ] = $this->boot_platform_integrations();

		$this->assertTrue( $integration->is_active() );
		$this->assertSame( self::NETWORK_SITE_CONFIG, constant( 'VIP_SOURCEPOINT_CONFIGS' ) );
		$this->assertSame( self::NETWORK_SITE_CONFIG, $integrations->get_integration_info( self::SLUG )['site_config'] );
	}

	/*
	|--------------------------------------------------------------------------
	| AC5: nothing SourcePoint-related runs without delivered, enabled config
	|--------------------------------------------------------------------------
	*/

	public function test_ac5_nothing_runs_when_no_config_file_is_delivered(): void {
		[ $integrations, $integration ] = $this->boot_platform_integrations();

		$this->assertFalse( $integration->is_active() );
		$this->assertFalse( $integrations->is_integration_enabled( self::SLUG ) );
		$this->assertFalse( defined( 'VIP_SOURCEPOINT_CONFIGS' ) );
	}

	public function test_ac5_nothing_runs_when_the_delivered_status_is_disabled(): void {
		// Shape produced by GOOP after AC3 (disable keeps the saved settings).
		$this->deliver_config_file( $this->php_file_for( [
			'type' => self::SLUG,
			'org'  => [ 'status' => 'enabled' ],
			'env'  => [
				'status' => 'disabled',
				'config' => self::ENV_CONFIG,
			],
		] ) );

		[ $integrations, $integration ] = $this->boot_platform_integrations();

		$this->assertFalse( $integration->is_active() );
		$this->assertFalse( $integrations->is_integration_enabled( self::SLUG ) );
		$this->assertFalse( defined( 'VIP_SOURCEPOINT_CONFIGS' ) );
	}

	public function test_ac5_a_network_site_not_enabled_individually_stays_inactive_on_multisite(): void {
		$this->switch_to_secondary_blog();

		// network_wide_enable 'false' (the multisite default) and no entry for the current blog.
		$this->deliver_config_file( $this->php_file_for( [
			'type'          => self::SLUG,
			'org'           => [ 'status' => 'enabled' ],
			'env'           => [
				'status' => 'enabled',
				'config' => self::ENV_CONFIG + [ 'network_wide_enable' => 'false' ],
			],
			'network_sites' => [
				1 => [
					'status' => 'enabled',
					'config' => self::NETWORK_SITE_CONFIG,
				],
			],
		] ) );

		[ , $integration ] = $this->boot_platform_integrations();

		$this->assertFalse( $integration->is_active() );
		$this->assertFalse( defined( 'VIP_SOURCEPOINT_CONFIGS' ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Story edge cases
	|--------------------------------------------------------------------------
	*/

	public function test_edge_booleans_arrive_as_strings_and_are_passed_through_untouched(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single-site payload.' );
		}

		$this->deliver_config_file( $this->php_file_for( [
			'type' => self::SLUG,
			'org'  => [ 'status' => 'enabled' ],
			'env'  => [
				'status' => 'enabled',
				'config' => self::ENV_CONFIG + [ 'network_wide_enable' => 'false' ],
			],
		] ) );

		$this->boot_platform_integrations();

		$configs = constant( 'VIP_SOURCEPOINT_CONFIGS' );
		$this->assertSame( 'false', $configs['network_wide_enable'] );
		$this->assertIsString( $configs['network_wide_enable'] );
	}

	public function test_edge_no_cmp_script_tag_is_emitted_in_this_slice(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Single-site payload.' );
		}

		$this->deliver_config_file( self::GOOP_SINGLE_SITE_FILE );
		$this->boot_platform_integrations();

		ob_start();
		do_action( 'wp_head' );
		do_action( 'wp_footer' );
		$output = (string) ob_get_clean();

		$this->assertStringNotContainsStringIgnoringCase( 'sourcepoint', $output );
		$this->assertStringNotContainsString( self::ENV_CONFIG['account_id'], $output );
	}
}
