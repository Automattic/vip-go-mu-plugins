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
	 * Loads the plugin.
	 *
	 * This is called after the integration is activated and configured.
	 *
	 * @private
	 */
	public function load(): void {
		/**
		 * Return if the integration is already loaded.
		 *
		 * In activate() method we do make sure to not activate the integration if its already loaded
		 * but still adding it here as a safety measure i.e. if load() is called directly.
		 */
		if ( $this->is_loaded() ) {
			return;
		}

		/**
		 * Load the custom build of Gutenberg from vip-integrations.
		 *
		 * Built from - https://github.com/Automattic/gutenberg/tree/add/experimental-collaborative-editing
		 * This has the code required to support collaborative editing.
		 */
		$gutenberg_path = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/gutenberg/gutenberg.php';
		if ( file_exists( $gutenberg_path ) ) {
			require_once $gutenberg_path;
		}

		/**
		 * Get all the entries in the path of WPVIP_MU_PLUGIN_DIR/vip-integrations/vip-real-time-collaboration-<version>/
		 * and check what versions are available.
		 */
		$versions = $this->get_versions();

		// if no versions are found, return early.
		if ( empty( $versions ) ) {
			$this->is_active = false;
			return;
		}

		// Load the latest version of the plugin.
		$latest_directory = array_key_first( $versions );
		$load_path        = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/' . $latest_directory . '/vip-real-time-collaboration.php';

		// This check isn't strictly necessary, but better safe than sorry.
		if ( file_exists( $load_path ) ) {
			require_once $load_path;
		} else {
			$this->is_active = false;
		}
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
	 *
	 * @private
	 */
	public function configure(): void {
		$env_config = $this->get_env_config();

		// Set up WebSocket authentication secret constant
		if ( isset( $env_config['web_socket_auth_secret'] ) && ! defined( 'VIP_RTC_WS_AUTH_SECRET' ) ) {
			define( 'VIP_RTC_WS_AUTH_SECRET', $env_config['web_socket_auth_secret'] );
		}

		// Set up WebSocket URL constant with dummy value
		if ( isset( $env_config['web_socket_url'] ) && ! defined( 'VIP_RTC_WS_URL' ) ) {
			define( 'VIP_RTC_WS_URL', $env_config['web_socket_url'] );
		}
	}
}
