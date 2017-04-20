<?php

namespace Automattic\VIP\Performance;

// Prevent core from doing filename lookups for media search.
// https://core.trac.wordpress.org/ticket/39358
add_action( 'pre_get_posts', function() {
	remove_filter( 'posts_clauses', '_filter_query_attachment_filenames' );
} );

/**
 * Stores query for audio items in a transient so it doesn't have to run on every page load
 *
 * @param null $show_audio_playlist
 * @return mixed|null|string
 */
function cache_has_audio_query( $show_audio_playlist ) {

	if ( false === ( $show_audio_playlist = get_transient( 'media_library_show_audio_playlist' ) ) ) {

		global $wpdb;

		$show_audio_playlist = $wpdb->get_var( " 
			SELECT ID 
			FROM $wpdb->posts 
			WHERE post_type = 'attachment' 
			AND post_mime_type LIKE 'audio%' 
			LIMIT 1 
		" );

		set_transient( 'media_library_show_audio_playlist', $show_audio_playlist, HOUR_IN_SECONDS + rand( 0, 10 * MINUTE_IN_SECONDS ) );

	}

	return $show_audio_playlist;

}
add_filter( 'media_library_show_audio_playlist', __NAMESPACE__ . '\cache_has_audio_query' );

/**
 * Stores query for video items in a transient so it doesn't have to run on every page load
 *
 * @param null $show_video_playlist
 * @return mixed|null|string
 */
function cache_has_video_query( $show_video_playlist ) {

	if ( false === ( $show_video_playlist = get_transient( 'media_library_show_video_playlist' ) ) ) {

		global $wpdb;

		$show_video_playlist = $wpdb->get_var( " 
			SELECT ID 
			FROM $wpdb->posts 
			WHERE post_type = 'attachment' 
			AND post_mime_type LIKE 'video%' 
			LIMIT 1 
		" );

		set_transient( 'media_library_show_video_playlist', $show_video_playlist, HOUR_IN_SECONDS + rand( 0, 10 * MINUTE_IN_SECONDS ) );

	}

	return $show_video_playlist;

}
add_filter( 'media_library_show_video_playlist', __NAMESPACE__ . '\cache_has_video_query' );

/**
 * Stores query for months that have media items so it doesn't have to run on every page load
 *
 * @param null $months
 * @return array|mixed|null|object
 */
function cache_media_months_query( $months ) {

	if ( false === ( $months = get_transient( 'media_library_months' ) ) ) {

		global $wpdb;

		$months = $wpdb->get_results( $wpdb->prepare( " 
			SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month 
			FROM $wpdb->posts 
			WHERE post_type = %s 
			ORDER BY post_date DESC 
		", 'attachment' ) );

		set_transient( 'media_library_months', $months, HOUR_IN_SECONDS + rand( 0, 10 * MINUTE_IN_SECONDS ) );

	}

	return $months;

}
add_filter( 'media_library_months_with_files', __NAMESPACE__ . '\cache_media_months_query' );
