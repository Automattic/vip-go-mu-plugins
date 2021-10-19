<?php
/**
 * Plugin Name: VaultPress
 * Plugin URI: http://vaultpress.com/?utm_source=plugin-uri&amp;utm_medium=plugin-description&amp;utm_campaign=1.0
 * Description: Protect your content, themes, plugins, and settings with <strong>realtime backup</strong> and <strong>automated security scanning</strong> from <a href="http://vaultpress.com/?utm_source=wp-admin&amp;utm_medium=plugin-description&amp;utm_campaign=1.0" rel="nofollow">VaultPress</a>. Activate, enter your registration key, and never worry again. <a href="http://vaultpress.com/help/?utm_source=wp-admin&amp;utm_medium=plugin-description&amp;utm_campaign=1.0" rel="nofollow">Need some help?</a>
 * Version: 2.2.0
 * Author: Automattic
 * Author URI: http://vaultpress.com/?utm_source=author-uri&amp;utm_medium=plugin-description&amp;utm_campaign=1.0
 * License: GPL2+
 * Text Domain: vaultpress
 * Domain Path: /languages/
 *
 * @package automattic/vaultpress
 */

// Avoid loading VaultPress altogether if VIP_JETPACK_SKIP_LOAD is set to true (Jetpack is required for VP to work in VIP)
if ( defined( 'VIP_JETPACK_SKIP_LOAD' ) && true === VIP_JETPACK_SKIP_LOAD ) {
	return;
}

// Avoid loading VaultPress altogether if VIP_VAULTPRESS_SKIP_LOAD is set to true
if ( defined( 'VIP_VAULTPRESS_SKIP_LOAD' ) && true === VIP_VAULTPRESS_SKIP_LOAD ) {
	return;
}

define( 'VIP_VAULTPRESS_SKIP_FILES', [
	// The following EP files are large and complex and consistently trigger 100% CPU usage on scan.
	'/elasticpress/dist/js/ordering-script.min.js',
	'/elasticpress/dist/js/related-posts-block-script.min.js',
	'/elasticpress/dist/js/stats-script.min.js',
] );

// VaultPress uses a default timeout of 60s, which can be bad in the rare cases where its API is slow to respond.
// Drop it down to something a bit more reasonable.
if ( ! defined( 'VAULTPRESS_TIMEOUT' ) ) {
	define( 'VAULTPRESS_TIMEOUT', 10 );
}

add_filter( 'pre_scan_file', function( $should_skip_file, $file ) {
	foreach ( VIP_VAULTPRESS_SKIP_FILES as $vp_skip_file ) {
		if ( wp_endswith( $file, $vp_skip_file ) ) {
			return true;
		}
	}

	return $should_skip_file;
}, 10, 2 );

require_once __DIR__ . '/vaultpress/vaultpress.php';

add_filter( 'in_admin_header', 'vip_remove_vaultpress_connect_notice' );

function vip_remove_vaultpress_connect_notice() {
	// Not actually initializing VP, just getting the instance
	$vaultpress = VaultPress::init();
	remove_action( 'user_admin_notices', [ $vaultpress, 'connect_notice' ] );
	remove_action( 'vaultpress_notices', [ $vaultpress, 'connect_notice' ] );
	remove_action( 'admin_notices', [ $vaultpress, 'connect_notice' ] );
	return null;
}

add_action( 'admin_menu', 'vip_remove_vaultpress_admin_menu', 999 );

function vip_remove_vaultpress_admin_menu() {
	$vaultpress = VaultPress::init();
	if ( ! $vaultpress->is_registered() ) {
		remove_submenu_page( 'jetpack', 'vaultpress' );
	}
}
