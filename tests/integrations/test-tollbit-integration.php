<?php
/**
 * Test Tollbit Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;

/**
 * Test class for TollbitIntegration.
 */
class TestTollbitIntegration extends WP_UnitTestCase {

	/**
	 * Test instance.
	 *
	 * @var TollbitIntegration
	 */
	private TollbitIntegration $integration;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->integration = new TollbitIntegration( 'tollbit' );
	}

	/**
	 * Test that the integration is not loaded by default.
	 */
	public function test_is_loaded_returns_false_by_default(): void {
		$this->assertFalse( $this->integration->is_loaded() );
	}

	/**
	 * Test that the integration is not active by default.
	 */
	public function test_is_active_returns_false_by_default(): void {
		$this->assertFalse( $this->integration->is_active() );
	}

	/**
	 * Test that the integration can be activated.
	 */
	public function test_can_activate_integration(): void {
		$this->integration->activate();
		$this->assertTrue( $this->integration->is_active() );
	}

	/**
	 * Test that the integration can be activated without configuration.
	 */
	public function test_can_activate_without_configuration(): void {
		$this->integration->activate();
		$this->assertTrue( $this->integration->is_active() );
	}

	/**
	 * Test that the integration returns empty environment configuration.
	 */
	public function test_get_env_config_returns_empty_config(): void {
		$this->integration->activate();
		$env_config = $this->integration->get_env_config();

		$this->assertEmpty( $env_config );
	}

	/**
	 * Test that the integration has the correct slug.
	 */
	public function test_get_slug_returns_correct_slug(): void {
		$this->assertEquals( 'tollbit', $this->integration->get_slug() );
	}

	/**
	 * Test that the integration can be loaded.
	 */
	public function test_load_does_not_throw_error(): void {
		// This should not throw any errors
		$this->integration->load();
		$this->assertTrue( true ); // If we get here, no error was thrown
	}


	/**
	 * Test that the integration always returns false for is_loaded since it's only available through this integration.
	 */
	public function test_is_loaded_always_returns_false(): void {
		// Since Tollbit is only available through this integration, it should always return false
		$this->assertFalse( $this->integration->is_loaded() );
	}

	/**
	 * Test that the integration configures the correct hooks.
	 */
	public function test_configure_sets_up_hooks(): void {
		$this->integration->configure();

		$this->assertEquals( PHP_INT_MAX, has_action( 'init', [ $this->integration, 'handle_ai_bot_redirect' ] ) );

		$this->assertEquals( PHP_INT_MAX, has_action( 'init', [ $this->integration, 'setup_cache_segmentation' ] ) );
	}

	/**
	 * Test that create_tollbit_url creates correct subdomain URL.
	 */
	public function test_create_tollbit_url_creates_subdomain(): void {
		$current_url = 'https://example.com/page?param=value#fragment';
		
		$reflection = new \ReflectionClass( $this->integration );
		$method     = $reflection->getMethod( 'create_tollbit_url' );
		$method->setAccessible( true );
		
		$result = $method->invoke( $this->integration, $current_url );

		$this->assertEquals( 'https://tollbit.example.com/page?param=value#fragment', $result );
	}

	/**
	 * Test that create_tollbit_url returns null for already tollbit subdomains.
	 */
	public function test_create_tollbit_url_returns_null_for_tollbit_subdomain(): void {
		$reflection = new \ReflectionClass( $this->integration );
		$method     = $reflection->getMethod( 'create_tollbit_url' );
		$method->setAccessible( true );
		
		$result = $method->invoke( $this->integration, 'https://tollbit.example.com/page' );
		$this->assertNull( $result );
	}
} 
