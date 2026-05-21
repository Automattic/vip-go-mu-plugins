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
	private string $slug                            = 'real-time-collaboration';
	private array $previous_active_plugins          = [];
	private array $previous_active_sitewide_plugins = [];

	public function setUp(): void {
		parent::setUp();

		$this->previous_active_plugins          = get_option( 'active_plugins', [] );
		$this->previous_active_sitewide_plugins = get_site_option( 'active_sitewide_plugins', [] );

		update_option( 'active_plugins', [] );
		update_site_option( 'active_sitewide_plugins', [] );
	}

	public function tearDown(): void {
		Constant_Mocker::clear();
		update_option( 'active_plugins', $this->previous_active_plugins );
		update_site_option( 'active_sitewide_plugins', $this->previous_active_sitewide_plugins );

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
		 * Integration mock that expects is_loaded to be called and return true
		 *
		 * @var MockObject|RealTimeCollaborationIntegration
		 */
		$integration_mock = $this->getMockBuilder( RealTimeCollaborationIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'is_loaded' ] )
			->getMock();

		$integration_mock->expects( $this->once() )
			->method( 'is_loaded' )
			->willReturn( true );

		$integration_mock->load();

		// Trigger the plugins_loaded action to execute the closure
		do_action( 'plugins_loaded' );
	}

	public function test_load_sets_inactive_if_plugin_file_not_found(): void {
		// Set up required constants
		Constant_Mocker::define( 'VIP_RTC_WS_AUTH_SECRET', 'test-secret' );
		Constant_Mocker::define( 'VIP_RTC_WS_URL', 'wss://test.example.com' );
		Constant_Mocker::define( 'WPVIP_MU_PLUGIN_DIR', '/nonexistent/path' );

		/** @var MockObject|RealTimeCollaborationIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( RealTimeCollaborationIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'is_loaded' ] )
			->getMock();

		$integration_mock->activate(); // Initial state is active
		$this->assertTrue( $integration_mock->is_active(), 'Initial: Integration should be active.' );

		$integration_mock->method( 'is_loaded' )->willReturn( false );

		$integration_mock->load();

		// Trigger the plugins_loaded action to execute the closure
		do_action( 'plugins_loaded' );

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
			'web_socket_url' => 'wss://test.example.com/_ws',
		] );

		$integration_mock->configure();

		$this->assertTrue( defined( 'VIP_RTC_WS_URL' ) );
		$this->assertEquals( 'wss://test.example.com/_ws', constant( 'VIP_RTC_WS_URL' ) );
	}

	/**
	 * @dataProvider websocket_multiplexing_enabled_provider
	 */
	public function test_configure_defines_websocket_multiplexing_enabled_constant( bool $enabled ): void {
		/** @var MockObject|RealTimeCollaborationIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( RealTimeCollaborationIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'get_env_config' ] )
			->getMock();

		$integration_mock->method( 'get_env_config' )->willReturn( [
			'web_socket_multiplexing_enabled' => $enabled,
		] );

		$integration_mock->configure();

		$this->assertTrue( defined( 'VIP_RTC_WS_MULTIPLEXING_ENABLED' ) );
		$this->assertSame( $enabled, constant( 'VIP_RTC_WS_MULTIPLEXING_ENABLED' ) );
	}

	public static function websocket_multiplexing_enabled_provider(): array {
		return [
			'enabled'  => [ true ],
			'disabled' => [ false ],
		];
	}

	public function test_configure_does_not_redefine_existing_constants(): void {
		Constant_Mocker::define( 'VIP_RTC_WS_AUTH_SECRET', 'existing-secret' );
		Constant_Mocker::define( 'VIP_RTC_WS_URL', 'wss://existing.example.com/_ws' );
		Constant_Mocker::define( 'VIP_RTC_WS_MULTIPLEXING_ENABLED', false );

		/** @var MockObject|RealTimeCollaborationIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( RealTimeCollaborationIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'get_env_config' ] )
			->getMock();

		$integration_mock->method( 'get_env_config' )->willReturn( [
			'web_socket_auth_secret'          => 'new-secret',
			'web_socket_url'                  => 'wss://new.example.com/_ws',
			'web_socket_multiplexing_enabled' => true,
		] );

		$integration_mock->configure();

		$this->assertEquals( 'existing-secret', constant( 'VIP_RTC_WS_AUTH_SECRET' ) );
		$this->assertEquals( 'wss://existing.example.com/_ws', constant( 'VIP_RTC_WS_URL' ) );
		$this->assertFalse( constant( 'VIP_RTC_WS_MULTIPLEXING_ENABLED' ) );
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
		$this->assertFalse( defined( 'VIP_RTC_WS_MULTIPLEXING_ENABLED' ) );
	}

	public function test_load_sets_inactive_when_ws_auth_secret_missing(): void {
		Constant_Mocker::define( 'VIP_RTC_WS_URL', 'wss://test.example.com' );
		// VIP_RTC_WS_AUTH_SECRET is intentionally not defined

		/** @var MockObject|RealTimeCollaborationIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( RealTimeCollaborationIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'is_loaded' ] )
			->getMock();

		$integration_mock->method( 'is_loaded' )->willReturn( false );

		$integration_mock->load();

		// Trigger the plugins_loaded action to execute the closure
		do_action( 'plugins_loaded' );

		$this->assertFalse( $integration_mock->is_active() );
	}

	public function test_load_sets_inactive_when_ws_url_missing(): void {
		Constant_Mocker::define( 'VIP_RTC_WS_AUTH_SECRET', 'test-secret' );
		// VIP_RTC_WS_URL is intentionally not defined

		/** @var MockObject|RealTimeCollaborationIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( RealTimeCollaborationIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'is_loaded' ] )
			->getMock();

		$integration_mock->method( 'is_loaded' )->willReturn( false );

		$integration_mock->load();

		// Trigger the plugins_loaded action to execute the closure
		do_action( 'plugins_loaded' );

		$this->assertFalse( $integration_mock->is_active() );
	}

	public function test_load_sets_inactive_when_both_ws_constants_missing(): void {
		// Both VIP_RTC_WS_AUTH_SECRET and VIP_RTC_WS_URL are intentionally not defined

		/** @var MockObject|RealTimeCollaborationIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( RealTimeCollaborationIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'is_loaded' ] )
			->getMock();

		$integration_mock->method( 'is_loaded' )->willReturn( false );

		$integration_mock->load();

		// Trigger the plugins_loaded action to execute the closure
		do_action( 'plugins_loaded' );

		$this->assertFalse( $integration_mock->is_active() );
	}

	public function test_load_sets_inactive_when_gutenberg_plugin_active(): void {
		Constant_Mocker::define( 'VIP_RTC_WS_AUTH_SECRET', 'test-secret' );
		Constant_Mocker::define( 'VIP_RTC_WS_URL', 'wss://test.example.com' );
		Constant_Mocker::define( 'IS_GUTENBERG_PLUGIN', true );

		/** @var MockObject|RealTimeCollaborationIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( RealTimeCollaborationIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'is_loaded' ] )
			->getMock();

		$integration_mock->method( 'is_loaded' )->willReturn( false );

		$integration_mock->load();

		// Trigger the plugins_loaded action to execute the closure
		do_action( 'plugins_loaded' );

		$this->assertFalse( $integration_mock->is_active() );
	}

	public function test_load_sets_inactive_when_gutenberg_plugin_activated(): void {
		Constant_Mocker::define( 'VIP_RTC_WS_AUTH_SECRET', 'test-secret' );
		Constant_Mocker::define( 'VIP_RTC_WS_URL', 'wss://test.example.com' );
		update_option( 'active_plugins', [ 'gutenberg/gutenberg.php' ] );

		/** @var MockObject|RealTimeCollaborationIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( RealTimeCollaborationIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'is_loaded' ] )
			->getMock();

		$integration_mock->method( 'is_loaded' )->willReturn( false );

		$integration_mock->load();

		// Trigger the plugins_loaded action to execute the closure
		do_action( 'plugins_loaded' );

		$this->assertFalse( $integration_mock->is_active() );
	}

	public function test_load_sets_inactive_when_gutenberg_plugin_network_activated(): void {
		Constant_Mocker::define( 'VIP_RTC_WS_AUTH_SECRET', 'test-secret' );
		Constant_Mocker::define( 'VIP_RTC_WS_URL', 'wss://test.example.com' );
		update_site_option( 'active_sitewide_plugins', [ 'gutenberg/gutenberg.php' => time() ] );

		/** @var MockObject|RealTimeCollaborationIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( RealTimeCollaborationIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'is_loaded' ] )
			->getMock();

		$integration_mock->method( 'is_loaded' )->willReturn( false );

		$integration_mock->load();

		// Trigger the plugins_loaded action to execute the closure
		do_action( 'plugins_loaded' );

		$this->assertFalse( $integration_mock->is_active() );
	}

	public function test_load_sets_inactive_when_gutenberg_file_missing(): void {
		Constant_Mocker::define( 'VIP_RTC_WS_AUTH_SECRET', 'test-secret' );
		Constant_Mocker::define( 'VIP_RTC_WS_URL', 'wss://test.example.com' );
		Constant_Mocker::define( 'WPVIP_MU_PLUGIN_DIR', '/nonexistent/path' );

		/** @var MockObject|RealTimeCollaborationIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( RealTimeCollaborationIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'is_loaded' ] )
			->getMock();

		$integration_mock->method( 'is_loaded' )->willReturn( false );

		$integration_mock->load();

		// Trigger the plugins_loaded action to execute the closure
		do_action( 'plugins_loaded' );

		$this->assertFalse( $integration_mock->is_active() );
	}
}
