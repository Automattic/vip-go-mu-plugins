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
	$is_files_acl_enabled = defined( 'VIP_FILES_ACL_ENABLED' ) && true === VIP_FILES_ACL_ENABLED;
	$is_restrict_all_enabled = get_option_as_bool( 'vip_files_acl_restrict_all_enabled' );
	$is_restrict_unpublished_enabled = get_option_as_bool( 'vip_files_acl_restrict_unpublished_enabled' );

	if ( ! $is_files_acl_enabled ) {
		// Throw warning if restrictions are enabled but ACL constant is not set.
		// This is probably a sign that options were copied between sites or someone missed a setup step.
		if ( $is_restrict_all_enabled || $is_restrict_unpublished_enabled ) {
			trigger_error( 'File ACL restrictions are enabled without server configs (missing `VIP_FILES_ACL_ENABLED` constant).', E_USER_WARNING );
		}

		return;
	}

	if ( $is_restrict_all_enabled ) {
		require_once( __DIR__ . '/restrict-all-files.php' );

		add_filter( 'vip_files_acl_file_visibility', __NAMESPACE__ . '\Restrict_All_Files\check_file_visibility', 10, 2 );
	} elseif ( $is_restrict_unpublished_enabled ) {
		require_once( __DIR__ . '/restrict-unpublished-files.php' );

		add_filter( 'vip_files_acl_file_visibility', __NAMESPACE__ . '\Restrict_Unpublished_Files\check_file_visibility', 10, 2 );
	}
}

function get_option_as_bool( $option_name, $default = false ) {
	$value = get_option( $option_name, false );

	return in_array( $value, [
		true,
		'true',
		'yes',
		1,
		'1',
	], true );
}

/**
 * Check if the path is allowed for the current context.
 *
 * @param string $file_path Path to the file, including the `/wp-content/uploads/` bit.
 */
function is_valid_path_for_site( $file_path ) {
	if ( ! is_multisite() ) {
		return true;
	}

	// If main site, don't allow access to /sites/ subdirectories.
	if ( is_main_network() && is_main_site() ) {
		if ( 0 === strpos( $file_path, 'sites/' ) ) {
			return false;
		}

		return true;
	}

	$base_path = sprintf( 'sites/%d', get_current_blog_id() );

	return 0 === strpos( $file_path, $base_path );
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
