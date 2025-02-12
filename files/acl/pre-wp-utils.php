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
 * @param string $logger The logger to use. Defaults to \Automattic\VIP\Logstash\log2logstash - this is a workaround to make the function testable.
 * @return boolean|array false on invalid request, otherwise returns value from Pre_WP_Utils\sanitize_and_split_path
 */
function prepare_request( $file_request_uri, $logger = '\Automattic\VIP\Logstash\log2logstash' ) {
	if ( ! $file_request_uri ) {
		log_warning( 'VIP Files ACL failed due to empty URI', $logger );

		return false;
	}

	$file_path = parse_url( $file_request_uri, PHP_URL_PATH );  // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url

	$is_valid_path = validate_path( $file_path, $logger );
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
function validate_path( $file_path, $logger = '\Automattic\VIP\Logstash\log2logstash' ) {
	if ( ! $file_path ) {
		log_warning( 'VIP Files ACL failed due to empty path', $logger );

		return false;
	}

	// Relative path not allowed
	if ( '/' !== $file_path[0] ) {
		log_warning( 'VIP Files ACL failed due to relative path', $logger, htmlspecialchars( $file_path ) );

		return false;
	}

	// Missing `/wp-content/uploads/`.
	// Using `strpos` since we can have subsite / subdirectory paths.
	if ( false === strpos( $file_path, '/wp-content/uploads/' ) ) {
		log_warning( 'VIP Files ACL failed due to invalid path', $logger, htmlspecialchars( $file_path ) );

		return false;
	}

	$decoded = urldecode( $file_path );
	if ( false !== strpos( $decoded, './' ) ) {
		log_warning( 'VIP Files ACL failed due to a possible path traversal attack', $logger, htmlspecialchars( $file_path ) );
		return false;
	}

	// Trailing whitespace (like %0A) in the filename. This won't work on our prod servers but will work in dev env.
	if ( strlen( rtrim( $decoded ) ) !== strlen( $decoded ) ) {
		log_warning( 'VIP Files ACL failed due to a possible attack', $logger, htmlspecialchars( $file_path ) );
		return false;
	}

	return true;
}

/**
 * Log an error to Logstash.
 *
 * @param string $message The error message.
 * @param string $file_path The file path that caused the error.
 */
function log_warning( $message, $logger = '\Automattic\VIP\Logstash\log2logstash', $file_path = null ) {
	$logger(
		[
			'severity' => 'warning',
			'feature'  => 'files_acl_pre_wp_utils',
			'message'  => $message,
			'extra'    => [
				'file_path' => $file_path,
			],
		]
	);
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
	$decoded    = urldecode( $file_path );
	$compressed = preg_replace( '!/{2,}!', '/', $decoded );
	list( $pre_wpcontent_path, $post_wpcontent_path ) = explode( '/wp-content/uploads/', $compressed, 2 );

	return [
		$pre_wpcontent_path,
		$post_wpcontent_path,
	];
}
