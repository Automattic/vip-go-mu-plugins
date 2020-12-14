<?php

namespace Automattic\VIP\Files\Acl;

const FILE_IS_PUBLIC = 'FILE_IS_PUBLIC';
const FILE_IS_PRIVATE_AND_ALLOWED = 'FILE_IS_PRIVATE_AND_ALLOWED';
const FILE_IS_PRIVATE_AND_DENIED = 'FILE_IS_PRIVATE_AND_DENIED';
const FILE_NOT_FOUND = 'FILE_NOT_FOUND';

/**
 * Validate the incoming files request.
 * 
 * Note: As the function name suggests, this is called before WordPress is loaded.
 *
 * @param string $file_request_uri
 */
function pre_wp_validate_request( $file_request_uri ) {
	// Note: cannot use WordPress functions here.

	if ( ! $file_request_uri ) {
		return [ 400, 'missing-uri' ];
	}

	// strpos since we can have subsite / subdirectory paths.
	if ( false === strpos( $file_request_uri, '/wp-content/uploads/' ) ) {
		return [ 400, 'invalid-path' ];
	}

	// Strip off non-path elements (scheme/host/querystring).
	$file_path = parse_url( $file_request_uri, PHP_URL_PATH );
	if ( ! $file_path ) {
		return [ 400, 'parse-error' ];
	}

	if ( 0 !== strpos( $file_path, '/' ) ) {
		return [ 400, 'relative-path' ];
	}

	// Strip off subdirectory (i.e. /en/wp-content/ <= remove `/en` )
	$wp_content_index = strpos( $file_path, '/wp-content/uploads/' );
	$file_path = substr( $file_path, $wp_content_index + strlen( '/wp-content/uploads/' ) );

	return $file_path;
}

/**
 * Helper function to send error headers.
 *
 * Called before WordPress is loaded.
 *
 * @param array $error Array containing [ (int) status code, (string) error message ]
 */
function pre_wp_send_error_headers( $error ) {
	list( $error_code, $error_message ) = $error;

	http_response_code( $error_code );

	header( 'X-Reason: ' . $error_message );
}

function send_visibility_headers( $file_visibility, $file_path ) {
	// Default to throwing an error so we can catch unexpected problems more easily.
	$status_code = 500;
	$header = false;

	switch ( $file_visibility ) {
		case FILE_IS_PUBLIC:
			$status_code = 202;
			break;

		case FILE_IS_PRIVATE_AND_ALLOWED:
			$status_code = 202;
			$header = 'X-Private: true';
			break;

		case FILE_IS_PRIVATE_AND_DENIED:
			$status_code = 403;
			$header = 'X-Private: true';
			break;

		case FILE_NOT_FOUND:
			// This is a signal for the server to pass the request through to the Files Service.
			// Even though the attachment was not found, it may still be a physical file in the Files Service.
			$status_code = 202;
			$header = 'X-Reason: attachment-not-found';
			break;

		default:
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			trigger_error( sprintf( 'Invalid file visibility (%s) ACL set for %s', $file_visibility, $file_path ), E_USER_WARNING );
			break;
	}

	http_response_code( $status_code );

	if ( $header ) {
		header( $header );
	}
}

/**
 * Variant of core's attachment_url_to_postid() function
 *
 * @param $path (string) The path to resolve.
 *
 * @return (int) The found attachment ID, or 0 on failure.
 */
function get_file_path_from_attachment_id( $path ) {
	global $wpdb;

	$cache_key = 'path_' . md5( $path );
	$attachment_id = wp_cache_get( $cache_key, 'files-acl' );
	if ( false !== $attachment_id ) {
		return $attachment_id;
	}

	$attachment_id = 0;

	$results = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value = %s",
			$path
		)
	);

	if ( $results ) {
		// Use the first available result, but prefer a case-sensitive match, if exists.
		$attachment_id = reset( $results )->post_id;

		if ( count( $results ) > 1 ) {
			foreach ( $results as $result ) {
				if ( $path === $result->meta_value ) {
					$attachment_id = $result->post_id;
					break;
				}
			}
		}
	}

	wp_cache_set( $cache_key, $attachment_id, 'files-acl', MINUTE_IN_SECONDS );

	return $attachment_id;
}
