<?php

/**
 * Plugin Name: VIP Client mu-plugins
 * Description: Helper plugin to load custom mu-plugins in the `client-mu-plugins` dir.
 * Author: Automattic
 */

// Note: This file is prefixed with `z-` for load order

/**
 * Gets PHP files in the client-mu-plugins folder.
 *
 * The code for this function is adapted from `wp_get_mu_plugins()`
 */
function wpcom_vip_get_client_mu_plugins( $directory = WPCOM_VIP_CLIENT_MU_PLUGIN_DIR ) {
	$directory = untrailingslashit( $directory );

	$client_mu_plugins = [];

	if ( ! is_dir( $directory ) ) {
		return $client_mu_plugins;
	}

	$dir_handle = opendir( $directory );
	if ( ! $dir_handle ) {
		return $client_mu_plugins;
	}

	do {
		$plugin = readdir( $dir_handle );
		if ( false === $plugin ) {
			break;
		}

		$is_php_file = substr( $plugin, -4 ) === '.php';
		if ( $is_php_file ) {
			$client_mu_plugins[] = $directory . '/' . $plugin;
		}
	} while ( false !== $plugin );

	closedir( $dir_handle );

	// Make sure plugins load in a consistent, predictable order
	sort( $client_mu_plugins );

	return $client_mu_plugins;
}

function wpcom_vip_get_client_mu_plugins_data( $directory = WPCOM_VIP_CLIENT_MU_PLUGIN_DIR ) {
	$client_mu_plugins_files = wpcom_vip_get_client_mu_plugins( $directory );

	if ( empty( $client_mu_plugins_files ) ) {
		return [];
	}

	$client_mu_plugins_data = [];

	foreach ( $client_mu_plugins_files as $plugin_path ) {
		if ( ! is_readable( $plugin_path ) ) {
			continue;
		}

		$plugin_filename = basename( $plugin_path );
		$plugin_data = get_plugin_data( $plugin_path, false, false ); // Do not apply markup/translate as it'll be cached.

		if ( empty( $plugin_data['Name'] ) ) {
			$plugin_data['Name'] = $plugin_filename;
		}

		$client_mu_plugins_data[ $plugin_filename ] = $plugin_data;
	}

	// Don't include "// silence is golden" index file
	if ( isset( $client_mu_plugins_data['index.php'] ) && filesize( $directory . '/index.php' ) <= 30 ) {
		unset( $client_mu_plugins_data['index.php'] );
	}

	uasort( $client_mu_plugins_data, '_sort_uname_callback' );

	return $client_mu_plugins_data;
}

// Let's load the plugins
foreach ( wpcom_vip_get_client_mu_plugins() as $client_mu_plugin ) {
	include_once( $client_mu_plugin );
}
unset( $client_mu_plugin );
