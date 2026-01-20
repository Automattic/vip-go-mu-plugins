<?php
/**
 * Test: Block Data API Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing

class Block_Data_API_Integration_Test extends WP_UnitTestCase {
	private string $slug = 'block-data-api';

	public function test__load_call_returns_inactive_because_no_block_data_api_plugin_loaded(): void {
		$block_data_api_integration = new BlockDataApiIntegration( $this->slug );

		$block_data_api_integration->load();

		$this->assertFalse( $block_data_api_integration->is_active() );
	}

	public function test__if_is_loaded_gives_back_false_when_not_loaded(): void {
		$block_data_api_integration = new BlockDataApiIntegration( $this->slug );

		$this->assertFalse( $block_data_api_integration->is_loaded() );
	}

	public function test_load_sets_inactive_if_no_versions_found(): void {
		/** @var MockObject|BlockDataApiIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( BlockDataApiIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'is_loaded', 'get_latest_version' ] )
			->getMock();

		$integration_mock->activate(); // Initial state is active
		$this->assertTrue( $integration_mock->is_active(), 'Initial: Integration should be active.' );

		$integration_mock->method( 'is_loaded' )->willReturn( false );
		$integration_mock->method( 'get_latest_version' )->willReturn( null );

		$integration_mock->load();

		// Trigger the plugins_loaded action to execute the closure
		do_action( 'plugins_loaded' );

		$this->assertFalse( $integration_mock->is_active() );
	}
}
