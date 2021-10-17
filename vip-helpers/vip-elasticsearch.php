<?php

/**
 * Executes an elasticsearch query via our REST API.
 *
 * Requires setup on our end and a paid addon to your hosting account.
 * You probably shouldn't be using this function. Questions? Ask us.
 *
 * Valid arguments:
 *
 * * size: Number of query results to return.
 *
 * * from: Offset, the starting result to return.
 *
 * * multi_match: Will do a match search against the default fields (this is almost certainly what you want).
 *                e.g. array( 'query' => 'this is a test', 'fields' => array( 'content' ) )
 *                See: http://www.elasticsearch.org/guide/reference/query-dsl/multi-match-query.html
 *
 * * query_string: Will do a query_string search, interprets the string as Lucene query syntax.
 *                 e.g. array( 'default_field' => 'content', 'query' => 'tag:(world OR nation) howdy' )
 *                 This can fail if the user doesn't close parenthesis, or specifies a field that is not valid.
 *                 See: http://www.elasticsearch.org/guide/reference/query-dsl/query-string-query.html
 *
 * * more_like_this: Will do a more_like_this search, which is best for related content.
 *                   e.g. array( 'fields' => array( 'title', 'content' ), 'like_text' => 'this is a test', 'min_term_freq' => 1, 'max_query_terms' => 12 )
 *                   See: http://www.elasticsearch.org/guide/reference/query-dsl/mlt-query.html
 *
 * * facets: Structured set of facets. DO NOT do a terms facet on the content of posts/comments. It will load ALL terms into memory,
 *           probably taking minutes to complete and slowing down the entire cluster. With great power... etc.
 *           See: http://www.elasticsearch.org/guide/reference/api/search/facets/index.html
 *
 * * filter: Structured set of filters (often FASTER, since cached from one query to the next).
 *            See: http://www.elasticsearch.org/guide/reference/query-dsl/filtered-query.html
 *
 * * highlight: Structure defining how to highlight the results.
 *              See: http://www.elasticsearch.org/guide/reference/api/search/highlighting.html
 *
 * * fields: Structure defining what fields to return with the results.
 *           See: http://www.elasticsearch.org/guide/reference/api/search/fields.html
 *
 * * sort: Structure defining how to sort the results.
 *         See: http://www.elasticsearch.org/guide/reference/api/search/sort.html
 *
 * @param array $args
 * @return bool|string False if WP_Error, otherwise JSON string
 */
function es_api_search_index( $args ) {
	if ( class_exists( 'Jetpack' ) ) {
		$jetpack_blog_id = Jetpack::get_option( 'id' );
		if ( ! $jetpack_blog_id ) {
			return array( 'error' => 'Failed to get Jetpack blog_id' );
		}

		$args['blog_id'] = $jetpack_blog_id;
	}

	$defaults = array(
		'blog_id' => get_current_blog_id(),
	);

	$args = wp_parse_args( $args, $defaults );

	$args['blog_id'] = absint( $args['blog_id'] );

	$endpoint    = sprintf( '/sites/%s/search', $args['blog_id'] );
	$service_url = 'https://public-api.wordpress.com/rest/v1' . $endpoint;

	$do_authenticated_request = false;
	if ( class_exists( 'Jetpack_Client' )
			&& isset( $args['authenticated_request'] )
		&& true === $args['authenticated_request'] ) {
		$do_authenticated_request = true;
	}

	unset( $args['blog_id'] );
	unset( $args['authenticated_request'] );

	$request_args = array(
		'headers' => array(
			'Content-Type' => 'application/json',
		),
	);
	$request_body = wp_json_encode( $args );

	$start_time = microtime( true );

	if ( $do_authenticated_request ) {
		$request_args['method'] = 'POST';

		$request = Jetpack_Client::wpcom_json_api_request_as_blog( $endpoint, Jetpack_Client::WPCOM_JSON_API_VERSION, $request_args, $request_body );
	} else {
		$request_args = array_merge( $request_args, array(
			'body' => $request_body,
		) );

		$request = wp_remote_post( $service_url, $request_args );
	}

	$end_time = microtime( true );

	if ( is_wp_error( $request ) ) {
		return false;
	}

	$response = json_decode( wp_remote_retrieve_body( $request ), true );

	// Rewrite blog id from remote to local id
	if ( isset( $response['results'] ) && isset( $response['results']['hits'] ) ) {
		$local_blog_id = get_current_blog_id();
		foreach ( $response['results']['hits'] as $key => $hit ) {
			if ( isset( $hit['fields']['blog_id'] ) && $hit['fields']['blog_id'] == $jetpack_blog_id ) {
				$response['results']['hits'][ $key ]['fields']['blog_id'] = $local_blog_id;
			}
		}
	}

	$took = $response && $response['took'] ? $response['took'] : null;

	$queried = array(
		'args'          => $args,
		'response'      => $response,
		'response_code' => wp_remote_retrieve_response_code( $request ),
		'elapsed_time'  => ( $end_time - $start_time ) * 1000, // Convert from float seconds to ms
		'es_time'       => $took,
		'url'           => $service_url,
	);

	do_action( 'did_vip_elasticsearch_query', $queried );

	return $response;
}

