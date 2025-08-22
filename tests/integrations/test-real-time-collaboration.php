<?php

/**
 * Test: Real-Time Collaboration Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;
use Automattic\Test\Constant_Mocker;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing

class Real_Time_Collaboration_Integration_Test extends WP_UnitTestCase {
	private string $slug = 'real-time-collaboration';

	public function tearDown(): void {
		Constant_Mocker::clear();

		parent::tearDown();
	}

	public function test_is_loaded_returns_false_when_not_loaded(): void {
		$rtc_integration = new RealTimeCollaborationIntegration( $this->slug );
		$this->assertFalse( $rtc_integration->is_loaded() );
	}

	public function test_is_loaded_returns_true_when_constant_defined(): void {
		Constant_Mocker::define( 'VIP_REAL_TIME_COLLABORATION__LOADED', true );
		$rtc_integration = new RealTimeCollaborationIntegration( $this->slug );
		$this->assertTrue( $rtc_integration->is_loaded() );
	}

	public function test_load_returns_early_if_plugin_already_loaded(): void {
		/**
		 * Integration mock that expects is_loaded to be called and return true,
		 * and get_versions to never be called (proving early return)
		 *
		 * @var MockObject|RealTimeCollaborationIntegration
		 */
		$integration_mock = $this->getMockBuilder( RealTimeCollaborationIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'is_loaded', 'get_versions' ] )
			->getMock();

		$integration_mock->expects( $this->once() )
			->method( 'is_loaded' )
			->willReturn( true );

		$integration_mock->load();

		// If early return works, get_versions should never be called
		$integration_mock->expects( $this->never() )
			->method( 'get_versions' );
	}

	public function test_load_sets_inactive_if_no_versions_found(): void {
		/** @var MockObject|RealTimeCollaborationIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( RealTimeCollaborationIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'is_loaded', 'get_versions' ] )
			->getMock();

		$integration_mock->activate(); // Initial state is active
		$this->assertTrue( $integration_mock->is_active(), 'Initial: Integration should be active.' );

		$integration_mock->method( 'is_loaded' )->willReturn( false );
		$integration_mock->method( 'get_versions' )->willReturn( [] ); // No versions found

		$integration_mock->load();

		$this->assertFalse( $integration_mock->is_active() );
	}

	public function test_configure_defines_websocket_auth_secret_constant(): void {
		/** @var MockObject|RealTimeCollaborationIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( RealTimeCollaborationIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'get_env_config' ] )
			->getMock();

		$integration_mock->method( 'get_env_config' )->willReturn( [
			'web_socket_auth_secret' => 'test-secret-key',
		] );

		$integration_mock->configure();

		$this->assertTrue( defined( 'VIP_RTC_WS_AUTH_SECRET' ) );
		$this->assertEquals( 'test-secret-key', constant( 'VIP_RTC_WS_AUTH_SECRET' ) );
	}

	public function test_configure_defines_websocket_url_constant(): void {
		/** @var MockObject|RealTimeCollaborationIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( RealTimeCollaborationIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'get_env_config' ] )
			->getMock();

		$integration_mock->method( 'get_env_config' )->willReturn( [
			'web_socket_url' => 'wss://test.example.com/ws',
		] );

		$integration_mock->configure();

		$this->assertTrue( defined( 'VIP_RTC_WS_URL' ) );
		$this->assertEquals( 'wss://test.example.com/ws', constant( 'VIP_RTC_WS_URL' ) );
	}

	public function test_configure_does_not_redefine_existing_constants(): void {
		Constant_Mocker::define( 'VIP_RTC_WS_AUTH_SECRET', 'existing-secret' );
		Constant_Mocker::define( 'VIP_RTC_WS_URL', 'wss://existing.example.com/ws' );

		/** @var MockObject|RealTimeCollaborationIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( RealTimeCollaborationIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'get_env_config' ] )
			->getMock();

		$integration_mock->method( 'get_env_config' )->willReturn( [
			'web_socket_auth_secret' => 'new-secret',
			'web_socket_url'         => 'wss://new.example.com/ws',
		] );

		$integration_mock->configure();

		$this->assertEquals( 'existing-secret', constant( 'VIP_RTC_WS_AUTH_SECRET' ) );
		$this->assertEquals( 'wss://existing.example.com/ws', constant( 'VIP_RTC_WS_URL' ) );
	}

	public function test_configure_handles_missing_config_values(): void {
		/** @var MockObject|RealTimeCollaborationIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( RealTimeCollaborationIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'get_env_config' ] )
			->getMock();

		$integration_mock->method( 'get_env_config' )->willReturn( [] );

		$integration_mock->configure();

		$this->assertFalse( defined( 'VIP_RTC_WS_AUTH_SECRET' ) );
		$this->assertFalse( defined( 'VIP_RTC_WS_URL' ) );
	}

	public function test_get_versions_returns_array(): void {
		$rtc_integration = new RealTimeCollaborationIntegration( $this->slug );
		$versions        = $rtc_integration->get_versions();

		$this->assertIsArray( $versions );
	}
}
