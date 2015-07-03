<?php
/*
Author: Livefyre, Inc.
Version: 4.2.0
Author URI: http://livefyre.com/
*/

add_action( 'livefyre_check_for_sync', 'livefyre_check_site_sync' );

function livefyre_write_to_log( $msg ) {
    if ( WP_DEBUG === false ) {
        return;
    }
    error_log( $msg );
}
/*
* To alleviate site_syncs not firing, check to make sure they are set up
*/
function livefyre_setup_sync_check() {
    $hook = 'livefyre_check_for_sync';
    if ( !wp_next_scheduled( $hook ) ) {
        livefyre_write_to_log( 'Livefyre: Setting up sync_check.' );
        wp_schedule_event( time(), 'hourly', 'livefyre_check_for_sync' );
    }
}

/*
* Is there a site sync scheduled? (There should be...) If not schedule one for 7 hours down the road
*/

function livefyre_check_site_sync() {
    $hook = 'livefyre_sync';
    $msg = '';
    $timeout = time();
    livefyre_write_to_log( 'Livefyre: Checking for a site sync.' );
    if ( wp_next_scheduled( $hook ) > time() ) {
        return;
    }
    if ( !wp_next_scheduled( $hook ) ) {
        // Nothing scheduled for site sync
        $msg = 'Livefyre: Scheduling a site sync. Don\'t know why one is not scheduled.';
        $timeout += LFAPPS_SYNC_LONG_TIMEOUT;
    }
    elseif ( wp_next_scheduled( $hook ) < time() ) {
        // Sync was scheduled, but now timestamp is now expired
        $msg = "Livefyre: Site sync cron job expired. Scheduling sync on short timeout";
        $timeout += LFAPPS_SYNC_SHORT_TIMEOUT ;
        wp_clear_scheduled_hook( $hook );
    }
    livefyre_write_to_log( $msg );
    wp_schedule_single_event( $timeout, $hook );

}

livefyre_setup_sync_check();
