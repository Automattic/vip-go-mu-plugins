<?php

/**
 * Plugin Name: VIP All Options Limit
 * Description: Provides warnings and notifications for wp_options exceeding limits.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

add_action( 'plugins_loaded', 'wpcom_vip_sanity_check_alloptions' );

function wpcom_vip_sanity_check_alloptions() {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return;
	}

	// Warn should *always* be =< die
	$alloptions_size_warn  =  800000;
	$alloptions_size_die   = 1000000; // 1000000 ~ 1MB, too big for memcache

	$alloptions_size = wp_cache_get( 'alloptions_size' );

	// Cache miss
	if ( false === $alloptions_size ) {
		$alloptions = wp_load_alloptions();

		$alloptions_size = strlen( serialize( $alloptions ) );

		wp_cache_add( 'alloptions_size', $alloptions_size, '', 60 );
	}

	$blocked = $alloptions_size > $alloptions_size_die;
	$warning = $alloptions_size > $alloptions_size_warn;

	// If it's at least over the warning threshold (will also run when blocked), notify
	if ( $warning ) {
		// NOTE - This function has built-in rate limiting so it's ok to call on every request
		wpcom_vip_sanity_check_alloptions_notify( $alloptions_size, $blocked );
	}

	// Will exit with a 503
	if ( $blocked ) {
		wpcom_vip_sanity_check_alloptions_die();
	}
}

function wpcom_vip_sanity_check_alloptions_die() {
	// 503 Service Unavailable - prevent caching, indexing, etc and alert Varnish of the problem
	http_response_code( 503 );

	echo file_get_contents( __DIR__ . '/errors/alloptions-limit.html' );

	exit;
}

function wpcom_vip_sanity_check_alloptions_notify( $size, $blocked = false ) {
	global $wpdb;

	$throttle_was_set = wp_cache_add( 'alloptions', 1, 'throttle', 15 * MINUTE_IN_SECONDS );

	// If adding to cache failed, we're already throttled (unless the operation actually failed),
	// so return without doing anything.
	if ( false === $throttle_was_set ) {
		return;
	}

	$irc_alert_level = 2; // ALERT

	if ( $blocked ) {
		$msg = "This site is now BLOCKED from loading until option sizes are under control.";

		// If site is blocked, then the IRC alert is CRITICAL
		$irc_alert_level = 3;
	} else {
		$msg = "Site will be blocked from loading if option sizes get too much bigger.";
	}

	$msg .= "\n\nDebug information can be found in the Fieldguide";

	$is_vip_env    = ( defined( 'WPCOM_IS_VIP_ENV' ) && true === WPCOM_IS_VIP_ENV );
	$environment   = ( ( defined( 'VIP_GO_ENV' ) && VIP_GO_ENV ) ? VIP_GO_ENV : 'unknown' );
	$site_id       = defined( 'FILES_CLIENT_SITE_ID' ) ? FILES_CLIENT_SITE_ID : false;

	// Send notices to VIP staff if this is happening on VIP-hosted sites
	if ( $is_vip_env && $site_id ) {
		/** silence alerts on selected sites due to known issues **/

		// Array of VIP Go site IDs to silence alerts on
		$vip_alerts_blocked = array(

		);

		if ( in_array( $site_id, $vip_alerts_blocked, true ) ) {
			return;
		}

		$subject = 'ALLOPTIONS: %s (%s VIP Go site ID: %s';

		if ( 0 !== $wpdb->blogid ) {
			$subject .= ", blog ID {$wpdb->blogid}";
		}

		$subject .= ') options is up to %s';

		$subject = sprintf(
			$subject,
			esc_url( home_url() ),
			esc_html( $environment ),
			(int) $site_id,
			size_format( $size )
		);

		$to_irc = $subject . ' #vipoptions';

		// Send to IRC, if we have a host configured
		if ( defined( 'ALERT_SERVICE_ADDRESS' ) && ALERT_SERVICE_ADDRESS ) {
			wpcom_vip_irc( '#nagios-vip', $to_irc, $irc_alert_level, 'a8c-alloptions' );
			wpcom_vip_irc( '#wordpress.com-errors', $to_irc , $irc_alert_level, 'a8c-alloptions' );
		}

		$email_recipient = defined( 'VIP_ALLOPTIONS_NOTIFY_EMAIL' ) ? VIP_ALLOPTIONS_NOTIFY_EMAIL : false;

		if ( $email_recipient ) {
			$size = size_format( $size );

			wp_mail( $email_recipient, $subject, "Alloptions size when serialized: $size\n\n$msg" );
		}
	}
}
