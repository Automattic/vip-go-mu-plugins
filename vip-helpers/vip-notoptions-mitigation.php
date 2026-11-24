<?php
/**
 * Plugin Name: VIP NotOptions Mitigation
 * Description: Detect invalid values in the notoptions cache.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Automattic\VIP;

if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
	return;
}

define( 'USER_ROLE_BACKUP_LENGTH', 3 );

if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	add_action( 'muplugins_loaded', __NAMESPACE__ . '\notoptions_mitigation', -999 );
	add_action( 'wp_loaded', __NAMESPACE__ . '\user_role_backup_scheduling' );
}
add_action( __NAMESPACE__ . '-backup_roles', __NAMESPACE__ . '\do_user_role_backup' );

function notoptions_mitigation() {
	$notoptions = wp_cache_get( 'notoptions', 'options' );

	if ( ! is_array( $notoptions ) ) {
		return;
	}

	$invalid = false;

	foreach ( $notoptions as $v ) {
		// notoptions should never have !true values, see get_option()
		// break on any values not equal to (bool)true.
		if ( true !== $v ) {
			$invalid = true;
			break;
		}
	}

	// if they exist, something is borked
	if ( $invalid ) {
		// Save the current "broken" state for future debugging
		if ( wp_cache_add( 'notoptions_mitigation_debugging', 'lock', 'vip', MINUTE_IN_SECONDS ) ) {
			update_option( 'vip_notoptions_mitigation_debugging', $notoptions, false );
		}

		// Attempt repair of the notoptions cache by flushing it.
		wp_cache_delete( 'notoptions', 'options' );
	}
}

/**
 * Schedule the backup event
 */
function user_role_backup_scheduling() {
	if ( ! wp_next_scheduled( __NAMESPACE__ . '-backup_roles' ) ) {
		wp_schedule_event( time(), 'daily', __NAMESPACE__ . '-backup_roles' );
	}
}

/**
 * Run the backup event
 *
 * @return bool Whether the roles were backed up
 */
function do_user_role_backup() {
	global $wpdb;
	$current_roles = get_option( $wpdb->prefix . 'user_roles' );

	// Don't push good backups out with a copy of missing or broken roles
	if ( ! is_array( $current_roles ) || ! $current_roles ) {
		return false;
	}

	$backup_roles = get_option( 'vip_backup_user_roles', [] );

	$backup_roles[ time() ] = $current_roles; // adds to end of array
	$backup_roles           = array_slice( $backup_roles, ( USER_ROLE_BACKUP_LENGTH * -1 ), null, true ); // retain last 3

	update_option( 'vip_backup_user_roles', $backup_roles, 'no' );

	return true;
}
