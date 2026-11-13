<?php

/**
 * Restrict access to files/media attached to unpublished content.
 */

namespace Automattic\VIP\Files\Acl\Restrict_Unpublished_Files;

use const Automattic\VIP\Files\Acl\FILE_IS_PUBLIC;
use const Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_DENIED;
use const Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_ALLOWED;

const CACHE_GROUP = 'vip-files-acl';

// Meta hooks that run clear_path_cache(), including the ones before an update or delete.
const PATH_CACHE_HOOKS = [ 'added_post_meta', 'update_post_meta', 'updated_post_meta', 'delete_post_meta', 'deleted_post_meta' ];

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
		if ( $parent_post && 'publish' === $parent_post->post_status ) {
			return FILE_IS_PUBLIC;
		}

		// Keep files of a deleted parent private, and check access against the attachment itself.
		$post_id_to_check     = $parent_post ? $parent_post->ID : $attachment->ID;
		$user_has_edit_access = current_user_can( 'edit_post', $post_id_to_check );

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

	$cache_key     = get_path_cache_key( $path );
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
 * Cache key for an attachment lookup by path.
 *
 * @param string $path Path relative to the uploads directory.
 * @return string
 */
function get_path_cache_key( $path ) {
	return 'path_' . md5( $path );
}

/**
 * Clear the cached attachment lookups for the paths an attachment starts or stops using.
 *
 * Otherwise a lookup cached before the change (as not found, or as an attachment that has since
 * moved or been deleted while another one still uses the path) would return an outdated result until it expires.
 *
 * The hooks after an update or delete only pass the new value, or the value given to `delete_post_meta()`,
 * so the hooks before it remember the paths in use, and those are cleared once the change is saved.
 *
 * @param int|int[] $meta_id    Meta ID, or IDs when deleted.
 * @param int       $post_id    Post ID.
 * @param string    $meta_key   Meta key.
 * @param mixed     $meta_value Meta value.
 */
function clear_path_cache( $meta_id, $post_id, $meta_key, $meta_value ) {
	static $previous_paths = [];

	if ( '_wp_attached_file' !== $meta_key ) {
		return;
	}

	if ( in_array( current_action(), [ 'update_post_meta', 'delete_post_meta' ], true ) ) {
		$previous_paths[ $post_id ] = (array) get_post_meta( $post_id, '_wp_attached_file', false );
		return;
	}

	$paths   = $previous_paths[ $post_id ] ?? [];
	$paths[] = $meta_value;
	unset( $previous_paths[ $post_id ] );

	foreach ( $paths as $path ) {
		if ( is_string( $path ) ) {
			wp_cache_delete( get_path_cache_key( $path ), CACHE_GROUP );
		}
	}
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
		// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters
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
