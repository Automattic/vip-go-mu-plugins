<?php

namespace Automattic\VIP\Migration;

add_action( 'vip_after_data_migration', 'Automattic\VIP\Migration\after_data_migration' );

function after_data_migration() {
	if ( is_multisite() ) {
		$sites = get_sites();

		// Update schema for global tables
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . '/wp-admin/includes/upgrade.php';
		}
		dbDelta( 'global' );

		foreach( $sites as $site ) {
			switch_to_blog( $site->blog_id );

			run_after_data_migration_cleanup();

			restore_current_blog();
		}

		return true;
	} else {
		return run_after_data_migration_cleanup();
	}
}

function run_after_data_migration_cleanup() {
	/**
	 * Fires on migration cleanup
	 *
	 * Migration cleanup runs on VIP Go during the initial site setup
	 * and after database imports. This hook can be used to add additional
	 * cleanup for a given site.
	 */
	do_action( 'vip_go_migration_cleanup' );

	delete_db_transients();

	// Update schema for blog tables
	if ( ! function_exists( 'dbDelta' ) ) {
		require_once ABSPATH . '/wp-admin/includes/upgrade.php';
	}
	dbDelta( 'blog' );

	wp_cache_flush();

	connect_jetpack();
	connect_vaultpress();
	connect_akismet();
}

function delete_db_transients() {
	global $wpdb;

	return $wpdb->query(
		"DELETE FROM $wpdb->options
		WHERE option_name LIKE '\_transient\_%'
		OR option_name LIKE '\_site\_transient\_%'"
	);
}

function connect_jetpack() {
	if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
		\WP_CLI::runcommand( sprintf( 'jetpack-start connect --url=%s', home_url() ) );
	} else {
		trigger_error( 'Cannot connect JP outside of a WP_CLI context, skipping', E_USER_WARNING );
	}
}

function connect_vaultpress() {
	if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'WP_CLI' ) ) {
		// Remove the VaultPress option from the db to prevent site registration from failing
		delete_option( 'vaultpress' );

		// Register VaultPress
		\WP_CLI::runcommand( sprintf( 'vaultpress register_via_jetpack --url=%s', home_url() ) );
	} else {
		trigger_error( 'Cannot connect VaultPress outside of a WP_CLI context, skipping', E_USER_WARNING );
	}
}

function connect_akismet() {
	$user = get_current_user_id();

	// Switch to wpcomvip -- Akismet connects the current user
	$wpcomvip = get_user_by( 'login', 'wpcomvip' );
	if ( ! $wpcomvip ) {
		trigger_error( sprintf( '%s: Failed to find wpcomvip user while attempting to connect Akismet; Akismet will need to be connected manually', __FUNCTION__ ), E_USER_WARNING );
	}

	wp_set_current_user( $wpcomvip->ID );

	if ( class_exists( 'Akismet_Admin' ) && method_exists( 'Akismet_Admin', 'connect_jetpack_user' ) ) {
		$connected = \Akismet_Admin::connect_jetpack_user();
		if ( ! $connected ) {
			trigger_error( sprintf( '%s: Failed to connect Akismet; Akismet will need to be connected manually', __FUNCTION__ ), E_USER_WARNING );
		}
	} else {
		trigger_error( sprintf( '%s: Failed to call `Akismet_Admin::connect_jetpack_user` as it does not exist; Akismet will need to be connected manually', __FUNCTION__ ), E_USER_WARNING );
	}

	// Switch back to current user
	wp_set_current_user( $user );
}
