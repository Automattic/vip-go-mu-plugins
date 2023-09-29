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
}