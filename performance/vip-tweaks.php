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

/**
 * Improves performance of all the post listing pages that display the months dropdown filter.
 * A unique transient is created for each post type.
 */
if ( is_admin() ) {

	add_filter( 'pre_months_dropdown_query', 'wpcom_vip_pre_months_dropdown_query', 10, 2 );
	function wpcom_vip_pre_months_dropdown_query( $months, $post_type ) {
		global $wpdb;
		
		// Don't set a transient for trashed post.
		if ( isset( $_GET['post_status'] ) && "trash" === $_GET['post_status'] ) {
			return __return_null();
		}

		$months = get_transient( 'wpcom_vip_pre_months_dropdown_query_'.$post_type );

		if ( false === $months ) {

			$extra_checks = "AND post_status != 'auto-draft'";
			if ( ! isset( $_GET['post_status'] ) || 'trash' !== $_GET['post_status'] ) {
				$extra_checks .= " AND post_status != 'trash'";
			} elseif ( isset( $_GET['post_status'] ) ) {
				$extra_checks = $wpdb->prepare( ' AND post_status = %s', $_GET['post_status'] );
			}

			$months = $wpdb->get_results(
				$wpdb->prepare(
					"
					SELECT DISTINCT YEAR( post_date ) AS year, MONTH( post_date ) AS month
					FROM $wpdb->posts
					WHERE post_type = %s
					$extra_checks
					ORDER BY post_date DESC
					",
					$post_type
				)
			);
			set_transient( 'wpcom_vip_pre_months_dropdown_query_'.$post_type, $months );
		}
		return $months;
	}
}
/**
 * Bust the months dropdown cache when needed.
 */
add_action( 'save_post', 'wpcom_vip_bust_post_months_cache', 10, 2 );
function wpcom_vip_bust_post_months_cache( $post_id, $post ) {
	if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
		return;
	}

	// Reset the transient if we are untrashing a post
	if( "untrash" === $_GET['action'] ) {
		delete_transient( 'wpcom_vip_pre_months_dropdown_query_'.$post->post_type );
		return;
	}

	// Grab the transient to see if it needs updating
	$post_months = get_transient( 'wpcom_vip_pre_months_dropdown_query_'.$post->post_type );

	// Make sure month and year exists in transient before comparing
	$cached_latest_year = ( ! empty( $post_months[0]->year ) ) ? $post_months[0]->year : '';
	$cached_latest_month = ( ! empty( $post_months[0]->month ) ) ? $post_months[0]->month : '';

	// If the transient exists, and the post doesn't match the first (latest) month or year in the transient, lets clear it.
	$matches_latest_year = get_the_time( 'Y', $post_id ) === $cached_latest_year;
	$matches_latest_month = get_the_time( 'n', $post_id ) === $cached_latest_month;
	if ( false !== $post_months && ( ! $matches_latest_year || ! $matches_latest_month ) ) {
		// the post is not in the same month/year as the data in our transient
		delete_transient( 'wpcom_vip_pre_months_dropdown_query_'.$post->post_type );
	}
}