<?php
/**
 * Test: Integration Plugin Display Trait
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

/**
 * Test integration class for testing the trait.
 */
class TestableIntegration extends Integration {
	use IntegrationPluginDisplayTrait;

	private $plugins_to_register = array();

	public function is_loaded(): bool {
		return false;
	}

	public function configure(): void {
		$this->setup_plugin_display_hooks();
	}

	public function load(): void {
		// Register plugins during load (mimics real integration behavior)
		foreach ( $this->plugins_to_register as $plugin ) {
			$this->register_integration_plugin( $plugin );
		}
	}

	// Helper method for tests to set which plugins to register
	public function set_plugins_to_register( array $plugins ): void {
		$this->plugins_to_register = $plugins;
	}
}

class IntegrationPluginDisplayTraitTest extends WP_UnitTestCase {

	/**
	 * Test: Integration plugins appear in admin list as active with custom status
	 *
	 * This tests the full integration flow:
	 * 1. Integration is configured (hooks are set up)
	 * 2. Integration loads and registers plugins
	 * 3. Plugins appear in the WordPress plugins list
	 * 4. Plugins show as active on the plugins screen
	 * 5. Action links are customized (activate/deactivate removed, custom message shown)
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__integration_plugins_appear_in_admin_list_as_active(): void {
		$plugin_path = 'vip-integrations/test-plugin/test-plugin.php';

		// Step 1: Create and configure integration (sets up hooks)
		$integration = new TestableIntegration( 'test-integration' );
		$integration->configure();

		// Step 2: Integration loads and registers plugins
		$integration->set_plugins_to_register( array( $plugin_path ) );
		$integration->load();

		// Step 3: Simulate being on the plugins admin screen
		set_current_screen( 'plugins' );

		// Step 4: Verify plugin appears in active plugins list
		$active_plugins   = array( 'some-other-plugin/plugin.php' );
		$filtered_plugins = $integration->filter_active_plugins_for_display( $active_plugins );

		$this->assertContains( $plugin_path, $filtered_plugins, 'Integration plugin should appear in active plugins list' );
		$this->assertContains( 'some-other-plugin/plugin.php', $filtered_plugins, 'Regular plugins should still be in the list' );

		// Step 5: Verify action links are customized
		$actions = array(
			'activate'   => '<a href="#">Activate</a>',
			'deactivate' => '<a href="#">Deactivate</a>',
			'edit'       => '<a href="#">Edit</a>',
		);

		$filtered_actions = $integration->filter_plugin_action_links( $actions, $plugin_path );

		$this->assertArrayNotHasKey( 'activate', $filtered_actions, 'Activate link should be removed' );
		$this->assertArrayNotHasKey( 'deactivate', $filtered_actions, 'Deactivate link should be removed' );
		$this->assertArrayHasKey( 'vip-code-activated-plugin', $filtered_actions, 'Custom status message should be added' );
		$this->assertEquals( 'Enabled via WPVIP Integrations', $filtered_actions['vip-code-activated-plugin'], 'Status message should indicate VIP integration' );
		$this->assertArrayHasKey( 'edit', $filtered_actions, 'Other action links should remain' );
	}

	/**
	 * Test: Integration plugins never persist to database
	 *
	 * This tests the critical database protection behavior:
	 * 1. Integration plugins are registered
	 * 2. WordPress tries to update active_plugins option (e.g., during plugin activation/deactivation)
	 * 3. Integration plugins are stripped out before the database write
	 * 4. Regular plugins are unaffected
	 *
	 * This ensures integration plugins only appear active for display purposes and never
	 * get written to the database, preventing conflicts and unexpected behavior.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__integration_plugins_never_persist_to_database(): void {
		$integration_plugin1 = 'vip-integrations/plugin-1/plugin-1.php';
		$integration_plugin2 = 'vip-integrations/plugin-2/plugin-2.php';
		$regular_plugin      = 'some-plugin/some-plugin.php';

		// Create and configure integration
		$integration = new TestableIntegration( 'test-integration' );
		$integration->configure();
		$integration->set_plugins_to_register( array( $integration_plugin1, $integration_plugin2 ) );
		$integration->load();

		// Simulate WordPress trying to update active_plugins (single site)
		$plugins_to_save  = array( $regular_plugin, $integration_plugin1, $integration_plugin2 );
		$filtered_plugins = $integration->filter_update_active_plugins( $plugins_to_save );

		$this->assertNotContains( $integration_plugin1, $filtered_plugins, 'Integration plugin 1 should be removed from database write' );
		$this->assertNotContains( $integration_plugin2, $filtered_plugins, 'Integration plugin 2 should be removed from database write' );
		$this->assertContains( $regular_plugin, $filtered_plugins, 'Regular plugin should remain' );
		$this->assertCount( 1, $filtered_plugins, 'Only regular plugin should be saved to database' );

		// Simulate WordPress trying to update active_sitewide_plugins (multisite)
		$network_plugins_to_save  = array(
			$regular_plugin      => time(),
			$integration_plugin1 => time(),
			$integration_plugin2 => time(),
		);
		$filtered_network_plugins = $integration->filter_update_network_active_plugins( $network_plugins_to_save );

		$this->assertArrayNotHasKey( $integration_plugin1, $filtered_network_plugins, 'Integration plugin 1 should be removed from network database write' );
		$this->assertArrayNotHasKey( $integration_plugin2, $filtered_network_plugins, 'Integration plugin 2 should be removed from network database write' );
		$this->assertArrayHasKey( $regular_plugin, $filtered_network_plugins, 'Regular plugin should remain in network active' );
		$this->assertCount( 1, $filtered_network_plugins, 'Only regular plugin should be saved to network database' );
	}

	/**
	 * Test: Integration plugins cannot be deactivated
	 *
	 * This tests the UI protection behavior:
	 * 1. Integration plugins have activate/deactivate links removed
	 * 2. Custom status message is shown instead
	 * 3. Regular plugins are unaffected
	 *
	 * This prevents users from trying to deactivate integration plugins through the UI,
	 * which would fail since they're not actually in the database.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__integration_plugins_cannot_be_deactivated(): void {
		$integration_plugin = 'vip-integrations/test-plugin/test-plugin.php';
		$regular_plugin     = 'some-plugin/some-plugin.php';

		// Create and configure integration
		$integration = new TestableIntegration( 'test-integration' );
		$integration->configure();
		$integration->set_plugins_to_register( array( $integration_plugin ) );
		$integration->load();

		// Test integration plugin action links
		$integration_actions = array(
			'activate'       => '<a href="#">Activate</a>',
			'deactivate'     => '<a href="#">Deactivate</a>',
			'network_active' => '<a href="#">Network Deactivate</a>',
			'edit'           => '<a href="#">Edit</a>',
		);

		$filtered_integration_actions = $integration->filter_plugin_action_links( $integration_actions, $integration_plugin );

		$this->assertArrayNotHasKey( 'activate', $filtered_integration_actions, 'Activate link should be removed' );
		$this->assertArrayNotHasKey( 'deactivate', $filtered_integration_actions, 'Deactivate link should be removed' );
		$this->assertArrayNotHasKey( 'network_active', $filtered_integration_actions, 'Network active link should be removed' );
		$this->assertArrayHasKey( 'vip-code-activated-plugin', $filtered_integration_actions, 'Custom status should be present' );
		$this->assertEquals( 'Enabled via WPVIP Integrations', $filtered_integration_actions['vip-code-activated-plugin'] );

		// Test regular plugin action links remain unchanged
		$regular_actions = array(
			'activate'   => '<a href="#">Activate</a>',
			'deactivate' => '<a href="#">Deactivate</a>',
		);

		$filtered_regular_actions = $integration->filter_plugin_action_links( $regular_actions, $regular_plugin );

		$this->assertEquals( $regular_actions, $filtered_regular_actions, 'Regular plugin action links should be unchanged' );
	}
}
