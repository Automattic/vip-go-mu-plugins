<?php

namespace Automattic\VIP\Search;

final class Query_Classifier {
	public const SCOPE_BOUNDED   = 'bounded';
	public const SCOPE_UNBOUNDED = 'unbounded';
	public const SCOPE_UNKNOWN   = 'unknown';

	private const TOP_LEVEL_VOLATILE_KEYS = [
		'from',
		'preference',
		'routing',
		'search_after',
		'size',
		'terminate_after',
		'timeout',
	];

	private const CLAUSE_CONTAINER_KEYS = [
		'filter',
		'must',
		'must_not',
		'negative',
		'positive',
		'queries',
		'query',
		'should',
	];

	private const ORDER_INSENSITIVE_LIST_KEYS = [
		'filter',
		'must',
		'must_not',
		'queries',
		'should',
	];

	private const SORT_STRUCTURAL_VALUE_KEYS = [
		'distance_type',
		'format',
		'ignore_unmapped',
		'max_children',
		'missing',
		'mode',
		'numeric_type',
		'order',
		'path',
		'type',
		'unit',
		'unmapped_type',
		'validation_method',
	];

	private const STRUCTURAL_OPTIONS_BY_OPERATOR = [
		'bool'                => [ 'minimum_should_match' ],
		'dis_max'             => [ 'tie_breaker' ],
		'exists'              => [ 'field' ],
		'function_score'      => [ 'boost_mode', 'max_boost', 'min_score', 'score_mode' ],
		'fuzzy'               => [ 'fuzziness', 'max_expansions', 'prefix_length', 'rewrite', 'transpositions' ],
		'has_child'           => [ 'ignore_unmapped', 'max_children', 'min_children', 'score_mode', 'type' ],
		'has_parent'          => [ 'ignore_unmapped', 'parent_type', 'score' ],
		'match'               => [ 'analyzer', 'auto_generate_synonyms_phrase_query', 'fuzziness', 'fuzzy_transpositions', 'lenient', 'max_expansions', 'minimum_should_match', 'operator', 'prefix_length', 'zero_terms_query' ],
		'match_bool_prefix'   => [ 'analyzer', 'fuzziness', 'fuzzy_transpositions', 'max_expansions', 'minimum_should_match', 'operator', 'prefix_length' ],
		'match_phrase'        => [ 'analyzer', 'slop', 'zero_terms_query' ],
		'match_phrase_prefix' => [ 'analyzer', 'max_expansions', 'slop', 'zero_terms_query' ],
		'multi_match'         => [ 'analyzer', 'auto_generate_synonyms_phrase_query', 'fields', 'fuzziness', 'fuzzy_rewrite', 'fuzzy_transpositions', 'lenient', 'max_expansions', 'minimum_should_match', 'operator', 'prefix_length', 'slop', 'tie_breaker', 'type', 'zero_terms_query' ],
		'nested'              => [ 'ignore_unmapped', 'path', 'score_mode' ],
		'prefix'              => [ 'case_insensitive', 'rewrite' ],
		'query_string'        => [ 'analyzer', 'analyze_wildcard', 'default_field', 'default_operator', 'fields', 'fuzziness', 'fuzzy_max_expansions', 'fuzzy_prefix_length', 'lenient', 'minimum_should_match', 'phrase_slop', 'quote_analyzer', 'quote_field_suffix', 'rewrite', 'time_zone', 'type' ],
		'range'               => [ 'format', 'relation', 'time_zone' ],
		'regexp'              => [ 'case_insensitive', 'flags', 'max_determinized_states', 'rewrite' ],
		'simple_query_string' => [ 'analyzer', 'analyze_wildcard', 'auto_generate_synonyms_phrase_query', 'default_operator', 'fields', 'flags', 'fuzzy_max_expansions', 'fuzzy_prefix_length', 'fuzzy_transpositions', 'lenient', 'minimum_should_match', 'quote_field_suffix' ],
		'wildcard'            => [ 'case_insensitive', 'rewrite' ],
	];

