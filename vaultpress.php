<?php
/*
 * Plugin Name: VaultPress
 * Plugin URI: https://vaultpress.com/?utm_source=plugin-uri&amp;utm_medium=plugin-description&amp;utm_campaign=1.0
 * Description: Protect your content, themes, plugins, and settings with <strong>realtime backup</strong> and <strong>automated security scanning</strong> from <a href="http://vaultpress.com/?utm_source=wp-admin&amp;utm_medium=plugin-description&amp;utm_campaign=1.0" rel="nofollow">VaultPress</a>.
 * Version: 1.9.2
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

require_once( __DIR__ . '/vaultpress/vaultpress.php' );
