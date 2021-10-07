<?php

namespace Automattic\VIP\Files\Acl;

require_once __DIR__ . '/pre-wp-utils.php';

$vip_files_acl_original_uri = $_SERVER['HTTP_X_ORIGINAL_URI'] ?? null;                      // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
$vip_files_acl_paths        = Pre_WP_Utils\prepare_request( $vip_files_acl_original_uri );

if ( ! $vip_files_acl_paths ) {
	// Note: a 400 might be more appropriate but we're limited in terms of response codes.
	// See `send_visibility_headers()` for more details.
	http_response_code( 500 );

	exit;
}

list( $vip_files_acl_subsite_path, $vip_files_acl_sanitized_file_path ) = $vip_files_acl_paths;

if ( $vip_files_acl_subsite_path ) {
	$_SERVER['REQUEST_URI'] = $vip_files_acl_subsite_path . ( $_SERVER['REQUEST_URI'] ?? '' );  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
}

// Load WordPress
require __DIR__ . '/../../../../wp-load.php';

$vip_files_acl_is_path_allowed = is_valid_path_for_site( $vip_files_acl_sanitized_file_path );
if ( ! $vip_files_acl_is_path_allowed ) {
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error, WordPress.Security.EscapeOutput.OutputNotEscaped -- using htmlspecialchars, which PHPCS complains about
	trigger_error( sprintf( 'Blocked request for file path that is not allowed (current site ID: %s | requested URI: %s)', (int) get_current_blog_id(), htmlspecialchars( $vip_files_acl_original_uri ) ), E_USER_WARNING );

	http_response_code( 400 );

	exit;
}

/**
 * Hook in here to adjust the visibility of a given file.
 *
 * Note: this is currently for VIP internal use only.
 *
 * @access private 
 *
 * @param string|boolean $file_visibility Return one of Automattic\VIP\Files\Acl\(FILE_IS_PUBLIC | FILE_IS_PRIVATE_AND_ALLOWED | FILE_IS_PRIVATE_AND_DENIED) to set visibility.
 * @param string $sanitized_file_path The requested file path (note: This does not include `/wp-content/uploads/`. And, on multisite subdirectory installs, this does not includes the subdirectory).
 */
$vip_files_acl_file_visibility = apply_filters( 'vip_files_acl_file_visibility', FILE_IS_PUBLIC, $vip_files_acl_sanitized_file_path );

send_visibility_headers( $vip_files_acl_file_visibility, $vip_files_acl_sanitized_file_path );

exit;
