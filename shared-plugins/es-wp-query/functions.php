<?php

if ( ! function_exists( 'es_get_posts' ) ) {

	/**
	 * Retrieve list of latest posts or posts matching criteria.
	 *
	 * The defaults are as follows:
	 *     'numberposts' - Default is 5. Total number of posts to retrieve.
	 *     'offset' - Default is 0. See {@link WP_Query::query()} for more.
	 *     'category' - What category to pull the posts from.
	 *     'orderby' - Default is 'date', which orders based on post_date. How to order the posts.
	 *     'order' - Default is 'DESC'. The order to retrieve the posts.
	 *     'include' - See {@link WP_Query::query()} for more.
	 *     'exclude' - See {@link WP_Query::query()} for more.
	 *     'meta_key' - See {@link WP_Query::query()} for more.
	 *     'meta_value' - See {@link WP_Query::query()} for more.
	 *     'post_type' - Default is 'post'. Can be 'page', or 'attachment' to name a few.
	 *     'post_parent' - The parent of the post or post type.
	 *     'post_status' - Default is 'publish'. Post status to retrieve.
	 *
	 * @uses WP_Query::query() See for more default arguments and information.
	 * @uses ES_WP_Query
	 * @link http://codex.wordpress.org/Template_Tags/get_posts
	 *
	 * @param array $args Optional. Overrides defaults.
	 * @return array List of posts.
	 */
	function es_get_posts($args = null) {
		$defaults = array(
			'numberposts' => 5, 'offset' => 0,
			'category' => 0, 'orderby' => 'date',
			'order' => 'DESC', 'include' => array(),
			'exclude' => array(), 'meta_key' => '',
			'meta_value' =>'', 'post_type' => 'post',
			'suppress_filters' => true
		);

		$r = wp_parse_args( $args, $defaults );
		if ( empty( $r['post_status'] ) )
			$r['post_status'] = ( 'attachment' == $r['post_type'] ) ? 'inherit' : 'publish';
		if ( ! empty($r['numberposts']) && empty($r['posts_per_page']) )
			$r['posts_per_page'] = $r['numberposts'];
		if ( ! empty($r['category']) )
			$r['cat'] = $r['category'];
		if ( ! empty($r['include']) ) {
			$incposts = wp_parse_id_list( $r['include'] );
			$r['posts_per_page'] = count($incposts);  // only the number of posts included
			$r['post__in'] = $incposts;
		} elseif ( ! empty($r['exclude']) )
			$r['post__not_in'] = wp_parse_id_list( $r['exclude'] );

		$r['ignore_sticky_posts'] = true;
		$r['no_found_rows'] = true;

		$get_posts = new ES_WP_Query;
		return $get_posts->query( $r );

	}

}


/**
 * Loads one of the included adapters.
 *
 * @param  string $adapter Which adapter to include. Currently allows searchpress, wpcom-vip, and travis.
 * @return void
 */
function es_wp_query_load_adapter( $adapter ) {
	if ( in_array( $adapter, array( 'searchpress', 'wpcom-vip', 'travis' ) ) ) {
		require_once( ES_WP_QUERY_PATH . "/adapters/{$adapter}.php" );
	}
}