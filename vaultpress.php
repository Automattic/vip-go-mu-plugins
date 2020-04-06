<?php
/*
 * Plugin Name: VaultPress
 * Plugin URI: https://vaultpress.com/?utm_source=plugin-uri&amp;utm_medium=plugin-description&amp;utm_campaign=1.0
 * Description: Protect your content, themes, plugins, and settings with <strong>realtime backup</strong> and <strong>automated security scanning</strong> from <a href="http://vaultpress.com/?utm_source=wp-admin&amp;utm_medium=plugin-description&amp;utm_campaign=1.0" rel="nofollow">VaultPress</a>.
 * Version: 2.1.1
 * Author: Automattic
 * Author URI: https://vaultpress.com/?utm_source=author-uri&amp;utm_medium=plugin-description&amp;utm_campaign=1.0
 * License: GPL2+
 * Text Domain: vaultpress
 * Domain Path: /languages/
 */

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

add_filter( 'pre_scan_file', function( $should_skip_file, $file, $real_file, $file_content ) {
	foreach ( VIP_VAULTPRESS_SKIP_FILES as $vp_skip_file ) {
		if ( wp_endswith( $file, $vp_skip_file ) ) {
			return true;
		}
	}

	return $should_skip_file;
}, 10, 4 );

require_once( __DIR__ . '/vaultpress/vaultpress.php' );
