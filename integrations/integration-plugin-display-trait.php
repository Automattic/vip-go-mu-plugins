<?php
/**
 * Trait: Integration Plugin Display
 *
 * Provides functionality to make plugins loaded by VIP Integrations appear in the
 * WordPress admin plugins list.
 *
 * This trait handles all the WordPress hooks and filters needed to:
 * - Display integration plugins in the plugins list
 * - Show them as active with custom status text
 * - Prevent accidental activation/deactivation
 * - Prevent database writes for these plugins
 * - Suppress error messages for plugins not in standard locations
 *
 * Usage Example:
 * ```php
 * class MyIntegration extends Integration {
 *     use IntegrationPluginDisplayTrait;
 *
 *     public function configure(): void {
 *         // Setup hooks to display integration plugins
 *         $this->setup_plugin_display_hooks();
 *     }
 *
 *     public function load(): void {
 *         // Load your plugin
 *         require_once $plugin_path;
 *
 *         // Register it for display (will show as "Enabled via WPVIP Integrations")
 *         $relative_path = str_replace(WP_CONTENT_DIR . '/mu-plugins/', '', $plugin_path);
 *         $this->register_integration_plugin($relative_path);
 *     }
 * }
 * ```
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Trait IntegrationPluginDisplayTrait
 *
 * Provides generic functionality for displaying integration plugins in WordPress admin.
 */
trait IntegrationPluginDisplayTrait {

	/**
	 * Collection of all integration plugins registered for display.
	 *
	 * This is shared across all integrations using this trait.
	 *
	 * @var array
	 */
	private static $loaded_integration_plugins = array();

	/**
	 * Flag to track if display hooks have been initialized.
	 *
	 * Ensures hooks are only registered once, even if multiple integrations use this trait.
	 *
	 * @var bool
	 */
	private static $display_hooks_initialized = false;

	/**
	 * Flag to track if output buffer has been started.
	 *
	 * Prevents multiple output buffers from being created.
	 *
	 * @var bool
	 */
	private static $buffer_started = false;

	/**
	 * Custom message to display for integration plugins.
	 *
	 * @var string
	 */
	private static $plugin_display_message = 'Enabled via WPVIP Integrations';

	/**
	 * Register a plugin loaded by this integration for display in WordPress admin.
	 *
	 * The plugin path should be relative to the mu-plugins directory.
	 *
	 * @param string $plugin_path Relative path to the plugin file from mu-plugins directory.
	 *                            Example: 'vip-integrations/my-plugin-1.0/my-plugin.php'
	 */
	protected function register_integration_plugin( string $plugin_path ): void {
		if ( ! in_array( $plugin_path, static::$loaded_integration_plugins, true ) ) {
			static::$loaded_integration_plugins[] = $plugin_path;
		}
	}

	/**
	 * Get list of all integration plugins that have been registered for display.
	 *
	 * @return array Array of plugin paths relative to mu-plugins directory.
	 */
	protected static function get_loaded_integration_plugins(): array {
		return static::$loaded_integration_plugins;
	}

	/**
	 * Setup WordPress hooks to make integration plugins appear in the plugins list.
	 *
	 * This should be called from the integration's configure() method.
	 * Hooks are only initialized once, even if called by multiple integrations.
	 */
	protected function setup_plugin_display_hooks(): void {
		// Only initialize hooks once
		if ( static::$display_hooks_initialized ) {
			return;
		}

		static::$display_hooks_initialized = true;

		// Core plugin display hooks
		add_filter( 'all_plugins', [ $this, 'filter_all_plugins' ] );
		add_filter( 'plugin_action_links', [ $this, 'filter_plugin_action_links' ], 10, 2 );
		add_filter( 'network_admin_plugin_action_links', [ $this, 'filter_plugin_action_links' ], 10, 2 );

		// Active plugin management (display only)
		add_filter( 'option_active_plugins', [ $this, 'filter_active_plugins_for_display' ] );
		add_filter( 'site_option_active_sitewide_plugins', [ $this, 'filter_network_active_plugins_for_display' ] );

		// Prevent accidental database writes
		add_filter( 'pre_update_option_active_plugins', [ $this, 'filter_update_active_plugins' ] );
		add_filter( 'pre_update_site_option_active_sitewide_plugins', [ $this, 'filter_update_network_active_plugins' ] );

		// Cleanup and error suppression
		add_action( 'admin_init', [ $this, 'cleanup_recently_activated' ], 1 );
		add_action( 'admin_notices', [ $this, 'suppress_deactivation_notices' ], 1 );
		add_action( 'network_admin_notices', [ $this, 'suppress_deactivation_notices' ], 1 );
	}

