<?php
/**
 * Plugin Name: VIP AllOptions Safeguard
 * Description: Provides warnings and notifications for wp_options exceeding limits.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Automattic\VIP\AllOptions;

use Automattic\VIP\Utils\Alerts;

require_once __DIR__ . '/lib/utils/class-alerts.php';

add_action( 'plugins_loaded', __NAMESPACE__ . '\run_alloptions_safeguard' );

define( 'VIP_ALLOPTIONS_ERROR_THRESHOLD', 1000000 );

/**
 * The purpose of this limit is to safe-guard against a barrage of requests with cache sets for values that are too large.
 * Because WP would keep trying to set the data to Memcached, potentially resulting in Memcached (and site's) performance degradation.
 */
function run_alloptions_safeguard() {
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
		$alloptions      = maybe_serialize( wp_load_alloptions() );
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
			$alloptions_size_deflated   = gzdeflate( maybe_serialize( wp_load_alloptions() ) );
			$alloptions_size_compressed = false !== $alloptions_size_deflated ? strlen( $alloptions_size_deflated ) : VIP_ALLOPTIONS_ERROR_THRESHOLD - 1;
			wp_cache_add( 'alloptions_size_compressed', $alloptions_size_compressed, '', 60 );
		}
	}

	if ( $alloptions_size_compressed >= VIP_ALLOPTIONS_ERROR_THRESHOLD ) {
		$really_blocked = true;
	}

	// NOTE - This function has built-in rate limiting so it's ok to call on every request
	alloptions_safeguard_notify( $alloptions_size, $alloptions_size_compressed, $really_blocked );

	// Will exit with a 503
	if ( $really_blocked ) {
		alloptions_safeguard_die();
	}
}

/**
 * Show error page and exit
 */
function alloptions_safeguard_die() {

	// 503 Service Unavailable - prevent caching, indexing, etc and alert Varnish of the problem
	http_response_code( 503 );

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- no need to escape the premade HTML file
	echo file_get_contents( __DIR__ . '/errors/alloptions-limit.html' );

	exit;
}

/**
 * Send notification
 *
 * @param int $size            Uncompressed sized of alloptions, in bytes
 * @param int $size_compressed Compressed size of alloption, in bytes.
 *                             HOWEVER, this is only set if $size meets a threshold.
 *                             @see run_alloptions_safeguard()
 * @param bool $really_blocked True if the options size is large enough to cause site to be blocked from loading.
 */
function alloptions_safeguard_notify( $size, $size_compressed = 0, $really_blocked = true ) {
	global $wpdb;

	$throttle_was_set = wp_cache_add( 'alloptions', 1, 'throttle', 30 * MINUTE_IN_SECONDS );

	// If adding to cache failed, we're already throttled (unless the operation actually failed),
	// so return without doing anything.
	if ( false === $throttle_was_set ) {
		return;
	}

	/**
	 * Fires under alloptions warning conditions
	 *
	 * @param bool $really_blocked False if alloptions size is large. True if site loading is being blocked.
	 */
	do_action( 'vip_alloptions_safeguard_notify', $really_blocked );

	$is_vip_env  = ( defined( 'WPCOM_IS_VIP_ENV' ) && true === WPCOM_IS_VIP_ENV );
	$environment = ( ( defined( 'VIP_GO_ENV' ) && VIP_GO_ENV ) ? VIP_GO_ENV : 'unknown' );
	$site_id     = defined( 'FILES_CLIENT_SITE_ID' ) ? FILES_CLIENT_SITE_ID : false;

	// Send notices to VIP staff if this is happening on VIP-hosted sites
	if (
		! $is_vip_env ||
		! $site_id ||
		! defined( 'ALERT_SERVICE_ADDRESS' ) ||
		! ALERT_SERVICE_ADDRESS ||
		'production' !== $environment
	) {
		return;
	}

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

	if ( $really_blocked ) {
		$priority    = 'P2';
		$description = sprintf( 'The size of AllOptions has breached %s bytes', VIP_ALLOPTIONS_ERROR_THRESHOLD );
	} elseif ( $size_compressed > 0 ) {
		$priority    = 'P3';
		$description = sprintf( 'The size of AllOptions is at %1$s bytes (compressed), %2$s bytes (uncompressed)', $size_compressed, $size );
	} else {
		$priority    = 'P5';
		$description = sprintf( 'The size of AllOptions is at %1$s bytes (uncompressed)', $size );
	}

	// Send to OpsGenie
	$alerts = Alerts::instance();
	$alerts->opsgenie(
		$subject,
		array(
			'alias'       => 'alloptions/' . $site_id,
			'description' => $description,
			'entity'      => (string) $site_id,
			'priority'    => $priority,
			'source'      => 'sites/alloptions-size',
		),
		'alloptions-size-alert',
		'10'
	);

}
