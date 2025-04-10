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
	 * The version of the Block Data API plugin to load, that's set to the latest version.
	 * This should be higher than the lowestVersion set in "vip-block-data-api" config (https://github.com/Automattic/vip-go-mu-plugins-ext/blob/trunk/config.json)
	 *
	 * @var string
	 */
	protected string $version = '1.4';

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

			// Load the version of the plugin that should be set to the latest version, otherwise if it's not found deactivate the integration.
			$load_path = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/vip-block-data-api-' . $this->version . '/vip-block-data-api.php';
			if ( file_exists( $load_path ) ) {
				require_once $load_path;
			} else {
				$this->is_active = false;
			}
		} );
	}
}
