<?php

if ( ! defined( 'VIP_ENCLOSEME_LIMIT_SIZE' ) ) {
	define( 'VIP_ENCLOSEME_LIMIT_SIZE', 1000 );
}

class VIP_Encloseme_Cleanup {
	const OPTION_NAME = 'vip_encloseme_cleanup_complete';
	const CRON_HOOK = 'vip_encloseme_cleanup_hook';
	const CRON_INTERVAL = 'vip_encloseme_cleanup_interval';

	public static function init() {

		if ( defined( 'ENABLE_VIP_ENCLOSEME_CLEANUP_ENV' ) && true === ENABLE_VIP_ENCLOSEME_CLEANUP_ENV ) {
			add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_schedule' ] );
			add_action( 'init', [ __CLASS__, 'schedule_cleanup' ], 99999 );
			add_action( self::CRON_HOOK, [ __CLASS__, 'cleanup_encloseme_meta' ] );
		}
	}

	public static function add_cron_schedule( $schedule ) {
		if ( isset( $schedule[ self::CRON_INTERVAL ] ) ) {
			return $schedule;
		}

		$schedule[ self::CRON_INTERVAL ] = [
			'interval' => 1200,
			'display' => __( 'Once every twenty minutes.' ),
		];
		
		return $schedule;
	}

	public static function schedule_cleanup() {
		$completed = get_option( self::OPTION_NAME );

		if ( $completed && wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
			return;
		}

		if ( false === $completed && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), self::CRON_INTERVAL, self::CRON_HOOK );
		}
	}

	public static function cleanup_encloseme_meta() {
		if ( ! wp_doing_cron() ) {
			return;
		}

		global $wpdb;
		// We use this query instead of count to mitigate the risk of the SQL server going away for large DBs. 
		$meta_exists = $wpdb->get_var( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_encloseme' LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( is_null( $meta_exists ) ) {
			update_option( self::OPTION_NAME, time() );
			wpcom_vip_irc( '#vip-go-encloseme-meta-cleanup', sprintf( '_encloseme meta cleanup completed for %s!', get_site_url() ) );
			return;
		}

		wpcom_vip_irc( '#vip-go-encloseme-meta-cleanup', sprintf( 'Starting _encloseme meta cleanup for %s.', get_site_url() ) );

		for ( $count = 0; $count < 10; $count++ ) {
			if ( ! defined( 'ENABLE_VIP_ENCLOSEME_CLEANUP_ENV' ) ) { // Constant not set, bail.
				break;
			}

			$pids = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT DISTINCT post_id FROM $wpdb->postmeta WHERE meta_key = '_encloseme' LIMIT %d",
					VIP_ENCLOSEME_LIMIT_SIZE
				),
				ARRAY_N
			);

			if ( empty( $pids ) ) {
				break; // Bail, no post IDs have been found.
			}

			foreach ( $pids as $pid ) {
				delete_post_meta( $pid[0], '_encloseme' );
			}
	
			sleep( 3 );
		} 
	}
}
