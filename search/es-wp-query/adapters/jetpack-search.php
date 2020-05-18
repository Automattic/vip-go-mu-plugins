<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * ES_WP_Query adapters: Jetpack Search adapter
 *
 * @package ES_WP_Query
 */

// phpcs:disable Generic.Classes.DuplicateClassName.Found

/**
 * An adapter for Jetpack Search.
 */
class ES_WP_Query extends ES_WP_Query_Wrapper {

	/**
	 * Implements the abstract function query_es from ES_WP_Query_Wrapper.
	 *
	 * @param array $es_args Arguments to pass to the Elasticsearch server.
	 * @access protected
	 * @return array The response from the Elasticsearch server.
	 */
	protected function query_es( $es_args ) {
		if ( class_exists( 'Jetpack_Search' ) ) {
			$jetpack_search = Jetpack_Search::instance();
			if ( method_exists( $jetpack_search, 'search' ) ) {
				$es_args = apply_filters( 'jetpack_search_es_query_args', $es_args, $this );
				return $jetpack_search->search( $es_args );
			}
		}
	}

	/**
	 * Sets the posts array to the list of found post IDs.
	 *
	 * @param array          $q           Query arguments.
	 * @param array|WP_Error $es_response Response from the Elasticsearch server.
	 * @access protected
	 */
	protected function set_posts( $q, $es_response ) {
		$this->posts = array();
		if ( ! is_wp_error( $es_response ) && isset( $es_response['results']['hits'] ) ) {
			switch ( $q['fields'] ) {
				case 'ids':
					foreach ( $es_response['results']['hits'] as $hit ) {
						$post_id       = (array) $hit['fields'][ $this->es_map( 'post_id' ) ];
						$this->posts[] = reset( $post_id );
					}
					return;

				case 'id=>parent':
					foreach ( $es_response['results']['hits'] as $hit ) {
						$post_id                          = (array) $hit['fields'][ $this->es_map( 'post_id' ) ];
						$post_parent                      = (array) $hit['fields'][ $this->es_map( 'post_parent' ) ];
						$this->posts[ reset( $post_id ) ] = reset( $post_parent );
					}
					return;

				default:
					if ( apply_filters( 'es_query_use_source', false ) ) {
						$this->posts = wp_list_pluck( $es_response['results']['hits'], '_source' );
						return;
					} else {
						$post_ids = array();
						foreach ( $es_response['results']['hits'] as $hit ) {
							$post_id    = (array) $hit['fields'][ $this->es_map( 'post_id' ) ];
							$post_ids[] = absint( reset( $post_id ) );
						}
						$post_ids = array_filter( $post_ids );
						if ( ! empty( $post_ids ) ) {
							global $wpdb;
							$post__in    = implode( ',', $post_ids );
							$this->posts = $wpdb->get_results( "SELECT $wpdb->posts.* FROM $wpdb->posts WHERE ID IN ($post__in) ORDER BY FIELD( {$wpdb->posts}.ID, $post__in )" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.VIP.DirectDatabaseQuery.NoCaching, WordPress.VIP.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
						}
						return;
					}
			}
		} else {
			$this->posts = array();
		}
	}

	/**
	 * Set up the amount of found posts and the number of pages (if limit clause was used)
	 * for the current query.
	 *
	 * @param array          $q           Query arguments.
	 * @param array|WP_Error $es_response The response from the Elasticsearch server.
	 * @access public
	 */
	public function set_found_posts( $q, $es_response ) {
		if ( ! is_wp_error( $es_response ) && isset( $es_response['results']['total'] ) ) {
			$this->found_posts = absint( $es_response['results']['total'] );
		} else {
			$this->found_posts = 0;
		}
		$this->found_posts   = apply_filters_ref_array( 'es_found_posts', array( $this->found_posts, &$this ) );
		$this->max_num_pages = ceil( $this->found_posts / $q['posts_per_page'] );
	}
}

/**
 * Maps Elasticsearch DSL keys to their VIP-specific naming conventions.
 *
 * @param array $es_map Additional fields to map.
 * @return array The final field mapping.
 */
function vip_es_field_map( $es_map ) {
	return wp_parse_args(
		array(
			'post_author'                   => 'author_id',
			'post_author.user_nicename'     => 'author_login',
			'post_date'                     => 'date',
			'post_date.year'                => 'date_token.year',
			'post_date.month'               => 'date_token.month',
			'post_date.week'                => 'date_token.week',
			'post_date.day'                 => 'date_token.day',
			'post_date.day_of_year'         => 'date_token.day_of_year',
			'post_date.day_of_week'         => 'date_token.day_of_week',
			'post_date.hour'                => 'date_token.hour',
			'post_date.minute'              => 'date_token.minute',
			'post_date.second'              => 'date_token.second',
			'post_date_gmt'                 => 'date_gmt',
			'post_date_gmt.year'            => 'date_gmt_token.year',
			'post_date_gmt.month'           => 'date_gmt_token.month',
			'post_date_gmt.week'            => 'date_gmt_token.week',
			'post_date_gmt.day'             => 'date_gmt_token.day',
			'post_date_gmt.day_of_year'     => 'date_gmt_token.day_of_year',
			'post_date_gmt.day_of_week'     => 'date_gmt_token.day_of_week',
			'post_date_gmt.hour'            => 'date_gmt_token.hour',
			'post_date_gmt.minute'          => 'date_gmt_token.minute',
			'post_date_gmt.second'          => 'date_gmt_token.second',
			'post_content'                  => 'content',
			'post_content.analyzed'         => 'content',
			'post_title'                    => 'title',
			'post_title.analyzed'           => 'title',
			'post_excerpt'                  => 'excerpt',
			'post_password'                 => 'post_password',  // This isn't indexed on VIP.
			'post_name'                     => 'post_name',      // This isn't indexed on VIP.
			'post_modified'                 => 'modified',
			'post_modified.year'            => 'modified_token.year',
			'post_modified.month'           => 'modified_token.month',
			'post_modified.week'            => 'modified_token.week',
			'post_modified.day'             => 'modified_token.day',
			'post_modified.day_of_year'     => 'modified_token.day_of_year',
			'post_modified.day_of_week'     => 'modified_token.day_of_week',
			'post_modified.hour'            => 'modified_token.hour',
			'post_modified.minute'          => 'modified_token.minute',
			'post_modified.second'          => 'modified_token.second',
			'post_modified_gmt'             => 'modified_gmt',
			'post_modified_gmt.year'        => 'modified_gmt_token.year',
			'post_modified_gmt.month'       => 'modified_gmt_token.month',
			'post_modified_gmt.week'        => 'modified_gmt_token.week',
			'post_modified_gmt.day'         => 'modified_gmt_token.day',
			'post_modified_gmt.day_of_year' => 'modified_gmt_token.day_of_year',
			'post_modified_gmt.day_of_week' => 'modified_gmt_token.day_of_week',
			'post_modified_gmt.hour'        => 'modified_gmt_token.hour',
			'post_modified_gmt.minute'      => 'modified_gmt_token.minute',
			'post_modified_gmt.second'      => 'modified_gmt_token.second',
			'post_parent'                   => 'parent_post_id',
			'menu_order'                    => 'menu_order',     // This isn't indexed on VIP.
			'post_mime_type'                => 'post_mime_type', // This isn't indexed on VIP.
			'comment_count'                 => 'comment_count',  // This isn't indexed on VIP.
			'post_meta'                     => 'meta.%s.value.raw_lc',
			'post_meta.analyzed'            => 'meta.%s.value',
			'post_meta.long'                => 'meta.%s.long',
			'post_meta.double'              => 'meta.%s.double',
			'post_meta.binary'              => 'meta.%s.boolean',
			'term_id'                       => 'taxonomy.%s.term_id',
			'term_slug'                     => 'taxonomy.%s.slug',
			'term_name'                     => 'taxonomy.%s.name.raw_lc',
			'category_id'                   => 'category.term_id',
			'category_slug'                 => 'category.slug',
			'category_name'                 => 'category.name.raw',
			'tag_id'                        => 'tag.term_id',
			'tag_slug'                      => 'tag.slug',
			'tag_name'                      => 'tag.name.raw',
		),
		$es_map
	);
}
add_filter( 'es_field_map', 'vip_es_field_map' );

/**
 * Returns the lowercase version of a meta value.
 *
 * @param mixed  $meta_value   The meta value.
 * @param string $meta_key     The meta key.
 * @param string $meta_compare The comparison operation.
 * @param string $meta_type    The type of meta (post, user, term, etc).
 * @return mixed If value is a string, returns the lowercase version. Otherwise, returns the original value, unmodified.
 */
function vip_es_meta_value_tolower( $meta_value, $meta_key, $meta_compare, $meta_type ) {
	if ( ! is_string( $meta_value ) || empty( $meta_value ) ) {
		return $meta_value;
	}
	return strtolower( $meta_value );
}
add_filter( 'es_meta_query_meta_value', 'vip_es_meta_value_tolower', 10, 4 );

/**
 * Normalise term name to lowercase as we are mapping that against raw_lc field.
 *
 * @param string|mixed $term     Term's name which should be normalised to
 *                               lowercase.
 * @param string       $taxonomy Taxonomy of the term.
 * @return mixed If $term is a string, lowercased string is returned. Otherwise
 *               original value is return unchanged.
 */
function vip_es_term_name_slug_tolower( $term, $taxonomy ) {
	if ( ! is_string( $term ) || empty( $term ) ) {
		return $term;
	}
	return strtolower( $term );
}
add_filter( 'es_tax_query_term_name', 'vip_es_term_name_slug_tolower', 10, 2 );

/**
 * Advanced Post Cache and es-wp-query do not work well together. In
 * particular, the WP_Query->found_posts attribute gets corrupted when using
 * both of these plugins, so here we disable Advanced Post Cache completely
 * when queries are being made using Elasticsearch.
 *
 * On the other hand, if a non-Elasticsearch query is run, and we disabled
 * Advanced Post Cache earlier, we enable it again, to make use of its caching
 * features.
 *
 * Note that this applies only to calls done via WP_Query(), and not
 * ES_WP_Query()
 *
 * @param WP_Query|ES_WP_Query|ES_WP_Query_Wrapper $query The query to examine.
 */
function vip_es_disable_advanced_post_cache( &$query ) {
	global $advanced_post_cache_object;

	static $disabled_apc = false;

	if ( empty( $advanced_post_cache_object ) || ! is_object( $advanced_post_cache_object ) ) {
		return;
	}

	/*
	 * These two might be passsed to us; we only
	 * handle WP_Query, so ignore these.
	 */
	if (
		( $query instanceof ES_WP_Query_Wrapper ) ||
		( $query instanceof ES_WP_Query )
	) {
		return;
	}

	if ( $query->get( 'es' ) ) {
		if ( true === $disabled_apc ) {
			// Already disabled, don't try again.
			return;
		}

		/*
		 * An Elasticsearch-enabled query is being run. Disable Advanced Post Cache
		 * entirely.
		 *
		 * Note that there is one action-hook that is not deactivated: The switch_blog
		 * action is not deactivated, because it might be called in-between
		 * Elasticsearch-enabled query, and a non-Elasticsearch query, and because it
		 * does not have an effect on WP_Query()-results directly.
		 */

		remove_filter( 'posts_request', array( $advanced_post_cache_object, 'posts_request' ) );
		remove_filter( 'posts_results', array( $advanced_post_cache_object, 'posts_results' ) );

		remove_filter( 'post_limits_request', array( $advanced_post_cache_object, 'post_limits_request' ), 999 );

		remove_filter( 'found_posts_query', array( $advanced_post_cache_object, 'found_posts_query' ) );
		remove_filter( 'found_posts', array( $advanced_post_cache_object, 'found_posts' ) );

		$disabled_apc = true;
	} else {
		// A non-ES query.
		if ( true === $disabled_apc ) {
			/*
			 * Earlier, we disabled Advanced Post Cache
			 * entirely, but now a non-Elasticsearch query is
			 * being run, and in such cases it might be useful
			 * to have the Cache enabled. Here we enable
			 * it again.
			 */
			$advanced_post_cache_object->__construct();

			$disabled_apc = false;
		}
	}
}
add_action( 'pre_get_posts', 'vip_es_disable_advanced_post_cache', -100 );
