<?php

/**
 * Plugin Name: VIP Restricted Files
 * Description: Secure your content by restricting access to unpublished or private files.
 * Author: WordPress VIP
 * Author URI: https://wpvip.com
 * Version: 1.0
 */

namespace Automattic\VIP\Files\Acl;

const FILE_IS_PUBLIC              = 'FILE_IS_PUBLIC';
const FILE_IS_PRIVATE_AND_ALLOWED = 'FILE_IS_PRIVATE_AND_ALLOWED';
const FILE_IS_PRIVATE_AND_DENIED  = 'FILE_IS_PRIVATE_AND_DENIED';

// Meta hooks that run clear_path_cache(), including the ones before an update or delete.
const PATH_CACHE_HOOKS = [ 'added_post_meta', 'update_post_meta', 'updated_post_meta', 'delete_post_meta' ];

// Attachment meta that lists its files.
const FILE_META_KEYS = [ '_wp_attached_file', '_wp_attachment_metadata', '_wp_attachment_backup_sizes' ];

add_action( 'muplugins_loaded', __NAMESPACE__ . '\maybe_load_restrictions' );

function maybe_load_restrictions() {
	$is_files_acl_enabled            = defined( 'VIP_FILES_ACL_ENABLED' ) && true === constant( 'VIP_FILES_ACL_ENABLED' );
	$is_restrict_all_enabled         = get_option_as_bool_if_exists( 'vip_files_acl_restrict_all_enabled' );
	$is_restrict_unpublished_enabled = get_option_as_bool_if_exists( 'vip_files_acl_restrict_unpublished_enabled' );
	$no_option_set                   = null === $is_restrict_all_enabled && null === $is_restrict_unpublished_enabled;

	if ( ! $is_files_acl_enabled ) {
		// Throw warning if restrictions are enabled but ACL constant is not set.
		// This is probably a sign that options were copied between sites or someone missed a setup step.
		if ( $is_restrict_all_enabled || $is_restrict_unpublished_enabled ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error( 'File ACL restrictions are enabled without server configs (missing `VIP_FILES_ACL_ENABLED` constant).', E_USER_WARNING );
		}

		return;
	}

	// Keep cached path lookups current on every site, since a request can switch to a site that restricts unpublished files.
	foreach ( PATH_CACHE_HOOKS as $hook ) {
		add_action( $hook, __NAMESPACE__ . '\clear_path_cache', 10, 3 );
	}

	if ( true === $is_restrict_all_enabled || ( $no_option_set && ( defined( 'VIP_GO_ENV' ) && 'production' !== VIP_GO_ENV ) ) ) {
		require_once __DIR__ . '/restrict-all-files.php';

		add_filter( 'vip_files_acl_file_visibility', __NAMESPACE__ . '\Restrict_All_Files\check_file_visibility', 10, 2 );
	} elseif ( true === $is_restrict_unpublished_enabled ) {
		require_once __DIR__ . '/restrict-unpublished-files.php';

		add_filter( 'vip_files_acl_file_visibility', __NAMESPACE__ . '\Restrict_Unpublished_Files\check_file_visibility', 10, 2 );
		// Purge attachments for posts for better cacheability
		add_filter( 'wpcom_vip_cache_purge_urls', __NAMESPACE__ . '\Restrict_Unpublished_Files\purge_attachments_for_post', 10, 2 );
		add_action( 'wpcom_vip_cache_pre_execute_purges', __NAMESPACE__ . '\Restrict_Unpublished_Files\send_derived_file_purges' );
	}
}

/**
 * Clear the cached attachment lookups for the files an attachment starts or stops using.
 *
 * Otherwise a lookup cached before the change (as not found, or as an attachment that has since
 * moved or been deleted while another one still uses the path) would return an outdated result until it expires.
 *
 * The attachment's files are read from its stored meta: on the hooks before an update or delete, they're
 * the files it's about to stop using; on the hooks after an add or update, the ones it now uses.
 *
 * restrict-unpublished-files.php is loaded only once there's something to clear: some sites check whether
 * it's loaded at `muplugins_loaded` to tell if they restrict unpublished files.
 *
 * @param int|int[] $meta_id  Meta ID, or IDs when deleted.
 * @param int       $post_id  Post ID.
 * @param string    $meta_key Meta key.
 */
function clear_path_cache( $meta_id, $post_id, $meta_key ) {
	if ( ! in_array( $meta_key, FILE_META_KEYS, true ) ) {
		return;
	}

	require_once __DIR__ . '/restrict-unpublished-files.php';

	// Every stored main file, and the files derived from it (original, edited copies, sizes).
	$paths = array_merge( get_post_meta( $post_id, '_wp_attached_file', false ), Restrict_Unpublished_Files\get_attachment_file_paths( $post_id ) );

	foreach ( $paths as $path ) {
		if ( is_string( $path ) ) {
			wp_cache_delete( Restrict_Unpublished_Files\get_path_cache_key( $path ), Restrict_Unpublished_Files\CACHE_GROUP );
			wp_cache_delete( Restrict_Unpublished_Files\get_path_cache_key( $path, 'image' ), Restrict_Unpublished_Files\CACHE_GROUP );
		}
	}
}

/**
 * Get an option as a boolean if it exists. If it does not exist, return null.
 *
 * @param string $option_name The name of the option to get.
 *
 * @return boolean|null The option value as a boolean, or null if the option does not exist.
 */
function get_option_as_bool_if_exists( $option_name ) {
	$value = get_option( $option_name, null );

	if ( null === $value ) {
		return $value;
	}

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
 * @param string $file_path Path to the file, minus the `/wp-content/uploads/` bit.
 *                          This is the second portion returned by `Pre_Wp_Utils\prepare_request()`.
 * @return bool True if the file path is valid for the current site, false otherwise.
 */
function is_valid_path_for_site( $file_path ) {
	$is_valid = true;

	if ( is_multisite() ) {
		// If main site, don't allow access to `/sites/` subdirectories.
		if ( is_main_network() && is_main_site() ) {
			$is_valid = ! str_starts_with( $file_path, 'sites/' );
		} else {
			// Check if the file path matches the current site ID's directory.
			$base_path = sprintf( 'sites/%d/', get_current_blog_id() );
			$is_valid  = str_starts_with( $file_path, $base_path );
		}
	}

	/**
	 * Filter the result of the path validation for the current site.
	 * Allows to override the logic used to determine if a file path is valid for the current site.
	 *
	 * @param bool   $is_valid  Whether the file path is valid for the current site.
	 * @param string $file_path Path to the file, minus the `/wp-content/uploads/` bit.
	 */
	return apply_filters( 'vip_files_acl_is_valid_path_for_site', $is_valid, $file_path );
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
	$is_private  = null;

	switch ( $file_visibility ) {
		case FILE_IS_PUBLIC:
			$status_code = 202;
			$is_private  = false;
			break;

		case FILE_IS_PRIVATE_AND_ALLOWED:
			$status_code = 202;
			$is_private  = true;
			break;

		case FILE_IS_PRIVATE_AND_DENIED:
			$status_code = 403;
			$is_private  = true;
			break;

		default:
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			trigger_error( sprintf( 'Invalid file visibility (%s) ACL set for %s', $file_visibility, $file_path ), E_USER_WARNING );
			break;
	}

	http_response_code( $status_code );

	if ( null !== $is_private ) {
		$private_header_value = $is_private ? 'true' : 'false';
		header( sprintf( 'X-Private: %s', $private_header_value ) );
	}
}
