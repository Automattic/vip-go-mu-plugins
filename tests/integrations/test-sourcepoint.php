<?php
/**
 * Test: SourcePoint Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use Automattic\Test\Constant_Mocker;
use Env_Integration_Status;
use WP_UnitTestCase;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing

require_once __DIR__ . '/trait-secondary-blog.php';

class Sourcepoint_Integration_Test extends WP_UnitTestCase {
	use Secondary_Blog;

	private string $slug = 'sourcepoint';

	private const CONFIG = [
		'account_id'         => '1234',
		'property_id'        => '5678',
		'privacy_manager_id' => '9012',
	];

	/**
	 * Register the integration on an Integrations instance whose platform config is the given array.
	 *
	 * @return array{0: Integrations, 1: SourcepointIntegration}
	 */
	private function create_platform_integrations( array $vip_config ): array {
		/**
		 * Integrations mock.
		 *
		 * @var Integrations $integrations
		 */
		$integrations = $this->getMockBuilder( Integrations::class )->onlyMethods( [ 'get_integration_vip_config' ] )->getMock();
		$integrations->method( 'get_integration_vip_config' )->willReturn( new IntegrationVipConfig( $this->slug, $vip_config ) );

		$integration = new SourcepointIntegration( $this->slug );
		$integrations->register( $integration );

		return [ $integrations, $integration ];
	}

	public function test_configure_defines_the_env_config(): void {
		$integration = new SourcepointIntegration( $this->slug );
		$integration->activate( [ 'config' => self::CONFIG ] );

		$integration->configure();

		$this->assertTrue( defined( 'VIP_SOURCEPOINT_CONFIGS' ) );
		$this->assertSame( self::CONFIG, constant( 'VIP_SOURCEPOINT_CONFIGS' ) );
	}

	public function test_configure_uses_network_site_config_for_multisite(): void {
		$blog_2_id = $this->switch_to_secondary_blog();

		$network_site_config = [
			'account_id'         => '4321',
			'property_id'        => '8765',
			'privacy_manager_id' => '2109',
		];
		$config              = new IntegrationVipConfig(
			$this->slug,
			[
				'env'           => [
					'status' => Env_Integration_Status::ENABLED,
					'config' => self::CONFIG,
				],
				'network_sites' => [
					1          => [
						'status' => Env_Integration_Status::ENABLED,
						'config' => [ 'account_id' => 'blog-1' ],
					],
					$blog_2_id => [
						'status' => Env_Integration_Status::ENABLED,
						'config' => $network_site_config,
					],
				],
			]
		);

		$integration = new SourcepointIntegration( $this->slug );
		$integration->set_vip_config( $config );
		$integration->configure();

		$this->assertSame( $network_site_config, constant( 'VIP_SOURCEPOINT_CONFIGS' ) );
	}

	public function test_configure_keeps_a_constant_defined_by_site_code(): void {
		Constant_Mocker::define( 'VIP_SOURCEPOINT_CONFIGS', [ 'account_id' => 'site-code' ] );

		$integration = new SourcepointIntegration( $this->slug );
		$integration->activate( [ 'config' => self::CONFIG ] );
		$integration->configure();

		$this->assertSame( [ 'account_id' => 'site-code' ], constant( 'VIP_SOURCEPOINT_CONFIGS' ) );
	}

	public function test_platform_activation_exposes_the_delivered_config(): void {
		$network_site_config = [
			'account_id'         => '4321',
			'property_id'        => '8765',
			'privacy_manager_id' => '2109',
		];

		[ $integrations, $integration ] = $this->create_platform_integrations( [
			'env'           => [
				'status' => Env_Integration_Status::ENABLED,
				'config' => self::CONFIG,
			],
			'network_sites' => [
				get_current_blog_id() => [
					'status' => Env_Integration_Status::ENABLED,
					'config' => $network_site_config,
				],
			],
		] );

		$integrations->activate_platform_integrations();
		$integrations->load_active();

		$this->assertTrue( $integration->is_active() );
		// Multisites read the current network site's config; single sites read the environment config.
		$this->assertSame( is_multisite() ? $network_site_config : self::CONFIG, constant( 'VIP_SOURCEPOINT_CONFIGS' ) );
		$this->assertSame( self::CONFIG, $integrations->get_integration_info( $this->slug )['env_config'] );
	}

	/**
	 * @dataProvider data_inactive_platform_config
	 */
	public function test_nothing_runs_without_an_enabled_platform_config( array $vip_config ): void {
		[ $integrations, $integration ] = $this->create_platform_integrations( $vip_config );

		$integrations->activate_platform_integrations();
		$integrations->load_active();

		$this->assertFalse( $integration->is_active() );
		$this->assertFalse( defined( 'VIP_SOURCEPOINT_CONFIGS' ) );
	}

	public static function data_inactive_platform_config(): array {
		return [
			'disabled env'  => [
				[
					'env' => [
						'status' => Env_Integration_Status::DISABLED,
						'config' => self::CONFIG,
					],
				],
			],
			'absent config' => [ [] ],
		];
	}

	public function test_load_keeps_the_integration_active_and_defines_nothing(): void {
		$integration = new SourcepointIntegration( $this->slug );
		$integration->activate( [ 'config' => self::CONFIG ] );

		$integration->load();

		$this->assertTrue( $integration->is_active() );
		$this->assertFalse( defined( 'VIP_SOURCEPOINT_CONFIGS' ) );
	}
}
