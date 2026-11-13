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

	// Reverse lookup for the attachment ID. An image can also be a file derived from an attachment's main file.
	$attachment_id = 'image' === wp_ext2type( pathinfo( $file_path, PATHINFO_EXTENSION ) )
		? get_attachment_id_from_image_path( $file_path )
		: get_attachment_id_from_file_path( $file_path );

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

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$results = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value = %s",
			$path
		)
	);

	$attachment_id = pick_attachment_id( $results, $path );

	wp_cache_set( $cache_key, $attachment_id, CACHE_GROUP, 5 * MINUTE_IN_SECONDS );

	return $attachment_id;
}

/**
 * Pick the attachment from rows whose `_wp_attached_file` matches a path.
 *
 * @param object[] $results Rows with `post_id` and `meta_value`.
 * @param string   $path    Path relative to the uploads directory.
 * @return int|string The attachment ID, or 0 if there are no rows.
 */
function pick_attachment_id( $results, $path ) {
	if ( ! $results ) {
		return 0;
	}

	// Use the first available result, but prefer a case-sensitive match, if exists.
	foreach ( $results as $result ) {
		if ( $path === $result->meta_value ) {
			return $result->post_id;
		}
	}

	return $results[0]->post_id;
}

/**
 * Find the attachment an image belongs to, as its main file or as a file derived from it.
 *
 * Next to the file in `_wp_attached_file`, core keeps the original of a scaled, rotated or converted
 * image, copies replaced in the image editor, and generated sizes. Their names are the same base name
 * plus suffixes, e.g. photo.jpg, photo-rotated.jpg, photo-rotated-e1700000000123-300x200.jpg.
 *
 * @param string $path Path relative to the uploads directory.
 * @return int|string The attachment ID, or 0 if the path isn't one of an attachment's files.
 */
function get_attachment_id_from_image_path( $path ) {
	global $wpdb;

	$cache_key     = get_path_cache_key( $path, 'image' );
	$attachment_id = wp_cache_get( $cache_key, CACHE_GROUP );
	if ( false !== $attachment_id ) {
		return $attachment_id;
	}

	// The main file is named after this file's name with core's suffixes stripped: first a size, then up to
	// three of edits, rotation, scaling, temporary thumbnails and PDF previews. Try each step, as a name can end
	// in such a suffix itself. A stem stripped of -e<n>, -rotated or -scaled only matches names that the next,
	// shorter stem's patterns already do, so it's skipped.
	$filename = wp_basename( $path );
	$dir      = substr( $path, 0, -strlen( $filename ) );
	$name     = pathinfo( $filename, PATHINFO_FILENAME );
	$stem     = preg_replace( '/-\d+x\d+$/', '', $name );
	$stems    = [ $name ];
	$strips   = 0;
	while ( $strips++ < 3 && preg_match( '/^(.+)-(e\d+|rotated|scaled|temp|pdf(?:-\d+)?)$/', $stem, $matches ) ) {
		if ( 'temp' === $matches[2] || 0 === strpos( $matches[2], 'pdf' ) ) {
			$stems[] = $stem;
		}
		$stem = $matches[1];
	}
	$stems[] = $stem;

	$patterns = [];
	foreach ( array_unique( $stems ) as $stem ) {
		$like = $wpdb->esc_like( $dir . $stem );
		array_push( $patterns, $like . '.%', $like . '-scaled%', $like . '-rotated%', $like . '-e%' );
	}
	$like_sql = implode( ' OR ', array_fill( 0, count( $patterns ), 'meta_value LIKE %s' ) );

	// One scan finds the attachment whose main file this is, and any main file it could be derived from.
	// Checking the directory first keeps the pattern checks to files in the same directory.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $like_sql only holds placeholders.
	$results = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_id, meta_value, meta_value = %s AS is_main FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s AND ( meta_value = %s OR $like_sql )",
			$path,
			$wpdb->esc_like( $dir ) . '%',
			$path,
			...$patterns
		)
	);
	// phpcs:enable

	$main_files    = [];
	$candidate_ids = [];
	foreach ( $results as $result ) {
		if ( $result->is_main ) {
			$main_files[] = $result;
		} else {
			$candidate_ids[] = $result->post_id;
		}
	}

	$attachment_id = pick_attachment_id( $main_files, $path );
	if ( ! $attachment_id ) {
		update_meta_cache( 'post', $candidate_ids );

		foreach ( $candidate_ids as $candidate_id ) {
			if ( in_array( $path, get_attachment_file_paths( $candidate_id ), true ) ) {
				$attachment_id = (int) $candidate_id;
				break;
			}
		}
	}

	wp_cache_set( $cache_key, $attachment_id, CACHE_GROUP, 5 * MINUTE_IN_SECONDS );

	return $attachment_id;
}

