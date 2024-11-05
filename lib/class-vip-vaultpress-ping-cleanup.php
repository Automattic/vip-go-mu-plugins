<?php

class VIP_VaultPress_Ping_Cleanup {
	const VP_PING_OPTION_NAME = '_vp_ai_ping%';
	const OPTION_NAME         = 'vip_vaultpress_ping_cleanup_complete';
	const CRON_HOOK           = 'vip_vaultpress_ping_cleanup';
	const CRON_INTERVAL       = 'hourly';

	// How many options to delete per cron run.
	const QUERY_LIMIT = 100;

	public static function init(): void {
		if ( ! class_exists( 'VaultPress' ) && defined( 'ENABLE_VIP_VAULTPRESS_PING_CLEANUP' ) && true === ENABLE_VIP_VAULTPRESS_PING_CLEANUP ) {
			add_action( 'init', array( __CLASS__, 'schedule_cron' ), 99999 );
			add_action( self::CRON_HOOK, array( __CLASS__, 'do_cron' ) );
		}
	}

	public static function schedule_cron(): void {
		$completed = get_option( self::OPTION_NAME );

		if ( $completed && wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
			return;
		}

		if ( false === $completed && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), self::CRON_INTERVAL, self::CRON_HOOK );
		}
	}

	public static function do_cron(): void {
		if ( ! wp_doing_cron() ) {
			return;
		}

		// Mark cleanup as complete if no options are found.
		if ( ! self::requires_cleanup() ) {
			update_option( self::OPTION_NAME, time() );
			return;
		}

		self::delete_options();
	}

	private static function delete_options(): int|bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $wpdb->options WHERE option_name LIKE %s LIMIT %d",
				self::VP_PING_OPTION_NAME,
				self::QUERY_LIMIT
			)
		);
	}

	private static function requires_cleanup(): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$has_option = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT 1 FROM $wpdb->options WHERE option_name LIKE %s LIMIT 1",
				self::VP_PING_OPTION_NAME,
			)
		);

		return ! is_null( $has_option );
	}
}
