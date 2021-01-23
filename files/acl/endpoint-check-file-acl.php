<?php

namespace Automattic\VIP\Files\Acl;

require_once __DIR__ . '/pre-wp-utils.php';

$vip_files_acl_paths = Pre_WP_Utils\prepare_request( $_SERVER['HTTP_X_ORIGINAL_URI'] ?? null );

if ( ! $vip_files_acl_paths ) {
	// TODO: verify code to return
	http_response_code( 500 );

	exit;
}

list( $vip_files_acl_subsite_path, $vip_files_acl_sanitized_file_path ) = $vip_files_acl_paths;

if ( $vip_files_acl_subsite_path ) {
	$_SERVER['REQUEST_URI'] = $vip_files_acl_subsite_path . ( $_SERVER['REQUEST_URI'] ?? '' );
}

// Load WordPress
require __DIR__ . '/../../../../wp-load.php';

// Temp transitional check
if ( defined( 'VIP_GO_ENV' ) && VIP_GO_ENV
	&& true !== WPCOM_SANDBOXED ) {
	die( 'Sorry, internal testing only.' );
}

// Load the ACL lib
// TODO: not needed after https://github.com/Automattic/vip-go-mu-plugins/pull/1948
require_once __DIR__ . '/acl.php';

/**
 * Hook in here to adjust the visibility of a given file.
 *
 * Note: this is currently for VIP internal use only.
 *
 * @access private 
 *
 * @param string|boolean $file_visibility Return one of Automattic\VIP\Files\Acl\(FILE_IS_PUBLIC | FILE_IS_PRIVATE_AND_ALLOWED | FILE_IS_PRIVATE_AND_DENIED) to set visibility.
 * @param string $sanitized_file_path The requested file path (note: on multisite subdirectory installs, this does not includes the subdirectory).
 */
$vip_files_acl_file_visibility = apply_filters( 'vip_files_acl_file_visibility', FILE_IS_PUBLIC, $vip_files_acl_sanitized_file_path );

send_visibility_headers( $vip_files_acl_file_visibility, $vip_files_acl_sanitized_file_path );

exit;
