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
		$plugin_data     = get_plugin_data( $plugin_path, false, false ); // Do not apply markup/translate as it'll be cached.

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

/**
 * This callback is hooked on `plugins_url` enables, allows us call `plugins_url` with a client-mu-plugins path (or inside any file in that path).
 *
 * e.g. plugins_url( 'file.js', WPCOM_VIP_CLIENT_MU_PLUGIN_DIR . '/plugin/file.php' );
 */
function wpcom_vip_filter_client_mu_plugins_url( $url, $url_path, $plugin_path ) {
	static $mu_plugins_dir, $mu_plugins_url;

	if ( ! isset( $mu_plugins_dir ) ) {
		$mu_plugins_dir = WPCOM_VIP_CLIENT_MU_PLUGIN_DIR;
	}

	if ( ! isset( $mu_plugins_url ) ) {
		$mu_plugins_basename = basename( WPCOM_VIP_CLIENT_MU_PLUGIN_DIR );
		$mu_plugins_url      = content_url( $mu_plugins_basename );
	}

	// Only override client-mu-plugins paths
	if ( 0 === strpos( $plugin_path, $mu_plugins_dir ) ) {
		// Let's get the root of the plugin's path and replace the root bits of the path with the URL
		// E.g. starting with a plugin path (`/var/www/wp-content/client-mu-plugins/plugin/file.php`), replace the root path (`/var/www/wp-content/client-mu-plugins`) with the URL (https://example.com/wp-content/client-mu-plugins).
		$plugin_dirname  = dirname( $plugin_path );
		$plugin_url_base = str_replace( $mu_plugins_dir, $mu_plugins_url, $plugin_dirname );

		$url = trailingslashit( $plugin_url_base ) . ltrim( $url_path, '/\\' );
	}

	return $url;
}
add_filter( 'plugins_url', 'wpcom_vip_filter_client_mu_plugins_url', 10, 3 );

do_action( 'vip_mu_plugins_loaded' );

if ( wpcom_vip_should_load_plugins() ) {
	// Let's load the plugins
	foreach ( wpcom_vip_get_client_mu_plugins() as $client_mu_plugin ) {
		include_once $client_mu_plugin;
	}
	unset( $client_mu_plugin );
}
