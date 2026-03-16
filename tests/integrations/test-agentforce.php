<?php

/**
 * Test: Agentforce Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use Env_Integration_Status;
use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;
use Automattic\Test\Constant_Mocker;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing

class Agentforce_Integration_Test extends WP_UnitTestCase {
	private string $slug = 'agentforce';

	public function tearDown(): void {
		parent::tearDown();

		Constant_Mocker::clear();
	}

	public function test_is_loaded_returns_false_when_not_loaded(): void {
		$agentforce_integration = new AgentforceIntegration( $this->slug );
		$this->assertFalse( $agentforce_integration->is_loaded() );
	}

	public function test_is_loaded_returns_true_when_constant_defined(): void {
		Constant_Mocker::define( 'VIP_AGENTFORCE_FILE', '/path/to/vip-agentforce.php' );
		$agentforce_integration = new AgentforceIntegration( $this->slug );
		$this->assertTrue( $agentforce_integration->is_loaded() );
	}

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

	public function test_configure_defines_config_constant(): void {
		$agentforce_integration = new AgentforceIntegration( $this->slug );
		$agentforce_integration->configure();

		$this->assertTrue( defined( 'VIP_AGENTFORCE_CONFIGS' ) );
		$this->assertEquals( [], constant( 'VIP_AGENTFORCE_CONFIGS' ) );
	}

	public function test_configure_uses_network_site_config_for_multisite(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only valid for multisite.' );
		}

		$blog_2_id = $this->factory()->blog->create_object( [ 'domain' => 'agentforce-test.site/2' ] );
		switch_to_blog( $blog_2_id );

		try {
			/** @var IntegrationVipConfig&MockObject $config_mock */
			$config_mock = $this->getMockBuilder( IntegrationVipConfig::class )
				->disableOriginalConstructor()
				->onlyMethods( [ 'get_vip_config_from_file' ] )
				->getMock();

			$config_mock->method( 'get_vip_config_from_file' )->willReturn(
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
			$config_mock->__construct( $this->slug );

			$agentforce_integration = new AgentforceIntegration( $this->slug );
			$agentforce_integration->set_vip_config( $config_mock );
			$agentforce_integration->configure();

			$this->assertSame( '2.0', $agentforce_integration->version );
			$this->assertEquals(
				[
					'version' => '2.0',
					'api_key' => 'site-2-key',
				],
				constant( 'VIP_AGENTFORCE_CONFIGS' )
			);
		} finally {
			restore_current_blog();
		}
	}

	public function test_configure_does_not_redefine_constant(): void {
		Constant_Mocker::define( 'VIP_AGENTFORCE_CONFIGS', [ 'test' => 'value' ] );

		$agentforce_integration = new AgentforceIntegration( $this->slug );
		$agentforce_integration->configure();

		$this->assertEquals( [ 'test' => 'value' ], constant( 'VIP_AGENTFORCE_CONFIGS' ) );
	}

	public function test_get_selected_version_folder_returns_latest_version_when_version_is_latest(): void {
		$agentforce_integration          = new AgentforceIntegration( $this->slug );
		$agentforce_integration->version = 'latest';
		$versions                        = array(
			'vip-agentforce-2.5'  => '2.5',
			'vip-agentforce-1.11' => '1.11',
			'vip-agentforce-1.2'  => '1.2',
		);
		$this->assertEquals( 'vip-agentforce-2.5', $agentforce_integration->get_selected_version_folder( $versions ) );
	}

	public function test_get_selected_version_folder_returns_desired_version_when_version_is_specified(): void {
		$agentforce_integration          = new AgentforceIntegration( $this->slug );
		$agentforce_integration->version = '1.2';
		$versions                        = array(
			'vip-agentforce-2.5'  => '2.5',
			'vip-agentforce-1.11' => '1.11',
			'vip-agentforce-1.2'  => '1.2',
		);
		$this->assertEquals( 'vip-agentforce-1.2', $agentforce_integration->get_selected_version_folder( $versions ) );
	}

	public function test_get_selected_version_folder_returns_latest_version_when_version_not_found(): void {
		$agentforce_integration          = new AgentforceIntegration( $this->slug );
		$agentforce_integration->version = '9.9';
		$versions                        = array(
			'vip-agentforce-2.5'  => '2.5',
			'vip-agentforce-1.11' => '1.11',
			'vip-agentforce-1.2'  => '1.2',
		);
		$this->assertEquals( 'vip-agentforce-2.5', $agentforce_integration->get_selected_version_folder( $versions ) );
	}

	public function test_get_selected_version_folder_returns_latest_version_when_version_is_empty(): void {
		$agentforce_integration          = new AgentforceIntegration( $this->slug );
		$agentforce_integration->version = '';
		$versions                        = array(
			'vip-agentforce-2.5'  => '2.5',
			'vip-agentforce-1.11' => '1.11',
			'vip-agentforce-1.2'  => '1.2',
		);
		$this->assertEquals( 'vip-agentforce-2.5', $agentforce_integration->get_selected_version_folder( $versions ) );
	}
}
