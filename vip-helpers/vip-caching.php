<?php
/**
 * This file contains a bunch of helper functions that handle add caching to core WordPress functions.
 */

// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.term_exists_term_exists
// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.count_user_posts_count_user_posts
// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.get_page_by_title_get_page_by_title
// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.get_page_by_path_get_page_by_path
// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.attachment_url_to_postid_attachment_url_to_postid
// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.url_to_postid_url_to_postid
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery

/**
 * Cached version of get_category_by_slug.
 *
 * @param string $slug Category slug
 * @return object|null|bool Term Row from database. Will return null if $slug doesn't match a term. If taxonomy does not exist then false will be returned.
 * @link https://docs.wpvip.com/technical-references/caching/uncached-functions/ Uncached Functions
 */
function wpcom_vip_get_category_by_slug( $slug ) {
	return get_term_by( 'slug', $slug, 'category' );
}

/**
 * Cached version of get_term_by.
 *
 * Many calls to get_term_by (with name or slug lookup) across on a single pageload can easily add up the query count.
 * This function helps prevent that by adding a layer of caching.
 *
 * @param string     $field Either 'slug', 'name', or 'id'
 * @param string|int $value Search for this term value
 * @param string     $taxonomy Taxonomy Name
 * @param string     $output Optional. Constant OBJECT, ARRAY_A, or ARRAY_N
 * @param string     $filter Optional. Default is 'raw' or no WordPress defined filter will applied.
 * @return mixed|null|bool Term Row from database in the type specified by $filter. Will return false if $taxonomy does not exist or $term was not found.
 * @link https://docs.wpvip.com/technical-references/caching/uncached-functions/ Uncached Functions
 */
function wpcom_vip_get_term_by( $field, $value, $taxonomy, $output = OBJECT, $filter = 'raw' ) {
	// ID lookups are cached
	if ( 'id' === $field ) {
		return get_term_by( $field, $value, $taxonomy, $output, $filter );
	}

	$cache_key = $field . '|' . $taxonomy . '|' . md5( $value );
	$term_id   = wp_cache_get( $cache_key, 'get_term_by' );

	if ( false === $term_id ) {
		$term = get_term_by( $field, $value, $taxonomy );
		if ( $term && ! is_wp_error( $term ) ) {
			wp_cache_set( $cache_key, $term->term_id, 'get_term_by', 4 * HOUR_IN_SECONDS );
		} else {
			wp_cache_set( $cache_key, 0, 'get_term_by', 15 * MINUTE_IN_SECONDS ); // if we get an invalid value, let's cache it anyway but for a shorter period of time
		}
	} else {
		$term = get_term( $term_id, $taxonomy, $output, $filter );
	}

	if ( is_wp_error( $term ) ) {
		$term = false;
	}

	return $term;
}

/**
 * Properly clear wpcom_vip_get_term_by() cache when a term is updated
 */
