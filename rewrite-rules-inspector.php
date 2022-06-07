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
 * Version:           1.3.1
 * Author:            Automattic, Daniel Bachhuber
 * Author URI:        https://automattic.com/
 * Text Domain:       rewrite-rules-inspector
 * License:           GPL-2.0-or-later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * GitHub Plugin URI: https://github.com/Automattic/Rewrite-Rules-Inspector
 * Requires PHP:      5.6
 * Requires WP:       3.1.0
 */

require_once __DIR__ . '/rewrite-rules-inspector/rewrite-rules-inspector.php';

/**
 * Place the rewrite rules view under the VIP dashboard instead of under tools.php
 */
add_filter( 'rri_parent_slug', function() {
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

/**
 * We can't use flush_rewrite_rules( false ) on wpcom because
 * on WPCOM it deletes the transient representation of rewrite_rules, not the option.
 * For now, we need to do some code replication.
 */
add_action( 'rri_flush_rules', 'rri_wpcom_flush_rules' );
function rri_wpcom_flush_rules() {
	global $wp_rewrite;

	/**
	 * VIPs and other themes can declare the permastruct, tag and category bases in their themes.
	 * This is done by filtering the option. To ensure we're getting the proper data, refresh.
	 *
	 * However, wpcom_vip_refresh_wp_rewrite() noops the values in the database so we only want to run it
	 * if the permastructs are defined in the theme (not for clients using the admin screen)
	 */
	if ( ( defined( 'WPCOM_VIP_CUSTOM_PERMALINKS' ) && WPCOM_VIP_CUSTOM_PERMALINKS )
		|| ( defined( 'WPCOM_VIP_CUSTOM_CATEGORY_BASE' ) && WPCOM_VIP_CUSTOM_CATEGORY_BASE )
		|| ( defined( 'WPCOM_VIP_CUSTOM_TAG_BASE' ) && WPCOM_VIP_CUSTOM_TAG_BASE ) ) {
		wpcom_vip_refresh_wp_rewrite();
	}

	/**
	 * We can't use flush_rewrite_rules( false ) in this context because
	 * on WPCOM it deletes the transient representation of rewrite_rules, not the option.
	 * For now, we need to do some code replication.
	 *
	 * See:
	 * - https://wpcom.trac.automattic.com/ticket/2589
	 * - https://wpcom.trac.automattic.com/browser/trunk/wp-includes/rewrite.php?rev=47524#L1928
	 * - https://wpcom.trac.automattic.com/browser/trunk/wp-includes/rewrite.php?rev=47524#L1596
	 */
	$wp_rewrite->matches = 'matches';
	$wp_rewrite->rewrite_rules();
	update_option( 'rewrite_rules', $wp_rewrite->rules );
}
