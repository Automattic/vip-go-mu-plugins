<?php
/**
 * This file contains a bunch of helper functions that handle add caching to core WordPress functions.
 */

/**
 * Cached version of get_category_by_slug.
 *
 * @param string $slug Category slug
 * @return object|null|bool Term Row from database. Will return null if $slug doesn't match a term. If taxonomy does not exist then false will be returned.
 * @link http://vip.wordpress.com/documentation/uncached-functions/ Uncached Functions
 */
function wpcom_vip_get_category_by_slug( $slug ) {
	return wpcom_vip_get_term_by( 'slug', $slug, 'category' );
}

/**
 * Cached version of get_term_by.
 *
 * Many calls to get_term_by (with name or slug lookup) across on a single pageload can easily add up the query count.
 * This function helps prevent that by adding a layer of caching.
 *
 * @param string $field Either 'slug', 'name', or 'id'
 * @param string|int $value Search for this term value
 * @param string $taxonomy Taxonomy Name
 * @param string $output Optional. Constant OBJECT, ARRAY_A, or ARRAY_N
 * @param string $filter Optional. Default is 'raw' or no WordPress defined filter will applied.
 * @return mixed|null|bool Term Row from database in the type specified by $filter. Will return false if $taxonomy does not exist or $term was not found.
 * @link http://vip.wordpress.com/documentation/uncached-functions/ Uncached Functions
 */
function wpcom_vip_get_term_by( $field, $value, $taxonomy, $output = OBJECT, $filter = 'raw' ) {
	// ID lookups are cached
	if ( 'id' == $field )
		return get_term_by( $field, $value, $taxonomy, $output, $filter );

	$cache_key = $field . '|' . $taxonomy . '|' . md5( $value );
	$term_id = wp_cache_get( $cache_key, 'get_term_by' );

	if ( false === $term_id ) {
		$term = get_term_by( $field, $value, $taxonomy );
		if ( $term && ! is_wp_error( $term ) )
			wp_cache_set( $cache_key, $term->term_id, 'get_term_by' );
		else
			wp_cache_set( $cache_key, 0, 'get_term_by' ); // if we get an invalid value, let's cache it anyway
	} else {
		$term = get_term( $term_id, $taxonomy, $output, $filter );
	}

	if ( is_wp_error( $term ) )
		$term = false;

	return $term;
}

/**
 * Properly clear wpcom_vip_get_term_by() cache when a term is updated
 */
add_action( 'edit_terms', 'wp_flush_get_term_by_cache', 10, 2 );
function wp_flush_get_term_by_cache( $term_id, $taxonomy ){
	$term = get_term_by( 'id', $term_id, $taxonomy );
	if ( ! $term ) {
		return;
	}
	foreach( array( 'name', 'slug' ) as $field ) {
		$cache_key = $field . '|' . $taxonomy . '|' . md5( $term->$field );
		$cache_group = 'get_term_by';
		wp_cache_delete( $cache_key, $cache_group );
	}
}

/**
 * Cached version of term_exists()
 *
 * Term exists calls can pile up on a single pageload.
 * This function adds a layer of caching to prevent lots of queries.
 *
 * @param int|string $term The term to check can be id, slug or name.
 * @param string $taxonomy The taxonomy name to use
 * @param int $parent Optional. ID of parent term under which to confine the exists search.
 * @return mixed Returns null if the term does not exist. Returns the term ID
 *               if no taxonomy is specified and the term ID exists. Returns
 *               an array of the term ID and the term taxonomy ID the taxonomy
 *               is specified and the pairing exists.
 */

function wpcom_vip_term_exists( $term, $taxonomy = '', $parent = null ) {
	//If $parent is not null, let's skip the cache.
	if ( null !== $parent ){
		return term_exists( $term, $taxonomy, $parent );
	}

	if ( ! empty( $taxonomy ) ){
		$cache_key = $term . '|' . $taxonomy;
	}else{
		$cache_key = $term;
	}

	$cache_value = wp_cache_get( $cache_key, 'term_exists' );

	//term_exists frequently returns null, but (happily) never false
	if ( false  === $cache_value ) {
		$term_exists = term_exists( $term, $taxonomy );
		wp_cache_set( $cache_key, $term_exists, 'term_exists' );
	}else{
		$term_exists = $cache_value;
	}

	if ( is_wp_error( $term_exists ) )
		$term_exists = null;

	return $term_exists;
}

/**
 * Properly clear wpcom_vip_term_exists() cache when a term is updated
 */
add_action( 'delete_term', 'wp_flush_term_exists', 10, 4 );
function wp_flush_term_exists( $term, $tt_id, $taxonomy, $deleted_term ){
	foreach( array( 'term_id', 'name', 'slug' ) as $field ) {
		$cache_key = $deleted_term->$field . '|' . $taxonomy ;
		$cache_group = 'term_exists';
		wp_cache_delete( $cache_key, $cache_group );
	}
}

