<?php

/**
 * Test: Share a Draft Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use Env_Integration_Status;
use WP_UnitTestCase;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing, Squiz.Commenting.FunctionComment.MissingParamComment

require_once __DIR__ . '/trait-secondary-blog.php';

class Shareadraft_Integration_Test extends WP_UnitTestCase {
	use Secondary_Blog;

	private string $slug = 'shareadraft';

	/**
	 * On multisite, the current network site's config is merged over the environment config, so a
	 * network site without config of its own still gets the environment's IP allowlist.
	 *
	 * @dataProvider data_multisite_config
	 */
	public function test_configure_merges_environment_and_network_site_config_for_multisite( array $env_config, ?array $site_config, array $expected ): void {
		$blog_2_id = $this->switch_to_secondary_blog();

		$network_sites = [];
		if ( null !== $site_config ) {
			$network_sites[ $blog_2_id ] = [
				'status' => Env_Integration_Status::ENABLED,
				'config' => $site_config,
			];
		}

		$integration = new ShareadraftIntegration( $this->slug );
		$integration->set_vip_config(
			new IntegrationVipConfig(
				$this->slug,
				[
					'env'           => [
						'status' => Env_Integration_Status::ENABLED,
						'config' => $env_config,
					],
					'network_sites' => $network_sites,
				]
			)
		);
		$integration->configure();

		$this->assertSame( $expected, constant( 'VIP_SHAREADRAFT_CONFIG' ) );
	}

	public static function data_multisite_config(): array {
		$env_config = [
			'ip_allowlist'           => '192.0.2.0/24',
			'dead_link_grace_period' => '1814400',
		];

		return [
			'network site without config inherits the environment' => [
				$env_config,
				null,
				$env_config,
			],
			'network site values win for duplicate keys' => [
				$env_config,
				[ 'ip_allowlist' => '198.51.100.0/24' ],
				[
					'ip_allowlist'           => '198.51.100.0/24',
					'dead_link_grace_period' => '1814400',
				],
			],
		];
	}
}
