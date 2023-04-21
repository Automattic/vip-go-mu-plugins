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

const VIP_BLOCK_API_SUPPORTED_VERSIONS = [
	'0.2.0',
	'0.1.2',
];

const VIP_BLOCK_API_LATEST_VERSION = VIP_BLOCK_API_SUPPORTED_VERSIONS[0];

class PluginLoadStrategy {
	const PREFER_MU_PLUGINS_VERSION    = 'PREFER_MU_PLUGINS_VERSION';
	const PREFER_CUSTOMER_CODE_VERSION = 'PREFER_CUSTOMER_CODE_VERSION';
}

$plugin_load_strategy = PluginLoadStrategy::PREFER_CUSTOMER_CODE_VERSION;

function load_block_data_api() {
	$plugin_version = VIP_BLOCK_API_LATEST_VERSION;

	if ( defined( 'VIP_BLOCK_DATA_API_VERSION' ) && in_array( constant( 'VIP_BLOCK_DATA_API_VERSION' ), VIP_BLOCK_API_SUPPORTED_VERSIONS ) ) {
		$plugin_version = constant( 'VIP_BLOCK_DATA_API_VERSION' );
	}

	require __DIR__ . '/vip-integrations/vip-block-data-api-' . $plugin_version . '/vip-block-data-api.php';
}

if ( PluginLoadStrategy::PREFER_MU_PLUGINS_VERSION === $plugin_load_strategy ) {
	load_block_data_api();
} elseif ( PluginLoadStrategy::PREFER_CUSTOMER_CODE_VERSION === $plugin_load_strategy ) {
	add_action( 'plugins_loaded', function() {
		if ( ! defined( 'VIP_BLOCK_DATA_API_LOADED' ) ) {
			load_block_data_api();
		}
	}, 1 );
}
