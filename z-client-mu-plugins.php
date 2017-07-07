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

// Load the plugins
// TODO: move out of function scope to avoid issues with globals not being properly set
wpcom_vip_load_client_mu_plugins();

function wpcom_vip_get_client_mu_plugins() {
	$wp_plugins = array();
	$plugin_files = array();

	if ( ! is_dir( WPCOM_VIP_CLIENT_MU_PLUGIN_DIR ) ) {
		return $wp_plugins;
	}

	if ( $plugins_dir = @opendir( WPCOM_VIP_CLIENT_MU_PLUGIN_DIR ) ) {
		while ( ( $file = readdir( $plugins_dir ) ) !== false ) {
			if ( substr( $file, -4 ) === '.php' ) {
				$plugin_files[] = $file;
			}
		}
	} else {
		return $wp_plugins;
	}

	@closedir( $plugins_dir );

	if ( empty( $plugin_files ) ) {
		return $wp_plugins;
	}

	return $plugin_files;
}

function wpcom_vip_get_client_mu_plugins_data() {
	$plugin_files = wpcom_vip_get_client_mu_plugins();

	if ( empty( $plugin_files ) ) {
		return $plugin_files;
	}

	foreach ( $plugin_files as $plugin_file ) {
		if ( ! is_readable( WPCOM_VIP_CLIENT_MU_PLUGIN_DIR . "/$plugin_file" ) ) {
			continue;
		}

		$plugin_data = get_plugin_data( WPCOM_VIP_CLIENT_MU_PLUGIN_DIR . "/$plugin_file", false, false ); //Do not apply markup/translate as it'll be cached.

		if ( empty( $plugin_data['Name'] ) ) {
			$plugin_data['Name'] = $plugin_file;
		}

		$wp_plugins[ $plugin_file ] = $plugin_data;
	}

	if ( isset( $wp_plugins['index.php'] ) && filesize( WPCOM_VIP_CLIENT_MU_PLUGIN_DIR . '/index.php' ) <= 30 ) { // silence is golden
		unset( $wp_plugins['index.php'] );
	}

	uasort( $wp_plugins, '_sort_uname_callback' );

	return $wp_plugins;
}
