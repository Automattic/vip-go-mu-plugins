<?php

/**
 * Rewrite Rules Inspector
 *
 * @package      automattic\rewrite-rules-inspector
 * @author       Automattic, Daniel Bachhuber
 * @copyright    2012 Automattic
 * @license      GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Rewrite Rules Inspector
 * Plugin URI:        https://wordpress.org/plugins/rewrite-rules-inspector/
 * Description:       Simple WordPress admin tool for inspecting your rewrite rules.
 * Version:           1.5.1
 * Author:            Automattic, Daniel Bachhuber
 * Author URI:        https://automattic.com/
 * Text Domain:       rewrite-rules-inspector
 * License:           GPL-2.0-or-later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * GitHub Plugin URI: https://github.com/Automattic/Rewrite-Rules-Inspector
 * Requires PHP:      7.4
 * Requires WP:       5.9.0
 */

require_once __DIR__ . '/rewrite-rules-inspector/rewrite-rules-inspector.php';

/**
 * Place the rewrite rules view under the VIP dashboard instead of under tools.php
 */
add_filter( 'rri_parent_slug', function () {
	return 'vip-dashboard';
} );

/**
 * When a VIP switches their theme, make a request to flush and reload their rules
 * It's less than ideal to do a remote request, but all of the new theme's code
 * won't be loaded on this request
 */
add_action( 'switch_theme', 'rri_wpcom_action_switch_theme' );
function rri_wpcom_action_switch_theme() {
	// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules
	flush_rewrite_rules();
}

/**
 * Only allow the site to flush rules if the theme is whitelisted
 */
add_filter( 'rri_flushing_enabled', '__return_true' );
