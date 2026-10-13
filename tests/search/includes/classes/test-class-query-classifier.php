<?php

namespace Automattic\VIP\Search;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../../search/includes/classes/class-query-classifier.php';

class Query_Classifier_Test extends TestCase {
	/** @var Query_Classifier */
	private $classifier;

	public function setUp(): void {
		parent::setUp();
		$this->classifier = new Query_Classifier();
	}

	public function unbounded_query_data(): array {
		return [
			'missing query'        => [ wp_json_encode( [ 'size' => 10 ] ) ],
			'empty query'          => [ wp_json_encode( [ 'query' => [] ] ) ],
			'explicit match all'   => [ wp_json_encode( [ 'query' => [ 'match_all' => [] ] ] ) ],
			'empty bool'           => [ wp_json_encode( [ 'query' => [ 'bool' => [] ] ] ) ],
			'empty bool arrays'    => [
				wp_json_encode( [
					'query' => [
						'bool' => [
							'must'   => [],
							'filter' => [],
						],
					],
				] ),
			],
			'empty function score' => [ wp_json_encode( [ 'query' => [ 'function_score' => [ 'functions' => [ [ 'weight' => 2 ] ] ] ] ] ) ],
			'empty nested wrapper' => [
				wp_json_encode( [
					'query' => [
						'nested' => [
							'path'  => 'author',
							'query' => [],
						],
					],
				] ),
			],
		];
	}

	/**
	 * @dataProvider unbounded_query_data
	 */
	public function test_classifies_queries_without_a_limiting_clause_as_unbounded( string $body ): void {
		$result = $this->classifier->classify( $body );

		$this->assertSame( Query_Classifier::SCOPE_UNBOUNDED, $result['scope'] );
	}

	public function bounded_query_data(): array {
		return [
			'match none'     => [ [ 'query' => [ 'match_none' => [] ] ] ],
			'term query'     => [ [ 'query' => [ 'term' => [ 'post_status' => 'publish' ] ] ] ],
			'bool filter'    => [ [ 'query' => [ 'bool' => [ 'filter' => [ [ 'term' => [ 'post_type' => 'post' ] ] ] ] ] ] ],
			'function score' => [ [ 'query' => [ 'function_score' => [ 'query' => [ 'match' => [ 'post_title' => 'events' ] ] ] ] ] ],
		];
	}

	/**
	 * @dataProvider bounded_query_data
	 */
	public function test_classifies_effective_queries_as_bounded( array $body ): void {
		$result = $this->classifier->classify( $body );

		$this->assertSame( Query_Classifier::SCOPE_BOUNDED, $result['scope'] );
	}

	public function test_invalid_body_has_unknown_scope(): void {
		$result = $this->classifier->classify( '{not-json' );

		$this->assertSame( Query_Classifier::SCOPE_UNKNOWN, $result['scope'] );
		$this->assertSame( [ '_body' => 'invalid' ], $result['structure'] );
	}

	public function test_scope_can_be_classified_without_building_a_family_structure(): void {
		$this->assertSame( Query_Classifier::SCOPE_UNKNOWN, $this->classifier->scope( '{not-json' ) );
		$this->assertSame( Query_Classifier::SCOPE_UNBOUNDED, $this->classifier->scope( [ 'size' => 10 ] ) );
		$this->assertSame( Query_Classifier::SCOPE_BOUNDED, $this->classifier->scope( [ 'query' => [ 'term' => [ 'post_status' => 'publish' ] ] ] ) );
	}

