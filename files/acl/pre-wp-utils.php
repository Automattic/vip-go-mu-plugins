<?php

/**
 * Utility functions used by our ACL lib prior to loading WordPress.
 *
 * As the name suggests, these are loaded and called before WordPress and only pure PHP can be used here.
 */

namespace Automattic\VIP\Files\Acl\Pre_WP_Utils;

/**
 * Validate the incoming files request.
 *
 * @param string $file_path The file path to validate.
 */
function validate_path( $file_path ) {
	// Note: cannot use WordPress functions here.

	if ( ! $file_path ) {
		trigger_error( 'VIP Files ACL failed due to empty path', E_USER_WARNING );

		return false;
	}

	// Relative path not allowed
	if ( '/' !== $file_path[ 0 ] ) {
		trigger_error( sprintf( 'VIP Files ACL failed due to relative path (for %s)', $file_path ), E_USER_WARNING );

		return false;
	}

	// Missing `/wp-content/uploads/`.
	// Using `strpos` since we can have subsite / subdirectory paths.
	if ( false === strpos( $file_path, '/wp-content/uploads/' ) ) {
		trigger_error( sprintf( 'VIP Files ACL failed due to invalid path (for %s)', $file_path ), E_USER_WARNING );

		return false;
	}

	return true;
}

/**
 * Sanitize the path by stripping off the wp-content/uploads bits.
 *
 * @param string $file_path The file path to sanitize.
 */
function sanitize_path( $file_path ) {
	// Strip off subdirectory (i.e. /en/wp-content/ <= remove `/en` )
	$wp_content_index = strpos( $file_path, '/wp-content/uploads/' );
	$file_path = substr( $file_path, $wp_content_index + strlen( '/wp-content/uploads/' ) );

	return $file_path;
}