<?php
/**
 * Test: Integration Plugin Display Trait
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing

/**
 * Test class using the trait for testing purposes.
 */
class TestableIntegration extends Integration {
	use IntegrationPluginDisplayTrait;

	public function is_loaded(): bool {
		return false;
	}

	public function configure(): void {
		$this->setup_plugin_display_hooks();
	}

	public function load(): void {
		// Not needed for these tests
	}

	// Expose protected methods for testing
	public function test_register_integration_plugin( string $plugin_path ): void {
		$this->register_integration_plugin( $plugin_path );
	}

	public function test_get_loaded_integration_plugins(): array {
		return static::get_loaded_integration_plugins();
	}

	public function test_reset_for_tests(): void {
		static::reset_plugin_display_for_tests();
	}
}

class IntegrationPluginDisplayTraitTest extends WP_UnitTestCase {
	private $integration;

	public function setUp(): void {
		parent::setUp();
		$this->integration = new TestableIntegration( 'test-integration' );
		// Reset state between tests
		$this->integration->test_reset_for_tests();
	}

	public function tearDown(): void {
		// Clean up state after each test
		$this->integration->test_reset_for_tests();
		parent::tearDown();
	}

	/**
	 * Test: Plugin registration adds plugin to loaded list
	 */
	public function test__plugin_registration_adds_to_list(): void {
		$plugin_path = 'vip-integrations/test-plugin/test-plugin.php';

		$this->integration->test_register_integration_plugin( $plugin_path );
		$loaded_plugins = $this->integration->test_get_loaded_integration_plugins();

		$this->assertContains( $plugin_path, $loaded_plugins );
		$this->assertCount( 1, $loaded_plugins );
	}

	/**
	 * Test: Multiple plugins can be registered
	 */
	public function test__multiple_plugin_registration(): void {
		$plugin1 = 'vip-integrations/plugin-1/plugin-1.php';
		$plugin2 = 'vip-integrations/plugin-2/plugin-2.php';

		$this->integration->test_register_integration_plugin( $plugin1 );
		$this->integration->test_register_integration_plugin( $plugin2 );
		$loaded_plugins = $this->integration->test_get_loaded_integration_plugins();

		$this->assertCount( 2, $loaded_plugins );
		$this->assertContains( $plugin1, $loaded_plugins );
		$this->assertContains( $plugin2, $loaded_plugins );
	}

	/**
	 * Test: Duplicate registration doesn't create duplicates
	 */
	public function test__duplicate_registration_prevented(): void {
		$plugin_path = 'vip-integrations/test-plugin/test-plugin.php';

		$this->integration->test_register_integration_plugin( $plugin_path );
		$this->integration->test_register_integration_plugin( $plugin_path );
		$loaded_plugins = $this->integration->test_get_loaded_integration_plugins();

		$this->assertCount( 1, $loaded_plugins );
	}

	/**
	 * Test: Active plugins filter adds integration plugins when on plugins screen
	 */
	public function test__active_plugins_filter_adds_integration_plugins(): void {
		$plugin_path    = 'vip-integrations/test-plugin/test-plugin.php';
		$active_plugins = [ 'some-plugin/some-plugin.php' ];

		$this->integration->test_register_integration_plugin( $plugin_path );

		// Simulate being on the plugins screen by setting the global
		set_current_screen( 'plugins' );

		$filtered_plugins = $this->integration->filter_active_plugins_for_display( $active_plugins );

		$this->assertContains( $plugin_path, $filtered_plugins );
		$this->assertContains( 'some-plugin/some-plugin.php', $filtered_plugins );
		$this->assertCount( 2, $filtered_plugins );
	}

	/**
	 * Test: Active plugins filter does NOT add plugins when not on plugins screen
	 */
	public function test__active_plugins_filter_only_on_plugins_screen(): void {
		$plugin_path    = 'vip-integrations/test-plugin/test-plugin.php';
		$active_plugins = [ 'some-plugin/some-plugin.php' ];

		$this->integration->test_register_integration_plugin( $plugin_path );

		// Simulate NOT being on the plugins screen
		set_current_screen( 'dashboard' );

		$filtered_plugins = $this->integration->filter_active_plugins_for_display( $active_plugins );

		// Integration plugin should NOT be added
		$this->assertNotContains( $plugin_path, $filtered_plugins );
		$this->assertCount( 1, $filtered_plugins );
	}

