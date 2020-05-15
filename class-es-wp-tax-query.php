<?php

/**
 * Elasticsearch wrapper for WP_Meta_Query
 */
class ES_WP_Tax_Query extends WP_Tax_Query {

	public function __construct( $tax_query ) {
		$this->relation = $tax_query->relation;
		$this->queries = $tax_query->queries;
	}

	/**
	 * Turns an array of tax query parameters into ES Query DSL
	 *
	 * @access public
	 *
	 * @return array
	 */
	public function get_dsl( $es_query ) {
		global $wpdb;

		$join = '';
		$filter = array();
		$count = count( $this->queries );

		foreach ( $this->queries as $index => $query ) {
			$filter_options = array();
			$current_filter = null;

			$this->clean_query( $query );

			if ( is_wp_error( $query ) )
				return false;

			if ( 'AND' == $query['operator'] ) {
				$filter_options = array( 'execution' => 'and' );
			}

			if ( 'IN' == $query['operator'] ) {

				if ( empty( $query['terms'] ) ) {
					if ( 'OR' == $this->relation ) {
						if ( ( $index + 1 === $count ) && empty( $filter ) )
							return false;
						continue;
					} else {
						return false;
					}
				}

			} elseif ( 'NOT IN' == $query['operator'] ) {

				if ( empty( $query['terms'] ) )
					continue;

			} elseif ( 'AND' == $query['operator'] ) {

				if ( empty( $query['terms'] ) )
					continue;

			}

			switch ( $query['field'] ) {
				case 'slug' :
				case 'name' :
					$terms = array_map( 'sanitize_title_for_query', array_values( $query['terms'] ) );
					$current_filter = $es_query::dsl_terms( $es_query->tax_map( $query['taxonomy'], 'term_' . $query['field'] ), $terms, $filter_options );
					break;

				case 'term_taxonomy_id' :
					// This will likely not be hit, as these were probably turned into term_ids. However, by
					// returning false to the 'es_use_mysql_for_term_taxonomy_id' filter, you disable that.
					$current_filter = $es_query::dsl_terms( $es_query->tax_map( $query['taxonomy'], 'term_tt_id' ), $query['terms'], $filter_options );
					break;

				default :
					$terms = array_map( 'absint', array_values( $query['terms'] ) );
					$current_filter = $es_query::dsl_terms( $es_query->tax_map( $query['taxonomy'], 'term_id' ), $terms, $filter_options );
					break;
			}

			if ( 'NOT IN' == $query['operator'] ) {
				$filter[] = array( 'not' => $current_filter );
			} else {
				$filter[] = $current_filter;
			}
		}

		if ( 1 == count( $filter ) ) {
			return reset( $filter );
		} elseif ( ! empty( $filter ) ) {
			return array( strtolower( $this->relation ) => $filter );
		} else {
			return array();
		}
	}

	/**
	 * Validates a single query.
	 *
	 * This is copied from core verbatim, because the core method is private.
	 *
	 * @access private
	 *
	 * @param array &$query The single query
	 */
	private function clean_query( &$query ) {
		if ( empty( $query['taxonomy'] ) || ! taxonomy_exists( $query['taxonomy'] ) ) {
			$query = new WP_Error( 'Invalid taxonomy' );
			return;
		}

		$query['terms'] = array_unique( (array) $query['terms'] );

		if ( is_taxonomy_hierarchical( $query['taxonomy'] ) && $query['include_children'] ) {
			$this->transform_query( $query, 'term_id' );

			if ( is_wp_error( $query ) )
				return;

			$children = array();
			foreach ( $query['terms'] as $term ) {
				$children = array_merge( $children, get_term_children( $term, $query['taxonomy'] ) );
				$children[] = $term;
			}
			$query['terms'] = $children;
		}

		// If we have a term_taxonomy_id, use mysql, as that's almost certainly not stored in ES.
		// However, you can override this.
		if ( 'term_taxonomy_id' == $query['field'] ) {
			if ( apply_filters( 'es_use_mysql_for_term_taxonomy_id', true ) ) {
				$this->transform_query( $query, 'term_id' );
			}
		}
	}

	/**
	 * Transforms a single query, from one field to another.
	 *
	 * @param array &$query The single query
	 * @param string $resulting_field The resulting field
	 */
	public function transform_query( &$query, $resulting_field ) {
		global $wpdb;

		if ( empty( $query['terms'] ) )
			return;

		if ( $query['field'] == $resulting_field )
			return;

		$resulting_field = sanitize_key( $resulting_field );

		switch ( $query['field'] ) {
			case 'slug':
			case 'name':
				$terms = "'" . implode( "','", array_map( 'sanitize_title_for_query', $query['terms'] ) ) . "'";
				$terms = $wpdb->get_col( "
					SELECT $wpdb->term_taxonomy.$resulting_field
					FROM $wpdb->term_taxonomy
					INNER JOIN $wpdb->terms USING (term_id)
					WHERE taxonomy = '{$query['taxonomy']}'
					AND $wpdb->terms.{$query['field']} IN ($terms)
				" );
				break;
			case 'term_taxonomy_id':
				$terms = implode( ',', array_map( 'intval', $query['terms'] ) );
				$terms = $wpdb->get_col( "
					SELECT $resulting_field
					FROM $wpdb->term_taxonomy
					WHERE term_taxonomy_id IN ($terms)
				" );
				break;
			default:
				$terms = implode( ',', array_map( 'intval', $query['terms'] ) );
				$terms = $wpdb->get_col( "
					SELECT $resulting_field
					FROM $wpdb->term_taxonomy
					WHERE taxonomy = '{$query['taxonomy']}'
					AND term_id IN ($terms)
				" );
		}

		if ( 'AND' == $query['operator'] && count( $terms ) < count( $query['terms'] ) ) {
			$query = new WP_Error( 'Inexistent terms' );
			return;
		}

		$query['terms'] = $terms;
		$query['field'] = $resulting_field;
	}
}