	/**
	 * @param array|string $request_body Elasticsearch request body.
	 * @return array{scope:string,structure:array}
	 */
	public function classify( $request_body ): array {
		$body = $this->decode_body( $request_body );

		if ( null === $body ) {
			return [
				'scope'     => self::SCOPE_UNKNOWN,
				'structure' => [ '_body' => 'invalid' ],
			];
		}

		return [
			'scope'     => $this->scope_from_body( $body ),
			'structure' => $this->normalize( $body ),
		];
	}

	/**
	 * Classify query scope without building the family-identity structure.
	 *
	 * @param array|string $request_body Elasticsearch request body.
	 */
	public function scope( $request_body ): string {
		$body = $this->decode_body( $request_body );

		return null === $body ? self::SCOPE_UNKNOWN : $this->scope_from_body( $body );
	}

	/**
	 * @param array|string $request_body Elasticsearch request body.
	 * @return array|null
	 */
	private function decode_body( $request_body ): ?array {
		if ( is_array( $request_body ) ) {
			return $request_body;
		}

		if ( ! is_string( $request_body ) || '' === trim( $request_body ) ) {
			return null;
		}

		$decoded = json_decode( $request_body, true );

		return is_array( $decoded ) ? $decoded : null;
	}

	private function scope_from_body( array $body ): string {
		return ! array_key_exists( 'query', $body ) || ! $this->has_effective_clause( $body['query'] )
			? self::SCOPE_UNBOUNDED
			: self::SCOPE_BOUNDED;
	}

