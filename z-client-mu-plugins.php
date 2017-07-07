<?php

// Note: This file is prefixed with `z-` for load order

function wpcom_vip_load_client_mu_plugins() {
	static $loaded = false;

	// Prevent running this multiple times
	if ( $loaded ) {
		return;
	}

	$loaded = true;

	// Code below is adapted from wp_get_mu_plugins()
	$client_mu_plugins = [];

	if ( ! is_dir( WPCOM_VIP_CLIENT_MU_PLUGIN_DIR ) ) {
		return;
	}

	$dh = opendir( WPCOM_VIP_CLIENT_MU_PLUGIN_DIR );
	if ( ! $dh ) {
		return;
	}

	do {
		$plugin = readdir( $dh );
		if ( false === $plugin ) {
			break;
		}

		if ( substr( $plugin, -4 ) === '.php' ) {
			$client_mu_plugins[] = WPCOM_VIP_CLIENT_MU_PLUGIN_DIR . '/' . $plugin;
		}
	} while ( false !== $plugin );

	closedir( $dh );

	// Make sure plugins load in a consistent, predictable order
	sort( $client_mu_plugins );

	foreach ( $client_mu_plugins as $plugin ) {
		include_once( $plugin );
	}
}

wpcom_vip_load_client_mu_plugins();
