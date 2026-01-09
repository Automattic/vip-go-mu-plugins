<?php
/**
 * Integration: Block Data API.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

/**
 * Loads VIP Block Data REST API.
 *
 * @private
 */
class BlockDataApiIntegration extends Integration {

	/**
	 * Returns `true` if `Block Data API` is already available e.g. via customer code. We will use
	 * this function to prevent activating of integration from platform side.
	 */
	public function is_loaded(): bool {
		return defined( 'VIP_BLOCK_DATA_API_LOADED' );
	}

	/**
	 * Applies hooks to load Block Data API plugin.
	 *
	 * @private
	 */
	public function load(): void {
		// Wait until plugins_loaded to give precedence to the plugin in the customer repo.
		add_action( 'plugins_loaded', function () {
			// Return if the integration is already loaded.
			//
			// In activate() method we do make sure to not activate the integration if its already loaded
			// but still adding it here as a safety measure i.e. if load() is called directly.
			if ( $this->is_loaded() ) {
				return;
			}

			// Load the latest version of the plugin.
			$latest_directory = $this->get_latest_version();

			if ( empty( $latest_directory ) ) {
				$this->is_active = false;
				return;
			}

			// Load the plugin.
			$load_path = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/' . $latest_directory . '/vip-block-data-api.php';

			// This check isn't strictly necessary, but better safe than sorry.
			if ( file_exists( $load_path ) ) {
				require_once $load_path;
			} else {
				$this->is_active = false;
			}
		} );
	}

	/**
	 * Get the latest version of Block Data API.
	 *
	 * @return string|null The latest version of Block Data API or null if no versions are found.
	 */
	public function get_latest_version() {
		return get_latest_version( WPVIP_MU_PLUGIN_DIR . '/vip-integrations/', 'vip-block-data-api', 'vip-block-data-api.php' );
	}
}
