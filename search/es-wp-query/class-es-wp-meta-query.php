<?php

/**
 * Elasticsearch wrapper for WP_Meta_Query
 */
class ES_WP_Meta_Query extends WP_Meta_Query {

	/**
	 * Turns an array of meta query parameters into ES Query DSL
	 *
	 * @access public
	 *
	 * @param object $es_query Any object which extends ES_WP_Query_Wrapper.
	 * @param string $type Type of meta. Currently, only 'post' is supported.
	 * @return array array()
	 */
	public function get_dsl( $es_query, $type ) {
		global $wpdb;

		if ( 'post' != $type ) {
			return false;
		}

		$queries = array();
		$filter = array();

		// Split out 'exists' and 'not exists' queries. These may also be
		// queries missing a value or with an empty array as the value.
		foreach ( $this->queries as $k => $q ) {

			// WordPress 4.1 introduces a new structure for WP_Meta_Query:queries to support nested queries.
			// the following protects against PHP warnings until support for nesting is added
			if ( 'relation' === $k ) {
				continue;
			}

			if ( isset( $q['compare'] ) && 'EXISTS' == strtoupper( substr( $q['compare'], -6 ) ) ) {
				unset( $q['value'] );
			}

			if ( ( isset( $q['value'] ) && is_array( $q['value'] ) && empty( $q['value'] ) ) || ( ! array_key_exists( 'value', $q ) && ! empty( $q['key'] ) ) ) {
				if ( isset( $q['compare'] ) && 'NOT EXISTS' == strtoupper( $q['compare'] ) ) {
					$filter[] = $es_query->dsl_missing( $es_query->meta_map( trim( $q['key'] ) ) );
				} else {
					$filter[] = $es_query->dsl_exists( $es_query->meta_map( trim( $q['key'] ) ) );
				}
			} else {
				$queries[ $k ] = $q;
			}
		}

		foreach ( $queries as $k => $q ) {
			$meta_key = isset( $q['key'] ) ? trim( $q['key'] ) : '*';

			if ( array_key_exists( 'value', $q ) && is_null( $q['value'] ) )
				$q['value'] = '';

			$meta_value = isset( $q['value'] ) ? $q['value'] : null;

			if ( isset( $q['compare'] ) )
				$meta_compare = strtoupper( $q['compare'] );
			else
				$meta_compare = is_array( $meta_value ) ? 'IN' : '=';

			if ( in_array( $meta_compare, array( 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ) ) ) {
				if ( ! is_array( $meta_value ) ) {
					$meta_value = preg_split( '/[,\s]+/', $meta_value );
				}

				if ( empty( $meta_value ) ) {
					continue;
				}
			} else {
				$meta_value = trim( $meta_value );
			}

			if ( '*' == $meta_key && ! in_array( $meta_compare, array( '=', '!=', 'LIKE', 'NOT LIKE' ) ) ) {
				$keyless_filter = apply_filters( 'es_meta_query_keyless_query', array(), $meta_value, $meta_compare, $this, $es_query );
				if ( ! empty( $keyless_filter ) ) {
					$filter[] = $keyless_filter;
				} else {
					// @todo: How should we handle errors like this?
				}
				continue;
			}

			$meta_type = $this->get_cast_for_type( isset( $q['type'] ) ? $q['type'] : '' );

			// Allow adapters to normalize meta values (like `strtolower` if mapping to `raw_lc`)
			$meta_value = apply_filters( 'es_meta_query_meta_value', $meta_value, $meta_key, $meta_compare, $meta_type );

			switch ( $meta_compare ) {
				case '>' :
				case '>=' :
				case '<' :
				case '<=' :
					switch ( $meta_compare ) {
						case '>' :   $operator = 'gt';   break;
						case '>=' :  $operator = 'gte';  break;
						case '<' :   $operator = 'lt';   break;
						case '<=' :  $operator = 'lte';  break;
					}
					$this_filter = $es_query->dsl_range( $es_query->meta_map( $meta_key, $meta_type ), array( $operator => $meta_value ) );
					break;

				case 'LIKE' :
				case 'NOT LIKE' :
					if ( '*' == $meta_key ) {
						$this_filter = array( 'query' => $es_query->dsl_multi_match( $es_query->meta_map( $meta_key, 'analyzed' ), $meta_value ) );
					} else {
						$this_filter = array( 'query' => $es_query->dsl_match( $es_query->meta_map( $meta_key, 'analyzed' ), $meta_value ) );
					}
					break;

				case 'BETWEEN' :
				case 'NOT BETWEEN' :
					// These may produce unexpected results depending on how your data is indexed.
					$meta_value = array_slice( $meta_value, 0, 2 );
					if ( 'DATETIME' == $meta_type && $date1 = strtotime( $meta_value[0] ) && $date2 = strtotime( $meta_value[1] ) ) {
						$meta_value = array( $date1, $date2 );
						sort( $meta_value );
						$this_filter = $es_query->dsl_range(
							$es_query->meta_map( $meta_key, $meta_type ),
							ES_WP_Date_Query::build_date_range( $meta_value[0], '>=', $meta_value[1], '<=' )
						);
					} else {
						natcasesort( $meta_value );
						$this_filter = $es_query->dsl_range(
							$es_query->meta_map( $meta_key, $meta_type ),
							array( 'gte' => $meta_value[0], 'lte' => $meta_value[1] )
						);
					}
					break;

				default :
					if ( '*' == $meta_key ) {
						$this_filter = array( 'query' => $es_query->dsl_multi_match( $es_query->meta_map( $meta_key, $meta_type ), $meta_value ) );
					} else {
						$this_filter = $es_query->dsl_terms( $es_query->meta_map( $meta_key, $meta_type ), $meta_value );
					}
					break;

			}

			if ( ! empty( $this_filter ) ) {
				if ( in_array( $meta_compare, array( 'NOT IN', '!=', 'NOT BETWEEN', 'NOT LIKE' ) ) ) {
					$filter[] = array( 'not' => $this_filter );
				} else {
					$filter[] = $this_filter;
				}
			}

		}

		$filter = array_filter( $filter );

		if ( ! empty( $filter ) && count( $filter ) > 1 ) {
			$filter = array( strtolower( $this->relation ) => $filter );
		} elseif ( ! empty( $filter ) ) {
			$filter = reset( $filter );
		}

		return apply_filters_ref_array( 'get_meta_dsl', array( $filter, $queries, $type, $es_query ) );
	}


	/**
	 * Get the ES mapping suffix for the given type.
	 *
	 * @param  string $type Meta_Query type. See Meta_Query docs.
	 * @return string
	 */
	public function get_cast_for_type( $type = '' ) {
		$type = preg_replace( '/^([A-Z]+).*$/', '$1', strtoupper( $type ) );
		switch ( $type ) {
			case 'NUMERIC'  : return 'long';
			case 'SIGNED'   : return 'long';
			case 'UNSIGNED' : return 'long';
			case 'BINARY'   : return 'boolean';
			case 'DECIMAL'  : return 'double';
			case 'DATE'     : return 'date';
			case 'DATETIME' : return 'datetime';
			case 'TIME'     : return 'time';
		}
		return '';
	}
}