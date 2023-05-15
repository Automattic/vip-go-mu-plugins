<?php

/**
 * Plugin Name: VIP Integrations
 * Description: Plugin loading for VIP plugin integrations.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Automattic\VIP\Integrations;

/**
 * Enum which represents all the integration plugins available
 */
abstract class Integration {
	const BLOCK_DATA_API = 'block-data-api';
	// In the future, we can add wp-parsely and other integrations here.
}

/**
 * Load the integration based on the name provided.
 *
 * @param string $integration_name The name of the integration to load.
 * @param array $integration_config The configuration for the integration, if supported.
 */
function load_integration( $integration_name, $integration_config = array() ) {
	// This is temporary until more integrations are added.
	if ( Integration::BLOCK_DATA_API !== $integration_name ) {
		return;
	}

	// Use the plugins_loaded filter to ensure customer code version of the plugin overrides the mu-plugins version.
	add_action( 'plugins_loaded', function() {
		// New integration requires go here

		// Block Data API defines this when it is loaded, so it's a guard against loading twice.
		if ( ! defined( 'VIP_BLOCK_DATA_API_LOADED' ) ) {
			$load_path = __DIR__ . '/vip-integrations/vip-block-data-api-1.0.0/vip-block-data-api.php';
			if ( file_exists( $load_path ) ) {
				require_once $load_path;
			}
		}
	}, 1 );
}
