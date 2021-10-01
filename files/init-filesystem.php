<?php
/**
 * With the filters below, our override should load automatically when `WP_Filesystem()` is called.
 *
 * Here is sample code on how to use $wp_filesystem:
 *
 *      global $wp_filesystem;
 *      if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base') ){
 *          $creds = request_filesystem_credentials( site_url()
 *          wp_filesystem($creds);
 *      }
 *      $wp_filesystem->put_contents( wp_get_upload_dir()['basedir'] . '/test.txt', 'this is a test file');
 *
 */
// Note: we're using `PHP_INT_MAX` for the priority because we want our `WP_Filesystem_VIP` class to always take precedence.

define( 'VIP_FILESYSTEM_METHOD', 'VIP' );

require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';

require_once __DIR__ . '/class-wp-filesystem-vip.php';
require_once __DIR__ . '/class-api-client.php';

// Stub class to match the format that `WP_Filesystem()` expects.
// it does a check for class_exists() of the filesystem method i.e. `WP_Filesystem_{type}`
class WP_Filesystem_VIP extends Automattic\VIP\Files\WP_Filesystem_VIP {}

add_filter( 'filesystem_method', function() {
	return VIP_FILESYSTEM_METHOD; // The VIP base class transparently handles using the direct filesystem as well as the VIP Go File API
}, PHP_INT_MAX );

add_filter( 'request_filesystem_credentials', function( $credentials, $form_post, $type ) {
	// Handle the default `''` case which we'll override thanks to the `filesystem_method` filter.
	if ( '' === $type || VIP_FILESYSTEM_METHOD === $type ) {
		if ( true === WPCOM_IS_VIP_ENV ) {
			$api_client  = Automattic\VIP\Files\new_api_client();
			$credentials = [
				new Automattic\VIP\Files\WP_Filesystem_VIP_Uploads( $api_client ),
				new WP_Filesystem_Direct( null ),
			];
		} else {
			// When not on VIP we'll pass direct to both. This means we'll still get the errors thrown when writes are done outside the /tmp and the uploads folder
			$credentials = [
				new WP_Filesystem_Direct( null ),
				new WP_Filesystem_Direct( null ),
			];
		}
	}
	return $credentials;
}, PHP_INT_MAX, 3 );

// Should't need this because we `require`-ed the class already.
// But just in case :)
add_filter( 'filesystem_method_file', function( $file, $method ) {
	if ( 'VIP' === $method ) {
		$file = __DIR__ . '/class-wp-filesystem-vip.php';
	}
	return $file;
}, PHP_INT_MAX, 2 );
