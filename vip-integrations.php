<?php

/**
 * Plugin Name: VIP Integrations
 * Description: Plugin loading for VIP plugin integrations.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Automattic\VIP\Integrations;

if ( ! ( defined( 'VIP_BLOCK_DATA_API_ENABLE' ) && true === constant( 'VIP_BLOCK_DATA_API_ENABLE' ) ) ) {
	// Do not automatically activate a plugin unless specified.
	return;
}

const VIP_BLOCK_API_LATEST_VERSION = '1.0.0';

// Use the plugins_loaded filter to a ensure customer code version of the plugin overrides the mu-plugins version.
add_action( 'plugins_loaded', function() {
	if ( ! defined( 'VIP_BLOCK_DATA_API_LOADED' ) ) {
		require_once __DIR__ . '/vip-integrations/vip-block-data-api-' . VIP_BLOCK_API_LATEST_VERSION . '/vip-block-data-api.php';
	}
}, 1 );
