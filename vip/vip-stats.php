<?php
/*
 *	VIP Helper Functions for Statistics that are specific to WordPress.com
 *
 * wpcom_vip_get_stats_csv() and wpcom_vip_get_stats_xml() are output compatible to
 * stats_get_csv() provided by http://wordpress.org/extend/plugins/stats/
 *
 * To add these functions to your theme add
 *
 *    wpcom_vip_load_helper_stats();
 *
 * in the theme's 'functions.php'. This should be wrapped in a
 *
 *     if ( function_exists('function_name') ) { // WPCOM specific
 *
 * so you don't load it in your local environment. This will help alert you if
 * have any unconditional dependencies on the WordPress.com environment.
 */


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
	if ( true === WPCOM_IS_VIP_ENV ) {
		global $wpdb;

		$cache_id 	= md5( '2013-01-01-top|' . $wpdb->blogid . '|' . 'postviews' . '|' . $end_date . '|' . $num_days . '|' . $limit );
		$arr 		= wp_cache_get( $cache_id, 'vip_stats' );

		if ( !$arr ) {
			$stat_result = _wpcom_vip_get_stats_result( 'postviews', $end_date, $num_days, '', $limit );
			$arr = wpcom_vip_stats_csv_print( $stat_result, 'postviews', $limit, true, true );
			wp_cache_set( $cache_id, $arr, 'vip_stats', 600 );
		}

		return $arr;
	} else {
		$posts = array();
		$words = array( 'dessert', 'cotton', 'candy', 'caramels', 'tiramisu', 'muffin',  'wafer', 'toffee', 'gummi', 'lemon', 'drops', 'brownie', 'lollipop', 'bears', 'danish', 'chocolate', 'bar', 'topping', 'apple', 'pie', 'pastry', 'powder', 'pudding' );

		for ( $i = 0; $i < $limit; $i++ ) {
			shuffle( $words );

			$posts[] = array(
				'post_id' 			=> $i,
				'post_title' 		=> ucfirst( implode( ' ', array_slice( $words, 2, mt_rand( 2, 5 ) ) ) ),
				'post_permalink' 	=> add_query_arg( 'p', $i, home_url() ),
				'views' 			=> mt_rand( 0, 20000 ),
			);
		}

		return $posts;
	}
}

/**
 * Get the WP.com stats
 *
 * @author tott
 * @param string $table Optional. Table for stats can be views, postviews, authorviews, referrers, searchterms, clicks. Default is views.
 * @param string $end_data Optional. The last day of the desired time frame. Format is 'Y-m-d' (e.g. 2007-05-01) and default is UTC date.
 * @param int $num_days Optional. The length of the desired time frame. Default is 1. Maximum 90 days
 * @param string $and Optional. Possibility to refine the query with additional AND condition. Usually unused.
 * @param int $limit Optional. The maximum number of records to return. Default is 5. Maximum 100.
 * @param bool $summarize If present, summarizes all matching records.
 * @return array Result as array.
 */
function wpcom_vip_get_stats_array( $table = 'views', $end_date = false, $num_days = 1, $and = '', $limit = 5, $summarize = NULL ) {
	if ( true === WPCOM_IS_VIP_ENV ) {
		global $wpdb;

		$cache_id 	= md5( 'array|' . $wpdb->blogid . '|' . $table . '|' . $end_date . '|' . $num_days . '|' . $and . '|' . $limit . '|' . $summarize );
		$arr 		= wp_cache_get( $cache_id, 'vip_stats' );

		if ( !$arr ) {
			$stat_result 	= _wpcom_vip_get_stats_result( $table, $end_date, $num_days, $and, $limit );
			$arr 			= wpcom_vip_stats_csv_print( $stat_result, $table, $limit, $summarize, true );

			wp_cache_set( $cache_id, $arr, 'vip_stats', 600 );
		}

		return $arr;
	} else {
		return array(); // TODO: local fallback
	}
}