	public function same_structure_data(): array {
		$data = [
			'volatile values and pagination'          => [
				[
					'from'  => 0,
					'size'  => 10,
					'query' => [ 'term' => [ 'post_author' => 123 ] ],
				],
				[
					'from'  => 9000,
					'size'  => 100,
					'query' => [ 'term' => [ 'post_author' => 987654 ] ],
				],
			],
			'scalar lists use type and count buckets' => [
				[ 'query' => [ 'terms' => [ 'post_author' => [ 1, 2, 3 ] ] ] ],
				[ 'query' => [ 'terms' => [ 'post_author' => [ 7, 8, 9, 10, 11 ] ] ] ],
			],
			'multi match fields but not query text'   => [
				[
					'query' => [
						'multi_match' => [
							'query'  => 'events',
							'fields' => [ 'post_title', 'post_content' ],
						],
					],
				],
				[
					'query' => [
						'multi_match' => [
							'query'  => 'a different term',
							'fields' => [ 'post_content', 'post_title' ],
						],
					],
				],
			],
			'nested sort filter values'               => [
				[
					'query' => [ 'match_all' => [] ],
					'sort'  => [
						[
							'offer.price' => [
								'order'  => 'asc',
								'nested' => [
									'path'   => 'offer',
									'filter' => [ 'term' => [ 'offer.color' => 'blue' ] ],
								],
							],
						],
					],
				],
				[
					'query' => [ 'match_all' => [] ],
					'sort'  => [
						[
							'offer.price' => [
								'order'  => 'asc',
								'nested' => [
									'path'   => 'offer',
									'filter' => [ 'term' => [ 'offer.color' => 'red' ] ],
								],
							],
						],
					],
				],
			],
			'order insensitive bool clauses'          => [
				[
					'query' => [
						'bool' => [
							'filter' => [
								[ 'term' => [ 'post_type' => 'post' ] ],
								[ 'term' => [ 'post_status' => 'publish' ] ],
							],
						],
					],
				],
				[
					'query' => [
						'bool' => [
							'filter' => [
								[ 'term' => [ 'post_status' => 'private' ] ],
								[ 'term' => [ 'post_type' => 'page' ] ],
							],
						],
					],
				],
			],
		];

		// Runtime values for reserved-looking document field names must not change the family.
		foreach ( [ 'field', 'fields', 'path' ] as $document_field ) {
			$data[ "runtime value of document field {$document_field}" ] = [
				[ 'query' => [ 'term' => [ $document_field => 'first runtime value' ] ] ],
				[ 'query' => [ 'term' => [ $document_field => 'second runtime value' ] ] ],
			];
		}

		return $data;
	}

	/**
	 * @dataProvider same_structure_data
	 */
	public function test_queries_of_the_same_family_produce_the_same_structure( array $first, array $second ): void {
		$this->assertSame( $this->classifier->classify( $first )['structure'], $this->classifier->classify( $second )['structure'] );
	}

	public function distinct_structure_data(): array {
		$should_clauses  = [
			[ 'term' => [ 'post_type' => 'post' ] ],
			[ 'term' => [ 'post_status' => 'publish' ] ],
		];
		$date_then_title = [
			'query' => [ 'match_all' => [] ],
			'sort'  => [
				[ 'post_date' => [ 'order' => 'desc' ] ],
				[ 'post_title.keyword' => [ 'order' => 'asc' ] ],
			],
		];

		return [
			'field names'        => [
				[ 'query' => [ 'match' => [ 'post_title' => 'events' ] ] ],
				[ 'query' => [ 'match' => [ 'post_content' => 'events' ] ] ],
			],
			'operators'          => [
				[ 'query' => [ 'match' => [ 'post_title' => 'events' ] ] ],
				[ 'query' => [ 'term' => [ 'post_title' => 'events' ] ] ],
			],
			'structural options' => [
				[
					'query' => [
						'bool' => [
							'minimum_should_match' => 1,
							'should'               => $should_clauses,
						],
					],
				],
				[
					'query' => [
						'bool' => [
							'minimum_should_match' => 2,
							'should'               => $should_clauses,
						],
					],
				],
			],
			'sort order'         => [
				$date_then_title,
				[
					'query' => [ 'match_all' => [] ],
					'sort'  => [
						[ 'post_title.keyword' => [ 'order' => 'asc' ] ],
						[ 'post_date' => [ 'order' => 'desc' ] ],
					],
				],
			],
			'sort direction'     => [
				$date_then_title,
				[
					'query' => [ 'match_all' => [] ],
					'sort'  => [
						[ 'post_date' => [ 'order' => 'asc' ] ],
						[ 'post_title.keyword' => [ 'order' => 'asc' ] ],
					],
				],
			],
		];
	}

	/**
	 * @dataProvider distinct_structure_data
	 */
	public function test_query_differences_that_change_the_family_change_the_structure( array $first, array $second ): void {
		$this->assertNotSame( $this->classifier->classify( $first )['structure'], $this->classifier->classify( $second )['structure'] );
	}
}
