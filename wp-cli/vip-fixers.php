<?php

class VIP_Go_OneTimeFixers_Command extends WPCOM_VIP_CLI_Command {
	const VP_PING_OPTION_NAME = '_vp_ai_ping%';

	/**
	 * Remove all VaultPress ping options from the database.
	 *
	 * @subcommand cleanup-vaultpress-ping
	 */
	public function cleanup_vp_pings_for_all_sites() {
		if ( ! is_multisite() ) {
			$this->cleanup_vp_pings_for_site();
		} else {
			$site_ids = get_sites( [
				'fields' => 'ids',
				'number' => 0,
			] );

			foreach ( $site_ids as $site_id ) {
				WP_CLI::log( sprintf( 'Cleaning up network site %d', $site_id ) );

				switch_to_blog( $site_id );

				$this->cleanup_vp_pings_for_site();

				restore_current_blog();
			}
		}

		WP_CLI::success( sprintf( 'Cleanup complete' ) );
	}

	private function cleanup_vp_pings_for_site() {
		// Probably no sites have added VaultPress to their repo to continue using it, but check just in case.
		if ( class_exists( 'VaultPress' ) ) {
			WP_CLI::warning( 'VaultPress is active on this site, skipping...' );
			return;
		}

		$count = $this->get_vp_pings_count();

		if ( $count > 0 ) {
			$deleted = $this->delete_vp_pings();
			WP_CLI::log( sprintf( 'Deleted %d ping option/s.', $deleted ) );
		} else {
			WP_CLI::log( 'No ping options found, skipping...' );
		}
	}

	private function delete_vp_pings() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name LIKE %s;", self::VP_PING_OPTION_NAME ) );
	}

	private function get_vp_pings_count() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(option_id) FROM $wpdb->options WHERE option_name LIKE %s;", self::VP_PING_OPTION_NAME ) );
	}
}

WP_CLI::add_command( 'vip fixers', 'VIP_Go_OneTimeFixers_Command' );