/**
 * Get the WP.com stats as CSV
 *
 * Strings containing double quotes, commas, or "\n" are enclosed in double-quotes. Double-quotes in strings are escaped by inserting another double-quote.
 * Example: "pet food" recipe
 * Becomes: """pet food"" recipe"
 *
 * @author tott
 * @param string $table Optional. Table for stats can be views, postviews, referrers, searchterms, clicks. Default is views.
 * @param string $end_data Optional. The last day of the desired time frame. Format is 'Y-m-d' (e.g. 2007-05-01) and default is UTC date.
 * @param int $num_days Optional. The length of the desired time frame. Default is 1. Maximum 90 days
 * @param string $and Optional. Possibility to refine the query with additional AND condition. Usually unused.
 * @param int $limit Optional. The maximum number of records to return. Default is 5. Maximum 100.
 * @param bool $summarize Optional. If present, summarizes all matching records.
 * @return string Result format is CSV with one row per line and column names in first row.
 */
function wpcom_vip_get_stats_csv( $table = 'views', $end_date = false, $num_days = 1, $and = '', $limit = 5, $summarize = NULL ) {
	if ( true === WPCOM_IS_VIP_ENV ) {
		global $wpdb;

		$cache_id 	= md5( 'csv|' . $wpdb->blogid . '|' . $table . '|' . $end_date . '|' . $num_days . '|' . $and . '|' . $limit . '|' . $summarize );
		$csv 		= wp_cache_get( $cache_id, 'vip_stats' );

		if ( !$csv ) {
			$stat_result 	= _wpcom_vip_get_stats_result( $table, $end_date, $num_days, $and, $limit );
			$csv 			= wpcom_vip_stats_csv_print( $stat_result, $table, $limit, $summarize );

			wp_cache_set( $cache_id, $csv, 'vip_stats', 600 );
		}

		return $csv;
	} else {
		return array(); // TODO: local fallback
	}
}

/**
 * Get the WP.com stats as XML
 *
 * @author tott
 * @param string $table Optional. Table for stats can be views, postviews, referrers, searchterms, clicks. Default is views.
 * @param string $end_data Optional. The last day of the desired time frame. Format is 'Y-m-d' (e.g. 2007-05-01) and default is UTC date.
 * @param int $num_days Optional. The length of the desired time frame. Default is 1. Maximum 90 days
 * @param string $and Optional. Possibility to refine the query with additional AND condition. Usually unused.
 * @param int $limit Optional. The maximum number of records to return. Default is 5. Maximum 100.
 * @param bool $summarize Optional. If present, summarizes all matching records.
 * @return string Result format is XML dataset.
 */
function wpcom_vip_get_stats_xml( $table = 'views', $end_date = false, $num_days = 1, $and = '', $limit = 5, $summarize = NULL ) {
	global $wpdb;

	$cache_id 	= md5( 'xml|' . $wpdb->blogid . '|' . $table . '|' . $end_date . '|' . $num_days . '|' . $and . '|' . $limit . '|' . $summarize );
	$xml 		= wp_cache_get( $cache_id, 'vip_stats' );

	if ( !$xml ) {
		$stat_result 	= _wpcom_vip_get_stats_result( $table, $end_date, $num_days, $and, $limit );
		$xml 			= wpcom_vip_stats_xml_print( $stat_result, $table, $limit, $summarize );

		wp_cache_set( $cache_id, $xml, 'vip_stats', 600 );
	}
	return $xml;
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
	global $post, $wpdb;

	if ( empty( $post_id ) && ! empty( $post->ID ) )
		$post_id = $post->ID;

	$post_id = absint( $post_id );

	if ( empty( $post_id ) )
		return false;

	// At least 1 but no more than 90
	$num_days = max( 1, min( 90, intval( $num_days ) ) );

	if ( true === WPCOM_IS_VIP_ENV ) {
		$cache_key = 'views_' . $wpdb->blogid . '_' . $post_id . '_' . $num_days . '_' . $end_date;

		$views = wp_cache_get( $cache_key, 'vip_stats' );

		if ( false === $views ) {
			$views = 0;

			$data = stats_get_daily_history( false, $wpdb->blogid, 'postviews', 'post_id', $end_date, $num_days, $wpdb->prepare( "AND post_id = %d", $post_id ), 1, true );

			if ( is_array( $data ) )
				$views = (int) array_pop( array_pop( $data ) );

			wp_cache_set( $cache_key, $views, 'vip_stats', 3600 );
		}
	} else {
		$views = mt_rand( 0, 20000 );
	}

	return $views;
}