/**
 * Optimized version of get_term_link that adds caching for slug-based lookups.
 *
 * Returns permalink for a taxonomy term archive, or a WP_Error object if the term does not exist.
 *
 * @param int|string|object $term The term object / term ID / term slug whose link will be retrieved.
 * @param string $taxonomy The taxonomy slug. NOT required if you pass the term object in the first parameter
 *
 * @return string|WP_Error HTML link to taxonomy term archive on success, WP_Error if term does not exist.
 */
function wpcom_vip_get_term_link( $term, $taxonomy ) {
	// ID- or object-based lookups already result in cached lookups, so we can ignore those.
	if ( is_numeric( $term ) || is_object( $term ) ) {
		return get_term_link( $term, $taxonomy );
	}

	$term_object = wpcom_vip_get_term_by( 'slug', $term, $taxonomy );
	return get_term_link( $term_object );
}

/**
 * Cached version of get_page_by_title so that we're not making unnecessary SQL all the time
 *
 * @param string $page_title Page title
 * @param string $output Optional. Output type; OBJECT*, ARRAY_N, or ARRAY_A.
 * @param string $post_type Optional. Post type; default is 'page'.
 * @return WP_Post|null WP_Post on success or null on failure
 * @link http://vip.wordpress.com/documentation/uncached-functions/ Uncached Functions
 */
function wpcom_vip_get_page_by_title( $title, $output = OBJECT, $post_type = 'page' ) {
	$cache_key = $post_type . '_' . sanitize_key( $title );
	$page_id = wp_cache_get( $cache_key, 'get_page_by_title' );

	if ( $page_id === false ) {
		$page = get_page_by_title( $title, OBJECT, $post_type );
		$page_id = $page ? $page->ID : 0;
		wp_cache_set( $cache_key, $page_id, 'get_page_by_title' ); // We only store the ID to keep our footprint small
	}

	if ( $page_id )
		return get_page( $page_id, $output );

	return null;
}

/**
 * Cached version of get_page_by_path so that we're not making unnecessary SQL all the time
 *
 * @param string $page_path Page path
 * @param string $output Optional. Output type; OBJECT*, ARRAY_N, or ARRAY_A.
 * @param string $post_type Optional. Post type; default is 'page'.
 * @return WP_Post|null WP_Post on success or null on failure
 * @link http://vip.wordpress.com/documentation/uncached-functions/ Uncached Functions
 */
function wpcom_vip_get_page_by_path( $page_path, $output = OBJECT, $post_type = 'page' ) {
	if ( is_array( $post_type ) )
		$cache_key = sanitize_key( $page_path ) . '_' . md5( serialize( $post_type ) );
	else
		$cache_key = $post_type . '_' . sanitize_key( $page_path );

	$page_id = wp_cache_get( $cache_key, 'get_page_by_path' );

	if ( $page_id === false ) {
		$page = get_page_by_path( $page_path, $output, $post_type );
		$page_id = $page ? $page->ID : 0;
		wp_cache_set( $cache_key, $page_id, 'get_page_by_path' ); // We only store the ID to keep our footprint small
	}

	if ( $page_id )
		return get_page( $page_id, $output );

	return null;
}

/**
 * Flush the cache for published pages so we don't end up with stale data
 *
 * @param string $new_status The post's new status
 * @param string $old_status The post's previous status
 * @param WP_Post $post The post
 * @link http://vip.wordpress.com/documentation/uncached-functions/ Uncached Functions
 */
function wpcom_vip_flush_get_page_by_title_cache( $new_status, $old_status, $post ) {
	if ( 'publish' == $new_status || 'publish' == $old_status )
		wp_cache_delete( $post->post_type . '_' . sanitize_key( $post->post_title ), 'get_page_by_title' );
}
add_action( 'transition_post_status', 'wpcom_vip_flush_get_page_by_title_cache', 10, 3 );

/**
 * Flush the cache for published pages so we don't end up with stale data
 *
 * @param string  $new_status The post's new status
 * @param string  $old_status The post's previous status
 * @param WP_Post $post       The post
 *
 * @link http://vip.wordpress.com/documentation/uncached-functions/ Uncached Functions
 */
function wpcom_vip_flush_get_page_by_path_cache( $new_status, $old_status, $post ) {
	if ( 'publish' === $new_status || 'publish' === $old_status )
		wp_cache_delete( $post->post_type . '_' . sanitize_key( $post->post_name ), 'get_page_by_path' );
}
add_action( 'transition_post_status', 'wpcom_vip_flush_get_page_by_path_cache', 10, 3 );

