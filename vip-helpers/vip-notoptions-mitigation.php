<?php
/**
 * Plugin Name: VIP NotOptions Mitigation
 * Description: Detect invalid values in the notoptions cache.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Automattic\VIP;

use Automattic\VIP\Utils\Alerts;

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
	global $wpdb;
	$time       = microtime( true );
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

		// attempt repair
		$flushed = wp_cache_delete( 'notoptions', 'options' );

		// begin prepping notification
		$is_vip_env  = ( defined( 'WPCOM_IS_VIP_ENV' ) && true === WPCOM_IS_VIP_ENV );
		$environment = ( ( defined( 'VIP_GO_APP_ENVIRONMENT' ) && VIP_GO_APP_ENVIRONMENT ) ? VIP_GO_APP_ENVIRONMENT : 'unknown' );
		$site_id     = defined( 'VIP_GO_APP_ID' ) ? VIP_GO_APP_ID : false;

		// Send notices to VIP staff if this is happening on VIP-hosted sites
		if ( $is_vip_env && $site_id ) {

			$irc_alert_level      = 3; // CRITICAL
			$opsgenie_alert_level = 'P3';

			$subject = 'NOTOPTIONS: %s (%s VIP Go site ID: %s%s) - Invalid values found in notoptions @ %s (%s). Cache flush was %ssuccessful.';

			$subject = sprintf(
				$subject,
				esc_url( home_url() ),
				esc_html( $environment ),
				(int) $site_id,
				( ( 0 !== $wpdb->blogid ) ? ", blog ID {$wpdb->blogid}" : '' ),
				$time,
				gethostname(),
				( $flushed ? '' : 'un' )
			);

			$to_irc = $subject . ' [https://wp.me/PCYsg-vqN] #vipnotoptions';

			// Send to IRC, if we have a host configured
			if ( defined( 'ALERT_SERVICE_ADDRESS' ) && ALERT_SERVICE_ADDRESS ) {

				$alerts = Alerts::instance();
				$alerts->send_to_chat( '#vip-options-alerts', $to_irc, $irc_alert_level, 'a8c-notoptions' );

				if ( 'production' === $environment ) {
					// Send to OpsGenie
					$alerts->opsgenie(
						$subject,
						array(
							'alias'       => 'notoptions/cache-corruption/' . $site_id,
							'description' => 'Invalid values found in notoptions cache',
							'entity'      => (string) $site_id,
							'priority'    => $opsgenie_alert_level,
							'source'      => 'sites/notoptions-value-monitor',
						),
						'notoptions-value-alert',
						'10'
					);

				}
			}
		}
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
 */
function do_user_role_backup() {
	global $wpdb;
	$current_roles = get_option( $wpdb->prefix . 'user_roles' );
	$backup_roles  = get_option( 'vip_backup_user_roles', [] );

	$backup_roles[ time() ] = $current_roles; // adds to end of array
	$backup_roles           = array_slice( $backup_roles, ( USER_ROLE_BACKUP_LENGTH * -1 ), null, true ); // retain last 3

	update_option( 'vip_backup_user_roles', $backup_roles, 'no' );
}
