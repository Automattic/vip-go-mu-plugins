<?php

/**
 * Plugin Name: VIP Integrations
 * Description: Plugin loading for VIP plugin integrations.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Automattic\VIP\Integrations;

// New integrations names go here
const BLOCK_DATA_API_SLUG = 'block-data-api';

/**
 * Load the integration based on the slug provided.
 *
 * @param string $slug The slug of the integration to load.
 * @param array $config The configuration for the integration, if supported.
 */
function load_integration( $slug, $config = array() ) {
	if ( BLOCK_DATA_API_SLUG !== $slug ) {
		return;
	}

	// New integration paths go here
	$block_data_api_path = '/vip-integrations/vip-block-data-api-1.0.0/vip-block-data-api.php';

	// Use the plugins_loaded filter to ensure customer code version of the plugin overrides the mu-plugins version.
	add_action( 'plugins_loaded', function() {
		// New integration requires go here

		// Block Data API defines this when it is loaded, so it's a guard against loading twice.
		if ( ! defined( 'VIP_BLOCK_DATA_API_LOADED' ) ) {
			require_once __DIR__ . $block_data_api_path;
		}
	}, 1 );
}
