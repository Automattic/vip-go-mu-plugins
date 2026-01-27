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
	/**
	 * Version of the vip-real-time-collaboration plugin to load.
	 * Used to control staged rollouts (e.g., staging gets new version first).
	 */
	const VIP_RTC_PLUGIN_VERSION = '0.2';

	/**
	 * Version of the Gutenberg plugin to load.
	 * Empty string means load from the unversioned 'gutenberg' folder.
	 * A version number (e.g., '1.0') loads from 'gutenberg-1.0' folder.
	 */
	const VIP_RTC_GUTENBERG_VERSION = '22.4.1-f48ad9c';

	/**
	 * Enable Pendo tracking for this integration.
	 *
	 * @var bool
	 */
	protected bool $enable_pendo_tracking = true;

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

	/**
	 * Get the path to the Gutenberg plugin.
	 *
	 * @return string|false The path to the Gutenberg plugin, or false if not found.
	 */
	private function get_gutenberg_path(): string|false {
		// Empty string means use the unversioned folder
		if ( defined( 'VIP_RTC_GUTENBERG_VERSION' ) && '' === constant( 'VIP_RTC_GUTENBERG_VERSION' ) ) {
			$gutenberg_folder = 'gutenberg';
		} elseif ( defined( 'VIP_RTC_GUTENBERG_VERSION' ) && '' !== constant( 'VIP_RTC_GUTENBERG_VERSION' ) ) {
			$gutenberg_folder = 'gutenberg-' . constant( 'VIP_RTC_GUTENBERG_VERSION' );
		} else {
			return false;
		}

		$gutenberg_path = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/' . $gutenberg_folder . '/gutenberg.php';
		if ( ! file_exists( $gutenberg_path ) ) {
			return false;
		}

		return $gutenberg_path;
	}

	/**
	 * Get the path to the RTC plugin.
	 *
	 * @return string|false The path to the RTC plugin, or false if not found.
	 */
	private function get_plugin_path(): string|false {
		if ( defined( 'VIP_RTC_PLUGIN_VERSION' ) ) {
			$plugin_directory = 'vip-real-time-collaboration-' . constant( 'VIP_RTC_PLUGIN_VERSION' );
		} else {
			return false;
		}

		$load_path = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/' . $plugin_directory . '/vip-real-time-collaboration.php';
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
		add_action('plugins_loaded', function () {
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

			/**
			 * Load the custom build of Gutenberg from vip-integrations
			 * and the configured version of the vip-real-time-collaboration plugin.
			 */
			require_once $gutenberg_path;
			require_once $load_path;
		}, 1);
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
	}
}
