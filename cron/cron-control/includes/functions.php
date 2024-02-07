<?php
/**
 * Common functions, often wrappers for various classes
 *
 * @package a8c_Cron_Control
 */

namespace Automattic\WP\Cron_Control;

/**
 * Check if an event is an internal one that the plugin will always run
 *
 * @param string $action Action name.
 * @return bool
 */
function is_internal_event( $action ) {
	return Internal_Events::instance()->is_internal_event( $action );
}

/**
 * Check which of the plugin's REST endpoints the current request is for, if any
 *
 * @return string|bool
 */
function get_endpoint_type() {
	// Request won't change, so hold for the duration.
	static $endpoint_slug = null;
	if ( ! is_null( $endpoint_slug ) ) {
		return $endpoint_slug;
	}

	// Determine request URL according to how Core does.
	$request = parse_request();

	// Search by our URL "prefix".
	$namespace = sprintf( '%s/%s', rest_get_url_prefix(), REST_API::API_NAMESPACE );

	// Check if any parts of the parse request are in our namespace.
	$endpoint_slug = false;

	foreach ( $request as $req ) {
		if ( 0 === stripos( $req, $namespace ) ) {
			$req_parts     = explode( '/', $req );
			$endpoint_slug = array_pop( $req_parts );
			break;
		}
	}

	return $endpoint_slug;
}

/**
 * Check if the current request is to one of the plugin's REST endpoints
 *
 * @param string $type Endpoint Constant from REST_API class to compare against.
 * @return bool
 */
function is_rest_endpoint_request( $type ) {
	return get_endpoint_type() === $type;
}

/**
 * Execute a specific event
 *
 * @param int    $timestamp      Unix timestamp.
 * @param string $action_hashed  md5 hash of the action used when the event is registered.
 * @param string $instance       md5 hash of the event's arguments array, which Core uses to index the `cron` option.
 * @param bool   $force          Run event regardless of timestamp or lock status? eg, when executing jobs via wp-cli.
 * @return array|\WP_Error
 */
function run_event( $timestamp, $action_hashed, $instance, $force = false ) {
	return Events::instance()->run_event( $timestamp, $action_hashed, $instance, $force );
}

/**
 * Count events with a given status
 *
 * @param string $status Status to count.
 * @return int|false
 */
function count_events_by_status( $status ) {
	return Events_Store::instance()->count_events_by_status( $status );
}
