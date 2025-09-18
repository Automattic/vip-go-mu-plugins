<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * ES_WP_Query adapters: VIP Search adapter
 *
 * @package ES_WP_Query
 */

// phpcs:disable Generic.Classes.DuplicateClassName.Found

/**
 * An adapter for VIP Search.
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
		if ( class_exists( '\\Automattic\\VIP\\Search\\Search' ) ) {
			$vip_search = \Automattic\VIP\Search\Search::instance();

			if ( method_exists( $vip_search, 'query_es' ) ) {
				$result = $vip_search->query_es( 'post', $es_args );

				return $result;
			}
		}
	}

	/**
	 * Sets the posts array to the list of found post IDs.
	 *
	 * @param array          $q           Query arguments.
	 * @param array|WP_Error $es_response Response from VIP Search.
	 * @access protected
	 */
	protected function set_posts( $q, $es_response ) {
		$this->posts = array();
		if ( ! is_wp_error( $es_response ) && isset( $es_response['documents'] ) ) {
			switch ( $q['fields'] ) {
				case 'ids':
					foreach ( $es_response['documents'] as $hit ) {
						$post_id       = (array) $hit[ $this->es_map( 'post_id' ) ];
						$this->posts[] = reset( $post_id );
					}
					return;

				case 'id=>parent':
					foreach ( $es_response['documents'] as $hit ) {
						$post_id                          = (array) $hit[ $this->es_map( 'post_id' ) ];
						$post_parent                      = (array) $hit[ $this->es_map( 'post_parent' ) ];
						$this->posts[ reset( $post_id ) ] = reset( $post_parent );
					}
					return;

				default:
					if ( apply_filters( 'es_query_use_source', false ) ) {
						$this->posts = wp_list_pluck( $es_response['documents'], '_source' );
						return;
					} else {
						$post_ids = array();
						foreach ( $es_response['documents'] as $hit ) {
							$post_id    = (array) $hit[ $this->es_map( 'post_id' ) ];
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
		if ( ! is_wp_error( $es_response ) && isset( $es_response['found_documents']['value'] ) ) {
			$this->found_posts = absint( $es_response['found_documents']['value'] );
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
			'post_author'                   => 'post_author.id',
			'post_author.user_nicename'     => 'post_author.login.raw',
			'post_date'                     => 'post_date',
			'post_date.year'                => 'date_terms.year',
			'post_date.month'               => 'date_terms.month',
			'post_date.week'                => 'date_terms.week',
			'post_date.day'                 => 'date_terms.day',
			'post_date.day_of_year'         => 'date_terms.dayofyear',
			'post_date.day_of_week'         => 'date_terms.dayofweek',
			'post_date.hour'                => 'date_terms.hour',
			'post_date.minute'              => 'date_terms.minute',
			'post_date.second'              => 'date_terms.second',
			'post_date_gmt'                 => 'post_date_gmt',
			'post_date_gmt.year'            => 'date_gmt_terms.year',
			'post_date_gmt.month'           => 'date_gmt_terms.month',
			'post_date_gmt.week'            => 'date_gmt_terms.week',
			'post_date_gmt.day'             => 'date_gmt_terms.day',
			'post_date_gmt.day_of_year'     => 'date_gmt_terms.day_of_year',
			'post_date_gmt.day_of_week'     => 'date_gmt_terms.day_of_week',
			'post_date_gmt.hour'            => 'date_gmt_terms.hour',
			'post_date_gmt.minute'          => 'date_gmt_terms.minute',
			'post_date_gmt.second'          => 'date_gmt_terms.second',
			'post_content'                  => 'post_content',
			'post_content.analyzed'         => 'post_content',
			'post_title'                    => 'post_title.raw',
			'post_title.analyzed'           => 'post_title',
			'post_type'                     => 'post_type.raw',
			'post_excerpt'                  => 'post_excerpt',
			'post_password'                 => 'post_password',  // This isn't indexed on VIP.
			'post_name'                     => 'post_name.raw',
			'post_modified'                 => 'post_modified',
			'post_modified.year'            => 'modified_date_terms.year',
			'post_modified.month'           => 'modified_date_terms.month',
			'post_modified.week'            => 'modified_date_terms.week',
			'post_modified.day'             => 'modified_date_terms.day',
			'post_modified.day_of_year'     => 'modified_date_terms.day_of_year',
			'post_modified.day_of_week'     => 'modified_date_terms.day_of_week',
			'post_modified.hour'            => 'modified_date_terms.hour',
			'post_modified.minute'          => 'modified_date_terms.minute',
			'post_modified.second'          => 'modified_date_terms.second',
			'post_modified_gmt'             => 'post_modified_gmt',
			'post_modified_gmt.year'        => 'modified_date_gmt_terms.year',
			'post_modified_gmt.month'       => 'modified_date_gmt_terms.month',
			'post_modified_gmt.week'        => 'modified_date_gmt_terms.week',
			'post_modified_gmt.day'         => 'modified_date_gmt_terms.day',
			'post_modified_gmt.day_of_year' => 'modified_date_gmt_terms.day_of_year',
			'post_modified_gmt.day_of_week' => 'modified_date_gmt_terms.day_of_week',
			'post_modified_gmt.hour'        => 'modified_date_gmt_terms.hour',
			'post_modified_gmt.minute'      => 'modified_date_gmt_terms.minute',
			'post_modified_gmt.second'      => 'modified_date_gmt_terms.second',
			'post_parent'                   => 'post_parent',
			'menu_order'                    => 'menu_order',
			'post_mime_type'                => 'post_mime_type',
			'comment_count'                 => 'comment_count',
			'post_meta'                     => 'meta.%s.value.sortable',
			'post_meta.analyzed'            => 'meta.%s.value',
			'post_meta.long'                => 'meta.%s.long',
			'post_meta.double'              => 'meta.%s.double',
			'post_meta.binary'              => 'meta.%s.boolean',
			'post_meta.datetime'            => 'meta.%s.datetime',
			'post_meta.date'                => 'meta.%s.date',
			'term_id'                       => 'terms.%s.term_id',
			'term_slug'                     => 'terms.%s.slug',
			'term_name'                     => 'terms.%s.name.sortable',
			'category_id'                   => 'terms.category.term_id',
			'category_slug'                 => 'terms.category.slug',
			'category_name'                 => 'terms.category.name.sortable',
			'tag_id'                        => 'terms.post_tag.term_id',
			'tag_slug'                      => 'terms.post_tag.slug',
			'tag_name'                      => 'terms.post_tag.name.sortable',
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
 * Normalise term name to lowercase as we are mapping that against the "sortable" field, which is a lowercased keyword.
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