/**
 * Get the most shared posts of the current blog, ordered DESC by share count
 *
 * @author jjj
 * @access public
 *
 * @global WPDB $wpdb WordPress's Database class
 * @param int $limit Optional. Number of posts to retrieve. Defaults to 5.
 * @param int $cache_duration Optional. Length of time to cache the query. Defaults to 3600.
 * @return array Array of most shared post IDs
 */
function wpcom_vip_get_most_shared_posts( $limit = 5, $cache_duration = 3600 ) {
	global $wpdb;

	// If not in the WordPress.com VIP environment, return some randomized dummy data.
	if ( false === WPCOM_IS_VIP_ENV ) {
		$shares = array();

		for( $i = $limit; $i > 0; $i-- ) {
			$shares[] = (object) array( 'ID' => $i, 'total_shares' => ( $i * 1000 + rand( 0, 999 ) ) );
		}

		return $shares;
	}

	// Look for cached results
	$cache_key = 'most_shared_posts_' . $wpdb->blogid . '_' . $limit . '_' . $cache_duration;
	$shares    = wp_cache_get( $cache_key, 'vip_stats' );

	// No cache, so query the DB and set the cache
	if ( false === $shares ) {
		$shares = $wpdb->get_results( $wpdb->prepare( "SELECT post_id as ID, SUM( count ) as total_shares FROM sharing_stats WHERE blog_id = %d GROUP BY post_id ORDER BY total_shares DESC LIMIT %d", $wpdb->blogid, $limit ) );

		wp_cache_set( $cache_key, $shares, 'vip_stats', $cache_duration );
	}

	return $shares;
}

/*
 * ONLY INTERNAL FUNCTIONS FROM HERE ON, USE ONLY wpcom_vip_get_stats_csv() and wpcom_vip_get_stats_xml()
 */

/**
 * Helper function for wpcom_vip_stats_csv_print() to pull out certain fields from a post object.
 *
 * @param WP_Post $post
 * @return array
 */
function wpcom_vip_csv_expand_post( $post ) {
	return array( $post->ID, $post->post_title, $post->permalink );
}

/**
 * Helper function for wpcom_vip_stats_csv_print() to double-quote a string for CSV output.
 *
 * @param string|array $v Strings to double-quote
 * @return string
 */
function wpcom_vip_csv_quote( $v ) {
	if ( is_array( $v ) )
		return join(',', array_map( 'wpcom_vip_csv_quote', $v ));

	if ( strstr( $v, '"' ) || strstr( $v, ',' ) || strstr( $v, "\n" ) )
		return '"' . str_replace( '"', '""', $v ) . '"';

	return "$v";
}

/**
 * Transforms data from the stats API into CSV.
 *
 * Notice: this function will return more than one value than what you need because of a bug in the limit logic. Adjust accordingly.
 *
 * @param array $rows Raw data from the stats API.
 * @param string $table Table for stats can be views, postviews, referrers, searchterms, clicks.
 * @param int $limit The maximum number of records to return.
 * @param null $summarize Optional. If set, will summarizes all the records.
 * @param bool|string $return_array Optional. If true, return an array, otherwise returns a string.
 * @return array Fresh tasty data
 */
