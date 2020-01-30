<?php

class VIP_Encloseme_Cleanup {
    const OPTION_NAME = 'vip_encloseme_cleanup';
    const CRON_HOOK = 'vip_encloseme_cleanup_hook';

    public static function init() {
        add_action( 'init', [ __CLASS__, 'schedule_cleanup' ], 99999 );
        add_action( self::CRON_HOOK, [ __CLASS__, 'cleanup_encloseme_meta' ] );
    }

    public static function schedule_cleanup() {
        $already_ran = get_option( self::OPTION_NAME );
        if ( false === $already_ran && ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_single_event( time() + mt_rand( 15, 30 ), self::CRON_HOOK );
        }
    }

    public static function cleanup_encloseme_meta() {
        if ( ! wp_doing_cron() ) {
            return;
        }

        global $wpdb;
        $find_meta_query = "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_encloseme' LIMIT 1"; // We use this instead of count because of the risk of the SQL server going away if there's :alot: of rows. 
        $meta_exists = $wpdb->get_var( $find_meta_query ); 

        if ( is_null( $meta_exists ) ) {
            update_option( self::OPTION_NAME, time() );
            return;
        }

        do {
            $pids = $wpdb->get_results(
                "SELECT DISTINCT post_id FROM $wpdb->postmeta WHERE meta_key = '_encloseme' LIMIT 1000",
                ARRAY_N
            );
            $pids = array_map( function( $pid ) {
                return $pid[0];
            }, $pids );
            foreach( $pids as $pid ) {
                delete_post_meta( $pid, '_encloseme' );
            }
    
            sleep( 3 );

            $meta_exists = $wpdb->get_var( $find_meta_query );
        } while ( ! is_null( $meta_exists ) );
        update_option( self::OPTION_NAME, time() );
    }
}

if ( false === defined( 'VIP_CLEANUP_ENV' ) && defined( 'VIP_GO_ENV' ) && 'preprod' === VIP_GO_ENV ) { // Automatically enable for certain environments.
    define( 'VIP_CLEANUP_ENV', true );
}

if ( defined( 'VIP_CLEANUP_ENV' ) && true === VIP_CLEANUP_ENV ) {
    add_action( 'init', [ 'VIP_Encloseme_Cleanup', 'init' ] );
}
