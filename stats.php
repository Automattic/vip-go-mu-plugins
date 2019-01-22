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
	add_action( 'async_transition_post_status', __NAMESPACE__ . '\track_publish_post', 9999, 2 );
	add_filter( 'wp_handle_upload', __NAMESPACE__ . '\handle_file_upload', 9999, 2 );
	add_filter( 'wp_delete_file', __NAMESPACE__ . '\handle_file_delete', 9999, 1 );
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

	$pixel = add_query_arg( array(
		'v'                     => 'wpcom-no-pv',
		'x_vip-go-publish-post' => FILES_CLIENT_SITE_ID,
	), 'http://pixel.wp.com/b.gif' );

	wp_remote_get( $pixel, array(
		'blocking' => false,
		'timeout'  => 1,
	) );
}

/**
 * Count uploaded files
 */
function handle_file_upload( $upload, $context ) {
	track_file_upload();

	return $upload;
}

function track_file_upload() {
	$using_streams = false;
	if ( defined( 'VIP_FILESYSTEM_USE_STREAM_WRAPPER' ) ) {
		$using_streams = (bool) VIP_FILESYSTEM_USE_STREAM_WRAPPER;
	}

	$stat_group = $using_streams ? 'stream' : 'a8c-files';

	$pixel = add_query_arg( array(
		'v'                     => 'wpcom-no-pv',
		'x_vip-go-upload-via' => $stat_group,
		'x_vip-go-upload-site' => FILES_CLIENT_SITE_ID,
	), 'http://pixel.wp.com/b.gif' );

	wp_remote_get( $pixel, array(
		'blocking' => false,
		'timeout'  => 1,
	) );
}

function handle_file_delete( $file ) {
	track_file_delete();

	return $file;
}

/**
 * Count deleted files
 */
function track_file_delete() {
	$using_streams = false;
	if ( defined( 'VIP_FILESYSTEM_USE_STREAM_WRAPPER' ) ) {
		$using_streams = (bool) VIP_FILESYSTEM_USE_STREAM_WRAPPER;
	}

	$stat_group = $using_streams ? 'stream' : 'a8c-files';

	$pixel = add_query_arg( array(
		'v'                     => 'wpcom-no-pv',
		'x_vip-go-delete-via' => $stat_group,
		'x_vip-go-delete-site' => FILES_CLIENT_SITE_ID,
	), 'http://pixel.wp.com/b.gif' );

	wp_remote_get( $pixel, array(
		'blocking' => false,
		'timeout'  => 1,
	) );
}
