<?php
/**
 * ES_WP_Query classes: ES_WP_Tax_Query class
 *
 * @package ES_WP_Query
 */

/**
 * Elasticsearch wrapper for WP_Meta_Query
 */
class ES_WP_Tax_Query extends WP_Tax_Query {

	/**
	 * Some object which extends ES_WP_Query_Wrapper.
	 *
	 * @var ES_WP_Query_Wrapper
	 */
	protected $es_query;

	/**
	 * Generates DSL from a tax query.
	 *
	 * @param WP_Tax_Query $tax_query The tax query to transform.
	 * @access public
	 * @return ES_WP_Tax_Query
	 */
	public static function get_from_tax_query( $tax_query ) {
		$q           = new ES_WP_Tax_Query( $tax_query->queries );
		$q->relation = $tax_query->relation;
		return $q;
	}

	/**
	 * Get a (light) ES filter that will always produce no results. This allows
	 * individual tax query clauses to fail without breaking the rest of them.
	 *
	 * @return array ES term query for post_id:0.
	 */
	protected function get_no_results_clause() {
		return $this->es_query->dsl_terms( $this->es_query->es_map( 'post_id' ), 0 );
	}

	/**
	 * Turns an array of tax query parameters into ES Query DSL
	 *
	 * @access public
	 *
	 * @param object $es_query Any object which extends ES_WP_Query_Wrapper.
	 * @return array ES filters
	 */
	public function get_dsl( $es_query ) {
		$this->es_query = $es_query;

		$filters = $this->get_dsl_clauses();

		return apply_filters_ref_array( 'es_wp_tax_query_dsl', array( $filters, $this->queries, $this->es_query ) );
	}

	/**
	 * Generate ES Filter clauses to be appended to a main query.
	 *
	 * Called by the public {@see ES_WP_Meta_Query::get_dsl()}, this method
	 * is abstracted out to maintain parity with the other Query classes.
	 *
	 * @access protected
	 *
	 * @return array
	 */
	protected function get_dsl_clauses() {
		/*
		 * $queries are passed by reference to
		 * `ES_WP_Meta_Query::get_dsl_for_query()` for recursion. To keep
		 * $this->queries unaltered, pass a copy.
		 */
		$queries = $this->queries;
		return $this->get_dsl_for_query( $queries );
	}

	/**
	 * Generate ES filters for a single query array.
	 *
	 * If nested subqueries are found, this method recurses the tree to produce
	 * the properly nested DSL.
	 *
	 * @access protected
	 *
	 * @param array $query Query to parse, passed by reference.
	 * @return boolarray Array containing nested ES filter clauses on success or
	 *                   false on error.
	 */
	protected function get_dsl_for_query( &$query ) {
		$filters = array();

		foreach ( $query as $key => &$clause ) {
			if ( 'relation' === $key ) {
				$relation = $query['relation'];
			} elseif ( is_array( $clause ) ) {
				if ( $this->is_first_order_clause( $clause ) ) {
					// This is a first-order clause.
					$filters[] = $this->get_dsl_for_clause( $clause, $query );
				} else {
					// This is a subquery, so we recurse.
					$filters[] = $this->get_dsl_for_query( $clause );
				}
			}
		}

		// Filter to remove empties.
		$filters = array_filter( $filters );

		if ( ! empty( $relation ) && 'or' === strtolower( $relation ) ) {
			$relation = 'should';
		} else {
			$relation = 'filter';
		}

		if ( count( $filters ) > 1 ) {
			$filters = array(
				'bool' => array(
					$relation => $filters,
				),
			);
		} elseif ( ! empty( $filters ) ) {
			$filters = reset( $filters );
		}

		return $filters;
	}

	/**
	 * Generate ES filter clauses for a first-order query clause.
	 *
	 * "First-order" means that it's an array with a 'key' or 'value'.
	 *
	 * @access public
	 *
	 * @param array $clause Query clause, passed by reference.
	 * @param array $query Parent query array.
	 * @return bool|array ES filter clause on success, or false on error.
	 */
	public function get_dsl_for_clause( &$clause, $query ) {
		$current_filter = null;

		$this->clean_query( $clause );

		if ( is_wp_error( $clause ) ) {
			return $this->get_no_results_clause();
		}

		// If the comparison is EXISTS or NOT EXISTS, handle that first since
		// it's quick and easy.
		if ( 'EXISTS' === $clause['operator'] || 'NOT EXISTS' === $clause['operator'] ) {
			if ( empty( $clause['taxonomy'] ) ) {
				return $this->get_no_results_clause();
			}

			if ( 'EXISTS' === $clause['operator'] ) {
				return $this->es_query->dsl_exists( $this->es_query->tax_map( $clause['taxonomy'], 'term_id' ) );
			} elseif ( 'NOT EXISTS' === $clause['operator'] ) {
				return $this->es_query->dsl_missing( $this->es_query->tax_map( $clause['taxonomy'], 'term_id' ) );
			}
		}

		if ( 'AND' === $clause['operator'] ) {
			$terms_method = array( $this->es_query, 'dsl_all_terms' );
		} else {
			$terms_method = array( $this->es_query, 'dsl_terms' );
		}

		if ( empty( $clause['terms'] ) ) {
			if ( 'NOT IN' === $clause['operator'] || 'AND' === $clause['operator'] ) {
				return array();
			} elseif ( 'IN' === $clause['operator'] ) {
				return $this->get_no_results_clause();
			}
		}

		switch ( $clause['field'] ) {
			case 'slug':
			case 'name':
				foreach ( $clause['terms'] as &$term ) {
					/*
					 * 0 is the $term_id parameter. We don't have a term ID yet, but it doesn't
					 * matter because `sanitize_term_field()` ignores the $term_id param when the
					 * context is 'db'.
					 */
					$term = sanitize_term_field( $clause['field'], $term, 0, $clause['taxonomy'], 'db' );

					/**
					 * Allow adapters to normalize term value (like `strtolower` if mapping to
					 * `raw_lc`).
					 *
					 * The dynamic portion of the filter name, `$clause['field']`, refers to the
					 * term field.
					 *
					 * @param mixed  $term     Term's slug or name sanitized using
					 *                         `sanitize_term_field` function for db context.
					 * @param string $taxonomy Term's taxonomy slug.
					 */
					$term = apply_filters( "es_tax_query_term_{$clause['field']}", $term, $clause['taxonomy'] );

				}
				$current_filter = call_user_func( $terms_method, $this->es_query->tax_map( $clause['taxonomy'], 'term_' . $clause['field'] ), $clause['terms'] );
				break;

			case 'term_taxonomy_id':
				if ( ! empty( $clause['taxonomy'] ) ) {
					$current_filter = call_user_func( $terms_method, $this->es_query->tax_map( $clause['taxonomy'], 'term_tt_id' ), $clause['terms'] );
				} else {
					$matches = array();
					foreach ( $clause['terms'] as &$term ) {
						$matches[] = $this->es_query->dsl_multi_match( $this->es_query->tax_map( '*', 'term_tt_id' ), $term );
					}
					if ( count( $matches ) > 1 ) {
						$current_filter = array(
							'bool' => array(
								( 'AND' === $clause['operator'] ? 'filter' : 'should' ) => $matches,
							),
						);
					} else {
						$current_filter = reset( $matches );
					}
				}

				break;

			default:
				$terms          = array_map( 'absint', array_values( $clause['terms'] ) );
				$current_filter = call_user_func( $terms_method, $this->es_query->tax_map( $clause['taxonomy'], 'term_id' ), $terms );
				break;
		}

		if ( 'NOT IN' === $clause['operator'] ) {
			return array(
				'bool' => array(
					'must_not' => $current_filter,
				),
			);
		} else {
			return $current_filter;
		}
	}

