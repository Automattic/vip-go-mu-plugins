<?php

/**
 * Test: Agentforce Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;
use Automattic\Test\Constant_Mocker;
use PHPUnit\Framework\MockObject\MockObject;

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

	public function test_configure_does_not_redefine_constant(): void {
		Constant_Mocker::define( 'VIP_AGENTFORCE_CONFIGS', [ 'test' => 'value' ] );

		$agentforce_integration = new AgentforceIntegration( $this->slug );
		$agentforce_integration->configure();

		$this->assertEquals( [ 'test' => 'value' ], constant( 'VIP_AGENTFORCE_CONFIGS' ) );
	}

	public function test_configure_merges_org_and_env_config(): void {
		$vip_config_mock = $this->get_vip_config_mock( [
			'org' => [
				'status' => 'enabled',
				'config' => [ 'ingestion_api_token' => 'org-token' ],
			],
			'env' => [
				'status' => 'enabled',
				'config' => [ 'ingestion_api_sync_all_posts' => true ],
			],
		] );

		$agentforce_integration = new AgentforceIntegration( $this->slug );
		$agentforce_integration->set_vip_config( $vip_config_mock );
		$agentforce_integration->configure();

		$configs = constant( 'VIP_AGENTFORCE_CONFIGS' );
		$this->assertArrayHasKey( 'ingestion_api_token', $configs );
		$this->assertArrayHasKey( 'ingestion_api_sync_all_posts', $configs );
		$this->assertEquals( 'org-token', $configs['ingestion_api_token'] );
		$this->assertTrue( $configs['ingestion_api_sync_all_posts'] );
	}

	public function test_configure_works_when_org_config_is_empty(): void {
		$vip_config_mock = $this->get_vip_config_mock( [
			'org' => [
				'status' => 'enabled',
			],
			'env' => [
				'status' => 'enabled',
				'config' => [ 'env_key' => 'env-value' ],
			],
		] );

		$agentforce_integration = new AgentforceIntegration( $this->slug );
		$agentforce_integration->set_vip_config( $vip_config_mock );
		$agentforce_integration->configure();

		$configs = constant( 'VIP_AGENTFORCE_CONFIGS' );
		$this->assertArrayHasKey( 'env_key', $configs );
		$this->assertEquals( 'env-value', $configs['env_key'] );
	}

	/**
	 * Get a mock IntegrationVipConfig.
	 *
	 * @param array $vip_config The config to return from the mock.
	 * @return MockObject&IntegrationVipConfig
	 */
	private function get_vip_config_mock( array $vip_config ) {
		$mock = $this->getMockBuilder( IntegrationVipConfig::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_vip_config_from_file' ] )
			->getMock();

		$mock->method( 'get_vip_config_from_file' )->willReturn( $vip_config );
		$mock->__construct( $this->slug );

		return $mock;
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