	/**
	 * Add integration plugins to the all_plugins list for display.
	 *
	 * This filter runs when WordPress builds the plugins list for the admin screen.
	 *
	 * @param array $all_plugins Associative array of plugin data.
	 * @return array Modified array with integration plugins added.
	 */
	public function filter_all_plugins( $all_plugins ): array {
		$integration_plugins = static::get_loaded_integration_plugins();

		foreach ( $integration_plugins as $plugin_path ) {
			// Skip if already in the list
			if ( isset( $all_plugins[ $plugin_path ] ) ) {
				continue;
			}

			// Get the full path to the plugin file
			$full_path = WP_CONTENT_DIR . '/mu-plugins/' . $plugin_path;

			if ( ! file_exists( $full_path ) ) {
				continue;
			}

			// Get plugin data from the file headers
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			try {
				$plugin_data = get_plugin_data( $full_path, false, false );

				if ( empty( $plugin_data['Name'] ) ) {
					continue;
				}

				// Add to the plugins list
				$all_plugins[ $plugin_path ] = $plugin_data;
			} catch ( \Exception $e ) {
				// Skip plugins that can't be parsed
				continue;
			}
		}

		return $all_plugins;
	}

	/**
	 * Add integration plugins to active plugins list for display purposes only.
	 *
	 * Only affects the plugins screen display, not the actual active_plugins option in the database.
	 *
	 * @param array $active_plugins Array of active plugin paths.
	 * @return array Modified array with integration plugins added.
	 */
	public function filter_active_plugins_for_display( $active_plugins ): array {
		// Only filter when displaying the plugins screen
		if ( ! $this->is_plugins_screen() ) {
			return $active_plugins;
		}

		$active_plugins      = is_array( $active_plugins ) ? $active_plugins : array();
		$integration_plugins = static::get_loaded_integration_plugins();

		return array_unique( array_merge( $active_plugins, $integration_plugins ) );
	}

	/**
	 * Add integration plugins to network active plugins list for display purposes only.
	 *
	 * For multisite network admin, plugins are stored as associative array with timestamps.
	 *
	 * @param array $active_plugins Associative array of active plugin paths with timestamps.
	 * @return array Modified array with integration plugins added.
	 */
	public function filter_network_active_plugins_for_display( $active_plugins ): array {
		// Only filter when displaying the plugins screen
		if ( ! $this->is_network_plugins_screen() ) {
			return $active_plugins;
		}

		$active_plugins      = is_array( $active_plugins ) ? $active_plugins : array();
		$integration_plugins = static::get_loaded_integration_plugins();

		foreach ( $integration_plugins as $plugin ) {
			$active_plugins[ $plugin ] = time();
		}

		return $active_plugins;
	}

	/**
	 * Remove integration plugins from active plugins when updating to prevent database writes.
	 *
	 * This ensures integration plugins are never written to the active_plugins option.
	 *
	 * @param array $active_plugins Array of plugin paths to be saved.
	 * @return array Modified array with integration plugins removed.
	 */
	public function filter_update_active_plugins( $active_plugins ): array {
		$active_plugins      = is_array( $active_plugins ) ? $active_plugins : array();
		$integration_plugins = static::get_loaded_integration_plugins();

		return array_values( array_diff( $active_plugins, $integration_plugins ) );
	}

	/**
	 * Remove integration plugins from network active plugins when updating to prevent database writes.
	 *
	 * This ensures integration plugins are never written to the active_sitewide_plugins option.
	 *
	 * @param array $active_plugins Associative array of plugin paths to be saved.
	 * @return array Modified array with integration plugins removed.
	 */
	public function filter_update_network_active_plugins( $active_plugins ): array {
		$active_plugins      = is_array( $active_plugins ) ? $active_plugins : array();
		$integration_plugins = static::get_loaded_integration_plugins();

		return array_diff_key( $active_plugins, array_flip( $integration_plugins ) );
	}

