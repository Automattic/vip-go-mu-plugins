<?php
/**
 * Test: Block Data API Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing

class Block_Data_API_Integration_Test extends WP_UnitTestCase {
	private string $slug = 'block-data-api';

	public function test__load_call_returns_inactive_because_no_block_data_api_plugin_loaded(): void {
		$block_data_api_integration = new BlockDataApiIntegration( $this->slug );

		$block_data_api_integration->load();

		$this->assertFalse( $block_data_api_integration->is_active() );
	}

	public function test__if_is_loaded_gives_back_true_when_loaded(): void {
		$block_data_api_integration = new BlockDataApiIntegration( $this->slug );

		define( 'VIP_BLOCK_DATA_API_LOADED', true );

		$this->assertTrue( $block_data_api_integration->is_loaded() );
	}

	public function test__if_is_loaded_gives_back_false_when_loaded(): void {
		$block_data_api_integration = new BlockDataApiIntegration( $this->slug );

		$this->assertFalse( $block_data_api_integration->is_loaded() );
	}
}
