<?php
/*
 * Plugin Name: VaultPress
 * Plugin URI: https://vaultpress.com/?utm_source=plugin-uri&amp;utm_medium=plugin-description&amp;utm_campaign=1.0
 * Description: Protect your content, themes, plugins, and settings with <strong>realtime backup</strong> and <strong>automated security scanning</strong> from <a href="http://vaultpress.com/?utm_source=wp-admin&amp;utm_medium=plugin-description&amp;utm_campaign=1.0" rel="nofollow">VaultPress</a>.
 * Version: 1.9.10
 * Author: Automattic
 * Author URI: https://vaultpress.com/?utm_source=author-uri&amp;utm_medium=plugin-description&amp;utm_campaign=1.0
 * License: GPL2+
 * Text Domain: vaultpress
 * Domain Path: /languages/
 */

// VaultPress uses a default timeout of 60s, which can be bad in the rare cases where its API is slow to respond.
// Drop it down to something a bit more reasonable.
if ( ! defined( 'VAULTPRESS_TIMEOUT' ) ) {
	define( 'VAULTPRESS_TIMEOUT', 10 );
}

$vaultpress_to_load = WPMU_PLUGIN_DIR . '/vaultpress/vaultpress.php';
if ( defined( 'WPCOM_VIP_VAULTPRESS_LOCAL' ) && WPCOM_VIP_VAULTPRESS_LOCAL ) {
	// Set a specific alternative VaultPress
	$vaultpress_to_test = WPCOM_VIP_CLIENT_MU_PLUGIN_DIR . '/vaultpress/vaultpress.php';
	// Test that our proposed VaultPress exists, otherwise do not use it
	if ( file_exists( $vaultpress_to_test ) ) {
		$vaultpress_to_load = $vaultpress_to_test;
	}
}

require_once( $vaultpress_to_load );
