<?php

namespace Automattic\VIP\Migration;

use Automattic\VIP\Jetpack\Connection_Pilot;

add_action( 'vip_after_data_migration', 'Automattic\VIP\Migration\after_data_migration' );

function after_data_migration() {
	if ( is_multisite() ) {
		$sites = get_sites();

		// Update schema for global tables
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . '/wp-admin/includes/upgrade.php';
		}
		dbDelta( 'global' );

		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );

			run_after_data_migration_cleanup();

			restore_current_blog();
		}

		return true;
	} else {
		run_after_data_migration_cleanup();
		return false;
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

	if ( ! defined( 'VIP_JETPACK_SKIP_LOAD' ) || ! VIP_JETPACK_SKIP_LOAD ) {
		$connection_pilot = Connection_Pilot::instance();
		$connection_pilot->run_connection_pilot();
	}
}

function delete_db_transients() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return $wpdb->query(
		"DELETE FROM $wpdb->options
		WHERE option_name LIKE '\_transient\_%'
		OR option_name LIKE '\_site\_transient\_%'"
	);
}
