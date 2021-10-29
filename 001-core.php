<?php

/**
 * Plugin Name: VIP Go Core Modifications
 * Description: Changes to make WordPress core work better on VIP Go.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

require_once __DIR__ . '/001-core/privacy.php';

/**
 * Disable current theme validation
 *
 * By default, WordPress falls back to a default theme if it can't find
 * the active theme. This is undesirable because it requires manually
 * re-activating the correct theme and can lead to data loss in the form
 * of deactivated widgets and menu location assignments.
 */
add_filter( 'validate_current_theme', '__return_false' );

if ( false !== WPCOM_IS_VIP_ENV ) {
	add_action( 'muplugins_loaded', 'wpcom_vip_init_core_restrictions' );
}

function wpcom_vip_init_core_restrictions() {
	add_action( 'admin_init', 'wpcom_vip_disable_core_update_nag' );
	add_filter( 'map_meta_cap', 'wpcom_vip_disable_core_update_cap', 100, 2 );
}

function wpcom_vip_disable_core_update_nag() {
	remove_action( 'admin_notices', 'update_nag', 3 );
	remove_action( 'network_admin_notices', 'update_nag', 3 );
}

function wpcom_vip_disable_core_update_cap( $caps, $cap ) {
	if ( 'update_core' === $cap ) {
		$caps = [ 'do_not_allow' ];
	}
	return $caps;
}

/**
 * Disable tests in the Site Health (AKA site status) tool page
 *
 * By default, WordPress runs a series of tests on the Site Health tool
 * page in wp-admin. This disables all irrelevant or unnecessary tests.
 */
function vip_disable_unnecessary_site_health_tests( $tests ) {
	// Disable "Background Updates" test.
	// WordPress updates are managed by the VIP team.
	if ( isset( $tests['async'] ) && isset( $tests['async']['background_updates'] ) ) {
		unset( $tests['async']['background_updates'] );
	}

	return $tests;
}

add_filter( 'site_status_tests', 'vip_disable_unnecessary_site_health_tests' );

/**
 * Filter PHP modules in the Site Health (AKA site status) tool page
 *
 * By default, WordPress runs php_extension tests on the Site Health tool
 * page in wp-admin. This filters out all irrelevant or unnecessary PHP modules
 * within the test.
 */
function vip_filter_unnecessary_php_modules_for_site_health_tests( $modules ) {
	// Remove 'exif' PHP module.
	if ( isset( $modules['exif'] ) ) {
		unset( $modules['exif'] );
	}

	// Remove 'imagick' PHP module.
	if ( isset( $modules['imagick'] ) ) {
		unset( $modules['imagick'] );
	}

	return $modules;
}

add_filter( 'site_status_test_php_modules', 'vip_filter_unnecessary_php_modules_for_site_health_tests' );

/*
 * WordPress Core has a bug in the use_block_editor_for_post filter.
 * Here we temporarily patch this bug, the patch can be removed once
 * the bug has been fixed in Core.
 *
 * This bug can lead to Gutenberg Editor being loaded for posts of
 * types that do not support the editor, resulting in a JavaScript error
 * and a white screen. This will happen when using the filter in the
 * following way:
 *
 * add_filter( 'use_block_editor_for_post', '__return_true' );
 *
 * Here we add a check to the filter so that this is averted.
 *
 * Note that use_block_editor_for_post_type filter does not have this problem
 * and that the function that calls the filter does a similar check.
 *
 * WordPress Core Trac ticket with ways to replicate and a proposed patch
 * can be found here: https://core.trac.wordpress.org/ticket/52363
 */

add_filter(
	'use_block_editor_for_post',
	function( $can_edit, $post ) {
		if ( ! isset( $post->post_type ) ) {
			return $can_edit;
		}

		if ( ! post_type_supports( $post->post_type, 'editor' ) ) {
			return false;
		}

		return $can_edit;
	},
	20,
	2
);

/**
 * Prevent invalid query args from causing php errors & a limitless query.
 *
 * This patch can be removed when the following core ticket is resolved:
 * @see https://core.trac.wordpress.org/ticket/17737
 */
function vip_prevent_invalid_core_query_args() {
	if ( is_admin() ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['name'] ) && ! is_string( $_GET['name'] ) ) {
		unset( $_GET['name'] );
	}

	if ( isset( $_GET['pagename'] ) && ! is_string( $_GET['pagename'] ) ) {
		unset( $_GET['pagename'] );
	}
	// phpcs:enable
}

add_action( 'wp_loaded', 'vip_prevent_invalid_core_query_args', 1 );
