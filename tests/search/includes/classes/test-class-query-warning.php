<?php

namespace Automattic\VIP\Search;

use WP_UnitTestCase;

require_once __DIR__ . '/../../../../search/includes/classes/class-query-classifier.php';
require_once __DIR__ . '/../../../../search/includes/classes/class-query-warning.php';

class Query_Warning_Test extends WP_UnitTestCase {
	/** @var string[] */
	private $warnings = [];

	/** @var array */
	private $original_server = [];

	public function setUp(): void {
		parent::setUp();
		$this->original_server  = $_SERVER;
		$this->warnings         = [];
		$_SERVER['HTTPS']       = 'on';
		$_SERVER['HTTP_HOST']   = 'example.com';
		$_SERVER['REQUEST_URI'] = '/search/?category=events';

		wp_cache_flush();

		foreach ( [
			'vip_search_slow_query_threshold_ms',
			'vip_search_slow_query_payload_threshold_bytes',
			'vip_search_query_warning_dedupe_window_s',
			'vip_search_query_warning_budget',
			'wp_doing_cron',
		] as $filter ) {
			remove_all_filters( $filter );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		set_error_handler( function ( int $errno, string $message ): bool {
			if ( E_USER_WARNING === $errno ) {
				$this->warnings[] = $message;
				return true;
			}

			return false;
		}, E_USER_WARNING );
	}

	public function tearDown(): void {
		restore_error_handler();
		$_SERVER = $this->original_server;
		parent::tearDown();
	}

	public function test_200_ms_engine_time_does_not_warn_and_201_ms_does(): void {
		$warning = new Query_Warning( new Query_Classifier(), static fn(): int => 1000, '__return_true' );
		$body    = [
			'query' => [ 'term' => [ 'post_status' => 'publish' ] ],
			'size'  => 10,
		];

		$this->assertFalse( $warning->maybe_emit( $body, $this->response( 200, 1, 1 ), 230.0, $this->customer_backtrace() ) );
		$this->assertTrue( $warning->maybe_emit( $body, $this->response( 201, 1, 1 ), 230.0, $this->customer_backtrace() ) );
		$this->assertCount( 2, $this->warnings );
		$this->assertStringContainsString( 'Elasticsearch took 201 ms to execute the query, above the configured warning threshold of 200 ms.', $this->warnings[1] );
		$this->assertStringContainsString( 'Review how this query is constructed', $this->warnings[1] );
	}

	public function test_slow_request_with_fast_engine_and_small_payload_does_not_warn(): void {
		$warning = new Query_Warning( new Query_Classifier(), static fn(): int => 1000, '__return_true' );
		$body    = [ 'query' => [ 'term' => [ 'post_name' => 'example' ] ] ];

		$this->assertFalse( $warning->maybe_emit( $body, $this->response( 3, 1, 1 ), 260.0, $this->customer_backtrace(), 2048 ) );
		$this->assertSame( [], $this->warnings );
	}

	public function test_slow_request_with_fast_engine_and_large_payload_warns(): void {
		$warning = new Query_Warning( new Query_Classifier(), static fn(): int => 1000, '__return_true' );
		$body    = [
			'query' => [ 'term' => [ 'post_status' => 'publish' ] ],
			'size'  => 500,
		];

		$this->assertTrue( $warning->maybe_emit( $body, $this->response( 12, 500, 900 ), 450.0, $this->customer_backtrace(), 2 * MB_IN_BYTES ) );
		$this->assertCount( 2, $this->warnings );
		$this->assertStringContainsString( 'types=slow_query request_ms=450 request_limit_ms=200 engine_ms=12', $this->warnings[0] );
		$this->assertStringContainsString( 'payload_bytes=' . ( 2 * MB_IN_BYTES + strlen( wp_json_encode( $body ) ) ), $this->warnings[0] );
		$this->assertStringContainsString( 'The request took 450 ms, above the configured warning threshold of 200 ms, while transferring 2.0 MB of request and response data. Elasticsearch executed the query in 12 ms.', $this->warnings[1] );
		$this->assertStringContainsString( 'request fewer results or only the fields you need.', $this->warnings[1] );
		$this->assertStringNotContainsString( 'Review how this query is constructed', $this->warnings[1] );
	}

	public function test_payload_threshold_is_filterable_and_invalid_values_fall_back(): void {
		$warning = new Query_Warning( new Query_Classifier(), static fn(): int => 1000, '__return_true' );
		$body    = [ 'query' => [ 'term' => [ 'post_status' => 'publish' ] ] ];

		add_filter( 'vip_search_slow_query_payload_threshold_bytes', static fn() => 'invalid' );
		$this->assertFalse( $warning->maybe_emit( $body, $this->response( 5, 10, 10 ), 300.0, $this->customer_backtrace(), 64 * KB_IN_BYTES ) );

		remove_all_filters( 'vip_search_slow_query_payload_threshold_bytes' );
		add_filter( 'vip_search_slow_query_payload_threshold_bytes', static fn(): int => 32 * KB_IN_BYTES );
		$this->assertTrue( $warning->maybe_emit( $body, $this->response( 5, 10, 10 ), 300.0, $this->customer_backtrace(), 64 * KB_IN_BYTES ) );
	}

	public function test_large_payload_with_fast_request_does_not_warn(): void {
		$warning = new Query_Warning( new Query_Classifier(), static fn(): int => 1000, '__return_true' );
		$body    = [ 'query' => [ 'term' => [ 'post_status' => 'publish' ] ] ];

		$this->assertFalse( $warning->maybe_emit( $body, $this->response( 12, 500, 900 ), 150.0, $this->customer_backtrace(), 5 * MB_IN_BYTES ) );
	}

	public function test_missing_engine_time_falls_back_to_request_duration(): void {
		$warning = new Query_Warning( new Query_Classifier(), static fn(): int => 1000, '__return_true' );
		$body    = [ 'query' => [ 'term' => [ 'post_status' => 'publish' ] ] ];

		$this->assertFalse( $warning->maybe_emit( $body, $this->response( null, 1, 1 ), 200.0, $this->customer_backtrace() ) );
		$this->assertTrue( $warning->maybe_emit( $body, $this->response( null, 1, 1 ), 201.0, $this->customer_backtrace() ) );
		$this->assertStringContainsString( 'engine_ms=unknown', $this->warnings[0] );
		$this->assertStringContainsString( 'The query took 201 ms, above the configured warning threshold of 200 ms.', $this->warnings[1] );
	}

	public function test_invalid_filter_values_fall_back_and_valid_threshold_is_used(): void {
		add_filter( 'vip_search_slow_query_threshold_ms', static fn() => 'invalid' );
		$warning = new Query_Warning( new Query_Classifier(), static fn(): int => 1000, '__return_true' );
		$body    = [ 'query' => [ 'term' => [ 'post_status' => 'publish' ] ] ];

		$this->assertFalse( $warning->maybe_emit( $body, $this->response( 200, 0, 0 ), 210.0, $this->customer_backtrace() ) );

		remove_all_filters( 'vip_search_slow_query_threshold_ms' );
		add_filter( 'vip_search_slow_query_threshold_ms', static fn(): int => 50 );
		$this->assertTrue( $warning->maybe_emit( $body, $this->response( 51, 0, 0 ), 60.0, $this->customer_backtrace() ) );
	}

	public function test_tuned_dedupe_window_is_exposed_in_both_messages(): void {
		add_filter( 'vip_search_query_warning_dedupe_window_s', static fn(): int => 120 );
		$warning = new Query_Warning( new Query_Classifier(), static fn(): int => 1000, '__return_true' );
		$warning->maybe_emit( [], $this->response( 10, 0, 0 ), 50.0, $this->customer_backtrace() );

		$this->assertStringContainsString( 'dedupe_window_s=120', $this->warnings[0] );
		$this->assertStringContainsString( 'deduplicated for two minutes', $this->warnings[1] );
	}

	public function test_exact_combined_message_contract_and_order(): void {
		$warning = new Query_Warning( new Query_Classifier(), static fn(): int => 1000, '__return_true' );
		$warning->maybe_emit( [ 'size' => 10 ], $this->response( 493, 0, 0 ), 527.0, $this->customer_backtrace() );

		$this->assertCount( 2, $this->warnings );
		preg_match( '/warning_id=(VSQ-[A-F0-9]{8})/', $this->warnings[0], $matches );
		$this->assertArrayHasKey( 1, $matches );
		$id = $matches[1];

		$this->assertSame(
			'VIP_SEARCH_QUERY_WARNING v=1 warning_id=VSQ-TEST000 types=slow_query,unbounded_query request_ms=527 request_limit_ms=200 engine_ms=493 requested=10 returned=0 total_hits=0 query_scope=unbounded url="https://example.com/search/?category=events" deduplicated=true dedupe_window_s=300 payload_bytes=62',
			str_replace( $id, 'VSQ-TEST000', $this->warnings[0] )
		);
		$this->assertSame(
			'A request to https://example.com/search/?category=events triggered a potentially expensive VIP Search query originating from wp-content/plugins/acme/search.php:81. Elasticsearch took 493 ms to execute the query, above the configured warning threshold of 200 ms. The query also did not contain a limiting search condition. An unbounded query may search the entire index and become more expensive as the site grows. Review the query and add an appropriate search term or filter. Similar occurrences are deduplicated for five minutes, so this warning may represent repeated requests. Warning ID: VSQ-TEST000.',
			str_replace( $id, 'VSQ-TEST000', $this->warnings[1] )
		);
	}

	public function test_slow_only_and_unbounded_only_select_different_human_copy(): void {
		$slow = new Query_Warning( new Query_Classifier(), static fn(): int => 1000, '__return_true' );
		$slow->maybe_emit( [ 'query' => [ 'term' => [ 'post_status' => 'publish' ] ] ], $this->response( 250, 0, 0 ), 251.0, $this->customer_backtrace() );
		$this->assertStringContainsString( 'triggered a slow VIP Search query', $this->warnings[1] );
		$this->assertStringNotContainsString( 'unbounded', strtolower( $this->warnings[1] ) );

		$this->warnings = [];
		wp_cache_flush();
		$unbounded = new Query_Warning( new Query_Classifier(), static fn(): int => 1000, '__return_true' );
		$unbounded->maybe_emit( [], $this->response( 10, 2, 2 ), 50.0, $this->customer_backtrace() );
		$this->assertStringContainsString( 'triggered an unbounded VIP Search query', $this->warnings[1] );
		$this->assertStringNotContainsString( 'above the configured warning threshold', $this->warnings[1] );
	}

	public function test_volatile_query_values_share_warning_id_and_are_deduplicated(): void {
		$warning = new Query_Warning( new Query_Classifier(), static fn(): int => 1000, '__return_true' );
		$warning->maybe_emit( [ 'query' => [ 'term' => [ 'post_author' => 1 ] ] ], $this->response( 250, 1, 1 ), 251.0, $this->customer_backtrace() );
		$warning->maybe_emit( [ 'query' => [ 'term' => [ 'post_author' => 9999 ] ] ], $this->response( 250, 1, 1 ), 251.0, $this->customer_backtrace() );

		$this->assertCount( 2, $this->warnings );
	}

	public function test_new_violation_set_emits_with_same_warning_id_inside_window(): void {
		$warning = new Query_Warning( new Query_Classifier(), static fn(): int => 1000, '__return_true' );
		$warning->maybe_emit( [], $this->response( 10, 1, 1 ), 50.0, $this->customer_backtrace() );
		$warning->maybe_emit( [], $this->response( 250, 1, 1 ), 251.0, $this->customer_backtrace() );

		$this->assertCount( 4, $this->warnings );
		preg_match( '/warning_id=(VSQ-[A-F0-9]{8})/', $this->warnings[0], $first );
		preg_match( '/warning_id=(VSQ-[A-F0-9]{8})/', $this->warnings[2], $second );
		$this->assertSame( $first[1], $second[1] );
	}

	public function test_different_customer_origins_receive_different_warning_ids(): void {
		$warning = new Query_Warning( new Query_Classifier(), static fn(): int => 1000, '__return_true' );
		$warning->maybe_emit( [ 'query' => [ 'match_all' => [] ] ], $this->response( 250, 0, 0 ), 251.0, $this->customer_backtrace() );
		$warning->maybe_emit(
			[ 'query' => [ 'match_all' => [] ] ],
			$this->response( 250, 0, 0 ),
			251.0,
			[
				[
					'file'     => '/srv/www/wp-content/themes/beta/search.php',
					'line'     => 12,
					'function' => 'run',
				],
			]
		);

		preg_match( '/warning_id=(VSQ-[A-F0-9]{8})/', $this->warnings[0], $first );
		preg_match( '/warning_id=(VSQ-[A-F0-9]{8})/', $this->warnings[2], $second );
		$this->assertNotSame( $first[1], $second[1] );
	}

	public function test_site_budget_counts_pairs_and_suppresses_the_sixth_family(): void {
		$warning = new Query_Warning( new Query_Classifier(), static fn(): int => 1000, '__return_true' );

		for ( $index = 1; $index <= 6; $index++ ) {
			$warning->maybe_emit(
				[ 'query' => [ 'term' => [ 'field_' . $index => 'value' ] ] ],
				$this->response( 250, 1, 1 ),
				251.0,
				$this->customer_backtrace()
			);
		}

		$this->assertCount( 10, $this->warnings );
	}

	public function test_cache_failure_suppresses_warning(): void {
		$warning = new class( new Query_Classifier(), static fn(): int => 1000, '__return_true' ) extends Query_Warning {
			protected function cache_add( string $key, int $value, int $ttl ): bool {
				return false;
			}
		};

		$this->assertFalse( $warning->maybe_emit( [], $this->response( 250, 0, 0 ), 251.0, $this->customer_backtrace() ) );
		$this->assertSame( [], $this->warnings );
	}

	public function test_invalid_body_can_slow_warn_without_unbounded_label(): void {
		$warning = new Query_Warning( new Query_Classifier(), static fn(): int => 1000, '__return_true' );
		$warning->maybe_emit( '{invalid', $this->response( 250, 0, 0 ), 251.0, $this->customer_backtrace() );

		$this->assertStringContainsString( 'types=slow_query', $this->warnings[0] );
		$this->assertStringContainsString( 'query_scope=unknown', $this->warnings[0] );
		$this->assertStringNotContainsString( 'unbounded_query', $this->warnings[0] );
	}

	public function test_messages_are_single_line_and_do_not_copy_request_body_or_absolute_path(): void {
		$_SERVER['REQUEST_URI'] = "/search/?s=visible\nInjected";
		$warning                = new Query_Warning( new Query_Classifier(), static fn(): int => 1000, '__return_true' );
		$warning->maybe_emit(
			[ 'query' => [ 'match' => [ 'post_content' => 'secret-body-term' ] ] ],
			$this->response( 250, 0, 0 ),
			251.0,
			$this->customer_backtrace()
		);

		$this->assertCount( 2, $this->warnings );
		foreach ( $this->warnings as $message ) {
			$this->assertStringNotContainsString( "\n", $message );
			$this->assertStringNotContainsString( 'secret-body-term', $message );
			$this->assertStringNotContainsString( '/srv/www/', $message );
		}
	}

	public function test_missing_customer_frame_uses_safe_origin_fallback(): void {
		$warning = new Query_Warning( new Query_Classifier(), static fn(): int => 1000, '__return_true' );
		$warning->maybe_emit( [], $this->response( 10, 0, 0 ), 50.0, [
			[
				'file' => '/srv/www/wp-content/mu-plugins/search.php',
				'line' => 10,
			],
		] );

		$this->assertStringContainsString( 'during this request.', $this->warnings[1] );
		$this->assertStringNotContainsString( 'mu-plugins/search.php', $this->warnings[1] );
	}

	public function test_non_http_context_uses_an_explicit_allowlisted_source(): void {
		unset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] );
		$warning = new Query_Warning( new Query_Classifier(), static fn(): int => 1000, '__return_true' );
		$warning->maybe_emit( [], $this->response( 10, 0, 0 ), 50.0, $this->customer_backtrace() );
		$_SERVER['HTTP_HOST']   = 'example.com';
		$_SERVER['REQUEST_URI'] = '/search/?category=events';

