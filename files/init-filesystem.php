<?php
/**
 * With the filters below, our override should load automatically when `WP_Filesystem()` is called.
 *
 * If we don't want to explicitly initialize it everywhere:
 *
 *     $api_client = Automattic\VIP\Files\new_api_client();
 *     WP_Filesystem( [
 *         new Automattic\VIP\Files\WP_Filesystem_VIP_Uploads( $api_client ),
 *         new WP_Filesystem_Direct( null ),
 *     ], 'vip' );
 *     $GLOBALS['wp_filesystem']->get_contents( '...' );
 *
 * If needed, we can also instantiate manually:
 *
 *     $api_client = Automattic\VIP\Files\new_api_client();
 *     $filesystem = new WP_Filesystem_VIP( [
 *         new Automattic\VIP\Files\WP_Filesystem_VIP_Uploads( $api_client ),
 *         new WP_Filesystem_Direct( null ),
 *     ] );
 *     $filesystem->get_contents( '...' );
 *
 */
// Note: we're using `PHP_INT_MAX` for the priority because we want our `WP_Filesystem_VIP` class to always take precedence.

require_once( ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php' );

require_once( WPMU_PLUGIN_DIR . '/files/class-wp-filesystem-vip.php' );
require_once( WPMU_PLUGIN_DIR . '/files/class-api-client.php' );

// Stub class to match the format that `WP_Filesystem()` expects.
// it does a check for class_exists() of the filesystem method i.e. `WP_Filesystem_{type}`
class WP_Filesystem_VIP extends Automattic\VIP\Files\WP_Filesystem_VIP {}

add_filter( 'filesystem_method', function( $method, $args, $context, $allow_relaxed_file_ownership ) {
	return 'VIP'; // All VIP, all the time
}, PHP_INT_MAX, 4 );

add_filter( 'request_filesystem_credentials', function( $credentials, $form_post, $type, $error, $context, $extra_fields, $allow_relaxed_file_ownership ) {
	// Handle the default `''` case which we'll override thanks to the `filesystem_method` filter.
	if ( '' === $type || 'VIP' === $type ) {
		$api_client = Automattic\VIP\Files\new_api_client();
		$credentials = [
			new Automattic\VIP\Files\WP_Filesystem_VIP_Uploads( $api_client ),
			new WP_Filesystem_Direct( null ),
		];
	}
	return $credentials;
}, PHP_INT_MAX, 7 );

// Should't need this because we `require`-ed the class already.
// But just in case :)
add_filter( 'filesystem_method_file', function( $file, $method ) {
	if ( 'VIP' === $method ) {
		$file = WPMU_PLUGIN_DIR . '/files/class-wp-filesystem-vip.php';
	}
	return $file;
}, PHP_INT_MAX, 2 );