<?php

/**
 * Plugin Name: VIP Restricted Files
 * Description: Secure your content by restricting access to unpublished or private files.
 * Author: WordPress VIP
 * Author URI: https://wpvip.com
 * Version: 1.0
 */

namespace Automattic\VIP\Files\Acl;

const FILE_IS_PUBLIC = 'FILE_IS_PUBLIC';
const FILE_IS_PRIVATE_AND_ALLOWED = 'FILE_IS_PRIVATE_AND_ALLOWED';
const FILE_IS_PRIVATE_AND_DENIED = 'FILE_IS_PRIVATE_AND_DENIED';

add_action( 'muplugins_loaded', __NAMESPACE__ . '\maybe_load_restrictions' );

function maybe_load_restrictions() {
	$is_files_acl_enabled = defined( 'VIP_FILES_ACL_ENABLED' ) && VIP_FILES_ACL_ENABLED;
	if ( ! $is_files_acl_enabled ) {
		return;
	}

	$is_restrict_all_enabled = get_option_as_bool( 'vip_files_acl_restrict_all_enabled', false );
	if ( $is_restrict_all_enabled ) {
		require_once( __DIR__ . '/files/acl/restrict-all-files.php' );

		return;
	}

	$is_restrict_unpublished_enabled = get_option_as_bool( 'vip_files_acl_restrict_unpublished_enabled', false );
	if ( $is_restrict_unpublished_enabled ) {
		require_once( __DIR__ . '/files/acl/restrict-unpublished-files.php' );

		return;
	}
}

function get_option_as_bool( $option_name, $default = false ) {
	$value = get_option_as_bool( 'vip_files_acl_restrict_all_enabled', false );

	return in_array( $value, [
		true,
		'true',
		'yes',
		1,
		'1',
	], true );
}

/**
 * Sends the correct response code and headers based on the specified file availability.
 *
 * Note: the nginx module for using for the subrequest limits what status codes can be returned.
 *
 * Specifically, we can only send 2xx, 401, and 403. Everything else is sent to the client as a 500.
 *
 * Also note: for success responses, it's very important to not use 200 since that can be returned by
 * fatal errors as well which could result in leaking data.
 *
 * @param string $file_visibility One of the allowed visibility constants.
 * @param string $file_path Path to the file, minus the wp-content/uploads/ bits.
 */
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