	/**
	 * Customize plugin action links for integration plugins.
	 *
	 * Removes activate/deactivate links and adds custom status message.
	 *
	 * @param array  $actions     Associative array of action links.
	 * @param string $plugin_file Plugin file path relative to plugins directory.
	 * @return array Modified array of action links.
	 */
	public function filter_plugin_action_links( $actions, $plugin_file ): array {
		$integration_plugins = static::get_loaded_integration_plugins();

		if ( in_array( $plugin_file, $integration_plugins, true ) ) {
			// Remove activate/deactivate links
			unset( $actions['activate'], $actions['deactivate'], $actions['network_active'] );

			// Add custom status message using the same class as code-activated plugins
			// This will trigger the existing JS to hide the checkbox
			$actions['vip-code-activated-plugin'] = static::$plugin_display_message;
		}

		return $actions;
	}

	/**
	 * Check if we're on a specific admin screen.
	 *
	 * @param string $screen_id The screen ID to check for.
	 * @return bool True if on the specified screen, false otherwise.
	 */
	private function is_admin_screen( string $screen_id ): bool {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		return $screen && $screen->id === $screen_id;
	}

	/**
	 * Check if we're on the plugins screen.
	 *
	 * @return bool True if on the plugins screen, false otherwise.
	 */
	private function is_plugins_screen(): bool {
		return $this->is_admin_screen( 'plugins' );
	}

	/**
	 * Check if we're on the network plugins screen.
	 *
	 * @return bool True if on the network plugins screen, false otherwise.
	 */
	private function is_network_plugins_screen(): bool {
		return $this->is_admin_screen( 'plugins-network' );
	}

	/**
	 * Clean up recently activated list to prevent validation errors.
	 *
	 * This prevents WordPress from showing "Plugin file does not exist" errors
	 * when it tries to validate integration plugins that aren't in the standard plugins directory.
	 */
	public function cleanup_recently_activated(): void {
		$integration_plugins = static::get_loaded_integration_plugins();

		if ( empty( $integration_plugins ) ) {
			return;
		}

		$recently_activated = get_option( 'recently_activated', array() );
		$modified           = false;

		foreach ( $integration_plugins as $plugin ) {
			if ( isset( $recently_activated[ $plugin ] ) ) {
				unset( $recently_activated[ $plugin ] );
				$modified = true;
			}
		}

		if ( $modified ) {
			update_option( 'recently_activated', $recently_activated );
		}
	}

	/**
	 * Suppress deactivation error notices for integration plugins.
	 *
	 * This prevents WordPress from showing "Plugin file does not exist" errors
	 * when it tries to validate integration plugins that aren't in the standard plugins directory.
	 *
	 * Uses output buffering to filter out specific error messages from the admin page output.
	 */
	public function suppress_deactivation_notices(): void {
		$integration_plugins = static::get_loaded_integration_plugins();

		if ( empty( $integration_plugins ) || static::$buffer_started ) {
			return;
		}

		static::$buffer_started = true;

		// Use output buffering to filter out error notices about integration plugins
		ob_start( function ( $buffer ) use ( $integration_plugins ) {
			foreach ( $integration_plugins as $plugin ) {
				// Remove error notices for "Plugin file does not exist"
				// Pattern matches the exact WordPress error format:
				// <div ... class="notice error"><p>The plugin <code>PATH</code> has been deactivated due to an error: Plugin file does not exist.</p></div>
				$escaped_plugin = preg_quote( $plugin, '/' );
				$pattern        = '/<div[^>]*\bnotice\b[^>]*\berror\b[^>]*><p>The plugin <code>' . $escaped_plugin . '<\/code> has been deactivated due to an error: Plugin file does not exist\.<\/p><\/div>\s*/';
				$buffer         = preg_replace( $pattern, '', $buffer );
			}
			return $buffer;
		} );

		// Flush the buffer at the end of admin pages
		$footer_hook = is_network_admin() ? 'network_admin_footer' : 'admin_footer';
		add_action(
			$footer_hook,
			function () {
				if ( ob_get_level() > 0 ) {
					ob_end_flush();
				}
			},
			999
		);
	}
}
