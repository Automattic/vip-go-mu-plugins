<?php

/**
 * Restrict access to files/media attached to unpublished content.
 */

namespace Automattic\VIP\Files\Acl\Restrict_Unpublished_Files;

use const Automattic\VIP\Files\Acl\FILE_IS_PUBLIC;
use const Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_DENIED;
use const Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_ALLOWED;

const CACHE_GROUP = 'vip-files-acl';

/**
 * Given a path determine whether the file is private or public
 *
 * @param string $file_visibility one of Automattic\VIP\Files\Acl\(FILE_IS_PUBLIC | FILE_IS_PRIVATE_AND_ALLOWED | FILE_IS_PRIVATE_AND_DENIED).
 * @param string $file_path path to file to be checked for visibility.
 * @return string one of Automattic\VIP\Files\Acl\(FILE_IS_PUBLIC | FILE_IS_PRIVATE_AND_ALLOWED | FILE_IS_PRIVATE_AND_DENIED)
 */
function check_file_visibility( $file_visibility, $file_path ) {
	// Strip the `sites/ID` part for multisite URLs, because _wp_attached_file meta doesn't store it.
	if ( 0 === strpos( $file_path, 'sites/' ) ) {
		$file_path = preg_replace( '#^sites/\d+/#', '', $file_path );
	}

	// Reverse lookup for the attachment ID
	$attachment_id = get_attachment_id_from_file_path( $file_path );

	if ( ! $attachment_id ) {
		return FILE_IS_PUBLIC;
	}

	$attachment = get_post( $attachment_id );

	if ( ! $attachment ) {
		return FILE_IS_PUBLIC;
	}

	if ( 'inherit' === $attachment->post_status && $attachment->post_parent ) {
		$parent_post = get_post( $attachment->post_parent );
		if ( 'publish' === $parent_post->post_status ) {
			return FILE_IS_PUBLIC;
		}

		$user_has_edit_access = current_user_can( 'edit_post', $parent_post );

		if ( $user_has_edit_access ) {
			return FILE_IS_PRIVATE_AND_ALLOWED;
		}
		
		return FILE_IS_PRIVATE_AND_DENIED;
	}

	return FILE_IS_PUBLIC;
}

/**
 * Variant of core's attachment_url_to_postid() function
 *
 * The core function accepts the full URL and has extra logic for reversing the host / home_uri().
 *
 * In the contexts where this is used, we are only acting on the path, so we've simplified things
 * by only accepting a file path in this function.
 *
 * @param $path (string) The path to resolve.
 *
 * @return (int) The found attachment ID, or 0 on failure.
 */
function get_attachment_id_from_file_path( $path ) {
	global $wpdb;

	$cache_key     = 'path_' . md5( $path );
	$attachment_id = wp_cache_get( $cache_key, CACHE_GROUP );
	if ( false !== $attachment_id ) {
		return $attachment_id;
	}

	$attachment_id = 0;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$results = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value = %s",
			$path
		)
	);

	if ( $results ) {
		// Use the first available result, but prefer a case-sensitive match, if exists.
		$attachment_id = $results[0]->post_id;

		if ( count( $results ) > 1 ) {
			foreach ( $results as $result ) {
				if ( $path === $result->meta_value ) {
					$attachment_id = $result->post_id;
					break;
				}
			}
		}
	}

	wp_cache_set( $cache_key, $attachment_id, CACHE_GROUP, 5 * MINUTE_IN_SECONDS );

	return $attachment_id;
}

/**
 * PURGE all attachments for a given post.
 *
 * This is to allow us to propagate visibility changes ASAP to the edges
 * and also to allow us to cache public files for much longer (which comes
 * some performance benefits).
 *
 * @param array urls Array of URLs to be purged
 * @param int post_id The ID of the post for which we're purging URLs
 *
 * @return array An array of URLs to be purged
 */
function purge_attachments_for_post( $urls, $post_id ) {
	$post = get_post( $post_id );

	if ( empty( $post ) ) {
		return $urls;
	}

	$attachment_ids = get_posts( [
		'post_parent'      => $post->ID,
		'post_type'        => 'attachment',
		'posts_per_page'   => 250,            // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- set a reasonably high limit (instead of -1 as default)
		'orderby'          => 'ID',           // For performance (instead of date as default)
		'order'            => 'ASC',
		'fields'           => 'ids',
		// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFiltersTrue
		'suppress_filters' => true,
	] );

	if ( empty( $attachment_ids ) ) {
		return $urls;
	}

	foreach ( $attachment_ids as $attachment_id ) {
		$urls[] = wp_get_attachment_url( $attachment_id );
	}

	return $urls;
}
