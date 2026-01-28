<?php

/**
 * Test: VIP Agentforce Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;
use Automattic\Test\Constant_Mocker;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing

class Vip_Agentforce_Integration_Test extends WP_UnitTestCase {
	private string $slug = 'vip-agentforce';

	public function tearDown(): void {
		parent::tearDown();

		Constant_Mocker::clear();
	}

	public function test_is_loaded_returns_false_when_not_loaded(): void {
		$agentforce_integration = new VipAgentforceIntegration( $this->slug );
		$this->assertFalse( $agentforce_integration->is_loaded() );
	}

	public function test_is_loaded_returns_true_when_constant_defined(): void {
		Constant_Mocker::define( 'VIP_AGENTFORCE_FILE', '/path/to/vip-agentforce.php' );
		$agentforce_integration = new VipAgentforceIntegration( $this->slug );
		$this->assertTrue( $agentforce_integration->is_loaded() );
	}

	public function test_configure_sets_version_from_config(): void {
		$agentforce_integration = new VipAgentforceIntegration( $this->slug );
		$agentforce_integration->activate( [ 'config' => [ 'version' => '1.0' ] ] );
		$agentforce_integration->configure();

		$this->assertEquals( '1.0', $agentforce_integration->version );
	}

	public function test_configure_keeps_default_version_when_not_specified(): void {
		$agentforce_integration = new VipAgentforceIntegration( $this->slug );
		$agentforce_integration->configure();

		$this->assertEquals( 'latest', $agentforce_integration->version );
	}

	public function test_get_selected_version_folder_returns_latest_version_when_version_is_latest(): void {
		$agentforce_integration          = new VipAgentforceIntegration( $this->slug );
		$agentforce_integration->version = 'latest';
		$versions                        = array(
			'vip-agentforce-2.5'  => '2.5',
			'vip-agentforce-1.11' => '1.11',
			'vip-agentforce-1.2'  => '1.2',
		);
		$this->assertEquals( 'vip-agentforce-2.5', $agentforce_integration->get_selected_version_folder( $versions ) );
	}

	public function test_get_selected_version_folder_returns_desired_version_when_version_is_specified(): void {
		$agentforce_integration          = new VipAgentforceIntegration( $this->slug );
		$agentforce_integration->version = '1.2';
		$versions                        = array(
			'vip-agentforce-2.5'  => '2.5',
			'vip-agentforce-1.11' => '1.11',
			'vip-agentforce-1.2'  => '1.2',
		);
		$this->assertEquals( 'vip-agentforce-1.2', $agentforce_integration->get_selected_version_folder( $versions ) );
	}

	public function test_get_selected_version_folder_returns_latest_version_when_version_not_found(): void {
		$agentforce_integration          = new VipAgentforceIntegration( $this->slug );
		$agentforce_integration->version = '9.9';
		$versions                        = array(
			'vip-agentforce-2.5'  => '2.5',
			'vip-agentforce-1.11' => '1.11',
			'vip-agentforce-1.2'  => '1.2',
		);
		$this->assertEquals( 'vip-agentforce-2.5', $agentforce_integration->get_selected_version_folder( $versions ) );
	}

	public function test_get_selected_version_folder_returns_latest_version_when_version_is_empty(): void {
		$agentforce_integration          = new VipAgentforceIntegration( $this->slug );
		$agentforce_integration->version = '';
		$versions                        = array(
			'vip-agentforce-2.5'  => '2.5',
			'vip-agentforce-1.11' => '1.11',
			'vip-agentforce-1.2'  => '1.2',
		);
		$this->assertEquals( 'vip-agentforce-2.5', $agentforce_integration->get_selected_version_folder( $versions ) );
	}
}
