<?php

/**
 * Plugin Name: VIP Stats
 * Description: Basic VIP stats functions.
 * Author: Automattic
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed

namespace Automattic\VIP\Stats;

use Automattic\VIP\Telemetry\Tracks;

// Limit tracking to production
if ( true === WPCOM_IS_VIP_ENV && false === WPCOM_SANDBOXED ) {
	add_action( 'transition_post_status', __NAMESPACE__ . '\track_publish_post', 9999, 2 );
	add_filter( 'wp_handle_upload', __NAMESPACE__ . '\handle_file_upload', 9999 );
	// Hook early because overrides in a8c-files and stream wrapper return empty.
	// Which makes it hard to differentiate between full size and thumbs.
	add_action( 'wp_delete_file', __NAMESPACE__ . '\handle_file_delete', -1, 1 );
	// Determine the password type and store it in XML_RPC_Auth_Tracker
	add_filter( 'authenticate', __NAMESPACE__ . '\determine_xmlrpc_password_type', 30, 3 ); // core authenticates on 20
	// Send the telemetry event on xmlrpc_call
	add_action( 'xmlrpc_call', __NAMESPACE__ . '\record_xmlrpc_auth_telemetry_on_xmlrpc_call', 10, 1 );
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
function determine_xmlrpc_password_type( $user, $username, $password ) {
	// Only proceed if it's an XML-RPC request
	if ( ! ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
		return $user;
	}

	// We are only interested in successful authentication events.
	if ( is_wp_error( $user ) || ! ( $user instanceof \WP_User ) ) {
		return $user;
	}

	$password_type = 'none';

	if ( empty( $password ) ) {
		$password_type = 'cookie';  
	} elseif ( wp_check_password( $password, $user->user_pass ) ) {
		$password_type = 'user_pass';
	} elseif ( wp_is_application_passwords_available() ) {
		/*
		* Strips out anything non-alphanumeric. This is so passwords can be used with
		* or without spaces to indicate the groupings for readability.
		*
		* Generated application passwords are exclusively alphanumeric.
		*/
		$password = preg_replace( '/[^a-z\d]/i', '', $password );

		// Check if the provided password validates as an Application Password for this user.
		$hashed_passwords = \WP_Application_Passwords::get_user_application_passwords( $user->ID );

		foreach ( $hashed_passwords as $key => $item ) {
			$password_match = false;
			// Use the dedicated method if available (WP 6.8+), otherwise fall back to wp_check_password.
			if ( method_exists( '\WP_Application_Passwords', 'check_password' ) ) {
				$password_match = \WP_Application_Passwords::check_password( $password, $item['password'] );
			} else {
				// phpcs:ignore WordPress.WP.DeprecatedFunctions.wp_check_password_instead_of_hash_equals -- Needed for WP < 6.8 compatibility
				$password_match = wp_check_password( $password, $item['password'] );
			}

			if ( $password_match ) {
				$password_type = 'app_pass';
				break;
			}
		}
	}

	XML_RPC_Auth_Tracker::$xmlrpc_password_type = $password_type;

	// Always return the original $user object to avoid disrupting authentication.
	return $user;
}

function record_xmlrpc_auth_telemetry_on_xmlrpc_call( $xmlrpc_method ) {
	if ( ! defined( 'XMLRPC_REQUEST' ) || ! XMLRPC_REQUEST ) {
		return;
	}
	if ( ! is_user_logged_in() ) {
		return;
	}

	XML_RPC_Auth_Tracker::track( $xmlrpc_method );
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

// We can't call `$telemetry->record_event()` during `authenticate` because `wp_get_current_user()` won't return the correct user.
// So we'll use a two-step approach:
// 1. On `authenticate`, set a static variable with the password type.
// 2. On `xmlrpc_call`, call `track()` to send the telemetry event.
class XML_RPC_Auth_Tracker {
	public static $xmlrpc_password_type = 'none';
	public static $tracks_instance      = null;

	public static function track( $xmlrpc_method ) {
		if ( 'none' === static::$xmlrpc_password_type ) {
			return;
		}
		if ( ! static::$tracks_instance ) {
			static::$tracks_instance = new Tracks();
		}

		// Send telemetry event
		static::$tracks_instance->record_event( 'xmlrpc_authentication', [
			'password_type' => static::$xmlrpc_password_type,
			'method'        => $xmlrpc_method,
			'site_id'       => defined( 'FILES_CLIENT_SITE_ID' ) ? FILES_CLIENT_SITE_ID : 0,
		] );
	}
}