		$this->assertStringContainsString( 'source="unknown"', $this->warnings[0] );
		$this->assertStringContainsString( 'A non-HTTP WordPress process triggered', $this->warnings[1] );
	}

	public function test_fractional_slow_duration_is_reported_above_the_threshold(): void {
		$warning = new Query_Warning( new Query_Classifier(), static fn(): int => 1000, '__return_true' );
		$warning->maybe_emit( [ 'query' => [ 'match_all' => [] ] ], $this->response( null, 0, 0 ), 200.1, $this->customer_backtrace() );

		$this->assertStringContainsString( 'request_ms=201 request_limit_ms=200', $this->warnings[0] );
		$this->assertStringContainsString( 'took 201 ms, above the configured warning threshold of 200 ms', $this->warnings[1] );
	}

	public function test_non_persistent_cache_suppresses_warning(): void {
		$warning = new Query_Warning( new Query_Classifier(), static fn(): int => 1000, '__return_false' );

		$this->assertFalse( $warning->maybe_emit( [], $this->response( 250, 0, 0 ), 251.0, $this->customer_backtrace() ) );
		$this->assertSame( [], $this->warnings );
	}

	public function test_budget_counter_failure_suppresses_warning(): void {
		$warning = new class( new Query_Classifier(), static fn(): int => 1000, '__return_true' ) extends Query_Warning {
			private $add_calls = 0;

			protected function cache_add( string $key, int $value, int $ttl ): bool {
				++$this->add_calls;

				return 1 === $this->add_calls;
			}

			protected function cache_incr( string $key ) {
				return false;
			}
		};

		$this->assertFalse( $warning->maybe_emit( [], $this->response( 250, 0, 0 ), 251.0, $this->customer_backtrace() ) );
		$this->assertSame( [], $this->warnings );
	}

	public function test_full_family_hash_is_used_for_deduplication(): void {
		$warning = new class( new Query_Classifier(), static fn(): int => 1000, '__return_true' ) extends Query_Warning {
			private $family_count = 0;

			protected function family_hash( array $classified, string $origin_key ): string {
				++$this->family_count;

				return 'abcdef12' . str_repeat( (string) $this->family_count, 56 );
			}
		};

		$warning->maybe_emit( [ 'query' => [ 'term' => [ 'post_type' => 'post' ] ] ], $this->response( 250, 1, 1 ), 251.0, $this->customer_backtrace() );
		$warning->maybe_emit( [ 'query' => [ 'match' => [ 'post_title' => 'events' ] ] ], $this->response( 250, 1, 1 ), 251.0, $this->customer_backtrace() );

		$this->assertCount( 4, $this->warnings );
		$this->assertStringContainsString( 'warning_id=VSQ-ABCDEF12', $this->warnings[0] );
		$this->assertStringContainsString( 'warning_id=VSQ-ABCDEF12', $this->warnings[2] );
	}

	public function test_cron_source_takes_precedence_over_http_request_variables(): void {
		add_filter( 'wp_doing_cron', '__return_true' );
		$warning = new Query_Warning( new Query_Classifier(), static fn(): int => 1000, '__return_true' );
		$warning->maybe_emit( [], $this->response( 10, 0, 0 ), 50.0, $this->customer_backtrace() );

		$this->assertStringContainsString( 'source="wp_cron"', $this->warnings[0] );
		$this->assertStringNotContainsString( 'url=', $this->warnings[0] );
		$this->assertStringContainsString( 'A scheduled WordPress task triggered', $this->warnings[1] );
	}

	private function response( ?int $engine_ms, int $returned, int $total_hits ): array {
		$response = [
			'hits' => [
				'total' => [ 'value' => $total_hits ],
				'hits'  => array_fill( 0, $returned, [ '_id' => '1' ] ),
			],
		];

		if ( null !== $engine_ms ) {
			$response = [ 'took' => $engine_ms ] + $response;
		}

		return $response;
	}

	private function customer_backtrace(): array {
		return [
			[
				'file'     => '/srv/www/wp-content/plugins/acme/search.php',
				'line'     => 81,
				'class'    => 'Acme\\Search',
				'type'     => '->',
				'function' => 'run',
			],
		];
	}
}