	/**
	 * Test: Update active plugins removes integration plugins to prevent database writes
	 */
	public function test__update_active_plugins_removes_integration_plugins(): void {
		$integration_plugin = 'vip-integrations/test-plugin/test-plugin.php';
		$regular_plugin     = 'some-plugin/some-plugin.php';
		$active_plugins     = [ $regular_plugin, $integration_plugin ];

		$this->integration->test_register_integration_plugin( $integration_plugin );

		$filtered_plugins = $this->integration->filter_update_active_plugins( $active_plugins );

		// Integration plugin should be removed
		$this->assertNotContains( $integration_plugin, $filtered_plugins );
		// Regular plugin should remain
		$this->assertContains( $regular_plugin, $filtered_plugins );
		$this->assertCount( 1, $filtered_plugins );
	}

	/**
	 * Test: Network active plugins filter adds integration plugins with timestamp
	 */
	public function test__network_active_plugins_filter_adds_with_timestamp(): void {
		$plugin_path    = 'vip-integrations/test-plugin/test-plugin.php';
		$active_plugins = [ 'some-plugin/some-plugin.php' => 1234567890 ];

		$this->integration->test_register_integration_plugin( $plugin_path );

		// Simulate being on network plugins screen
		set_current_screen( 'plugins-network' );

		$filtered_plugins = $this->integration->filter_network_active_plugins_for_display( $active_plugins );

		$this->assertArrayHasKey( $plugin_path, $filtered_plugins );
		$this->assertArrayHasKey( 'some-plugin/some-plugin.php', $filtered_plugins );
		$this->assertIsInt( $filtered_plugins[ $plugin_path ] );
		$this->assertCount( 2, $filtered_plugins );
	}

	/**
	 * Test: Update network active plugins removes integration plugins
	 */
	public function test__update_network_active_plugins_removes_integration_plugins(): void {
		$integration_plugin = 'vip-integrations/test-plugin/test-plugin.php';
		$regular_plugin     = 'some-plugin/some-plugin.php';
		$active_plugins     = [
			$regular_plugin     => 1234567890,
			$integration_plugin => 1234567890,
		];

		$this->integration->test_register_integration_plugin( $integration_plugin );

		$filtered_plugins = $this->integration->filter_update_network_active_plugins( $active_plugins );

		// Integration plugin should be removed
		$this->assertArrayNotHasKey( $integration_plugin, $filtered_plugins );
		// Regular plugin should remain
		$this->assertArrayHasKey( $regular_plugin, $filtered_plugins );
		$this->assertCount( 1, $filtered_plugins );
	}

	/**
	 * Test: Plugin action links are customized for integration plugins
	 */
	public function test__plugin_action_links_customized(): void {
		$plugin_path = 'vip-integrations/test-plugin/test-plugin.php';
		$actions     = [
			'activate'   => '<a href="#">Activate</a>',
			'deactivate' => '<a href="#">Deactivate</a>',
			'edit'       => '<a href="#">Edit</a>',
		];

		$this->integration->test_register_integration_plugin( $plugin_path );

		$filtered_actions = $this->integration->filter_plugin_action_links( $actions, $plugin_path );

		// Activate and deactivate should be removed
		$this->assertArrayNotHasKey( 'activate', $filtered_actions );
		$this->assertArrayNotHasKey( 'deactivate', $filtered_actions );
		// Custom message should be added
		$this->assertArrayHasKey( 'vip-code-activated-plugin', $filtered_actions );
		$this->assertEquals( 'Enabled via WPVIP Integrations', $filtered_actions['vip-code-activated-plugin'] );
		// Other actions should remain
		$this->assertArrayHasKey( 'edit', $filtered_actions );
	}

	/**
	 * Test: Action links for non-integration plugins are unchanged
	 */
	public function test__plugin_action_links_unchanged_for_regular_plugins(): void {
		$plugin_path = 'some-plugin/some-plugin.php';
		$actions     = [
			'activate'   => '<a href="#">Activate</a>',
			'deactivate' => '<a href="#">Deactivate</a>',
		];

		$filtered_actions = $this->integration->filter_plugin_action_links( $actions, $plugin_path );

		// Should remain unchanged
		$this->assertEquals( $actions, $filtered_actions );
	}

	/**
	 * Test: Cleanup recently activated removes integration plugins
	 */
	public function test__cleanup_recently_activated(): void {
		$integration_plugin = 'vip-integrations/test-plugin/test-plugin.php';
		$regular_plugin     = 'some-plugin/some-plugin.php';

		// Set up recently activated plugins
		update_option( 'recently_activated', [
			$integration_plugin => time(),
			$regular_plugin     => time(),
		] );

		$this->integration->test_register_integration_plugin( $integration_plugin );
		$this->integration->cleanup_recently_activated();

		$recently_activated = get_option( 'recently_activated', [] );

		// Integration plugin should be removed
		$this->assertArrayNotHasKey( $integration_plugin, $recently_activated );
		// Regular plugin should remain
		$this->assertArrayHasKey( $regular_plugin, $recently_activated );
	}
}
