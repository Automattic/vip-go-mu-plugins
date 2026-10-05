<?php

namespace Automattic\VIP\Migration;

use Automattic\VIP\Jetpack\Connection_Pilot;

add_action( 'vip_after_data_migration', 'Automattic\VIP\Migration\after_data_migration' );

/**
 * Whether imported credentials must be removed from this local environment.
 */
function is_local_cleanup_environment(): bool {
	return ( defined( 'VIP_GO_APP_ENVIRONMENT' ) && 'local' === constant( 'VIP_GO_APP_ENVIRONMENT' ) ) || \is_local_env();
}

/**
 * Remove imported connection credentials without loading or contacting Jetpack.
 */
function cleanup_local_imported_credentials(): void {
	if ( ! is_local_cleanup_environment() ) {
		return;
	}

	foreach ( [ 'jetpack_options', 'jetpack_private_options', 'jetpack_secrets', 'vaultpress', 'wordpress_api_key', 'vip_jetpack_connection_pilot_heartbeat' ] as $name ) {
		delete_option( $name );
	}
}

/** Run hosted connection maintenance only when its integration is available. */
function run_connection_pilot_after_cleanup(): void {
	if ( is_local_cleanup_environment() || ( defined( 'VIP_JETPACK_SKIP_LOAD' ) && constant( 'VIP_JETPACK_SKIP_LOAD' ) ) ) {
		return;
	}

	if ( ! class_exists( Connection_Pilot::class ) ) {
		static $warned = false;
		if ( $warned ) {
			return;
		}
		$warned  = true;
		$message = 'Connection Pilot is unavailable; skipped connection maintenance after data cleanup. Check Jetpack compatibility and availability.';
		if ( defined( 'WP_CLI' ) && constant( 'WP_CLI' ) ) {
			\WP_CLI::warning( $message );
		} else {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Diagnostic, not HTML.
			trigger_error( $message, E_USER_WARNING );
		}
		return;
	}

	Connection_Pilot::instance()->run_connection_pilot();
}

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

	run_connection_pilot_after_cleanup();
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