	/**
	 * @param mixed $clause Query clause.
	 */
	private function has_effective_clause( $clause ): bool {
		if ( ! is_array( $clause ) || [] === $clause ) {
			return false;
		}

		if ( array_is_list( $clause ) ) {
			foreach ( $clause as $item ) {
				if ( $this->has_effective_clause( $item ) ) {
					return true;
				}
			}

			return false;
		}

		foreach ( $clause as $operator => $definition ) {
			if ( 'match_none' === $operator ) {
				return true;
			}

			if ( 'match_all' === $operator ) {
				continue;
			}

			if ( 'bool' === $operator ) {
				if ( is_array( $definition ) ) {
					foreach ( [ 'filter', 'must', 'must_not', 'should' ] as $bool_key ) {
						if ( isset( $definition[ $bool_key ] ) && $this->has_effective_clause( $definition[ $bool_key ] ) ) {
							return true;
						}
					}
				}

				continue;
			}

			if ( 'function_score' === $operator ) {
				return is_array( $definition ) && isset( $definition['query'] ) && $this->has_effective_clause( $definition['query'] );
			}

			if ( in_array( $operator, [ 'has_child', 'has_parent', 'nested', 'script_score' ], true ) ) {
				return is_array( $definition ) && isset( $definition['query'] ) && $this->has_effective_clause( $definition['query'] );
			}

			if ( 'constant_score' === $operator ) {
				return is_array( $definition ) && isset( $definition['filter'] ) && $this->has_effective_clause( $definition['filter'] );
			}

			if ( 'boosting' === $operator ) {
				return is_array( $definition ) && isset( $definition['positive'] ) && $this->has_effective_clause( $definition['positive'] );
			}

			if ( 'dis_max' === $operator ) {
				return is_array( $definition ) && isset( $definition['queries'] ) && $this->has_effective_clause( $definition['queries'] );
			}

			if ( null !== $definition && '' !== $definition && [] !== $definition ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param mixed  $value Value to normalize.
	 * @param string $parent_key Parent key.
	 * @param string $active_operator Active query operator.
	 * @param array  $path Path from the request-body root.
	 * @param int    $depth Current depth.
	 * @return mixed
	 */
	private function normalize( $value, string $parent_key = '', string $active_operator = '', array $path = [], int $depth = 0 ) {
		if ( is_array( $value ) ) {
			if ( array_is_list( $value ) ) {
				return $this->normalize_list( $value, $parent_key, $active_operator, $path, $depth );
			}

			$normalized = [];
			$keys       = array_keys( $value );
			sort( $keys, SORT_STRING );

			foreach ( $keys as $key ) {
				if ( 0 === $depth && in_array( $key, self::TOP_LEVEL_VOLATILE_KEYS, true ) ) {
					continue;
				}

				$child_operator = in_array( $parent_key, self::CLAUSE_CONTAINER_KEYS, true ) ? (string) $key : $active_operator;
				$child_path     = array_merge( $path, [ (string) $key ] );

				$normalized[ $key ] = $this->normalize( $value[ $key ], (string) $key, $child_operator, $child_path, $depth + 1 );
			}

			return $normalized;
		}

		if ( $this->is_structural_value( $parent_key, $active_operator, $path ) && is_scalar( $value ) ) {
			return $value;
		}

		if ( is_bool( $value ) ) {
			return '_bool';
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return '_number';
		}

		if ( is_string( $value ) ) {
			return '_string';
		}

		return '_null';
	}

	/**
	 * @param array  $values List values.
	 * @param string $parent_key Parent key.
	 * @param string $active_operator Active query operator.
	 * @param array  $path Path from the request-body root.
	 * @param int    $depth Current depth.
	 * @return array
	 */
	private function normalize_list( array $values, string $parent_key, string $active_operator, array $path, int $depth ): array {
		$all_scalar = [] === array_filter( $values, static fn( $value ): bool => ! is_scalar( $value ) && null !== $value );

		if ( $all_scalar && $this->is_structural_value( $parent_key, $active_operator, $path ) ) {
			$items = array_values( array_unique( $values, SORT_REGULAR ) );

			if ( 'fields' === $parent_key ) {
				sort( $items, SORT_STRING );
			}

			return [
				'_count' => $this->count_bucket( count( $values ) ),
				'_items' => $items,
			];
		}

		if ( $all_scalar ) {
			$types = array_map( fn( $value ) => $this->normalize( $value, $parent_key, $active_operator, $path, $depth + 1 ), $values );
			$types = array_values( array_unique( $types ) );
			sort( $types, SORT_STRING );

			return [
				'_count' => $this->count_bucket( count( $values ) ),
				'_types' => $types,
			];
		}

		$item_path = array_merge( $path, [ '[]' ] );
		$items     = array_map( fn( $value ) => $this->normalize( $value, $parent_key, $active_operator, $item_path, $depth + 1 ), $values );

		if ( in_array( $parent_key, self::ORDER_INSENSITIVE_LIST_KEYS, true ) ) {
			$unique_items = [];
			foreach ( $items as $item ) {
				$unique_items[ wp_json_encode( $item ) ] = $item;
			}

			ksort( $unique_items, SORT_STRING );
			$items = array_values( $unique_items );
		}

		return [
			'_count' => $this->count_bucket( count( $values ) ),
			'_items' => $items,
		];
	}

	private function is_structural_value( string $parent_key, string $active_operator, array $path ): bool {
		if ( isset( self::STRUCTURAL_OPTIONS_BY_OPERATOR[ $active_operator ] ) && in_array( $parent_key, self::STRUCTURAL_OPTIONS_BY_OPERATOR[ $active_operator ], true ) ) {
			return true;
		}

		if ( ! isset( $path[0] ) || 'sort' !== $path[0] ) {
			return false;
		}

		if ( 'sort' === $parent_key || in_array( $parent_key, self::SORT_STRUCTURAL_VALUE_KEYS, true ) ) {
			return true;
		}

		return 2 === count( $path ) || ( 3 === count( $path ) && '[]' === $path[1] );
	}

	private function count_bucket( int $count ): string {
		if ( 0 === $count ) {
			return '0';
		}

		if ( 1 === $count ) {
			return '1';
		}

		if ( $count <= 5 ) {
			return '2-5';
		}

		if ( $count <= 20 ) {
			return '6-20';
		}

		return '21+';
	}
}
