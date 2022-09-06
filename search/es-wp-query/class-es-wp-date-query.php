<?php
/**
 * ES_WP_Query classes: ES_WP_Date_Query class
 *
 * @package ES_WP_Query
 */

/**
 * Elasticsearch wrapper for WP_Meta_Query
 */
class ES_WP_Date_Query extends WP_Date_Query {

	/**
	 * Turns an array of date query parameters into ES Query DSL.
	 *
	 * @param ES_WP_Query_Wrapper $es_query The query to filter.
	 * @access public
	 * @return array
	 */
	public function get_dsl( $es_query ) {
		// The parts of the final query.
		$filter = array();

		foreach ( $this->queries as $query ) {
			$filter_parts = $this->get_es_subquery( $query, $es_query );
			if ( ! empty( $filter_parts ) ) {
				// Combine the parts of this subquery.
				if ( 1 === count( $filter_parts ) ) {
					$filter[] = reset( $filter_parts );
				} else {
					$filter[] = array(
						'bool' => array(
							'filter' => $filter_parts,
						),
					);
				}
			}
		}

		// Combine the subqueries.
		if ( 1 === count( $filter ) ) {
			$filter = reset( $filter );
		} elseif ( ! empty( $filter ) ) {
			if ( 'or' === strtolower( $this->relation ) ) {
				$relation = 'should';
			} else {
				$relation = 'filter';
			}
			$filter = array(
				'bool' => array(
					$relation => $filter,
				),
			);
		} else {
			$filter = array();
		}

		/**
		 * Filter the date query WHERE clause.
		 *
		 * @param string        $where WHERE clause of the date query.
		 * @param WP_Date_Query $this  The WP_Date_Query instance.
		 */
		return apply_filters( 'get_date_dsl', $filter, $this ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	}

	/**
	 * Turns a single date subquery into elasticsearch filters.
	 *
	 * @param array               $query    The date subquery.
	 * @param ES_WP_Query_Wrapper $es_query The ES_WP_Query object.
	 * @access protected
	 * @return array
	 */
	protected function get_es_subquery( $query, $es_query ) {
		// Ensure $query is an array before proceeding.
		if ( ! is_array( $query ) ) {
			return array();
		}

		// The sub-parts of a $where part.
		$filter_parts = array();

		$field = ( ! empty( $query['column'] ) ) ? esc_sql( $query['column'] ) : $this->column;
		$field = $this->validate_column( $field );

		// We don't actually want the mysql column here, so we'll remove it.
		$field = preg_replace( '/^.*\./', '', $field );

		$compare = $this->get_compare( $query );

		// Range queries, we like range queries.
		if ( ! empty( $query['after'] ) || ! empty( $query['before'] ) ) {
			$inclusive = ! empty( $query['inclusive'] );

			if ( $inclusive ) {
				$lt = 'lte';
				$gt = 'gte';
			} else {
				$lt = 'lt';
				$gt = 'gt';
			}

			$range = array();

			if ( ! empty( $query['after'] ) ) {
				$range[ $gt ] = $this->build_datetime( $query['after'], ! $inclusive );
			}

			if ( ! empty( $query['before'] ) ) {
				$range[ $lt ] = $this->build_datetime( $query['before'], $inclusive );
			}

			if ( ! empty( $range ) ) {
				$filter_parts[] = $es_query->dsl_range( $es_query->es_map( $field ), $range );
			}
			unset( $range );
		}

		// Legacy support and field renaming.
		if ( isset( $query['monthnum'] ) ) {
			$query['month'] = $query['monthnum'];
		}
		if ( isset( $query['w'] ) ) {
			$query['week'] = $query['w'];
		}
		if ( isset( $query['w'] ) ) {
			$query['week'] = $query['w'];
		}
		if ( isset( $query['dayofyear'] ) ) {
			$query['day_of_year'] = $query['dayofyear'];
		}
		if ( isset( $query['dayofweek'] ) ) {
			// We encourage you to store the day_of_week according to ISO-8601 standards.
			$day_of_week = 1 === $query['dayofweek'] ? 7 : $query['dayofweek'] - 1;

			// This is, of course, optional. Use this filter to manipualte the value however you'd like.
			$query['day_of_week'] = apply_filters( 'es_date_query_dayofweek', $day_of_week, $query['dayofweek'] );
		}

		foreach ( array( 'year', 'month', 'week', 'day', 'day_of_year', 'day_of_week' ) as $date_token ) {
			if ( isset( $query[ $date_token ] ) ) {
				$part = $this->build_dsl_part(
					$es_query->es_map( "{$field}.{$date_token}" ),
					$query[ $date_token ],
					$compare
				);
				if ( false !== $part ) {
					$filter_parts[] = $part;
				}
			}
		}

		// Avoid notices.
		$query = wp_parse_args(
			$query,
			array(
				'hour'   => null,
				'minute' => null,
				'second' => null,
			) 
		);

		$time = $this->build_es_time( $compare, $query['hour'], $query['minute'], $query['second'] );
		if ( false === $time ) {
			foreach ( array( 'hour', 'minute', 'second' ) as $date_token ) {
				if ( isset( $query[ $date_token ] ) ) {
					$part = $this->build_dsl_part(
						$es_query->es_map( "{$field}.{$date_token}" ),
						$query[ $date_token ],
						$compare
					);
					if ( false !== $part ) {
						$filter_parts[] = $part;
					}
				}
			}
		} else {
			if ( 1 > $time ) {
				$filter_parts[] = $this->build_dsl_part( $es_query->es_map( "{$field}.seconds_from_hour" ), $time, $compare, 'floatval' );
			} else {
				$filter_parts[] = $this->build_dsl_part( $es_query->es_map( "{$field}.seconds_from_day" ), $time, $compare, 'floatval' );
			}
		}

		return $filter_parts;
	}

	/**
	 * Builds a MySQL format date/time based on some query parameters.
	 *
	 * This is a clone of build_mysql_datetime, but specifically for static usage.
	 *
	 * You can pass an array of values (year, month, etc.) with missing parameter values being defaulted to
	 * either the maximum or minimum values (controlled by the $default_to parameter). Alternatively you can
	 * pass a string that that will be run through strtotime().
	 *
	 * @static
	 * @access public
	 *
	 * @param string|array $datetime       An array of parameters or a strotime() string.
	 * @param string|bool  $default_to_max Controls what values default to if they are missing from $datetime. Pass "min" or "max".
	 * @return string|false A MySQL format date/time or false on failure
	 */
	public static function build_datetime( $datetime, $default_to_max = false ) {
		$now = current_time( 'timestamp' );

		if ( ! is_array( $datetime ) ) {
			// @todo Timezone issues here possibly
			return gmdate( 'Y-m-d H:i:s', strtotime( $datetime, $now ) );
		}

		$datetime = array_map( 'absint', $datetime );

		if ( ! isset( $datetime['year'] ) ) {
			$datetime['year'] = gmdate( 'Y', $now );
		}

		if ( ! isset( $datetime['month'] ) ) {
			$datetime['month'] = ( $default_to_max ) ? 12 : 1;
		}

		if ( ! isset( $datetime['day'] ) ) {
			$datetime['day'] = ( $default_to_max ) ? (int) date( 't', mktime( 0, 0, 0, $datetime['month'], 1, $datetime['year'] ) ) : 1;
		}

		if ( ! isset( $datetime['hour'] ) ) {
			$datetime['hour'] = ( $default_to_max ) ? 23 : 0;
		}

		if ( ! isset( $datetime['minute'] ) ) {
			$datetime['minute'] = ( $default_to_max ) ? 59 : 0;
		}

		if ( ! isset( $datetime['second'] ) ) {
			$datetime['second'] = ( $default_to_max ) ? 59 : 0;
		}

		return sprintf( '%04d-%02d-%02d %02d:%02d:%02d', $datetime['year'], $datetime['month'], $datetime['day'], $datetime['hour'], $datetime['minute'], $datetime['second'] );
	}

	/**
	 * Given one or two dates and comparison operators for each, builds a date
	 * query that encompasses the requested range.
	 *
	 * @param string|array      $date     An array of parameters or a strotime() string.
	 * @param string            $compare  The comparison operator for the date.
	 * @param string|array|null $date2    Optional. An array of parameters or a strotime() string. Defaults to null.
	 * @param string|null       $compare2 Optional. The comparison operator for the date. Defaults to null.
	 * @access public
	 * @return array
	 */
	public static function build_date_range( $date, $compare, $date2 = null, $compare2 = null ) {
		// If we pass two dates, create a range for both.
		if ( isset( $date2 ) && isset( $compare2 ) ) {
			return array_merge( self::build_date_range( $date, $compare ), self::build_date_range( $date2, $compare2 ) );
		}

		// To improve readability.
		$upper_edge = true;
		$lower_edge = false;

		switch ( $compare ) {
			case '!=':
			case '=':
				return array(
					'gte' => self::build_datetime( $date, $lower_edge ),
					'lte' => self::build_datetime( $date, $upper_edge ),
				);

			case '>':
				return array( 'gt' => self::build_datetime( $date, $upper_edge ) );
			case '>=':
				return array( 'gte' => self::build_datetime( $date, $lower_edge ) );

			case '<':
				return array( 'lt' => self::build_datetime( $date, $lower_edge ) );
			case '<=':
				return array( 'lte' => self::build_datetime( $date, $upper_edge ) );
		}
	}

	/**
	 * Builds and validates a value string based on the comparison operator.
	 *
	 * @access public
	 *
	 * @param string       $field    The field name.
	 * @param string|array $value    The value.
	 * @param string       $compare  The compare operator to use.
	 * @param string       $sanitize Optional. The sanitization function to use. Defaults to 'intval'.
	 * @return string|int|false The value to be used in DSL or false on error.
	 */
	public function build_dsl_part( $field, $value, $compare, $sanitize = 'intval' ) {
		if ( ! isset( $value ) ) {
			return false;
		}

		$part = false;
		switch ( $compare ) {
			case 'IN':
			case 'NOT IN':
				$part = ES_WP_Query_Wrapper::dsl_terms( $field, array_map( $sanitize, (array) $value ) );
				break;

			case 'BETWEEN':
			case 'NOT BETWEEN':
				if ( ! is_array( $value ) ) {
					$value = array( $value, $value );
				} elseif ( count( $value ) >= 2 && ( ! isset( $value[0] ) || ! isset( $value[1] ) ) ) {
					$value = array( array_shift( $value ), array_shift( $value ) );
				} elseif ( count( $value ) ) {
					$value = reset( $value );
					$value = array( $value, $value );
				}

				if ( ! isset( $value[0] ) || ! isset( $value[1] ) ) {
					return false;
				}

				$value = array_map( $sanitize, $value );
				sort( $value );

				$part = ES_WP_Query_Wrapper::dsl_range(
					$field,
					array(
						'gte' => $value[0],
						'lte' => $value[1],
					) 
				);
				break;

			case '>':
			case '>=':
			case '<':
			case '<=':
				switch ( $compare ) {
					case '>':   
						$operator = 'gt';
						break;
					case '>=':  
						$operator = 'gte';
						break;
					case '<':   
						$operator = 'lt';
						break;
					case '<=':  
						$operator = 'lte';
						break;
				}
				$part = ES_WP_Query_Wrapper::dsl_range( $field, array( $operator => $sanitize( $value ) ) );
				break;

			default:
				$part = ES_WP_Query_Wrapper::dsl_terms( $field, $sanitize( $value ) );
				break;
		}

		if ( ! empty( $part ) && in_array( $compare, array( '!=', 'NOT IN', 'NOT BETWEEN' ), true ) ) {
			return array(
				'bool' => array(
					'must_not' => $part,
				),
			);
		} else {
			return $part;
		}
	}

	/**
	 * Builds a query string for comparing time values (hour, minute, second).
	 *
	 * If just hour, minute, or second is set than a normal comparison will be done.
	 * However if multiple values are passed, a pseudo-decimal time will be created
	 * in order to be able to accurately compare against.
	 *
	 * @access public
	 *
	 * @param string   $compare The comparison operator. Needs to be pre-validated.
	 * @param int|null $hour Optional. An hour value (0-23).
	 * @param int|null $minute Optional. A minute value (0-59).
	 * @param int|null $second Optional. A second value (0-59).
	 * @return string|false A query part or false on failure.
	 */
	public function build_es_time( $compare, $hour = null, $minute = null, $second = null ) {
		// Complex combined queries aren't supported for multi-value queries.
		if ( in_array( $compare, array( 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' ), true ) ) {
			return false;
		}

		// Lastly, ignore cases where just one unit is set or $minute is null.
		if ( count( array_filter( array( $hour, $minute, $second ), 'is_null' ) ) > 1 || is_null( $minute ) ) {
			return false;
		}

		// Hour.
		if ( ! $hour ) {
			$hour = 0;
		}

		return mktime( $hour, $minute, $second, 1, 1, 1970 );
	}
}
