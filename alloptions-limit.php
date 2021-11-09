<?php

/**
 * Plugin Name: VIP All Options Limit
 * Description: Provides warnings and notifications for wp_options exceeding limits.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */
require_once __DIR__ . '/lib/utils/class-alerts.php';
use Automattic\VIP\Utils\Alerts;

add_action( 'plugins_loaded', 'wpcom_vip_sanity_check_alloptions' );

define( 'VIP_ALLOPTIONS_ERROR_THRESHOLD', 1000000 );
/**
 * The purpose of this limit is to safe-guard against a barrage of requests with cache sets for values that are too large.
 * Because WP would keep trying to set the data to Memcached, potentially resulting in Memcached (and site's) performance degradation.
 * @return void
 */
function wpcom_vip_sanity_check_alloptions() {
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return;
	}

	// Uncompressed size thresholds.
	// Warn should *always* be =< die
	$alloptions_size_warn = MB_IN_BYTES * 2.5;

	// To avoid performing a potentially expensive calculation of the compressed size we use 4MB uncompressed (which is likely less than 1MB compressed)
	$alloptions_size_die = MB_IN_BYTES * 4;

	$alloptions_size = wp_cache_get( 'alloptions_size' );

	// Cache miss
	if ( false === $alloptions_size ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$alloptions      = serialize( wp_load_alloptions() );
		$alloptions_size = strlen( $alloptions );

		wp_cache_add( 'alloptions_size', $alloptions_size, '', 60 );
	}

	$warning        = $alloptions_size > $alloptions_size_warn;
	$maybe_blocked  = $alloptions_size > $alloptions_size_die;
	$really_blocked = false;

	$alloptions_size_compressed = 0;

	if ( ! $warning ) {
		return;
	}

	if ( $maybe_blocked ) {
		// It's likely at this point the site is already experiencing performance degradation.
		// We're using gzdeflate here because pecl-memcache uses Zlib compression for large values.
		// See https://github.com/websupport-sk/pecl-memcache/blob/e014963c1360d764e3678e91fb73d03fc64458f7/src/memcache_pool.c#L303-L354
		$alloptions_size_compressed = wp_cache_get( 'alloptions_size_compressed' );
		if ( ! $alloptions_size_compressed ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
			$alloptions_size_compressed = strlen( gzdeflate( serialize( wp_load_alloptions() ) ) );
			wp_cache_add( 'alloptions_size_compressed', $alloptions_size_compressed, '', 60 );
		}
	}

	if ( $alloptions_size_compressed >= VIP_ALLOPTIONS_ERROR_THRESHOLD ) {
		$really_blocked = true;
	}

	// NOTE - This function has built-in rate limiting so it's ok to call on every request
	wpcom_vip_sanity_check_alloptions_notify( $alloptions_size, $alloptions_size_compressed, $maybe_blocked, $really_blocked );

	// Will exit with a 503
	if ( $really_blocked ) {
		wpcom_vip_sanity_check_alloptions_die();
	}
}

function wpcom_vip_sanity_check_alloptions_die() {

	// 503 Service Unavailable - prevent caching, indexing, etc and alert Varnish of the problem
	http_response_code( 503 );

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- no need to escape the premade HTML file
	echo file_get_contents( __DIR__ . '/errors/alloptions-limit.html' );

	exit;
}

function wpcom_vip_alloptions_size_is_acked() {
	if ( apply_filters( 'alloptions_overrule_ack', false ) ) {
		return false;
	}

	$stat = get_option( 'vip_suppress_alloptions_alert', [] );

	if ( is_array( $stat ) && array_key_exists( 'expiry', $stat ) && $stat['expiry'] > time() ) {
		return true;
	}

	return false;
}

function wpcom_vip_sanity_check_alloptions_notify( $size, $size_compressed = 0, $maybe_blocked = false, $really_blocked = true ) {
	global $wpdb;

	$throttle_was_set = wp_cache_add( 'alloptions', 1, 'throttle', 30 * MINUTE_IN_SECONDS );

	// If adding to cache failed, we're already throttled (unless the operation actually failed),
	// so return without doing anything.
	if ( false === $throttle_was_set ) {
		return;
	}

	if ( $really_blocked ) {
		$msg = 'This site is now BLOCKED from loading until option sizes are under control.';
	} elseif ( $maybe_blocked ) {
		$msg  = 'This will soon be BLOCKED from loading until if the options sizes increase.';
		$msg .= PHP_EOL . PHP_EOL;
		$msg .= sprintf( 'Blocking threshold: %s. Current size: %s', VIP_ALLOPTIONS_ERROR_THRESHOLD, $size_compressed );
	} else {
		$msg = 'Site will be blocked from loading if option sizes get too much bigger.';
	}

	$is_vip_env  = ( defined( 'WPCOM_IS_VIP_ENV' ) && true === WPCOM_IS_VIP_ENV );
	$environment = ( ( defined( 'VIP_GO_ENV' ) && VIP_GO_ENV ) ? VIP_GO_ENV : 'unknown' );
	$site_id     = defined( 'FILES_CLIENT_SITE_ID' ) ? FILES_CLIENT_SITE_ID : false;

	// Send notices to VIP staff if this is happening on VIP-hosted sites
	if ( $is_vip_env && $site_id ) {

		$subject = 'ALLOPTIONS: %1$s (%2$s VIP Go site ID: %3$s';

		if ( 0 !== $wpdb->blogid ) {
			$subject .= ", blog ID {$wpdb->blogid}";
		}

		$subject .= ') options is up to %4$s';

		$subject = sprintf(
			$subject,
			esc_url( home_url() ),
			esc_html( $environment ),
			(int) $site_id,
			size_format( $size )
		);

		// Send to IRC, if we have a host configured
		if (
			defined( 'ALERT_SERVICE_ADDRESS' ) &&
			ALERT_SERVICE_ADDRESS &&
			'production' === $environment &&
			$really_blocked
		) {

			// Send to OpsGenie
			$alerts = Alerts::instance();
			$alerts->opsgenie(
				$subject,
				array(
					'alias'       => 'alloptions/' . $site_id,
					'description' => sprintf( 'The size of AllOptions has breached %s bytes', VIP_ALLOPTIONS_ERROR_THRESHOLD ),
					'entity'      => (string) $site_id,
					'priority'    => 'P3',
					'source'      => 'sites/alloptions-size',
				),
				'alloptions-size-alert',
				'10'
			);

		}

		$email_recipient = defined( 'VIP_ALLOPTIONS_NOTIFY_EMAIL' ) ? VIP_ALLOPTIONS_NOTIFY_EMAIL : false;

		if ( is_email( $email_recipient ) ) {
			$size = size_format( $size );

			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail
			wp_mail( $email_recipient, $subject, "Alloptions size when serialized: $size\n\n$msg" );
		}
	}
}
