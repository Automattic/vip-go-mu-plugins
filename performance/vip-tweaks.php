<?php

/**
 * This is a list of performance tweaks that will become default for All VIP sites
 */
function wpcom_vip_enable_performance_tweaks() {
	/**
	 * Improves performance of all the wp-admin pages that load comment counts in the menu.
	 *
	 * This caches them for 30 minutes. It does not impact the per page comment count, only
	 * the total comment count that shows up in the admin menu.
	 */
	if ( function_exists( 'wpcom_vip_enable_cache_full_comment_counts' ) ) {
		wpcom_vip_enable_cache_full_comment_counts();
	}

	// This disables the adjacent_post links in the header that are almost never beneficial and are very slow to compute.
	remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0 );

	if ( function_exists( 'wpcom_vip_enable_old_slug_redirect_caching' ) ) {
		wpcom_vip_enable_old_slug_redirect_caching();
	}

	if ( function_exists( 'wpcom_vip_enable_maybe_skip_old_slug_redirect' ) ) {
		wpcom_vip_enable_maybe_skip_old_slug_redirect();
	}
}
add_action( 'after_setup_theme', 'wpcom_vip_enable_performance_tweaks' );

/**
 * Use this function to disable the loading of performance tweaks
 */
function wpcom_vip_disable_performance_tweaks() {
	remove_action( 'after_setup_theme', 'wpcom_vip_enable_performance_tweaks' );
}


add_action( 'add_attachment', 'wpcom_vip_bust_media_months_cache' );
function wpcom_vip_bust_media_months_cache( $post_id ) {
	if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
		return;
	}

	// Grab the transient to see if it needs updating
	$media_months = get_transient( 'wpcom_media_months_array' );

	// Make sure month and year exists in transient before comparing
	$cached_latest_year = ( ! empty( $media_months[0]->year ) ) ? $media_months[0]->year : '';
	$cached_latest_month = ( ! empty( $media_months[0]->month ) ) ? $media_months[0]->month : '';

	// If the transient exists, and the attachment uploaded doesn't match the first (latest) month or year in the transient, lets clear it.
	$matches_latest_year = get_the_time( 'Y', $post_id ) === $cached_latest_year;
	$matches_latest_month = get_the_time( 'n', $post_id ) === $cached_latest_month;
	if ( false !== $media_months && ( ! $matches_latest_year || ! $matches_latest_month ) ) {
		// the new attachment is not in the same month/year as the data in our transient
		delete_transient( 'wpcom_media_months_array' );
	}
}

if ( is_admin() ) {
	add_filter( 'media_library_show_video_playlist', '__return_true' );
	add_filter( 'media_library_show_audio_playlist', '__return_true' );

	add_filter( 'media_library_months_with_files', 'wpcom_vip_media_library_months_with_files' );
	function wpcom_vip_media_library_months_with_files() {
		global $wpdb;

		$months = get_transient( 'wpcom_media_months_array' );

		if ( false === $months ) {
			$months = $wpdb->get_results( $wpdb->prepare( "
            		     SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
            			FROM $wpdb->posts
            			WHERE post_type = %s
            			ORDER BY post_date DESC
        			", 'attachment' ) );
			set_transient( 'wpcom_media_months_array', $months );
		}

		return $months;
	}
}
