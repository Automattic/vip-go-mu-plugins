<?php

/**
 * Get the top posts by page view, using Jetpack's Stats module.
 *
 * Reproduces the result of /wp-admin/index.php?page=stats&blog=<blogid>&view=postviews&numdays=30&summarize
 * returning the top 10 posts if called with default params.
 *
 * @param int $num_days The length of the desired time frame. Default is 30. Maximum 90 days.
 * @param int $limit The maximum number of records to return. Default is 10. Maximum 100.
 * @param string|bool $end_date The last day of the desired time frame. Format is 'Y-m-d' (e.g. 2007-05-01) and default is UTC date.
 *
 * @return array {
 *      An array of post view data, each post as an array
 *
 *      array {
 *          The post view data for a single post
 *
 *          @type int    $post_id        The ID of the post (note 0 is used for the homepage)
 *          @type string $post_title     The title of the post
 *          @type string $post_permalink The permalink for the post
 *          @type int    $views          The number of views for the post within the $num_days specified
 *      }
 * }
 */
function wpcom_vip_top_posts_array( $num_days = 30, $limit = 10, $end_date = false ) {
	// Check Jetpack is present and active
	if ( class_exists( 'Jetpack' ) && Jetpack::is_active() && Jetpack::is_module_active( 'stats' ) ) {
		
		// WordPress.com stats defaults to current UTC date, default to site's local date instead
		if ( ! $end_date ) {
			$end_date = current_datetime()->format( 'Y-m-d' );
		}
		
		$args = array(
			'days'  => $num_days,
			'limit' => 100, // Due to caching, we request max limit and only return requested $limit below. See PR 1998
			'end'   => $end_date,
		);

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.stats_get_csv_stats_get_csv
		$posts = stats_get_csv( 'postviews', $args );
	} else {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
		trigger_error( 'Cannot call wpcom_vip_top_posts_array() without both Jetpack and the Jetpack Stats module active.', E_USER_WARNING );
		return array();
	}

	foreach ( $posts as & $post ) {
		$post['post_id'] = absint( $post['post_id'] );
		$post['views']   = absint( $post['views'] );
	}
	$posts = array_slice( $posts, 0, $limit );
	return $posts;
}

/**
 * Get the number of pageviews for a given post ID (defaults to the current post)
 *
 * Note that it is not currently possible to retrieve the post views for
 * the homepage using this function
 *
 * @param int          $post_id   Optional. The post ID to fetch stats for. Defaults to the $post global's value.
 * @param int          $num_days  Optional. How many days to go back to include in the stats. Default is 1. Maximum 90 days.
 * @param string|bool  $end_data  Optional. The last day of the desired time frame. Format is 'Y-m-d' (e.g. 2007-05-01) and default is today's UTC date.
 * @return int|false Number of pageviews or false on error.
 */
function wpcom_vip_get_post_pageviews( $post_id = null, $num_days = 1, $end_date = false ) {
	// Check Jetpack is present and active
	if ( class_exists( 'Jetpack' ) && Jetpack::is_active() && Jetpack::is_module_active( 'stats' ) ) {
		$args = array(
			'post_id'  => $post_id,
			'num_days' => $num_days,
			'end_date' => $end_date,
		);

		// Default post_id to the current post ID, and check it's an int
		if ( is_null( $args['post_id'] ) ) {
			$args['post_id'] = get_the_ID();
		}
		$args['post_id'] = absint( $args['post_id'] );
		if ( empty( $args['post_id'] ) ) {
			return false;
		}

		// Ensure num_days is least 1, but no more than 90
		$args['num_days'] = max( 1, min( 90, absint( $args['num_days'] ) ) );

		$cache_key = 'views_' . $args['post_id'] . '_' . $args['num_days'] . '_' . $args['end_date'];

		$views = wp_cache_get( $cache_key, 'vip_stats' );

		if ( false === $views ) {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.stats_get_csv_stats_get_csv
			$posts = stats_get_csv( 'postviews', $args );
			$views = $posts[0]['views'] ?? 0;
			wp_cache_set( $cache_key, $views, 'vip_stats', 3600 );
		}
	} else {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
		trigger_error( 'Cannot call wpcom_vip_get_post_pageviews() without both Jetpack and the Jetpack Stats module active.', E_USER_WARNING );
		return 0;
	}

	return absint( $views );
}