function wpcom_vip_stats_csv_print( $rows, $table, $limit, $summarize = NULL, $return_array = false ) {
	if ( empty( $rows ) )
		return "Error: zero rows returned.";

	$result = '';

	switch ( $table ) {

		case 'views' :
			if ( !is_null( $summarize ) )
				$_rows = array(
					array(
						'date' => '-',
						'views' => array_sum(
							array_map( function( $row ) {
									return $row["views"];
								}
							, $rows )
						)
					)
				);
			else
				$_rows =& $rows;

			array_unshift( $_rows, array( 'date', 'views' ) );

			break;

		case 'postviews' :
			$posts = array();

			if ( isset( $GLOBALS['post_id'] ) && $GLOBALS['post_id'] ) {
				$_rows = array( array( 'date', 'views' ) );

				foreach ( $rows as $date => $array )
					$_rows[] = array( $date, $array[ $GLOBALS['post_id'] ] );

				break;
			}

			$_rows = array( array( 'date', 'post_id', 'post_title', 'post_permalink', 'views' ) );

			foreach ( $rows as $date => $day_rows ) {
				foreach ( $day_rows as $k => $v ) {
					if ( $k < 1 )
						continue;

					$posts[ $k ] = true;

					if ( !is_null( $summarize ) )
						$_rows[ $k ] = array( $date, &$posts[ $k ], $_rows[ $k ][2] + $v );
					else
						$_rows[] = array( $date, &$posts[ $k ], $v );
				}
			}

			// sort by views
			if ( !is_null( $summarize ) ) {
				$_head 	= array_shift( $_rows );
				$_srows = array();

				foreach( $_rows as $key => $vals ) {
					$_srows[ $vals[2] ] = $vals;
				}

				$_rows = $_srows;

				unset( $_srows );

				krsort( $_rows );

				array_unshift( $_rows, $_head );
			}

			foreach ( stats_get_posts( array_keys( $posts ), $GLOBALS['blog_id'] ) as $id => $post )
				$posts[ $id ] = wpcom_vip_csv_expand_post( $post );

			break;

		case 'authorviews':
			$_rows = array();

			foreach ( $rows as $date => $authors ) {
				foreach ( $authors as $author => $posts ) {
					$author_views = 0;

					foreach ( $posts as $views ) {
						$author_views += $views;
					}

					$_rows[] = array( $date, $author, $author_views );
				}
			}

			break;

		default :
			$_rows = array( array( 'date', rtrim( $table, 's' ), 'views' ) );

			foreach ( $rows as $date => $day_rows )
				foreach ( $day_rows as $k => $v )
					if ( $k !== $v )
						$_rows[] = array( $date, $k, $v );
	}


	if ( true === $return_array ) {
		$mapping 	= array_shift( $_rows );
		$out 		= array();

		foreach( $_rows as $key => $values ) {
			switch( $table ) {
				case "postviews":
					$out[] = array( 'date' => $values[0], 'post_id' => $values[1][0], 'post_title' => $values[1][1], 'post_permalink' => $values[1][2], 'views' => $values[2] );

					break;

				case "views":
					$out[] = array( 'date' => $values['date'], 'views' => $values['views'] );

					break;

				case "referrers":
					$out[] = array( 'date' => $values[0], 'referrer' => $values[1], 'views' => $values[2] );

					break;

				case "searchterms":
					$out[] = array( 'date' => $values[0], 'searchterm' => $values[1], 'views' => $values[2] );

					break;

				case "clicks":
					$out[] = array( 'date' => $values[0], 'url' => $values[1], 'clicks' => $values[2] );

					break;

				case 'authorviews':
					$out[] = array( 'date' => $values[0], 'author' => $values[1], 'views' => $values[2] );

					break;
			}
		}

		if ( $limit > 0 && count( $out ) > $limit + 1 )
			$out = array_slice( $out, 0, $limit );

		// Remove date col from summarized data
		if ( !is_null( $summarize ) ) {
			foreach ( $out as $key => $row ) {
				array_shift( $row );

				$out[ $key ] = $row;
			}
		}
		return $out;
	}

	if ( $limit > 0 && count( $_rows ) > $limit + 1 )
		$_rows = array_slice( $_rows, 0, $limit );

	foreach ( $_rows as $row ) {
		// Remove date col from summarized data
		if ( !is_null( $summarize ) )
			array_shift( $row );

		$row = array_map( 'wpcom_vip_csv_quote', $row );

		$result .= join( ',', $row ) . "\n";
	}

	return $result;
}

/**
 * Transforms data from the stats API into XML.
 *
 * @param array $rows Raw data from the stats API.
 * @param string $table Table for stats can be views, postviews, referrers, searchterms, clicks.
 * @param int $limit The maximum number of records to return.
 * @param null $summarize Optional. If set, will summarizes all the records.
 * @return string Fresh tasty data
 */
