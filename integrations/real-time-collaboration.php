<?php

/**
 * Integration: Real-Time Collaboration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Loads Real-Time Collaboration VIP Integration.
 *
 * @private
 */
class RealTimeCollaborationIntegration extends Integration {

	public function is_loaded(): bool {
		// Check for the existence of the plugin version constant defined in the main plugin file.
		return defined( 'VIP_REAL_TIME_COLLABORATION__LOADED' );
	}

	/**
	 * Check if the Gutenberg plugin is active.
	 */
	private function is_gutenberg_plugin_active(): bool {
		return defined( 'IS_GUTENBERG_PLUGIN' ) && constant( 'IS_GUTENBERG_PLUGIN' );
	}

	/**
	 * Check if all requirements are met to load the integration.
	 */
	private function can_load(): bool {
		// Check required configuration constants
		if ( ! defined( 'VIP_RTC_WS_AUTH_SECRET' ) || ! defined( 'VIP_RTC_WS_URL' ) ) {
			return false;
		}

		// Check Gutenberg requirements
		if ( $this->is_gutenberg_plugin_active() ) {
			return false;
		}

		return true;
	}

	private function get_gutenberg_path(): string|false {
		$gutenberg_path = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/gutenberg/gutenberg.php';
		if ( ! file_exists( $gutenberg_path ) ) {
			return false;
		}

		return $gutenberg_path;
	}

	private function get_plugin_path(): string|false {
		$versions = $this->get_versions();
		if ( empty( $versions ) ) {
			return false;
		}
		$latest_directory = array_key_first( $versions );
		$load_path        = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/' . $latest_directory . '/vip-real-time-collaboration.php';
		if ( ! file_exists( $load_path ) ) {
			return false;
		}

		return $load_path;
	}

	/**
	 * Loads the plugin.
	 *
	 * This is called after the integration is activated and configured.
	 */
	public function load(): void {
		/*
		* Wait until plugins_loaded to give precedence to the plugin in the customer repo.
		* Use priority 1 to ensure we load before any plugins hook into plugins_loaded.
		*/
		add_action( 'plugins_loaded', function () {
			/**
			 * Return if the integration is already loaded.
			 *
			 * In activate() method we do make sure to not activate the integration if its already loaded
			 * but still adding it here as a safety measure i.e. if load() is called directly.
			 */
			if ( $this->is_loaded() ) {
				return;
			}

			if ( ! $this->can_load() ) {
				$this->is_active = false;
				return;
			}

			$gutenberg_path = $this->get_gutenberg_path();
			$load_path      = $this->get_plugin_path();

			if ( false === $gutenberg_path || false === $load_path ) {
				$this->is_active = false;
				return;
			}

			// Get relative paths for plugin registration
			$gutenberg_relative = str_replace( WP_CONTENT_DIR . '/mu-plugins/', '', $gutenberg_path );
			$rtc_relative       = str_replace( WP_CONTENT_DIR . '/mu-plugins/', '', $load_path );

			// Register these plugins so they show up in the WordPress plugins list
			$this->register_integration_plugin( $gutenberg_relative );
			$this->register_integration_plugin( $rtc_relative );

			/**
			 * Load the custom build of Gutenberg from vip-integrations
			 * and the latest version of the vip-real-time-collaboration plugin.
			 */
			require_once $gutenberg_path;
			require_once $load_path;
		}, 1);
	}

	/**
	 * Get the available versions of Real-Time Collaboration in descending order.
	 *
	 * @return array<string, string> An associative array of available versions, where the key is the
	 *                               directory name and the value is the version number. The versions
	 *                               are sorted in descending order.
	 */
	public function get_versions() {
		return get_available_versions(
			WPVIP_MU_PLUGIN_DIR . '/vip-integrations/',
			'vip-real-time-collaboration',
			'vip-real-time-collaboration.php'
		);
	}

	/**
	 * Configure Real-Time Collaboration for VIP Platform.
	 *
	 * This is called after the integration is activated but before the plugin is loaded.
	 */
	public function configure(): void {
		$env_config = $this->get_env_config();

		// Set up WebSocket authentication secret constant
		if ( isset( $env_config['web_socket_auth_secret'] ) && ! defined( 'VIP_RTC_WS_AUTH_SECRET' ) ) {
			define( 'VIP_RTC_WS_AUTH_SECRET', $env_config['web_socket_auth_secret'] );
		}

		// Set up WebSocket URL constant
		if ( isset( $env_config['web_socket_url'] ) && ! defined( 'VIP_RTC_WS_URL' ) ) {
			define( 'VIP_RTC_WS_URL', $env_config['web_socket_url'] );
		}

		// Set up WordPress hooks to display integration plugins in the plugins list
		$this->setup_plugin_list_hooks();
	}

	/**
	 * Loaded integration plugins
	 *
	 * @var array
	 */
	private static $loaded_integration_plugins = array();

	/**
	 * Register a plugin loaded by this integration
	 *
	 * @param string $plugin_path Relative path to the plugin file from mu-plugins directory
	 */
	private function register_integration_plugin( string $plugin_path ): void {
		if ( ! in_array( $plugin_path, self::$loaded_integration_plugins, true ) ) {
			self::$loaded_integration_plugins[] = $plugin_path;
		}
	}

	/**
	 * Get list of all integration plugins that have been loaded
	 *
	 * @return array
	 */
	public static function get_loaded_integration_plugins(): array {
		return self::$loaded_integration_plugins;
	}

