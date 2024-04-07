<?php

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery

namespace Automattic\VIP\Core\OptionsAPI;

add_filter( 'pre_wp_load_alloptions', __NAMESPACE__ . '\pre_wp_load_alloptions_protections', 999, 2 );

/**
 * Add additional protections around the alloptions functionality.
 *
 * Note that there is one (unavoidable) core call to get_option() before this filter is registered (in wp_plugin_directory_constants()),
 * So by the time this starts filtering, there's already been one occurrence of wp_load_alloptions().
 *
 * Here we re-implement most of what core does in wp_load_alloptions(), with some notable adjustments:
 * - 1) Prevent spamming memcached & the DB if memcached is unable to add() the key to cache.
 * - 2) Kill the request if options cannot be retrieved from the database (or cache).
 */
function pre_wp_load_alloptions_protections( $pre_loaded_alloptions, $force_cache ) {
	global $wpdb;
	static $fallback_cache = [];

	// Allow other filters the chance to early return (before priority 999).
	// And abort from this special handling during installations.
	if ( is_array( $pre_loaded_alloptions ) || wp_installing() ) {
		return $pre_loaded_alloptions;
	}

	// 1) If successfully fetched from cache, return right away.
	$alloptions_from_cache = wp_cache_get( 'alloptions', 'options', $force_cache );
	if ( ! empty( $alloptions_from_cache ) && is_array( $alloptions_from_cache ) ) {
		return apply_filters( 'alloptions', $alloptions_from_cache );
	}

	// 2) Return from the local fallback cache if available, helping prevent excess queries in cases where memcached is unable to save the results.
	$blog_id = get_current_blog_id();
	if ( ! $force_cache && isset( $fallback_cache[ $blog_id ] ) ) {
		return apply_filters( 'alloptions', $fallback_cache[ $blog_id ] );
	}

	// 3) Otherwise query the DB for fresh results.
	if ( function_exists( 'wp_autoload_values_to_autoload' ) ) {
		$values = wp_autoload_values_to_autoload();
	} else {
		$values = [ 'yes' ];
	}

	$suppress      = $wpdb->suppress_errors();
	$alloptions_db = $wpdb->get_results( "SELECT option_name, option_value FROM $wpdb->options WHERE autoload IN ( '" . implode( "', '", $values ) . "' )" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->suppress_errors( $suppress );

	$alloptions = [];
	foreach ( (array) $alloptions_db as $o ) {
		$alloptions[ $o->option_name ] = $o->option_value;
	}

	if ( empty( $alloptions ) ) {
		trigger_error( 'VIP: Unable to query alloptions from database.', E_USER_WARNING );

		if ( defined( '_VIP_DIE_ON_ALLOPTIONS_FAILURE' ) && true === constant( '_VIP_DIE_ON_ALLOPTIONS_FAILURE' ) ) {
			http_response_code( 503 );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- no need to escape the premade HTML file
			echo file_get_contents( dirname( __DIR__ ) . '/errors/alloptions-limit.html' );
			exit;
		}

		return apply_filters( 'alloptions', apply_filters( 'pre_cache_alloptions', $alloptions ) );
	}

	$alloptions = apply_filters( 'pre_cache_alloptions', $alloptions );
	$add_result = wp_cache_add( 'alloptions', $alloptions, 'options' );

	if ( false === $add_result && false === wp_cache_get( 'alloptions', 'options', true ) ) {
		if ( defined( 'WPCOM_IS_VIP_ENV' ) && true === constant( 'WPCOM_IS_VIP_ENV' ) ) {
			trigger_error( 'VIP: Saving to alloptions fallback cache.', E_USER_WARNING );
		}

		// Prevent memory issues in case something is looping over thousands of subsites.
		if ( count( $fallback_cache ) > 10 ) {
			$fallback_cache = [];
		}

		// Start using the fallback cache if this request both failed to add() to cache, and there is
		// nothing currently there - indicating there is likely something wrong with the ability to cache alloptions.
		// Note that this is already the second time the request would have tried.
		$fallback_cache[ $blog_id ] = $alloptions;
	}

	return apply_filters( 'alloptions', $alloptions );
}