add_action( 'edit_terms', 'wp_flush_get_term_by_cache', 10, 2 );
add_action( 'create_term', 'wp_flush_get_term_by_cache', 10, 2 );
function wp_flush_get_term_by_cache( $term_id, $taxonomy ) {
	$term = get_term_by( 'id', $term_id, $taxonomy );
	if ( ! $term ) {
		return;
	}
	foreach ( array( 'name', 'slug' ) as $field ) {
		$cache_key   = $field . '|' . $taxonomy . '|' . md5( $term->$field );
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
 * @param string     $taxonomy The taxonomy name to use
 * @param int        $parent Optional. ID of parent term under which to confine the exists search.
 * @return mixed Returns null if the term does not exist. Returns the term ID
 *               if no taxonomy is specified and the term ID exists. Returns
 *               an array of the term ID and the term taxonomy ID the taxonomy
 *               is specified and the pairing exists.
 */

function wpcom_vip_term_exists( $term, $taxonomy = '', $parent = null ) {
	// If $parent is not null, let's skip the cache.
	if ( null !== $parent ) {
		return term_exists( $term, $taxonomy, $parent );
	}

	if ( ! empty( $taxonomy ) ) {
		$cache_key = $term . '|' . $taxonomy;
	} else {
		$cache_key = $term;
	}

	$cache_value = wp_cache_get( $cache_key, 'term_exists' );

	// term_exists frequently returns null, but (happily) never false
	if ( false === $cache_value ) {
		$term_exists = term_exists( $term, $taxonomy );
		wp_cache_set( $cache_key, $term_exists, 'term_exists', 3 * HOUR_IN_SECONDS );
	} else {
		$term_exists = $cache_value;
	}

	if ( is_wp_error( $term_exists ) ) {
		$term_exists = null;
	}

	return $term_exists;
}

/**
 * Properly clear wpcom_vip_term_exists() cache when a term is updated
 */
add_action( 'delete_term', 'wp_flush_term_exists', 10, 4 );
function wp_flush_term_exists( $term, $tt_id, $taxonomy, $deleted_term ) {
	foreach ( array( 'term_id', 'name', 'slug' ) as $field ) {
		$cache_key   = $deleted_term->$field . '|' . $taxonomy;
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
 * @param string            $taxonomy The taxonomy slug. NOT required if you pass the term object in the first parameter
 *
 * @return string|WP_Error HTML link to taxonomy term archive on success, WP_Error if term does not exist.
 */
function wpcom_vip_get_term_link( $term, $taxonomy = null ) {
	// ID- or object-based lookups already result in cached lookups, so we can ignore those.
	if ( is_numeric( $term ) || is_object( $term ) ) {
		return get_term_link( $term, $taxonomy );
	}

	$term_object = get_term_by( 'slug', $term, $taxonomy );
	return get_term_link( $term_object );
}

/**
 * Cached version of get_page_by_title so that we're not making unnecessary SQL all the time
 *
 * @param string $page_title Page title
 * @param string $output Optional. Output type; OBJECT*, ARRAY_N, or ARRAY_A.
 * @param string $post_type Optional. Post type; default is 'page'.
 * @return WP_Post|null WP_Post on success or null on failure
 * @link https://docs.wpvip.com/technical-references/caching/uncached-functions/ Uncached Functions
 */
function wpcom_vip_get_page_by_title( $title, $output = OBJECT, $post_type = 'page' ) {
	$cache_key = $post_type . '_' . sanitize_key( $title );
	$page_id   = wp_cache_get( $cache_key, 'get_page_by_title' );

	if ( false === $page_id ) {
		$page    = get_page_by_title( $title, OBJECT, $post_type );
		$page_id = $page ? $page->ID : 0;
		wp_cache_set( $cache_key, $page_id, 'get_page_by_title', 3 * HOUR_IN_SECONDS ); // We only store the ID to keep our footprint small
	}

	if ( $page_id ) {
		return get_post( $page_id, $output );
	}

	return null;
}

/**
 * Cached version of get_page_by_path so that we're not making unnecessary SQL all the time
 *
 * @param string        $page_path Page path
 * @param string        $output Optional. Output type; OBJECT*, ARRAY_N, or ARRAY_A.
 * @param string|array  $post_type Optional. Post type; default is 'page'.
 * @return WP_Post|null WP_Post on success or null on failure
 * @link https://docs.wpvip.com/technical-references/caching/uncached-functions/ Uncached Functions
 */
function wpcom_vip_get_page_by_path( $page_path, $output = OBJECT, $post_type = 'page' ) {
	if ( is_array( $post_type ) ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$cache_key = sanitize_key( $page_path ) . '_' . md5( serialize( $post_type ) );
	} else {
		$cache_key = $post_type . '_' . sanitize_key( $page_path );
	}

	$page_id = wp_cache_get( $cache_key, 'get_page_by_path' );

	if ( false === $page_id ) {
		$page    = get_page_by_path( $page_path, $output, $post_type );
		$page_id = $page ? $page->ID : 0;
		if ( 0 === $page_id ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand, WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
			wp_cache_set( $cache_key, $page_id, 'get_page_by_path', ( 1 * HOUR_IN_SECONDS + mt_rand( 0, HOUR_IN_SECONDS ) ) ); // We only store the ID to keep our footprint small
		} else {
			wp_cache_set( $cache_key, $page_id, 'get_page_by_path', 0 ); // We only store the ID to keep our footprint small
		}
	}

	if ( $page_id ) {
		return get_post( $page_id, $output );
	}

	return null;
}

/**
 * Flush the cache for published pages so we don't end up with stale data
 *
 * @param string  $new_status The post's new status
 * @param string  $old_status The post's previous status
 * @param WP_Post $post The post
 * @link https://docs.wpvip.com/technical-references/caching/uncached-functions/ Uncached Functions
 */
function wpcom_vip_flush_get_page_by_title_cache( $new_status, $old_status, $post ) {
	if ( 'publish' === $new_status || 'publish' === $old_status ) {
		wp_cache_delete( $post->post_type . '_' . sanitize_key( $post->post_title ), 'get_page_by_title' );
	}
}
add_action( 'transition_post_status', 'wpcom_vip_flush_get_page_by_title_cache', 10, 3 );

/**
 * Flush the cache for published pages so we don't end up with stale data
 *
 * @param string  $new_status The post's new status
 * @param string  $old_status The post's previous status
 * @param WP_Post $post       The post
 *
 * @link https://docs.wpvip.com/technical-references/caching/uncached-functions/ Uncached Functions
 */
function wpcom_vip_flush_get_page_by_path_cache( $new_status, $old_status, $post ) {
	if ( 'publish' === $new_status || 'publish' === $old_status ) {
		$page_path = get_page_uri( $post->ID );
		wp_cache_delete( $post->post_type . '_' . sanitize_key( $page_path ), 'get_page_by_path' );
	}
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
	$cache_key = md5( $url );
	$post_id   = wp_cache_get( $cache_key, 'url_to_postid' );

	if ( false === $post_id ) {
		$post_id = url_to_postid( $url ); // returns 0 on failure, so need to catch the false condition
		wp_cache_set( $cache_key, $post_id, 'url_to_postid', 3 * HOUR_IN_SECONDS );
	}

	return $post_id;
}

add_action( 'transition_post_status', function( $new_status, $old_status, $post ) {
	if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
		return;
	}

	$url = get_permalink( $post->ID );
	wp_cache_delete( md5( $url ), 'url_to_postid' );
}, 10, 3 );

/**
 * Cached version of wp_old_slug_redirect.
 *
 * Cache the results of the _wp_old_slug meta query, which can be expensive.
 *
 * @deprecated use wpcom_vip_wp_old_slug_redirect instead
 */
function wpcom_vip_old_slug_redirect() {
	global $wp_query;
	if ( is_404() && '' !== $wp_query->query_vars['name'] ) {
		global $wpdb;

		// Guess the current post_type based on the query vars.
		if ( get_query_var( 'post_type' ) ) {
			$post_type = get_query_var( 'post_type' );
		} elseif ( ! empty( $wp_query->query_vars['pagename'] ) ) {
			$post_type = 'page';
		} else {
			$post_type = 'post';
		}

		if ( is_array( $post_type ) ) {
			if ( count( $post_type ) > 1 ) {
				return;
			}
			$post_type = array_shift( $post_type );
		}

		// Do not attempt redirect for hierarchical post types
		if ( is_post_type_hierarchical( $post_type ) ) {
			return;
		}

		$query = $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta, $wpdb->posts WHERE ID = post_id AND post_type = %s AND meta_key = '_wp_old_slug' AND meta_value = %s", $post_type, $wp_query->query_vars['name'] );

		// if year, monthnum, or day have been specified, make our query more precise
		// just in case there are multiple identical _wp_old_slug values
		if ( '' !== $wp_query->query_vars['year'] ) {
			$query .= $wpdb->prepare( ' AND YEAR(post_date) = %d', $wp_query->query_vars['year'] );
		}
		if ( '' !== $wp_query->query_vars['monthnum'] ) {
			$query .= $wpdb->prepare( ' AND MONTH(post_date) = %d', $wp_query->query_vars['monthnum'] );
		}
		if ( '' !== $wp_query->query_vars['day'] ) {
			$query .= $wpdb->prepare( ' AND DAYOFMONTH(post_date) = %d', $wp_query->query_vars['day'] );
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- string is safe
		$cache_key = md5( serialize( $query ) );

		$id = wp_cache_get( $cache_key, 'wp_old_slug_redirect' );
		if ( false === $id ) {
			$id = (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- the query is properly constructed above

			wp_cache_set( $cache_key, $id, 'wp_old_slug_redirect', 5 * MINUTE_IN_SECONDS );
		}

		if ( ! $id ) {
			return;
		}

		$link = get_permalink( $id );

		if ( ! $link ) {
			return;
		}

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		wp_redirect( $link, 301 ); // Permanent redirect
		exit;
	}
}


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

	$cache_key   = 'vip_' . (int) $user_id;
	$cache_group = 'user_posts_count';
	
	$count = wp_cache_get( $cache_key, $cache_group );
	if ( false === $count ) {
		$count = count_user_posts( $user_id );

		wp_cache_set( $cache_key, $count, $cache_group, 5 * MINUTE_IN_SECONDS );
	}

	return $count;
}

/*
 * Cached version of wp_get_nav_menu_object
 *
 * Many calls to get_term_by (with name or slug lookup as used inside the wp_get_nav_menu_object) across on a single pageload can easily add up the query count.
 * This function helps prevent that by taking advantage of wpcom_vip_get_term_by function which adds a layer of caching.
 *
 * @param string $menu Menu ID, slug, or name.
 * @return mixed false if $menu param isn't supplied or term does not exist, menu object if successful.
 */
function wpcom_vip_get_nav_menu_object( $menu ) {
	if ( ! $menu ) {
		return false;
	}

	$menu_obj = get_term( $menu, 'nav_menu' );

	if ( ! $menu_obj ) {
		$menu_obj = get_term_by( 'slug', $menu, 'nav_menu' );
	}

	if ( ! $menu_obj ) {
		$menu_obj = get_term_by( 'name', $menu, 'nav_menu' );
	}

	if ( ! $menu_obj ) {
		$menu_obj = false;
	}

	return $menu_obj;
}

/**
 * Require the Stampedeless_Cache class for use in our helper functions below.
 *
 * The Stampedeless_Cache helps prevent cache stampedes by internally varying the cache
 * expiration slightly when creating a cache entry in an effort to avoid multiple keys
 * expiring simultaneously and allowing a single request to regenerate the cache shortly
 * before it's expiration.
 */
if ( function_exists( 'require_lib' ) && defined( 'WPCOM_IS_VIP_ENV' ) && WPCOM_IS_VIP_ENV ) {
	require_lib( 'class.stampedeless-cache' );
}

/**
 * Drop in replacement for wp_cache_set().
 *
 * Wrapper for the WPCOM Stampedeless_Cache class.
 *
 * @param string                  $key Cache key.
 * @param string|int|array|object $value Data to store in the cache.
 * @param string                  $group Optional. Cache group.
 * @param int                     $expiration Optional. Cache TTL in seconds.
 * @return bool This function always returns true.
 */
function wpcom_vip_cache_set( $key, $value, $group = '', $expiration = 0 ) {
	if ( ! class_exists( 'Stampedeless_Cache' ) ) {
		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
		return wp_cache_set( $key, $value, $group, $expiration );
	}

	$sc = new Stampedeless_Cache( $key, $group );
	$sc->set( $value, $expiration );

	return true;
}

/**
 * Drop in replacement for wp_cache_get().
 *
 * Wrapper for the WPCOM Stampedeless_Cache class.
 *
 * @param string $key Cache key.
 * @param string $group Optional. Cache group.
 * @return mixed Returns false if failing to retrieve cache entry or the cached data otherwise.
 */
function wpcom_vip_cache_get( $key, $group = '' ) {
	if ( ! class_exists( 'Stampedeless_Cache' ) ) {
		return wp_cache_get( $key, $group );
	}

	$sc = new Stampedeless_Cache( $key, $group );

	return $sc->get();
}

/**
 * Drop in replacement for wp_cache_delete().
 *
 * Wrapper for WPCOM Stampedeless_Cache class.
 *
 * @param string $key Cache key.
 * @param string $group Optional. Cache group.
 * @return bool True on successful removal, false on failure.
 */
function wpcom_vip_cache_delete( $key, $group = '' ) {
	// delete cache itself
	$deleted = wp_cache_delete( $key, $group );

	if ( class_exists( 'Stampedeless_Cache' ) ) {
		// delete lock
		$lock_key = $key . '_lock';
		wp_cache_delete( $lock_key, $group );
	}

	return $deleted;
}

/**
 * Retrieve adjacent post.
 *
 * Can either be next or previous post. The logic for excluding terms is handled within PHP, for performance benefits.
 * Props to Elliott Stocks
 *
 * @global wpdb $wpdb
 *
 * @param bool   $in_same_term    Optional. Whether post should be in a same taxonomy term. Note - only the first term will be used from wp_get_object_terms().
 * @param string $excluded_terms  Optional. A comma-separated list of the term IDs to exclude.
 * @param bool   $previous        Optional. Whether to retrieve previous post.
 * @param string $taxonomy        Optional. Taxonomy, if $in_same_term is true. Default 'category'.
 *
 * @return null|string|WP_Post Post object if successful. Null if global $post is not set. Empty string if no corresponding post exists.
 */
function wpcom_vip_get_adjacent_post( $in_same_term = false, $excluded_terms = '', $previous = true, $taxonomy = 'category' ) {
	global $wpdb;
	$post = get_post();
	if ( ! $post || ! taxonomy_exists( $taxonomy ) ) {
		return null;
	}
	$join              = '';
	$where             = '';
	$current_post_date = $post->post_date;
	
	/** @var string[] */
	$excluded_terms = empty( $excluded_terms ) ? [] : explode( ',', $excluded_terms );

	if ( $in_same_term ) {
		if ( is_object_in_taxonomy( $post->post_type, $taxonomy ) ) {
			$term_array = get_the_terms( $post->ID, $taxonomy );
			if ( ! empty( $term_array ) && ! is_wp_error( $term_array ) ) {
				$term_array_ids = wp_list_pluck( $term_array, 'term_id' );
				// Remove any exclusions from the term array to include.
				if ( ! empty( $excluded_terms ) ) {
					$term_array_ids = array_diff( $term_array_ids, $excluded_terms );
				}
				if ( ! empty( $term_array_ids ) ) {
					$term_array_ids    = array_map( 'intval', $term_array_ids );
					$term_id_to_search = array_pop( $term_array_ids ); // only allow for a single term to be used. picked pseudo randomly
				} else {
					$term_id_to_search = false;
				}

				$term_id_to_search = apply_filters( 'wpcom_vip_limit_adjacent_post_term_id', $term_id_to_search, $term_array_ids, $excluded_terms, $taxonomy, $previous );

				if ( ! empty( $term_id_to_search ) ) {  // allow filters to short circuit by returning a empty like value
					$join  = " INNER JOIN $wpdb->term_relationships AS tr ON p.ID = tr.object_id INNER JOIN $wpdb->term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id"; // Only join if we are sure there is a term
					$where = $wpdb->prepare( 'AND tt.taxonomy = %s AND tt.term_id IN (%d)  ', $taxonomy, $term_id_to_search ); //
				}
			}
		}
	}

	$op    = $previous ? '<' : '>';
	$order = $previous ? 'DESC' : 'ASC';
	$limit = 1;
	// We need 5 posts so we can filter the excluded term later on
	if ( ! empty( $excluded_terms ) ) {
		$limit = 5;
	}
	$sort = "ORDER BY p.post_date $order LIMIT $limit";
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$where = $wpdb->prepare( "WHERE p.post_date $op %s AND p.post_type = %s AND p.post_status = 'publish' ", $current_post_date, $post->post_type ) . $where;
	$query = "SELECT p.ID FROM $wpdb->posts AS p $join $where $sort";

	$found_post    = ''; // blank instead of false so not found is cached.
	$query_key     = 'wpcom_vip_adjacent_post_' . md5( $query );
	$cached_result = wp_cache_get( $query_key );

	if ( 'not found' === $cached_result ) {
		return false;
	} elseif ( false !== $cached_result ) {
		return get_post( $cached_result );
	}

	if ( empty( $excluded_terms ) ) {
		$result = $wpdb->get_var( $query );     // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	} else {
		$result = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	// Find the first post which doesn't have an excluded term
	if ( ! empty( $excluded_terms ) ) {
		foreach ( $result as $result_post ) {
			$post_terms = get_the_terms( $result_post, $taxonomy );
			if ( is_array( $post_terms ) ) {
				$terms_array = wp_list_pluck( $post_terms, 'term_id' );
				if ( ! in_array( $excluded_terms, $terms_array, true ) ) {
					$found_post = $result_post->ID;
					break;
				}
			}
		}
	} else {
		$found_post = $result;
	}

	// If the post isn't found lets cache a value we'll check against. Add some variation in the caching so if a site is being crawled all the caches don't get created all the time.
	if ( empty( $found_post ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_rand, WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
		wp_cache_set( $query_key, 'not found', 'default', 15 * MINUTE_IN_SECONDS + rand( 0, 15 * MINUTE_IN_SECONDS ) );
		return false;
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_rand, WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
	wp_cache_set( $query_key, $found_post, 'default', 6 * HOUR_IN_SECONDS + rand( 0, 2 * HOUR_IN_SECONDS ) );
	$found_post = get_post( $found_post );

	return $found_post;
}

function wpcom_vip_attachment_cache_key( $url ) {
	return 'wpcom_vip_attachment_url_post_id_' . md5( $url );
}

function wpcom_vip_attachment_url_to_postid( $url ) {
	$cache_key = wpcom_vip_attachment_cache_key( $url );
	$id        = wp_cache_get( $cache_key );
	if ( false === $id ) {
		$id = attachment_url_to_postid( $url );
		if ( empty( $id ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand, WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
			wp_cache_set( $cache_key, 'not_found', 'default', 12 * HOUR_IN_SECONDS + mt_rand( 0, 4 * HOUR_IN_SECONDS ) );
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand, WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
			wp_cache_set( $cache_key, $id, 'default', 24 * HOUR_IN_SECONDS + mt_rand( 0, 12 * HOUR_IN_SECONDS ) );
		}
	} elseif ( 'not_found' === $id ) {
		return false;
	}
	return $id;
}

/**
 * Remove cached post ID when attachment post deleted
 *
 * @see wpcom_vip_attachment_url_to_postid()
 */
add_action( 'delete_attachment', function ( $post_id ) {
	$url       = wp_get_attachment_url( $post_id );
	$cache_key = wpcom_vip_attachment_cache_key( $url );
	wp_cache_delete( $cache_key, 'default' );
} );

function wpcom_vip_enable_old_slug_redirect_caching() {
	add_action( 'template_redirect', 'wpcom_vip_wp_old_slug_redirect', 8 );
	// Hook the following actions to after the core's wp_check_for_changed_slugs - it's being hooke at prio 12
	add_action( 'post_updated', 'wpcom_vip_flush_wp_old_slug_redirect_cache', 13, 3 );
	add_action( 'attachment_updated', 'wpcom_vip_flush_wp_old_slug_redirect_cache', 13, 3 );
}

/**
 * This works by first looking in the cache to see if there is a value saved based on the name query var.
 * If one is found, redirect immediately. If nothing is found, including that there is no already cache "not_found" value we then add a hook to old_slug_redirect_url so that when the 'rea' wp_old_slug_redirect is run it will store the value in the cache @see wpcom_vip_set_old_slug_redirect_cache(). If we found a not_found we remove the template_redirect so the slow query is not run.
 */
function wpcom_vip_wp_old_slug_redirect() {
	global $wp_query;
	if ( is_404() && '' !== $wp_query->query_vars['name'] ) {

		$redirect = wp_cache_get( 'old_slug' . $wp_query->query_vars['name'] );

		if ( false === $redirect ) {
			// Run the caching callback as the very firts one in order to capture the value returned by WordPress from database. This allows devs from using `old_slug_redirect_url` filter w/o polluting the cache
			add_filter( 'old_slug_redirect_url', 'wpcom_vip_set_old_slug_redirect_cache', -9999, 1 );
			// If an old slug is not found the function returns early and does not apply the old_slug_redirect_url filter. so we will set the cache for not found and if it is found it will be overwritten later in wpcom_vip_set_old_slug_redirect_cache()
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand, WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined
			wp_cache_set( 'old_slug' . $wp_query->query_vars['name'], 'not_found', 'default', 12 * HOUR_IN_SECONDS + mt_rand( 0, 12 * HOUR_IN_SECONDS ) );
		} elseif ( 'not_found' === $redirect ) {
			// wpcom_vip_set_old_slug_redirect_cache() will cache 'not_found' when a url is not found so we don't keep hammering the database
			remove_action( 'template_redirect', 'wp_old_slug_redirect' );
			return;
		} else {
			/** This filter is documented in wp-includes/query.php. */
			$redirect = apply_filters( 'old_slug_redirect_url', $redirect );
			wp_redirect( $redirect, 301 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- this is kept to not safe_redirect to match the functionality of wp_old_slug_redirect
			exit;
		}
	}
}
function wpcom_vip_set_old_slug_redirect_cache( $link ) {
	global $wp_query;
	if ( ! empty( $link ) ) {
		wp_cache_set( 'old_slug' . $wp_query->query_vars['name'], $link, 'default', 7 * DAY_IN_SECONDS );
	}
	return $link;
}
function wpcom_vip_flush_wp_old_slug_redirect_cache( $post_id, $post, $post_before ) {
	// Don't bother if the slug or date hasn't changed.
	if ( $post->post_name == $post_before->post_name && $post->post_date == $post_before->post_date ) {
		return;
	}

	// We're only concerned with published, non-hierarchical objects.
	if ( ! ( 'publish' === $post->post_status || ( 'attachment' === get_post_type( $post ) && 'inherit' === $post->post_status ) ) || is_post_type_hierarchical( $post->post_type ) ) {
		return;
	}

	// Flush cache for all old slugs.
	$old_slugs = (array) get_post_meta( $post_id, '_wp_old_slug' );

	foreach ( $old_slugs as $old_slug ) {
		wp_cache_delete( 'old_slug' . $old_slug, 'default' );
	}

	// FLush cache for new post_name since it could had been among old slugs before this update.
	wp_cache_delete( 'old_slug' . $post->post_name, 'default' );
}

/**
 * Potentially skip redirect for old slugs.
 *
 * We're seeing an increase of URLs that match this pattern: http://example.com/http://othersite.com/random_text.
 *
 * These then cause really slow lookups inside of wp_old_slug_redirect. Since wp_old_slug redirect does not match
 * on full URLs but rather former slugs, it's safe to skip the lookup for these. Most of the calls are from bad ad
 * providers that generate random URLs.
 */
function wpcom_vip_maybe_skip_old_slug_redirect() {
	if ( ! is_404() ) {
		return;
	}

	if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- safe; used only in comparisons
	if ( 0 === strpos( $_SERVER['REQUEST_URI'], '/http:' ) || 0 === strpos( $_SERVER['REQUEST_URI'], '/https:' ) ) {
		remove_action( 'template_redirect', 'wp_old_slug_redirect' );
		remove_action( 'template_redirect', 'wpcom_vip_wp_old_slug_redirect', 8 );
	}
}

function wpcom_vip_enable_maybe_skip_old_slug_redirect() {
	add_action( 'template_redirect', 'wpcom_vip_maybe_skip_old_slug_redirect', 7 ); //Run this before wpcom_vip_wp_old_slug_redirect so we can also remove our caching helper
}

/**
 * Reset the local WordPress object cache
 *
 * This only cleans the local cache in WP_Object_Cache, without
 * affecting memcache
 */
function vip_reset_local_object_cache() {
	global $wp_object_cache;

	if ( ! is_object( $wp_object_cache ) ) {
		return;
	}

	$wp_object_cache->group_ops      = array();
	$wp_object_cache->memcache_debug = array();
	$wp_object_cache->cache          = array();

	if ( method_exists( $wp_object_cache, '__remoteset' ) ) {
		$wp_object_cache->__remoteset(); // important
	}
}

/**
 * Reset the WordPress DB query log
 */
function vip_reset_db_query_log() {
	global $wpdb;

	$wpdb->queries = array();
}