	/**
	 * Validates a single query.
	 *
	 * This is copied from core verbatim, because the core method is private.
	 *
	 * @access private
	 *
	 * @param array $query The single query.
	 */
	private function clean_query( &$query ) {
		if ( empty( $query['taxonomy'] ) ) {
			if ( 'term_taxonomy_id' !== $query['field'] ) {
				$query = new WP_Error( 'Invalid taxonomy' );
				return;
			}

			// So long as there are shared terms, include_children requires that a taxonomy is set.
			$query['include_children'] = false;
		} elseif ( ! taxonomy_exists( $query['taxonomy'] ) ) {
			$query = new WP_Error( 'Invalid taxonomy' );
			return;
		}

		$query['terms'] = array_values( array_unique( (array) $query['terms'] ) );

		if ( is_taxonomy_hierarchical( $query['taxonomy'] ) && $query['include_children'] ) {
			$this->transform_query( $query, 'term_id' );

			if ( is_wp_error( $query ) ) {
				return;
			}

			$children = array();
			foreach ( $query['terms'] as $term ) {
				$children   = array_merge( $children, get_term_children( $term, $query['taxonomy'] ) );
				$children[] = $term;
			}
			$query['terms'] = $children;
		}

		// If we have a term_taxonomy_id, use mysql, as that's almost certainly not stored in ES.
		// However, you can override this.
		if ( 'term_taxonomy_id' === $query['field'] && ! empty( $query['taxonomy'] ) ) {
			if ( apply_filters( 'es_use_mysql_for_term_taxonomy_id', true ) ) {
				$this->transform_query( $query, 'term_id' );
			}
		}
	}

	/**
	 * Transforms a single query, from one field to another.
	 *
	 * @param array  $query           The single query.
	 * @param string $resulting_field The resulting field.
	 */
	public function transform_query( &$query, $resulting_field ) {
		global $wpdb;

		if ( empty( $query['terms'] ) ) {
			return;
		}

		if ( $query['field'] === $resulting_field ) {
			return;
		}

		$resulting_field = sanitize_key( $resulting_field );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.VIP.DirectDatabaseQuery.NoCaching, WordPress.VIP.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		switch ( $query['field'] ) {
			case 'slug':
			case 'name':
				$terms = "'" . implode( "','", array_map( 'sanitize_title_for_query', $query['terms'] ) ) . "'";
				$terms = $wpdb->get_col(
					"
					SELECT $wpdb->term_taxonomy.$resulting_field
					FROM $wpdb->term_taxonomy
					INNER JOIN $wpdb->terms USING (term_id)
					WHERE taxonomy = '{$query['taxonomy']}'
					AND $wpdb->terms.{$query['field']} IN ($terms)
				" 
				);
				break;
			case 'term_taxonomy_id':
				$terms = implode( ',', array_map( 'intval', $query['terms'] ) );
				$terms = $wpdb->get_col(
					"
					SELECT $resulting_field
					FROM $wpdb->term_taxonomy
					WHERE term_taxonomy_id IN ($terms)
				" 
				);
				break;
			default:
				$terms = implode( ',', array_map( 'intval', $query['terms'] ) );
				$terms = $wpdb->get_col(
					"
					SELECT $resulting_field
					FROM $wpdb->term_taxonomy
					WHERE taxonomy = '{$query['taxonomy']}'
					AND term_id IN ($terms)
				" 
				);
		}
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.VIP.DirectDatabaseQuery.NoCaching, WordPress.VIP.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery

		if ( 'AND' === $query['operator'] && count( $terms ) < count( $query['terms'] ) ) {
			$query = new WP_Error( 'Inexistent terms' );
			return;
		}

		$query['terms'] = $terms;
		$query['field'] = $resulting_field;
	}
}