	/**
	 * Setup WordPress hooks to make integration plugins appear in the plugins list
	 */
	private function setup_plugin_list_hooks(): void {
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
	 * Add integration plugins to the all_plugins list for display
	 *
	 * @param array $all_plugins
	 * @return array
	 */
	public function filter_all_plugins( $all_plugins ): array {
		$integration_plugins = self::get_loaded_integration_plugins();

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
	 * Add integration plugins to active plugins list for display purposes only
	 *
	 * @param array $active_plugins
	 * @return array
	 */
	public function filter_active_plugins_for_display( $active_plugins ): array {
		// Only filter when displaying the plugins screen
		if ( ! $this->is_plugins_screen() ) {
			return $active_plugins;
		}

		$active_plugins      = is_array( $active_plugins ) ? $active_plugins : array();
		$integration_plugins = self::get_loaded_integration_plugins();

		return array_unique( array_merge( $active_plugins, $integration_plugins ) );
	}

	/**
	 * Add integration plugins to network active plugins list for display purposes only
	 *
	 * @param array $active_plugins
	 * @return array
	 */
	public function filter_network_active_plugins_for_display( $active_plugins ): array {
		// Only filter when displaying the plugins screen
		if ( ! $this->is_network_plugins_screen() ) {
			return $active_plugins;
		}

		$active_plugins      = is_array( $active_plugins ) ? $active_plugins : array();
		$integration_plugins = self::get_loaded_integration_plugins();

		foreach ( $integration_plugins as $plugin ) {
			$active_plugins[ $plugin ] = time();
		}

		return $active_plugins;
	}

	/**
	 * Remove integration plugins from active plugins when updating to prevent database writes
	 *
	 * @param array $active_plugins
	 * @return array
	 */
	public function filter_update_active_plugins( $active_plugins ): array {
		$active_plugins      = is_array( $active_plugins ) ? $active_plugins : array();
		$integration_plugins = self::get_loaded_integration_plugins();

		return array_values( array_diff( $active_plugins, $integration_plugins ) );
	}

	/**
	 * Remove integration plugins from network active plugins when updating to prevent database writes
	 *
	 * @param array $active_plugins
	 * @return array
	 */
	public function filter_update_network_active_plugins( $active_plugins ): array {
		$active_plugins      = is_array( $active_plugins ) ? $active_plugins : array();
		$integration_plugins = self::get_loaded_integration_plugins();

		return array_diff_key( $active_plugins, array_flip( $integration_plugins ) );
	}

	/**
	 * Customize plugin action links for integration plugins
	 *
	 * @param array $actions
	 * @param string $plugin_file
	 * @return array
	 */
	public function filter_plugin_action_links( $actions, $plugin_file ): array {
		$integration_plugins = self::get_loaded_integration_plugins();

		if ( in_array( $plugin_file, $integration_plugins, true ) ) {
			// Remove activate/deactivate links
			unset( $actions['activate'], $actions['deactivate'], $actions['network_active'] );

			// Add custom status message using the same class as code-activated plugins
			// This will trigger the existing JS to hide the checkbox
			$actions['vip-code-activated-plugin'] = __( 'Enabled via VIP Integrations', 'vip-integrations' );
		}

		return $actions;
	}

	/**
	 * Check if we're on a specific admin screen
	 *
	 * @param string $screen_id
	 * @return bool
	 */
	private function is_admin_screen( string $screen_id ): bool {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		return $screen && $screen->id === $screen_id;
	}

	/**
	 * Check if we're on the plugins screen
	 *
	 * @return bool
	 */
	private function is_plugins_screen(): bool {
		return $this->is_admin_screen( 'plugins' );
	}

	/**
	 * Check if we're on the network plugins screen
	 *
	 * @return bool
	 */
	private function is_network_plugins_screen(): bool {
		return $this->is_admin_screen( 'plugins-network' );
	}

	/**
	 * Clean up recently activated list to prevent validation errors
	 *
	 * This prevents WordPress from showing "Plugin file does not exist" errors
	 * when it tries to validate integration plugins that aren't in the standard plugins directory.
	 */
	public function cleanup_recently_activated(): void {
		$integration_plugins = self::get_loaded_integration_plugins();

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
	 * Flag to track if output buffer has been started
	 *
	 * @var bool
	 */
	private static $buffer_started = false;

	/**
	 * Suppress deactivation error notices for integration plugins
	 *
	 * This prevents WordPress from showing "Plugin file does not exist" errors
	 * when it tries to validate integration plugins that aren't in the standard plugins directory.
	 */
	public function suppress_deactivation_notices(): void {
		$integration_plugins = self::get_loaded_integration_plugins();

		if ( empty( $integration_plugins ) || self::$buffer_started ) {
			return;
		}

		self::$buffer_started = true;

		// Use output buffering to filter out error notices about integration plugins
		ob_start( function ( $buffer ) use ( $integration_plugins ) {
			foreach ( $integration_plugins as $plugin ) {
				// Remove error notices for "Plugin file does not exist"
				// Use more specific pattern with \b for word boundaries
				$escaped_plugin = preg_quote( $plugin, '/' );
				$pattern        = '/<div[^>]*\berror\b[^>]*>.*?<code>' . $escaped_plugin . '<\/code>.*?Plugin file does not exist\..*?<\/div>/s';
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
