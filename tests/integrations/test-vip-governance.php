<?php
/**
 * Test: VIP Governance Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

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
}
