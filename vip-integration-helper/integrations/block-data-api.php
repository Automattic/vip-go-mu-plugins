<?php

namespace Automattic\VIP\Integrations;

/**
 * Integrates VIP Block Data REST API.
 */
class BlockDataApi extends Integration {
	/**
	 * Applies hooks to integrate Block Data API plugin.
	 */
	public function integrate( array $config ): void {
		add_action( 'plugins_loaded', function() {
			// Do not load plugin if already loaded by customer code
			if ( defined( 'VIP_BLOCK_DATA_API_LOADED' ) ) {
				return;
			}

			$load_path = __DIR__ . '/../../vip-integrations/vip-block-data-api-1.0.0/vip-block-data-api.php';
			if ( file_exists( $load_path ) ) {
				require_once $load_path;
			}
		} );
	}
}
