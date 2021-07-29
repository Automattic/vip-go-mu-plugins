<?php
/*
 * Plugin Name: VIP Parse.ly Integration
 * Plugin URI: https://parse.ly
 * Description: Content analytics made easy. Parse.ly gives creators, marketers and developers the tools to understand content performance, prove content value, and deliver tailored content experiences that drive meaningful results.
 * Author: Automattic
 * Version: 1.0
 * Author URI: https://wpvip.com/
 * License: GPL2+
 * Text Domain: wp-parsely
 * Domain Path: /languages/
 */

define( 'WPVIP_PARSELY_DEFAULT_VERSION', '2.5' );

function wpvip_load_wp_parsely_plugin() {
	/**
	 * Sourcing the wp-parsely plugin via mu-plugins is opt-in.
	 * To enable it on your site, add this line:
	 *
	 * add_filter( 'wpvip_parsely_load_mu', '__return_true' );
	 */
	if ( ! apply_filters( 'wpvip_parsely_load_mu', false ) ) {
		return;
	}

	// Bail if the plugin has already initialized elsewhere
	if ( class_exists( 'Parsely' ) ) {
		return;
	}

	add_filter( 'option_parsely', 'wpvip_override_parsely_option_if_empty' );

	/**
	 * Allows specifying a major version of the plugin per-site.
	 * If the version is invalid, the default version will be used.
	 */
	$major_version = apply_filters( 'wpvip_parsely_major_version', WPVIP_PARSELY_DEFAULT_VERSION );
	if ( ! in_array( $major_version, [
		'2.5',
	] ) ) {
		trigger_error(
			sprintf( 'Invalid value configured via wpvip_parsely_major_version filter: %s', $major_version ),
			E_USER_WARNING
		);
		$major_version = WPVIP_PARSELY_DEFAULT_VERSION;
	}
	require 'wp-parsely-' . $major_version . '/wp-parsely.php';
}
add_action( 'after_setup_theme', 'wpvip_load_wp_parsely_plugin' );

/**
 * Pre-sets the "apikey" portion of the `parsely` site option hash map to the site_url IF it's empty.
 * This is only hooked into `option_parsely` if we're loading the plugin from this repo.
 *
 * @param mixed $parsely_settings Settings from the database
 * @return array Settings with the 'apikey' value overridden
 */
function wpvip_override_parsely_option_if_empty( $parsely_settings ): array {
	// Bail if an apikey is already set
	if ( isset( $parsely_settings['apikey'] ) && strlen( $parsely_settings['apikey'] ) > 0 ) {
		return $parsely_settings;
	}

	// Bail if this function has already initialized the option
	if ( isset( $parsely_settings['_wpvip_init_option'] ) ) {
		return $parsely_settings;
	}

	$parsed_url = parse_url( site_url() );
	$apikey = $parsed_url['host'];

	/**
	 * Paths are not supported in Parse.ly `apikey`s
	 * If this is a subdirectory install, prepend the (modified) path like it's a "subdomain"
	 * This is conventional for this situation.
	 */
	if ( preg_match( '/^\/(.*)/', $parsed_url['path'], $matches ) ) {
			// Change slashes to dots
			$prefix = preg_replace( '/\/+/', '.', $matches[1] );

			// Remove remaining "non-word" characters
			$prefix = preg_replace( '/[^\w.]/', '', $prefix );

			// Reverse the dot separated prefix parts (so last path segment becomes first)
			$prefix = implode( '.', array_reverse( explode( '.', $prefix ) ) );

			$apikey = "$prefix.$apikey";
	}

	return [
		'apikey'             => sanitize_text_field( $apikey ),
		'_wpvip_init_option' => time(),
	];
}
