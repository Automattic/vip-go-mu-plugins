<?php

/**
 * Test: VIP Workflows Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use Automattic\Test\Constant_Mocker;
use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing

class VIP_Workflows_Integration_Test extends WP_UnitTestCase {
	private string $slug = 'vip-workflows';

	public function tearDown(): void {
		Constant_Mocker::clear();

		parent::tearDown();
	}

	public function test_is_loaded_returns_false_when_not_loaded(): void {
		$integration = new VipWorkflowsIntegration( $this->slug );
		$this->assertFalse( $integration->is_loaded() );
	}

	public function test_is_loaded_returns_true_when_loaded_constant_is_defined(): void {
		Constant_Mocker::define( 'VIP_WORKFLOWS_LOADED', true );

		$integration = new VipWorkflowsIntegration( $this->slug );
		$this->assertTrue( $integration->is_loaded() );
	}

	public function test_is_loaded_returns_true_when_plugin_file_constant_is_defined(): void {
		Constant_Mocker::define( 'VIP_WORKFLOWS_PLUGIN_FILE', '/path/to/vip-workflows.php' );

		$integration = new VipWorkflowsIntegration( $this->slug );
		$this->assertTrue( $integration->is_loaded() );
	}

	public function test_load_returns_early_if_plugin_already_loaded(): void {
		/** @var MockObject|VipWorkflowsIntegration $integration */
		$integration = $this->getMockBuilder( VipWorkflowsIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'is_loaded' ] )
			->getMock();

		$integration->expects( $this->once() )
			->method( 'is_loaded' )
			->willReturn( true );

		$integration->load();
		do_action( 'plugins_loaded' );
	}

	public function test_load_sets_inactive_when_no_versions_are_available(): void {
		/** @var MockObject|VipWorkflowsIntegration $integration */
		$integration = $this->getMockBuilder( VipWorkflowsIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'is_loaded', 'get_versions' ] )
			->getMock();

		$integration->activate();
		$integration->method( 'is_loaded' )->willReturn( false );
		$integration->method( 'get_versions' )->willReturn( [] );

		$integration->load();
		do_action( 'plugins_loaded' );

		$this->assertFalse( $integration->is_active() );
	}

	public function test_get_selected_version_folder_returns_latest_version(): void {
		$integration = new VipWorkflowsIntegration( $this->slug );
		$versions    = [
			'vip-workflows-0.1' => '0.1',
			'vip-workflows-0.0' => '0.0',
		];

		$this->assertSame( 'vip-workflows-0.1', $integration->get_selected_version_folder( $versions ) );
	}

	public function test_get_selected_version_folder_returns_requested_version(): void {
		$integration          = new VipWorkflowsIntegration( $this->slug );
		$integration->version = '0.0';
		$versions             = [
			'vip-workflows-0.1' => '0.1',
			'vip-workflows-0.0' => '0.0',
		];

		$this->assertSame( 'vip-workflows-0.0', $integration->get_selected_version_folder( $versions ) );
	}

	public function test_pendo_tracking_is_enabled(): void {
		$integration = new VipWorkflowsIntegration( $this->slug );

		$this->assertTrue( $integration->should_track_in_pendo() );
	}
}