// Log all ES queries
function wpcom_vip_did_elasticsearch_query( $query ) {
	if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES ) {
		return;
	}

	global $wp_elasticsearch_queries_log;

	if ( ! $wp_elasticsearch_queries_log ) {
		$wp_elasticsearch_queries_log = array();
	}

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_wp_debug_backtrace_summary
	$query['backtrace'] = wp_debug_backtrace_summary();

	$wp_elasticsearch_queries_log[] = $query;
}

add_action( 'did_vip_elasticsearch_query', 'wpcom_vip_did_elasticsearch_query' );

/**
 * A wrapper for es_api_search_index() that accepts WP-style args
 *
 * This is a copy/paste, up to date as of WP.com r65003 (Jan 7th, 2013)
 * Updated Oct 17, 2021: es_api_search_index() no longer accepts the second argument
 *
 * @param array $args
 * @return bool|string False if WP_Error, otherwise JSON string
 * @see wpcom_search_api_wp_to_es_args() for details
 */
function wpcom_search_api_query( $args ) {
	$es_query_args = wpcom_search_api_wp_to_es_args( $args );

	return es_api_search_index( $es_query_args );
}

/**
 * Converts WP-style args to ES args
 *
 * @param array $args
 * @return array
 */
function wpcom_search_api_wp_to_es_args( $args ) {
	// phpcs:disable Squiz.PHP.CommentedOutCode.Found
	$defaults = array(
		'blog_id'        => get_current_blog_id(),

		'query'          => null,    // Search phrase
		'query_fields'   => array( 'title', 'content', 'author', 'tag', 'category' ),

		'post_type'      => 'post',  // string or an array
		'terms'          => array(), // ex: array( 'taxonomy-1' => array( 'slug' ), 'taxonomy-2' => array( 'slug-a', 'slug-b' ) )

		'author'         => null,    // id or an array of ids
		'author_name'    => array(), // string or an array

		'date_range'     => null,    // array( 'field' => 'date', 'gt' => 'YYYY-MM-dd', 'lte' => 'YYYY-MM-dd' ); date formats: 'YYYY-MM-dd' or 'YYYY-MM-dd HH:MM:SS'

		'orderby'        => null,    // Defaults to 'relevance' if query is set, otherwise 'date'. Pass an array for multiple orders.
		'order'          => 'DESC',

		'posts_per_page' => 10,
		'offset'         => null,
		'paged'          => null,

		/**
		 * Facets. Examples:
		 * array(
		 *     'Tag'       => array( 'type' => 'taxonomy', 'taxonomy' => 'post_tag', 'count' => 10 ) ),
		 *     'Post Type' => array( 'type' => 'post_type', 'count' => 10 ) ),
		 * );
		 */
		'facets'         => null,
	);

	$args = wp_parse_args( $args, $defaults );

	$es_query_args = array(
		'blog_id' => absint( $args['blog_id'] ),
		'size'    => absint( $args['posts_per_page'] ),
	);

	// ES "from" arg (offset)
	if ( $args['offset'] ) {
		$es_query_args['from'] = absint( $args['offset'] );
	} elseif ( $args['paged'] ) {
		$es_query_args['from'] = max( 0, ( absint( $args['paged'] ) - 1 ) * $es_query_args['size'] );
	}

	if ( ! is_array( $args['author_name'] ) ) {
		$args['author_name'] = array( $args['author_name'] );
	}

	// ES stores usernames, not IDs, so transform
	if ( ! empty( $args['author'] ) ) {
		if ( ! is_array( $args['author'] ) ) {
			$args['author'] = array( $args['author'] );
		}
		foreach ( $args['author'] as $author ) {
			$user = get_user_by( 'id', $author );

			if ( $user && ! empty( $user->user_login ) ) {
				$args['author_name'][] = $user->user_login;
			}
		}
	}

	// Build the filters from the query elements.
	// Filters rock because they are cached from one query to the next
	// but they are cached as individual filters, rather than all combined together.
	// May get performance boost by also caching the top level boolean filter too.
	$filters = array();

	if ( $args['post_type'] ) {
		if ( ! is_array( $args['post_type'] ) ) {
			$args['post_type'] = array( $args['post_type'] );
		}
		$filters[] = array( 'terms' => array( 'post_type' => $args['post_type'] ) );
	}

	if ( $args['author_name'] ) {
		$filters[] = array( 'terms' => array( 'author_login' => $args['author_name'] ) );
	}

	if ( ! empty( $args['date_range'] ) && isset( $args['date_range']['field'] ) ) {
		$field = $args['date_range']['field'];
		unset( $args['date_range']['field'] );
		$filters[] = array( 'range' => array( $field => $args['date_range'] ) );
	}

	if ( is_array( $args['terms'] ) ) {
		foreach ( $args['terms'] as $tax => $terms ) {
			$terms = (array) $terms;
			if ( count( $terms ) && mb_strlen( $tax ) ) {
				switch ( $tax ) {
					case 'post_tag':
						$tax_fld = 'tag.slug';
						break;
					case 'category':
						$tax_fld = 'category.slug';
						break;
					default:
						$tax_fld = 'taxonomy.' . $tax . '.slug';
						break;
				}
				foreach ( $terms as $term ) {
					$filters[] = array( 'term' => array( $tax_fld => $term ) );
				}
			}
		}
	}

	if ( ! empty( $filters ) ) {
		$es_query_args['filter'] = array( 'and' => $filters );
	} else {
		$es_query_args['filter'] = array( 'match_all' => new stdClass() );
	}

	// phpcs:disable WordPress.WP.CapitalPDangit.DeprecatedWhitelistCommentFound
	// Fill in the query
	// todo: add auto phrase searching
	// todo: add fuzzy searching to correct for spelling mistakes
	// todo: boost title, tag, and category matches
	if ( $args['query'] ) {
		$es_query_args['query'] = array(
			'multi_match' => array(
				'query'    => $args['query'],
				'fields'   => $args['query_fields'],
				'operator' => 'and',
				'type'     => 'cross_fields',
			),
		);

		if ( ! $args['orderby'] ) {
			$args['orderby'] = array( 'relevance' );
		}
	} else {
		if ( ! $args['orderby'] ) {
			$args['orderby'] = array( 'date' );
		}
	}

	// Validate the "order" field
	switch ( strtolower( $args['order'] ) ) {
		case 'asc':
			$args['order'] = 'asc';
			break;
		case 'desc':
		default:
			$args['order'] = 'desc';
			break;
	}

	$es_query_args['sort'] = array();
	foreach ( (array) $args['orderby'] as $orderby ) {
		// Translate orderby from WP field to ES field
		// todo: add support for sorting by title, num likes, num comments, num views, etc
		switch ( $orderby ) {
			case 'relevance':
				$es_query_args['sort'][] = array( '_score' => array( 'order' => $args['order'] ) );
				break;
			case 'date':
				$es_query_args['sort'][] = array( 'date' => array( 'order' => $args['order'] ) );
				break;
			case 'ID':
				$es_query_args['sort'][] = array( 'id' => array( 'order' => $args['order'] ) );
				break;
			case 'author':
				$es_query_args['sort'][] = array( 'author.raw' => array( 'order' => $args['order'] ) );
				break;
		}
	}
	if ( empty( $es_query_args['sort'] ) ) {
		unset( $es_query_args['sort'] );
	}

	// Facets. Deprecated in favor of Aggregations. Code is very similar, but left in place for backwards compatibility
	// while things are migrated over. Should be removed once everything is running > 2.x
	if ( ! empty( $args['facets'] ) ) {
		foreach ( (array) $args['facets'] as $label => $facet ) {
			switch ( $facet['type'] ) {

				case 'taxonomy':
					switch ( $facet['taxonomy'] ) {

						case 'post_tag':
							$field = 'tag';
							break;

						case 'category':
							$field = 'category';
							break;

						default:
							$field = 'taxonomy.' . $facet['taxonomy'];
							break;
					} // switch $facet['taxonomy']

					$es_query_args['facets'][ $label ] = array(
						'terms' => array(
							'field' => $field . '.slug',
							'size'  => $facet['count'],
						),
					);

					break;

				case 'post_type':
					$es_query_args['facets'][ $label ] = array(
						'terms' => array(
							'field' => 'post_type',
							'size'  => $facet['count'],
						),
					);

					break;

				case 'date_histogram':
					$es_query_args['facets'][ $label ] = array(
						'date_histogram' => array(
							'interval' => $facet['interval'],
							'field'    => ( ! empty( $facet['field'] ) && 'post_date_gmt' == $facet['field'] ) ? 'date_gmt' : 'date',
							'size'     => $facet['count'],
						),
					);

					break;
			}
		}
	}

	// Aggregations
	if ( ! empty( $args['aggregations'] ) ) {
		$max_aggregations_count = 100;

		foreach ( (array) $args['aggregations'] as $label => $aggregation ) {
			switch ( $aggregation['type'] ) {

				case 'taxonomy':
					switch ( $aggregation['taxonomy'] ) {

						case 'post_tag':
							$field = 'tag';
							break;

						case 'category':
							$field = 'category';
							break;

						default:
							$field = 'taxonomy.' . $aggregation['taxonomy'];
							break;
					} // switch $aggregation['taxonomy']

					$es_query_args['aggregations'][ $label ] = array(
						'terms' => array(
							'field' => $field . '.slug',
							'size'  => min( (int) $aggregation['count'], $max_aggregations_count ),
						),
					);

					break;

				case 'post_type':
					$es_query_args['aggregations'][ $label ] = array(
						'terms' => array(
							'field' => 'post_type',
							'size'  => min( (int) $aggregation['count'], $max_aggregations_count ),
						),
					);

					break;

				case 'date_histogram':
					$es_query_args['aggregations'][ $label ] = array(
						'date_histogram' => array(
							'interval' => $aggregation['interval'],
							'field'    => ( ! empty( $aggregation['field'] ) && 'post_date_gmt' == $aggregation['field'] ) ? 'date_gmt' : 'date',
						),
					);

					break;
			}
		}
	}

	return $es_query_args;
	// phpcs:enable
}
