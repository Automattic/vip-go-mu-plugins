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
