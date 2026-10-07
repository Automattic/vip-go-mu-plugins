<?php

/**
 * Test: Agentforce Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use Env_Integration_Status;
use WP_UnitTestCase;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing

require_once __DIR__ . '/trait-secondary-blog.php';

class Agentforce_Integration_Test extends WP_UnitTestCase {
	use Secondary_Blog;

	private string $slug = 'agentforce';

	public function test_configure_sets_version_from_config(): void {
		$agentforce_integration = new AgentforceIntegration( $this->slug );
		$agentforce_integration->activate( [ 'config' => [ 'version' => '1.0' ] ] );
		$agentforce_integration->configure();

		$this->assertEquals( '1.0', $agentforce_integration->version );
	}

	public function test_configure_keeps_default_version_when_not_specified(): void {
		$agentforce_integration = new AgentforceIntegration( $this->slug );
		$agentforce_integration->configure();

		$this->assertEquals( 'latest', $agentforce_integration->version );
	}

	public function test_configure_uses_network_site_config_for_multisite(): void {
		$blog_2_id = $this->switch_to_secondary_blog();

		$config = new IntegrationVipConfig(
			$this->slug,
			[
				'env'           => [
					'status' => Env_Integration_Status::ENABLED,
					'config' => [ 'version' => '1.0' ],
				],
				'network_sites' => [
					1          => [
						'status' => Env_Integration_Status::ENABLED,
						'config' => [ 'version' => '1.5' ],
					],
					$blog_2_id => [
						'status' => Env_Integration_Status::ENABLED,
						'config' => [
							'version' => '2.0',
							'api_key' => 'site-2-key',
						],
					],
				],
			]
		);

		$agentforce_integration = new AgentforceIntegration( $this->slug );
		$agentforce_integration->set_vip_config( $config );
		$agentforce_integration->configure();

		$this->assertSame( '2.0', $agentforce_integration->version );
		$this->assertEquals(
			[
				'version' => '2.0',
				'api_key' => 'site-2-key',
			],
			constant( 'VIP_AGENTFORCE_CONFIGS' )
		);
	}
}
