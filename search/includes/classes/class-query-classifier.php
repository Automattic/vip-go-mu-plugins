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

	private const STRUCTURAL_VALUE_KEYS = [
		'field',
		'fields',
		'path',
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

		$scope = ! array_key_exists( 'query', $body ) || ! $this->has_effective_clause( $body['query'] )
			? self::SCOPE_UNBOUNDED
			: self::SCOPE_BOUNDED;

		return [
			'scope'     => $scope,
			'structure' => $this->normalize( $body ),
		];
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
			if ( in_array( $operator, [ 'match_all', 'match_none' ], true ) ) {
				return true;
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
	 * @param int    $depth Current depth.
	 * @return mixed
	 */
	private function normalize( $value, string $parent_key = '', int $depth = 0 ) {
		if ( is_array( $value ) ) {
			if ( array_is_list( $value ) ) {
				return $this->normalize_list( $value, $parent_key, $depth );
			}

			$normalized = [];
			$keys       = array_keys( $value );
			sort( $keys, SORT_STRING );

			foreach ( $keys as $key ) {
				if ( 0 === $depth && in_array( $key, self::TOP_LEVEL_VOLATILE_KEYS, true ) ) {
					continue;
				}

				$normalized[ $key ] = $this->normalize( $value[ $key ], (string) $key, $depth + 1 );
			}

			return $normalized;
		}

		if ( in_array( $parent_key, self::STRUCTURAL_VALUE_KEYS, true ) && is_scalar( $value ) ) {
			return sanitize_key( (string) $value );
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
	 * @param int    $depth Current depth.
	 * @return array
	 */
	private function normalize_list( array $values, string $parent_key, int $depth ): array {
		if ( 'fields' === $parent_key ) {
			$fields = array_map( static fn( $field ): string => sanitize_key( (string) $field ), $values );
			$fields = array_values( array_unique( $fields ) );
			sort( $fields, SORT_STRING );

			return [
				'_count' => $this->count_bucket( count( $values ) ),
				'_items' => $fields,
			];
		}

		$all_scalar = [] === array_filter( $values, static fn( $value ): bool => ! is_scalar( $value ) && null !== $value );
		if ( $all_scalar ) {
			$types = array_map( fn( $value ) => $this->normalize( $value, $parent_key, $depth + 1 ), $values );
			$types = array_values( array_unique( $types ) );
			sort( $types, SORT_STRING );

			return [
				'_count' => $this->count_bucket( count( $values ) ),
				'_types' => $types,
			];
		}

		$items = array_map( fn( $value ) => $this->normalize( $value, $parent_key, $depth + 1 ), $values );
		usort( $items, static fn( $left, $right ): int => strcmp( wp_json_encode( $left ), wp_json_encode( $right ) ) );

		return [
			'_count' => $this->count_bucket( count( $values ) ),
			'_items' => $items,
		];
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
