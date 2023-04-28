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

class PluginLoadStrategy {
	const PREFER_MU_PLUGINS_VERSION    = 'PREFER_MU_PLUGINS_VERSION';
	const PREFER_CUSTOMER_CODE_VERSION = 'PREFER_CUSTOMER_CODE_VERSION';
}

$plugin_load_strategy = PluginLoadStrategy::PREFER_CUSTOMER_CODE_VERSION;

function load_block_data_api() {
	if ( ! defined( 'VIP_BLOCK_DATA_API_LOADED' ) ) {
		require __DIR__ . '/vip-integrations/vip-block-data-api-' . VIP_BLOCK_API_LATEST_VERSION . '/vip-block-data-api.php';
	}
}

if ( PluginLoadStrategy::PREFER_MU_PLUGINS_VERSION === $plugin_load_strategy ) {
	load_block_data_api();
} elseif ( PluginLoadStrategy::PREFER_CUSTOMER_CODE_VERSION === $plugin_load_strategy ) {
	add_action( 'plugins_loaded', function() {
		load_block_data_api();
	}, 1 );
}
