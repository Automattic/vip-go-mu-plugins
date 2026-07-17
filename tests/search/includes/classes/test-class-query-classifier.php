<?php

namespace Automattic\VIP\Search;

use WP_UnitTestCase;

require_once __DIR__ . '/../../../../search/includes/classes/class-query-classifier.php';

class Query_Classifier_Test extends WP_UnitTestCase {
	/** @var Query_Classifier */
	private $classifier;

	public function setUp(): void {
		parent::setUp();
		$this->classifier = new Query_Classifier();
	}

	public function empty_query_data(): array {
		return [
			'missing query'        => [ wp_json_encode( [ 'size' => 10 ] ) ],
			'empty query'          => [ wp_json_encode( [ 'query' => [] ] ) ],
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
	 * @dataProvider empty_query_data
	 */
	public function test_classifies_missing_and_empty_query_clauses_as_unbounded( string $body ): void {
		$result = $this->classifier->classify( $body );

		$this->assertSame( Query_Classifier::SCOPE_UNBOUNDED, $result['scope'] );
	}

	public function bounded_query_data(): array {
		return [
			'explicit match all' => [ [ 'query' => [ 'match_all' => [] ] ] ],
			'term query'         => [ [ 'query' => [ 'term' => [ 'post_status' => 'publish' ] ] ] ],
			'bool filter'        => [ [ 'query' => [ 'bool' => [ 'filter' => [ [ 'term' => [ 'post_type' => 'post' ] ] ] ] ] ] ],
			'function score'     => [ [ 'query' => [ 'function_score' => [ 'query' => [ 'match' => [ 'post_title' => 'events' ] ] ] ] ] ],
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

	public function test_volatile_values_and_pagination_produce_the_same_structure(): void {
		$first  = $this->classifier->classify( [
			'from'  => 0,
			'size'  => 10,
			'query' => [ 'term' => [ 'post_author' => 123 ] ],
		] );
		$second = $this->classifier->classify( [
			'from'  => 9000,
			'size'  => 100,
			'query' => [ 'term' => [ 'post_author' => 987654 ] ],
		] );

		$this->assertSame( $first['structure'], $second['structure'] );
	}

	public function test_scalar_list_values_use_type_and_count_buckets(): void {
		$first  = $this->classifier->classify( [ 'query' => [ 'terms' => [ 'post_author' => [ 1, 2, 3 ] ] ] ] );
		$second = $this->classifier->classify( [ 'query' => [ 'terms' => [ 'post_author' => [ 7, 8, 9, 10, 11 ] ] ] ] );

		$this->assertSame( $first['structure'], $second['structure'] );
	}

	public function test_field_names_and_operators_change_the_structure(): void {
		$title   = $this->classifier->classify( [ 'query' => [ 'match' => [ 'post_title' => 'events' ] ] ] );
		$content = $this->classifier->classify( [ 'query' => [ 'match' => [ 'post_content' => 'events' ] ] ] );
		$term    = $this->classifier->classify( [ 'query' => [ 'term' => [ 'post_title' => 'events' ] ] ] );

		$this->assertNotSame( $title['structure'], $content['structure'] );
		$this->assertNotSame( $title['structure'], $term['structure'] );
	}

	public function test_multi_match_fields_are_preserved_but_query_text_is_not(): void {
		$first  = $this->classifier->classify( [
			'query' => [
				'multi_match' => [
					'query'  => 'events',
					'fields' => [ 'post_title', 'post_content' ],
				],
			],
		] );
		$second = $this->classifier->classify( [
			'query' => [
				'multi_match' => [
					'query'  => 'a different term',
					'fields' => [ 'post_content', 'post_title' ],
				],
			],
		] );

		$this->assertSame( $first['structure'], $second['structure'] );
	}
}