/**
 * Paths of an attachment's files, relative to the uploads directory: its main file first, then its
 * original, copies replaced in the image editor and generated sizes.
 *
 * @param int $attachment_id Attachment ID.
 * @return string[]
 */
function get_attachment_file_paths( $attachment_id ) {
	$file = get_post_meta( $attachment_id, '_wp_attached_file', true );
	if ( ! $file || ! is_string( $file ) ) {
		return [];
	}

	$metadata     = get_post_meta( $attachment_id, '_wp_attachment_metadata', true );
	$metadata     = is_array( $metadata ) ? $metadata : [];
	$sizes        = $metadata['sizes'] ?? [];
	$backup_sizes = get_post_meta( $attachment_id, '_wp_attachment_backup_sizes', true );

	$names = array_merge(
		[ $file, $metadata['original_image'] ?? '' ],
		is_array( $sizes ) ? array_column( $sizes, 'file' ) : [],
		is_array( $backup_sizes ) ? array_column( $backup_sizes, 'file' ) : []
	);

	$dir   = substr( $file, 0, -strlen( wp_basename( $file ) ) );
	$paths = [];
	foreach ( $names as $name ) {
		// Skip VIP's dynamic sizes (photo.jpg?resize=...), which aren't files but can end up in the metadata.
		if ( is_string( $name ) && '' !== $name && false === strpos( $name, '?' ) ) {
			$paths[] = $dir . wp_basename( $name );
		}
	}

	return array_values( array_unique( $paths ) );
}

/**
 * Cache key for an attachment lookup by path.
 *
 * @param string $path Path relative to the uploads directory.
 * @param string $type `path` for the lookup by main file, `image` for the lookup of an image.
 * @return string
 */
function get_path_cache_key( $path, $type = 'path' ) {
	return $type . '_' . md5( $path );
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

	// The other files (original, edited copies, sizes) only need purging when they become private, as only
	// public answers are cached at the edges. Purging them on every update would evict them for nothing.
	$files_are_private = 'publish' !== $post->post_status;

	$derived_urls = [];
	foreach ( $attachment_ids as $attachment_id ) {
		$url    = wp_get_attachment_url( $attachment_id );
		$urls[] = $url;

		if ( $files_are_private ) {
			// They sit next to the attachment's main file.
			foreach ( array_slice( get_attachment_file_paths( $attachment_id ), 1 ) as $path ) {
				$derived_urls[] = trailingslashit( dirname( $url ) ) . wp_basename( $path );
			}
		}
	}

	queue_derived_file_purges( $derived_urls );

	return $urls;
}

/**
 * Queue derived files' URLs to purge after everything else in the request.
 *
 * The cache manager purges a limited number of URLs per request, keeping the first ones queued. Handing
 * it these last, as it's about to send its purges, means that if it has to drop URLs, it drops these
 * before any page or main file, including other posts'.
 *
 * @param string[] $urls URLs to purge.
 * @param bool     $send Whether to hand the URLs queued so far to the cache manager.
 */
function queue_derived_file_purges( array $urls, $send = false ) {
	static $queued = [];

	$queued = array_merge( $queued, $urls );
	if ( ! $send ) {
		return;
	}

	foreach ( array_unique( $queued ) as $url ) {
		wpvip_purge_edge_cache_for_url( $url );
	}

	$queued = [];
}

/**
 * Hand the queued derived files' URLs to the cache manager, which is about to send its purges.
 */
function send_derived_file_purges() {
	queue_derived_file_purges( [], true );
}
