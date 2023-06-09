<?php

namespace Automattic\VIP\Integrations;

/**
 * Loads VIP Block Data REST API.
 *
 * @private
 */
class BlockDataApi extends Integration {
	/**
	 * Applies hooks to load Block Data API plugin.
	 *
	 * @private
	 */
	public function load( array $config ): void {
		// Wait until plugins_loaded to give precedence to the plugin in the customer repo
		add_action( 'plugins_loaded', function() {
			// Do not load plugin if already loaded by customer code
			if ( defined( 'VIP_BLOCK_DATA_API_LOADED' ) ) {
				return;
			}

			$load_path = WPMU_PLUGIN_DIR . '/vip-integrations/vip-block-data-api-1.0.1/vip-block-data-api.php';
			if ( file_exists( $load_path ) ) {
				require_once $load_path;
			}
		} );
	}
}