/**
 * Cached version of url_to_postid, which can be expensive.
 *
 * Examine a url and try to determine the post ID it represents.
 *
 * @param string $url Permalink to check.
 * @return int Post ID, or 0 on failure.
 */
function wpcom_vip_url_to_postid( $url ) {
	// Can only run after init, since home_url() has not been filtered to the mapped domain prior to that,
	// which will cause url_to_postid to fail
	// @see https://vip.wordpress.com/documentation/vip-development-tips-tricks/home_url-vs-site_url/
	if ( ! did_action( 'init' ) ) {
		_doing_it_wrong( 'wpcom_vip_url_to_postid', 'wpcom_vip_url_to_postid must be called after the init action, as home_url() has not yet been filtered', '' );

		return 0;
	}

	// Sanity check; no URLs not from this site
	if ( parse_url( $url, PHP_URL_HOST ) != wpcom_vip_get_home_host() )
		return 0;

	$cache_key = md5( $url );
	$post_id = wp_cache_get( $cache_key, 'url_to_postid' );

	if ( false === $post_id ) {
		$post_id = url_to_postid( $url ); // returns 0 on failure, so need to catch the false condition
		wp_cache_set( $cache_key, $post_id, 'url_to_postid' );
	}

	return $post_id;
}

add_action( 'transition_post_status', function( $new_status, $old_status, $post ) {
	if ( 'publish' != $new_status && 'publish' != $old_status )
		return;

	$url = get_permalink( $post->ID );
	wp_cache_delete( md5( $url ), 'url_to_postid' );
}, 10, 3 );

/**
 * Cached version of wp_old_slug_redirect.
 *
 * Cache the results of the _wp_old_slug meta query, which can be expensive.
 */
function wpcom_vip_old_slug_redirect() {
    global $wp_query;
    if ( is_404() && '' != $wp_query->query_vars['name'] ) :
        global $wpdb;

        // Guess the current post_type based on the query vars.
        if ( get_query_var('post_type') )
            $post_type = get_query_var('post_type');
        elseif ( !empty($wp_query->query_vars['pagename']) )
            $post_type = 'page';
        else
            $post_type = 'post';

        if ( is_array( $post_type ) ) {
            if ( count( $post_type ) > 1 )
                return;
            $post_type = array_shift( $post_type );
        }

        // Do not attempt redirect for hierarchical post types
        if ( is_post_type_hierarchical( $post_type ) )
            return;

        $query = $wpdb->prepare("SELECT post_id FROM $wpdb->postmeta, $wpdb->posts WHERE ID = post_id AND post_type = %s AND meta_key = '_wp_old_slug' AND meta_value = %s", $post_type, $wp_query->query_vars['name']);

        // if year, monthnum, or day have been specified, make our query more precise
        // just in case there are multiple identical _wp_old_slug values
        if ( '' != $wp_query->query_vars['year'] )
            $query .= $wpdb->prepare(" AND YEAR(post_date) = %d", $wp_query->query_vars['year']);
        if ( '' != $wp_query->query_vars['monthnum'] )
            $query .= $wpdb->prepare(" AND MONTH(post_date) = %d", $wp_query->query_vars['monthnum']);
        if ( '' != $wp_query->query_vars['day'] )
            $query .= $wpdb->prepare(" AND DAYOFMONTH(post_date) = %d", $wp_query->query_vars['day']);

        $cache_key = md5( serialize( $query ) );

        if ( false === $id = wp_cache_get( $cache_key, 'wp_old_slug_redirect' ) ) {
            $id = (int) $wpdb->get_var($query);

            wp_cache_set( $cache_key, $id, 'wp_old_slug_redirect', 5 * MINUTE_IN_SECONDS );
        }

        if ( ! $id )
            return;

        $link = get_permalink($id);

        if ( !$link )
            return;

        wp_redirect( $link, 301 ); // Permanent redirect
        exit;
    endif;
}
remove_filter( 'template_redirect', 'wp_old_slug_redirect' );
add_action( 'template_redirect', 'wpcom_vip_old_slug_redirect' );

/**
 * Cached version of count_user_posts, which is uncached but doesn't always need to hit the db
 *
 * count_user_posts is generally fast, but it can be easy to end up with many redundant queries
 * if it's called several times per request. This allows bypassing the db queries in favor of
 * the cache
 */
function wpcom_vip_count_user_posts( $user_id ) {
    if ( ! is_numeric( $user_id ) ) {
        return 0;
    }

    $cache_key = 'vip_' . (int) $user_id;
    $cache_group = 'user_posts_count';

    if ( false === ( $count = wp_cache_get( $cache_key, $cache_group ) ) ) {
        $count = count_user_posts( $user_id );

        wp_cache_set( $cache_key, $count, $cache_group, 5 * MINUTE_IN_SECONDS );
    }

    return $count;
}
