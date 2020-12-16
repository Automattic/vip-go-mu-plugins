<?php

namespace Automattic\VIP\Files\Acl;

require_once __DIR__ . '/../files/acl.php';

$file_request_uri = $_SERVER['HTTP_X_ORIGINAL_URI'] ?? null;

if ( ! $file_request_uri ) {
	trigger_error( 'VIP Files ACL failed due to empty URI', E_USER_WARNING );

	http_response_code( 500 );

	exit;
}

$file_path = parse_url( $file_request_uri, PHP_URL_PATH );

$is_valid_path = pre_wp_validate_path( $file_path );
if ( ! $is_valid_path ) {
	http_response_code( 500 );

	exit;
}

$sanitized_file_path = pre_wp_sanitize_path( $file_path );

// TODO: make sure HTTP HOST and path have subdir set for multisite
// TODO: handle resized files (e.g. file-200x200.jpg)

// Bootstap WordPress
require __DIR__ . '/../../../wp-load.php';

/**
 * Hook in here to define the visibility of a given file.
 *
 * @access private 
 *
 * @param string|boolean $file_visibility Return one of Automattic\VIP\Files\Acl\(FILE_IS_PUBLIC | FILE_IS_PRIVATE_AND_ALLOWED | FILE_IS_PRIVATE_AND_DENIED | FILE_NOT_FOUND) to set visibility.
 * @param string $validated_file_path The requested file path (note: on multisite subdirectory installs, this does not includes the subdirectory).
 */
$file_visibility = apply_filters( 'vip_files_acl_file_visibility', FILE_IS_PUBLIC, $sanitized_file_path );

send_visibility_headers( $file_visibility, $sanitized_file_path );

exit;
