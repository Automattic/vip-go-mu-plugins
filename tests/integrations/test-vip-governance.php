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

	public function test__load_call_returns_without_setting_constant_if_vip_governance_is_already_loaded(): void {
		/**
		 * Integration mock.
		 *
		 * @var MockObject|VipGovernanceIntegration
		 */
		$vip_governance_integration_mock = $this->getMockBuilder( VipGovernanceIntegration::class )->setConstructorArgs( [ 'vip-governance' ] )->setMethods( [ 'is_loaded' ] )->getMock();
		$vip_governance_integration_mock->expects( $this->once() )->method( 'is_loaded' )->willReturn( true );
		$preload_state = defined( 'VIP_GOVERNANCE_LOADED' );

		$vip_governance_integration_mock->load();

		$this->assertEquals( $preload_state, defined( 'VIP_GOVERNANCE_LOADED' ) );
	}

	public function test__configure_is_doing_nothing_for_configuration_on_vip_platform(): void {
		$vip_governance_integration = new VipGovernanceIntegration( $this->slug );

		$vip_governance_integration->configure();
	}
}
