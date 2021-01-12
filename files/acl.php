<?php

namespace Automattic\VIP\Files\Acl;

const FILE_IS_PUBLIC = 'FILE_IS_PUBLIC';
const FILE_IS_PRIVATE_AND_ALLOWED = 'FILE_IS_PRIVATE_AND_ALLOWED';
const FILE_IS_PRIVATE_AND_DENIED = 'FILE_IS_PRIVATE_AND_DENIED';

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

	wp_cache_set( $cache_key, $attachment_id, 'files-acl', 5 * MINUTE_IN_SECONDS );

	return $attachment_id;
}
