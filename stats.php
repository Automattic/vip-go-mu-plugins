<?php

/**
 * Plugin Name: VIP Stats
 * Description: Basic VIP stats functions.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace Automattic\VIP\Stats;

// Limit tracking to production
if ( true === WPCOM_IS_VIP_ENV && false === WPCOM_SANDBOXED ) {
	add_action( 'transition_post_status', __NAMESPACE__ . '\track_publish_post', 9999, 2 );
	add_filter( 'wp_handle_upload', __NAMESPACE__ . '\handle_file_upload', 9999 );
	// Hook early because overrides in a8c-files and stream wrapper return empty.
	// Which makes it hard to differentiate between full size and thumbs.
	add_action( 'wp_delete_file', __NAMESPACE__ . '\handle_file_delete', -1, 1 );

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log( 'registering track_vip_xmlrpc_auth_type' );

	// Hook XML-RPC authentication type tracking
	add_filter( 'authenticate', __NAMESPACE__ . '\track_vip_xmlrpc_auth_type', 30, 3 ); // core authenticates on 20
}

/**
 * Count publish events regardless of post type
 */
function track_publish_post( $new_status, $old_status ) {
	if ( defined( 'WP_IMPORTING' ) && true === WP_IMPORTING ) {
		return;
	}

	if ( 'publish' !== $new_status || 'publish' === $old_status ) {
		return;
	}

	send_pixel([
		'vip-go-publish-post' => FILES_CLIENT_SITE_ID,
	]);
}

/**
 * Count uploaded files
 */
function handle_file_upload( $upload ) {
	track_file_upload();

	return $upload;
}

function track_file_upload() {
	if ( defined( 'WP_IMPORTING' ) && true === WP_IMPORTING ) {
		return;
	}

	$using_streams = false;
	if ( defined( 'VIP_FILESYSTEM_USE_STREAM_WRAPPER' ) ) {
		$using_streams = (bool) VIP_FILESYSTEM_USE_STREAM_WRAPPER;
	}

	$stat_group = $using_streams ? 'stream' : 'a8c-files';

	send_pixel( [
		'vip-go-file-upload-via'         => $stat_group,
		'vip-go-file-upload-by-site'     => FILES_CLIENT_SITE_ID,
		'vip-go-file-upload-by-site-via' => sprintf( '%s_%s', FILES_CLIENT_SITE_ID, $stat_group ),
		'vip-go-file-action'             => 'upload',
	] );
}

function handle_file_delete( $file ) {
	if ( empty( $file ) ) {
		return $file;
	}

	// TODO: We can replace most of this with a custom action once we've transitioned over to streams.
	// Hack: Don't bother tracking for thumbs and other sizes since those don't actually get deleted.
	// Thumbs will have the form `/path/to/file.jpg?w=123` (i.e. with a query string).
	if ( false !== strpos( $file, '?' ) ) {
		return $file;
	}

	// Only track once for each deleted file since this might fire multiple times per file.
	static $deleted_uris = [];
	if ( ! in_array( $file, $deleted_uris, true ) ) {
		track_file_delete();
		$deleted_uris[] = $file;
	}

	return $file;
}

/**
 * Count deleted files
 */
function track_file_delete() {
	if ( defined( 'WP_IMPORTING' ) && true === WP_IMPORTING ) {
		return;
	}

	$using_streams = false;
	if ( defined( 'VIP_FILESYSTEM_USE_STREAM_WRAPPER' ) ) {
		$using_streams = (bool) VIP_FILESYSTEM_USE_STREAM_WRAPPER;
	}

	$stat_group = $using_streams ? 'stream' : 'a8c-files';

	send_pixel( [
		'vip-go-file-delete-via'         => $stat_group,
		'vip-go-file-delete-by-site'     => FILES_CLIENT_SITE_ID,
		'vip-go-file-delete-by-site-via' => sprintf( '%s_%s', FILES_CLIENT_SITE_ID, $stat_group ),
		'vip-go-file-action'             => 'delete',
	] );
}

/**
 * Tracks the authentication type used during successful XML-RPC requests.
 */
function track_vip_xmlrpc_auth_type( $user, $username, $password ) {
	// Only proceed if it's an XML-RPC request
	if ( ! ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
		return $user;
	}

	// We are only interested in successful authentication events.
	if ( is_wp_error( $user ) || ! ( $user instanceof \WP_User ) ) {
		return $user;
	}

	// Default to 'user_pass', assuming standard authentication if not determined otherwise.
	$auth_type = 'user_pass';

	// Check if the provided password validates as an Application Password for this user.
	if ( function_exists( 'wp_validate_application_password' ) ) {
		$validated_app_pass = wp_validate_application_password( $user, $password );

		if ( $validated_app_pass ) {
			$auth_type = 'app_pass';
		}
	}

	// Send the tracking pixel
	$site_id = 0;
	if ( defined( 'FILES_CLIENT_SITE_ID' ) && FILES_CLIENT_SITE_ID ) {
		$site_id = FILES_CLIENT_SITE_ID;
	}

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log( 'track_vip_xmlrpc_auth_type: ' . $auth_type . ' ' . $site_id );

	send_pixel( [
		'vip-go-xmlrpc-auth-type' => $auth_type,
		'vip-go-xmlrpc-site-id'   => $site_id,
	] );

	// Always return the original $user object to avoid disrupting authentication.
	return $user;
}

function send_pixel( $stats ) {
	$query_args = [
		'v' => 'wpcom-no-pv',
	];

	foreach ( $stats as $name => $group ) {
		$query_param = rawurlencode( 'x_' . $name );
		$query_value = rawurlencode( $group );

		$query_args[ $query_param ] = $query_value;
	}

	$pixel = add_query_arg( $query_args, 'http://pixel.wp.com/b.gif' );

	// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get
	wp_remote_get( $pixel, array(
		'blocking' => false,
		'timeout'  => 1,
	) );
}

/**
 * Add extra hp=vip to allow for better tracking via gl
 */
add_filter( 'stats_array', __NAMESPACE__ . '\\add_hp' );
add_filter( 'jetpack_stats_footer_amp_data', __NAMESPACE__ . '\\add_hp' );
function add_hp( $data ) {
	$data['hp'] = 'vip';
	return $data;
}
