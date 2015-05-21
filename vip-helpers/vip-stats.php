<?php

/**
 * Get the WP.com top posts
 *
 * Reproduces the result of /wp-admin/index.php?page=stats&blog=<blogid>&view=postviews&numdays=30&summarize returning the top 10 posts if called with default params.
 *
 * @author tott
 * @param int $num_days The length of the desired time frame. Default is 30. Maximum 90 days.
 * @param int $limit The maximum number of records to return. Default is 10. Maximum 100.
 * @param string $end_date The last day of the desired time frame. Format is 'Y-m-d' (e.g. 2007-05-01) and default is UTC date.
 * @return array Result as array.
 */
function wpcom_vip_top_posts_array( $num_days = 30, $limit = 10, $end_date = false ) {
	// @todo Needs ported to Jetpack stats

	return array();
}

/**
 * Get the number of pageviews for a given post ID.
 *
 * Default to the current post.
 *
 * @param int $post_id Optional. The post ID to fetch stats for. Defaults to the $post global's value.
 * @param int $num_days Optional. How many days to go back to include in the stats. Default is 1. Maximum 90 days.
 * @param string $end_data Optional. The last day of the desired time frame. Format is 'Y-m-d' (e.g. 2007-05-01) and default is today's UTC date.
 * @return int|false Number of pageviews or false on error.
 */
function wpcom_vip_get_post_pageviews( $post_id = null, $num_days = 1, $end_date = false ) {
	// @todo Needs ported over to Jetpack stats

	return false;
}
