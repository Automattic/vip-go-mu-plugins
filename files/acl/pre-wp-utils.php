<?php

/**
 * Utility functions used by our ACL lib prior to loading WordPress.
 *
 * As the name suggests, these are loaded and called before WordPress and only pure PHP can be used here.
 */

namespace Automattic\VIP\Files\Acl\Pre_WP_Utils;

/**
 * Validates and sanitizes the submitted file URI.
 *
 * Encapsulates the logic into a helper function to avoid executing this in a global context and make it testable.
 *
 * @param string $file_request_uri The unvalidated / unsanitized file path.
 *
 * @return boolean|array false on invalid request, otherwise returns value from Pre_WP_Utils\sanitize_and_split_path
 */
function prepare_request( $file_request_uri ) {
	if ( ! $file_request_uri ) {
		trigger_error( 'VIP Files ACL failed due to empty URI', E_USER_WARNING );   // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error

		return false;
	}

	$file_path = parse_url( $file_request_uri, PHP_URL_PATH );  // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url

	$is_valid_path = validate_path( $file_path );
	if ( ! $is_valid_path ) {
		// validate_path calls trigger_error so no need to do it here
		return false;
	}

	return sanitize_and_split_path( $file_path );
}

/**
 * Validate the incoming files request.
 *
 * @param string $file_path The file path to validate.
 *
 * @return boolean is the path valid?
 */
function validate_path( $file_path ) {
	if ( ! $file_path ) {
		trigger_error( 'VIP Files ACL failed due to empty path', E_USER_WARNING );  // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error

		return false;
	}

	// Relative path not allowed
	if ( '/' !== $file_path[0] ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
		trigger_error( sprintf( 'VIP Files ACL failed due to relative path (for %s)', htmlspecialchars( $file_path ) ), E_USER_WARNING );

		return false;
	}

	// Missing `/wp-content/uploads/`.
	// Using `strpos` since we can have subsite / subdirectory paths.
	if ( false === strpos( $file_path, '/wp-content/uploads/' ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
		trigger_error( sprintf( 'VIP Files ACL failed due to invalid path (for %s)', htmlspecialchars( $file_path ) ), E_USER_WARNING );

		return false;
	}

	return true;
}

/**
 * Sanitize the path by stripping off the wp-content/uploads bits.
 *
 * For example, given a path like `/en/wp-content/file.jpg`, we'll get back `/en` and `file.jpg`
 *
 * @param string $file_path The path for the file.
 *
 * @return array $file_paths Indexed array with two entries: 0 is the path before `/wp-content/uploads/` and 1 is the path + file after.
 */
function sanitize_and_split_path( $file_path ) {
	list( $pre_wpcontent_path, $post_wpcontent_path ) = explode( '/wp-content/uploads/', $file_path, 2 );

	return [
		$pre_wpcontent_path,
		$post_wpcontent_path,
	];
}