function wpcom_vip_stats_xml_print( $rows, $table, $limit, $summarize = NULL ) {
	if ( empty( $rows ) )
		return "Error: zero rows returned.";

	$return .= '<' . $table . '>' . "\n";

	switch ( $table ) {
		case 'views' :
			if ( is_null( $summarize ) ) {
				$count = 0;

				foreach ( $rows as $row ) {
					$count++;

					if ( 0 < $limit && $count > $limit )
						break;

					$return .= "\t" . '<day date="' . attribute_escape( $row['date'] ) . '">' . (int) $row['views'] . '</day>' . "\n";
				}
			}

			$return .= "\t" . '<total>' . (int) array_sum( array_map( function( $row ) { return $row["views"]; }, $rows ) ) . '</total>' . "\n";

			break;

		case 'postviews' :
			if ( isset( $GLOBALS['post_id'] ) && $GLOBALS['post_id'] ) {
				if ( is_null( $summarize ) ) {
					$count = 0;

					foreach ( $rows as $date => $row ) {
						$count++;

						if ( 0 < $limit && $count > $limit )
							break;

						$return .= "\t" . '<day date="' . attribute_escape( $date ) . '">' . (int) $row[ $GLOBALS['post_id'] ] . '</day>' . "\n";
					}
				}

				$return .= "\t" . '<total>' . (int) array_sum( array_map( function( $row ) { return $row[ $GLOBALS['post_id']]; }, $rows ) ) . '</total>' . "\n";

				break;
			}

			$post_ids = array();

			foreach ( $rows as $day_rows )
				foreach ( $day_rows as $k => $v )
					if ( 0 < $k )
						$post_ids[] = $k;

			foreach ( stats_get_posts( $post_ids, $GLOBALS['blog_id'] ) as $id => $post )
				$posts[ $id ] = wpcom_vip_csv_expand_post( $post );

			foreach ( $rows as $date => $day_rows ) {
				if ( is_null( $summarize ) )
					$return .= "\t" . '<day date="' . $date . '">' . "\n";

				foreach ( $day_rows as $k => $v ) {
					if ( $k < 1 )
						continue;

					$return .= "\t\t" . '<post id="' . attribute_escape( $k ) . '" title="' . attribute_escape( $posts[ $k ][1] ) . '" url="' . attribute_escape( $posts[ $k ][2] ) . '">' . (int) $v . '</post>' . "\n";
				}

				if ( is_null( $summarize ) )
					$return .= "\t" . '</day>' . "\n";
			}

			break;

		default :
			$_rows = array( array( 'date', rtrim($table, 's'), 'views' ) );

			foreach ( $rows as $date => $day_rows ) {
				if ( is_null( $summarize ) )
					$return .= "\t" . '<day date="' . $date . '">' . "\n";

				foreach ( $day_rows as $k => $v )
					if ( $k !== $v )
						$return .= "\t\t" . '<' . rtrim( $table, 's' ) . ' value="' . attribute_escape( $k ) . '" count="' . $count . '" limit="' . $limit . '">' . (int) $v . '</' . rtrim( $table, 's' ) . '>' . "\n";

				if ( is_null( $summarize ) )
					$return .= "\t" . '</day>' . "\n";
			}
	}

	$return .= '</' . $table . '>' . "\n";

	return $return;
}

/**
 * A helper function to call the stats API
 *
 * @param string $table Optional. Table for stats can be views, postviews, referrers, searchterms, clicks. Default is views.
 * @param string $end_data Optional. The last day of the desired time frame. Format is 'Y-m-d' (e.g. 2007-05-01) and default is UTC date.
 * @param int $num_days Optional. The length of the desired time frame. Default is 1. Maximum 90 days.
 * @param string $and Optional. Possibility to refine the query with additional AND condition. Usually unused.
 * @param int $limit Optional. The maximum number of records to return. Default is 100 (also the maximum).
 * @return array Fresh tasty data
 */
function _wpcom_vip_get_stats_result( $table = 'views', $end_date = false, $num_days = 1, $and = '', $limit = 100 ) {
	global $wpdb;

	$blog_id = $wpdb->blogid;

	// adjust parameters
	if ( ! in_array( $table, array( 'views', 'postviews', 'authorviews', 'referrers', 'searchterms', 'clicks' ) ) )
		$table = 'views';

	if ( ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date ) )
		$end_date = stats_today( $blog_id );

	if ( $limit > 100 )
		$limit = 100;
	else
		$limit = (int) $limit;

	if ( $num_days > 90 )
		$num_days = 90;
	else
		$num_days = (int) $num_days;

	$args = array( $blog_id, $end_date, $num_days, $and, $limit );

	$result = array();

	if ( is_callable( "stats_get_$table" ) ) {
		$result = call_user_func_array( "stats_get_$table", $args );
	}

	return $result;
}

/**
 * Set the roles that can view stats
 *
 * @param array $roles The roles that can view stats
 */
function wpcom_vip_stats_roles( array $roles ) {
	add_filter( 'pre_option_stats_options', function( $stats_options ) use ( $roles ) {
		$stats_options['roles'] = $roles;
		return $stats_options;
	});
}
