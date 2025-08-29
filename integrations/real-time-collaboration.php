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
	}
}
