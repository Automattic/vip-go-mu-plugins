<?php

namespace Automattic\VIP\Files\Acl;

require_once __DIR__ . '/../files/acl.php';

$file_request_uri = $_SERVER['HTTP_X_ORIGINAL_URI'] ?? null;

$validated_file_path = pre_wp_validate_request( $file_request_uri );
if ( is_array( $validated_file_path ) ) {
	pre_wp_send_error_headers( $validated_file_path );

	exit;
}

// TODO: make sure HTTP HOST and path have subdir set for multisite
// TODO: handle resized files (e.g. file-200x200.jpg)

// Bootstap WordPress
require __DIR__ . '/../../../wp-load.php';

/**
 * Fires prior to the attachment lookup for a given file path.
 *
 * Useful for early handling of files when we don't care about the path <=> attachment connection.
 *
 * @param string|boolean $file_visibility Return one of Automattic\VIP\Files\Acl\(FILE_IS_PUBLIC | FILE_IS_PRIVATE_AND_ALLOWED | FILE_IS_PRIVATE_AND_DENIED | FILE_NOT_FOUND) to set visibility.
 * @param string $validated_file_path The requested file path (note: on multisite subdirectory installs, this does not includes the subdirectory).
 */
$file_visibility = apply_filters( 'vip_files_acl_file_visibility', FILE_IS_PUBLIC, $validated_file_path );

send_visibility_headers( $file_visibility, $validated_file_path );

exit;
