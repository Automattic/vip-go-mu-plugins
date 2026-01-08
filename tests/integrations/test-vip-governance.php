<?php
/**
 * Test: VIP Governance Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing

class VIP_Governance_Integration_Test extends WP_UnitTestCase {
	private string $slug = 'vip-governance';

	public function test__load_call_returns_inactive_because_no_governance_plugin_loaded(): void {
		$vip_governance_integration = new VipGovernanceIntegration( $this->slug );

		$vip_governance_integration->load();

		$this->assertFalse( $vip_governance_integration->is_active() );
	}

	public function test__if_is_loaded_gives_back_false_when_not_loaded(): void {
		$vip_governance_integration = new VipGovernanceIntegration( $this->slug );

		$this->assertFalse( $vip_governance_integration->is_loaded() );
	}

	public function test_load_sets_inactive_if_no_versions_found(): void {
		/** @var MockObject|VipGovernanceIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( VipGovernanceIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'is_loaded', 'get_versions' ] )
			->getMock();

		$integration_mock->activate(); // Initial state is active
		$this->assertTrue( $integration_mock->is_active(), 'Initial: Integration should be active.' );

		$integration_mock->method( 'is_loaded' )->willReturn( false );
		$integration_mock->method( 'get_versions' )->willReturn( [] );

		$integration_mock->load();

		// Trigger the plugins_loaded action to execute the closure
		do_action( 'plugins_loaded' );

		$this->assertFalse( $integration_mock->is_active() );
	}
}
