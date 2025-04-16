<?php

/**
 * Test: Remote Data Blocks Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;
use Automattic\Test\Constant_Mocker;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing

class Remote_Data_Blocks_Integration_Test extends WP_UnitTestCase {

	private string $slug = 'remote-data-blocks';

	public function tearDown(): void {
		parent::tearDown();

		Constant_Mocker::clear();

		// Clean up any action we might have added
		remove_all_actions( 'plugins_loaded' );

		// Reset our global flag if it was set
		if ( isset( $GLOBALS['_vip_remote_data_blocks_fired_action'] ) ) {
			$GLOBALS['_vip_remote_data_blocks_fired_action'] = false;
		}
	}

	public function test_is_loaded_returns_false_when_not_loaded(): void {
		$remote_data_blocks_integration = new RemoteDataBlocksIntegration( $this->slug );
		$this->assertFalse( $remote_data_blocks_integration->is_loaded() );
	}

	public function test_is_loaded_returns_true_when_constant_defined(): void {
		Constant_Mocker::define( 'REMOTE_DATA_BLOCKS__LOADED', true );
		$remote_data_blocks_integration = new RemoteDataBlocksIntegration( $this->slug );
		$this->assertTrue( $remote_data_blocks_integration->is_loaded() );
	}

	public function test_load_registers_plugin_loaded_hook(): void {
		$remote_data_blocks_integration = new RemoteDataBlocksIntegration( $this->slug );
		$remote_data_blocks_integration->load();

		$this->assertNotFalse( has_action( 'plugins_loaded' ) );
	}

	public function test_load_returns_early_if_plugin_already_loaded(): void {
		Constant_Mocker::define( 'REMOTE_DATA_BLOCKS__LOADED', true );

		/**
		 * Integration mock that expects is_loaded to be called once and return true
		 *
		 * @var MockObject|RemoteDataBlocksIntegration
		 */
		$integration_mock = $this->getMockBuilder( RemoteDataBlocksIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'is_loaded' ] )
			->getMock();

		$integration_mock->expects( $this->once() )
			->method( 'is_loaded' )
			->willReturn( true );

		$integration_mock->load();

		// Manually trigger the plugins_loaded action
		do_action( 'plugins_loaded' );

		// This test passes if we reach here without errors, as the expectation of is_loaded being called once is met
		$this->assertTrue( true );
	}

	public function test_configure_defines_config_constant(): void {
		$remote_data_blocks_integration = new RemoteDataBlocksIntegration( $this->slug );
		$remote_data_blocks_integration->configure();

		$this->assertTrue( defined( 'REMOTE_DATA_BLOCKS_CONFIGS' ) );
		$this->assertEquals( [], constant( 'REMOTE_DATA_BLOCKS_CONFIGS' ) );
	}

	public function test_configure_does_not_redefine_constant(): void {
		Constant_Mocker::define( 'REMOTE_DATA_BLOCKS_CONFIGS', [ 'test' => 'value' ] );

		$remote_data_blocks_integration = new RemoteDataBlocksIntegration( $this->slug );
		$remote_data_blocks_integration->configure();

		$this->assertEquals( [ 'test' => 'value' ], constant( 'REMOTE_DATA_BLOCKS_CONFIGS' ) );
	}

	public function test_get_versions_returns_empty_array(): void {
		$remote_data_blocks_integration = new RemoteDataBlocksIntegration( $this->slug );

		$versions = $remote_data_blocks_integration->get_versions();

		$this->assertEmpty( $versions );
	}
}
