<?php

namespace Automattic\VIP\Search;

use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;
use ElasticPress\Elasticsearch;
use ElasticPress\Indexable;
use ElasticPress\Indexables;
use WP_Error;

require_once __DIR__ . '/../../../../search/search.php';
require_once __DIR__ . '/../../../../search/includes/classes/class-health.php';
require_once __DIR__ . '/../../../../search/elasticpress/elasticpress.php';
require_once __DIR__ . '/../../../../search/elasticpress/includes/classes/Indexables.php';
require_once __DIR__ . '/../../../../search/elasticpress/includes/classes/Elasticsearch.php';
require_once __DIR__ . '/trait-es-http-mock.php';
require_once __DIR__ . '/trait-search-test-bootstrap.php';

class Health_Test extends WP_UnitTestCase {
	use ES_HTTP_Mock;
	use Search_Test_Bootstrap;

	/** @var array */
	private static $indexable_methods = [
		'query_es',
		'query_db',
		'get_mapping',
		'prepare_document',
		'put_mapping',
		'index_exists',
		'get_index_name',
		'generate_mapping',
	];

	private static $indexable_children_methods = [
		'format_args',
		'build_mapping',
		'get_index_settings',
		'update_index_settings',
	];

	public function test_get_missing_docs_or_posts_diff() {
		$found_post_ids     = array( 1, 3, 5 );
		$found_document_ids = array( 1, 3, 7 );

		$diff = Health::get_missing_docs_or_posts_diff( $found_post_ids, $found_document_ids );

		$expected_diff = array(
			'post_5' => array(
				'id'       => 5,
				'type'     => 'post',
				'issue'    => 'missing_from_index',
				'expected' => sprintf( 'Post %d to be indexed', 5 ),
				'actual'   => null,
			),
			'post_7' => array(
				'id'       => 7,
				'type'     => 'post',
				'issue'    => 'extra_in_index',
				'expected' => null,
				'actual'   => sprintf( 'Post %d is currently indexed', 7 ),
			),
		);

		$this->assertEquals( $expected_diff, $diff );
	}

	public function filter_expected_post_rows_data() {
		return [
			'protected content disabled' => [ false, [ 1, 5 ] ],
			'protected content enabled'  => [ true, [ 1, 5, 6 ] ],
		];
	}

	/**
	 * @dataProvider filter_expected_post_rows_data
	 */
	public function test_filter_expected_post_rows( $protected_content_enabled, $expected_ids ) {
		add_filter( 'ep_post_sync_kill', function ( $skip, $post_id ) {
			return 2 === $post_id;
		}, 10, 2 );

		$rows = array(
			// Indexed
			(object) array(
				'ID'            => 1,
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_password' => '',
			),

			// Filtered out by ep_post_sync_kill
			(object) array(
				'ID'            => 2,
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_password' => '',
			),

			// Un-indexed post_type
			(object) array(
				'ID'            => 3,
				'post_type'     => 'unindexed',
				'post_status'   => 'publish',
				'post_password' => '',
			),

			// Un-indexed post_status
			(object) array(
				'ID'            => 4,
				'post_type'     => 'post',
				'post_status'   => 'unindexed',
				'post_password' => '',
			),

			// Indexed
			(object) array(
				'ID'            => 5,
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_password' => '',
			),

			// Protected post, only indexed with protected content enabled
			(object) array(
				'ID'            => 6,
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_password' => 'test',
			),
		);

		$filtered = Health::filter_expected_post_rows( $rows, array( 'post' ), array( 'publish' ), $protected_content_enabled );

		// Grab just the IDs to make validation simpler
		$filtered_ids = array_values( wp_list_pluck( $filtered, 'ID' ) );

		$this->assertEquals( $expected_ids, $filtered_ids );
	}

	public function get_diff_document_and_prepared_document_data() {
		return array(
			// Simple diff
			array(
				// Expected
				array(
					'post_title' => 'foo',
				),

				// Indexed
				array(
					'post_title' => 'bar',
				),

				// Expected diff
				array(
					'post_title' => array(
						'expected' => 'foo',
						'actual'   => 'bar',
					),
				),
			),

			// Missing in Expected
			array(
				// Expected
				array(),

				// Indexed
				array(
					'post_title' => 'foo',
				),

				// Expected diff
				array(
					'post_title' => array(
						'expected' => null,
						'actual'   => 'foo',
					),
				),
			),

			// Missing in Indexed
			array(
				// Expected
				array(
					'post_title' => 'foo',
				),

				// Indexed
				array(),

				// Expected diff
				array(
					'post_title' => array(
						'expected' => 'foo',
						'actual'   => null,
					),
				),
			),

			// Nested props
			array(
				// Expected
				array(
					'post_title' => 'foo',
					'meta'       => array(
						'somemeta' => array(
							'raw'   => 'somemeta_raw',
							'value' => 'somemeta_value',
							'date'  => '1970-01-01',
						),
					),
				),

				// Indexed
				array(
					'post_title' => 'bar',
					'meta'       => array(
						'somemeta' => array(
							'raw'   => 'somemeta_raw_other',
							'value' => 'somemeta_value_other',
							'date'  => '1970-12-31', // Should not be validated
						),
					),
				),

				// Expected diff
				array(
					'post_title' => array(
						'expected' => 'foo',
						'actual'   => 'bar',
					),
					'meta'       => array(
						'somemeta' => array(
							'raw'   => array(
								'expected' => 'somemeta_raw',
								'actual'   => 'somemeta_raw_other',
							),
							'value' => array(
								'expected' => 'somemeta_value',
								'actual'   => 'somemeta_value_other',
							),
						),
					),
				),
			),

			// No diff
			array(
				// Expected
				array(
					'post_title' => 'foo',
				),

				// Indexed
				array(
					'post_title' => 'foo',
				),

				// Expected diff
				null,
			),
		);
	}

	/**
	 * @dataProvider get_diff_document_and_prepared_document_data
	 */
	public function test_diff_document_and_prepared_document( $prepared_document, $document, $expected_diff ) {
		$diff = Health::diff_document_and_prepared_document( $document, $prepared_document );

		$this->assertEquals( $expected_diff, $diff );
	}

	/**
	 * @dataProvider data_diff_document_and_prepared_document_does_not_generate_notices
	 */
	public function test_diff_document_and_prepared_document_does_not_generate_notices( array $document, array $prepared_document ): void {
		self::assertNull( Health::diff_document_and_prepared_document( $document, $prepared_document ) );
	}

	/**
	 * @dataProvider data_diff_document_and_prepared_document_does_not_generate_notices
	 */
	public function test_simplified_diff_document_and_prepared_document_does_not_generate_notices( array $document, array $prepared_document ): void {
		self::assertFalse( Health::simplified_diff_document_and_prepared_document( $document, $prepared_document ) );
	}

	public function data_diff_document_and_prepared_document_does_not_generate_notices(): iterable {
		return [
			[
				[
					'meta' => [
						'_dt_aop_include_in_feed' => [
							[
								'value'    => '',
								'raw'      => '',
								'boolean'  => false,
								'date'     => '1971-01-01',
								'datetime' => '1971-01-01 00:00:01',
								'time'     => '00:00:01',
							],
						],
					],
				],
				[
					'meta' => [],
				],
			],
			[
				[
					'meta' => [
						[
							'value' => '',
						],
					],
				],
				[
					'meta' => 'value',
				],
			],
		];
	}

	public function get_last_post_id_data() {
		return [
			'Elasticsearch has a newer post' => [ 10, 10 ],
			'database has a newer post'      => [ -1, 0 ],
		];
	}

	/**
	 * @dataProvider get_last_post_id_data
	 */
	public function test_get_last_post_id( $es_post_id_offset, $expected_offset ) {
		$last_db_post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$this->boot_search();

		$search_body = wp_json_encode( [
			'took' => 1,
			'hits' => [
				'total' => [ 'value' => 1 ],
				'hits'  => [
					[
						'_index'  => 'vip-123-post-1',
						'_source' => [ 'post_id' => $last_db_post_id + $es_post_id_offset ],
					],
				],
			],
		] );

		$this->with_es_http(
			static fn( $args, $url ) => self::es_response( false !== strpos( $url, '/_search' ) ? $search_body : '{}' ),
			function () use ( $last_db_post_id, $expected_offset ) {
				$this->assertSame( $last_db_post_id + $expected_offset, Health::get_last_post_id() );
			}
		);
	}

	public function test_get_last_db_post_id() {
		$post = $this->factory()->post->create_and_get( [ 'post_status' => 'draft' ] );

		$last_post_id = Health::get_last_db_post_id();

		$this->assertEquals( $post->ID, $last_post_id );
	}

	public function test_simplified_get_missing_docs_or_posts_diff() {
		$found_post_ids     = array( 1, 3, 5 );
		$found_document_ids = array( 1, 3, 7 );

		$diff = Health::simplified_get_missing_docs_or_posts_diff( $found_post_ids, $found_document_ids );

		$expected_diff = array(
			'post_5' => array(
				'type'  => 'post',
				'id'    => 5,
				'issue' => 'missing_from_index',
			),
			'post_7' => array(
				'type'  => 'post',
				'id'    => 7,
				'issue' => 'extra_in_index',
			),
		);

		$this->assertEquals( $expected_diff, $diff );
	}

	public function simplified_get_diff_document_and_prepared_document_data() {
		return array(
			// Simple diff
			array(
				// Expected
				array(
					'post_title' => 'foo',
				),

				// Indexed
				array(
					'post_title' => 'bar',
				),

				// Expected diff
				true,
			),

			// Nested props
			array(
				// Expected
				array(
					'post_title' => 'foo',
					'meta'       => array(
						'somemeta' => array(
							'raw'   => 'somemeta_raw',
							'value' => 'somemeta_value',
						),
					),
				),

				// Indexed
				array(
					'post_title' => 'foo',
					'meta'       => array(
						'somemeta' => array(
							'raw'   => 'somemeta_raw',
							'value' => 'somemeta_other_value',
						),
					),
				),

				// Expected diff
				true,
			),

			// No diff
			array(
				// Expected
				array(
					'post_title' => 'foo',
				),

				// Indexed
				array(
					'post_title' => 'foo',
				),

				// Expected diff
				false,
			),

			// Missing in Indexed
			array(
				// Expected
				array(
					'post_title' => 'foo',
				),

				// Indexed
				array(),

				// Expected diff
				true,
			),

			// Missing in Expected
			array(
				// Expected
				array(),

				// Indexed
				array(
					'post_title' => 'foo',
				),

				// Expected diff
				true,
			),
		);
	}

	/**
	 * @dataProvider simplified_get_diff_document_and_prepared_document_data
	 */
	public function test_simplified_diff_document_and_prepared_document( $prepared_document, $document, $expected_diff ) {
		$diff = Health::simplified_diff_document_and_prepared_document( $document, $prepared_document );

		// Should be false since there are no inconsistencies in the test data
		$this->assertEquals( $diff, $expected_diff );
	}

	public function test_get_index_entity_count_from_elastic_search__returns_result() {
		$health         = new Health( $this->createMock( Search::class ) );
		$expected_count = 42;

		$mocked_indexable = $this->mock_indexable( 'foo', true );
		$mocked_indexable->method( 'query_es' )
			->willReturn( [
				'found_documents' => [
					'value' => $expected_count,
				],
			] );

		$result = $health->get_index_entity_count_from_elastic_search( [], $mocked_indexable );

		$this->assertEquals( $result, $expected_count );
	}

	public function get_index_entity_count_from_elastic_search_failure_data() {
		return [
			'query throws' => [ new \Exception() ],
			'query fails'  => [ false ],
		];
	}

	/**
	 * @dataProvider get_index_entity_count_from_elastic_search_failure_data
	 */
	public function test_get_index_entity_count_from_elastic_search__failure( $query_es_result ) {
		$health = new Health( $this->createMock( Search::class ) );

		$mocked_indexable = $this->mock_indexable( 'foo', true );
		if ( $query_es_result instanceof \Exception ) {
			$mocked_indexable->method( 'query_es' )->willThrowException( $query_es_result );
		} else {
			$mocked_indexable->method( 'query_es' )->willReturn( $query_es_result );
		}

		$result = $health->get_index_entity_count_from_elastic_search( [], $mocked_indexable );

		$this->assertWPError( $result );
		$this->assertSame( 'es_query_error', $result->get_error_code() );
	}

	public function test_validate_index_entity_count__failed_ES_should_pass_error() {
		$error = new WP_Error( 'test error' );

		$mocked_indexable = $this->mock_indexable();
		$mocked_indexable->method( 'index_exists' )->willReturn( true );

		/** @var Health&MockObject */
		$patrtially_mocked_health = $this->getMockBuilder( Health::class )
			->setConstructorArgs( [ $this->createMock( Search::class ) ] )
			->onlyMethods( [ 'get_index_entity_count_from_elastic_search' ] )
			->getMock();

		$patrtially_mocked_health->method( 'get_index_entity_count_from_elastic_search' )
			->willReturn( $error );

		$result = $patrtially_mocked_health->validate_index_entity_count( [], $mocked_indexable );

		$this->assertEquals( $result, $error );
	}

	public function test_validate_index_entity_count__returns_all_data() {
		$expected_result = [
			'entity'   => 'foo',
			'type'     => 'N/A',
			'db_total' => 10,
			'es_total' => 8,
			'diff'     => -2,
			'skipped'  => false,
			'reason'   => 'N/A',
		];

		$mocked_indexable = $this->mock_indexable( $expected_result['entity'] );
		$mocked_indexable->method( 'query_db' )
			->willReturn( [
				'total_objects' => $expected_result['db_total'],
			] );
		$mocked_indexable->method( 'index_exists' )->willReturn( true );

		/** @var Health&MockObject */
		$patrtially_mocked_health = $this->getMockBuilder( Health::class )
			->setConstructorArgs( [ $this->createMock( Search::class ) ] )
			->onlyMethods( [ 'get_index_entity_count_from_elastic_search' ] )
			->getMock();

		$patrtially_mocked_health->method( 'get_index_entity_count_from_elastic_search' )
			->willReturn( $expected_result['es_total'] );

		$result = $patrtially_mocked_health->validate_index_entity_count( [], $mocked_indexable );

		$this->assertEquals( $result, $expected_result );
	}

	public function test_validate_index_entity_count__skipping_non_initialized_indexes() {
		$expected_result = [
			'entity'   => 'foo',
			'type'     => 'N/A',
			'db_total' => 'N/A',
			'es_total' => 0,
			'diff'     => 'N/A',
			'skipped'  => true,
			'reason'   => 'index-empty',
		];

		$mocked_indexable = $this->mock_indexable( $expected_result['entity'] );
		$mocked_indexable->method( 'index_exists' )->willReturn( true );

		/** @var Health&MockObject */
		$patrtially_mocked_health = $this->getMockBuilder( Health::class )
			->setConstructorArgs( [ $this->createMock( Search::class ) ] )
			->onlyMethods( [ 'get_index_entity_count_from_elastic_search' ] )
			->getMock();

		$patrtially_mocked_health->method( 'get_index_entity_count_from_elastic_search' )
			->willReturn( $expected_result['es_total'] );

		$result = $patrtially_mocked_health->validate_index_entity_count( [], $mocked_indexable );

		$this->assertEquals( $result, $expected_result );
	}

	public function test_validate_index_entity_count__skipping_non_existing_indexes() {
		$expected_result = [
			'entity'   => 'foo',
			'type'     => 'N/A',
			'db_total' => 'N/A',
			'es_total' => 'N/A',
			'diff'     => 'N/A',
			'skipped'  => true,
			'reason'   => 'index-not-found',
		];

		$mocked_indexable = $this->mock_indexable( $expected_result['entity'] );
		$mocked_indexable->method( 'index_exists' )->willReturn( false );

		$health = new Health( $this->createMock( Search::class ) );
		$result = $health->validate_index_entity_count( [], $mocked_indexable );

		$this->assertEquals( $result, $expected_result );
	}

	public function test_validate_index_posts_content__should_set_and_clear_lock() {
		$search      = $this->boot_search();
		$health      = new Health( $search );
		$second      = new Health( $search );
		$batch_calls = 0;
		$options     = array(
			'start_post_id' => 999000,
			'last_post_id'  => 999000,
		);
		$responder   = function ( $args, $url ) use ( $second, $options, &$batch_calls ) {
			if ( false !== strpos( $url, '/_mget' ) ) {
				++$batch_calls;
				$this->assertTrue( (bool) get_transient( Health::CONTENT_VALIDATION_LOCK_NAME ) );
				$result = $second->validate_index_posts_content( $options );
				$this->assertInstanceOf( WP_Error::class, $result );
				$this->assertSame( 'es_content_validation_already_ongoing', $result->get_error_code() );
			}
			return self::es_response( '{"docs":[]}' );
		};

		$this->with_es_http( $responder, function () use ( $health, $options, &$batch_calls ) {
			$this->assertSame( array(), $health->validate_index_posts_content( $options ) );
			$this->assertGreaterThan( 0, $batch_calls );
			$this->assertFalse( get_transient( Health::CONTENT_VALIDATION_LOCK_NAME ) );
		} );
	}

	public function test_validate_index_posts_content__resumes_persisted_checkpoint() {
		$search    = $this->boot_search();
		$options   = array(
			'last_post_id' => 4,
			'batch_size'   => 2,
			'do_not_heal'  => true,
		);
		$batches   = array();
		$interrupt = true;
		$responder = static function ( $args, $url ) use ( &$batches, &$interrupt ) {
			if ( false !== strpos( $url, '/_mget' ) ) {
				$ids       = json_decode( $args['body'], true )['ids'];
				$batches[] = $ids;
				if ( $interrupt && 3 === $ids[0] ) {
					throw new \RuntimeException( 'Controlled interruption' );
				}
			}
			return self::es_response( '{"docs":[]}' );
		};

		$this->with_es_http( $responder, function () use ( $search, $options, &$batches, &$interrupt ) {
			try {
				( new Health( $search ) )->validate_index_posts_content( $options );
				$this->fail( 'The controlled second-batch interruption must execute.' );
			} catch ( \RuntimeException $error ) {
				$this->assertSame( 'Controlled interruption', $error->getMessage() );
			}
			$this->assertSame( 3, get_option( Health::CONTENT_VALIDATION_PROCESS_OPTION ) );
			$this->assertContains( array( 1, 2 ), $batches );
			$this->assertContains( array( 3, 4 ), $batches );

			// Model the stale transient expiring after the interrupted process exits.
			delete_transient( Health::CONTENT_VALIDATION_LOCK_NAME );
			$interrupt = false;
			$batches   = array();
			$result    = ( new Health( $search ) )->validate_index_posts_content( $options );
			$this->assertIsArray( $result );
			$this->assertNotEmpty( $batches );
			foreach ( $batches as $ids ) {
				$this->assertSame( array( 3, 4 ), $ids );
			}
			$this->assertFalse( get_option( Health::CONTENT_VALIDATION_PROCESS_OPTION ) );
			$this->assertFalse( get_transient( Health::CONTENT_VALIDATION_LOCK_NAME ) );
		} );
	}

	public function test_validate_index_posts_content__do_not_pick_up_after_interruption_when_running_in_parallel() {
		$start_post_id = 1;

		$patrtially_mocked_health = $this->mock_content_validation_health( 5 );

		$patrtially_mocked_health->expects( $this->once() )
			->method( 'validate_index_posts_content_batch' )
			->with( $this->anything(), $start_post_id, $this->anything(), $this->anything() )
			->willReturn( [] );

		// A parallel run must not overwrite the progress of the run that tracks it.
		$patrtially_mocked_health->expects( $this->never() )->method( 'update_validate_content_process' );
		$patrtially_mocked_health->expects( $this->never() )->method( 'remove_validate_content_process' );

		$patrtially_mocked_health->validate_index_posts_content( [
			'start_post_id'            => $start_post_id,
			'last_post_id'             => $start_post_id,
			'force_parallel_execution' => true,
		] );
	}

	public function test_validate_index_posts_content__do_not_pick_up_after_interruption_when_non_default_start_post_id() {
		$start_post_id = 2;

		$patrtially_mocked_health = $this->mock_content_validation_health( 5 );

		$patrtially_mocked_health->expects( $this->once() )
			->method( 'validate_index_posts_content_batch' )
			->with( $this->anything(), $start_post_id, $this->anything(), $this->anything() )
			->willReturn( [] );

		// A run with a custom start_post_id must not overwrite the progress of the default run.
		$patrtially_mocked_health->expects( $this->never() )->method( 'update_validate_content_process' );
		$patrtially_mocked_health->expects( $this->never() )->method( 'remove_validate_content_process' );

		$patrtially_mocked_health->validate_index_posts_content( [
			'start_post_id' => $start_post_id,
			'last_post_id'  => $start_post_id,
		] );
	}

	public function get_index_settings_diff_for_indexable_data() {
		return array(
			'no diff'                      => array(
				// Actual settings of index in Elasticsearch
				array(
					'index.number_of_shards'   => 1,
					'index.number_of_replicas' => 2,
					'index.max_result_window'  => 9000,
				),
				// Desired index settings from ElasticPress
				array(
					'index.number_of_shards'   => 1,
					'index.number_of_replicas' => 2,
					'index.max_result_window'  => 9000,
				),
				// Options
				array(),
				// Expected diff
				array(),
			),
			'unmonitored settings ignored' => array(
				// Actual settings of index in Elasticsearch
				array(
					'index.number_of_shards'   => 1,
					'index.number_of_replicas' => 2,
					'foo'                      => 'bar',
					'index.max_result_window'  => '1000000',
				),
				// Desired index settings from ElasticPress
				array(
					'index.number_of_shards'   => 1,
					'index.number_of_replicas' => 1,
					'foo'                      => 'baz',
					'index.max_result_window'  => 9000,
				),
				// Options
				array(),
				// Expected diff
				array(
					'index.number_of_replicas' => array(
						'expected' => 1,
						'actual'   => 2,
					),
					'index.max_result_window'  => array(
						'expected' => 9000,
						'actual'   => 1000000,
					),
				),
			),
			'specific index version'       => array(
				// Actual settings of index in Elasticsearch
				array(
					'index.number_of_shards'   => 1,
					'index.number_of_replicas' => 2,
					'foo'                      => 'bar',
				),
				// Desired index settings from ElasticPress
				array(
					'index.number_of_shards'   => 1,
					'index.number_of_replicas' => 1,
					'foo'                      => 'baz',
				),
				// Options
				array(
					'index_version' => 2,
				),
				// Expected diff
				array(
					'index.number_of_replicas' => array(
						'expected' => 1,
						'actual'   => 2,
					),
				),
			),
			'index does not exist'         => array(
				// Actual settings of index in Elasticsearch
				array(
					'index.number_of_shards' => 1,
				),
				// Desired index settings from ElasticPress
				array(
					'index.number_of_shards' => 2,
				),
				// Options
				array(),
				// Expected diff
				array(),
				// Index exists
				false,
			),
		);
	}

	/**
	 * @dataProvider get_index_settings_diff_for_indexable_data
	 */
	public function test_get_index_settings_diff_for_indexable( $actual, $desired, $options, $expected_diff, $index_exists = true ) {
		$index_name = isset( $options['index_version'] ) ? 'vip-123-post-1-v2' : 'vip-123-post-1';

		[ $health, $mocked_indexable, $versioning ] = $this->build_settings_diff_health( $index_name, $actual, $desired, $index_exists );

		$versioning->expects( isset( $options['index_version'] ) ? $this->once() : $this->never() )
			->method( 'set_current_version_number' )
			->with( $mocked_indexable, $options['index_version'] ?? null );

		$actual_result = $health->get_index_settings_diff_for_indexable( $mocked_indexable, $options );

		$expected_result = [];
		if ( ! empty( $expected_diff ) ) {
			$expected_result = [
				'diff'          => $expected_diff,
				'index_version' => $options['index_version'] ?? 1,
				'index_name'    => $index_name,
			];
		}

		$this->assertEquals( $expected_result, $actual_result );
	}

	public function get_index_versions_settings_diff_for_indexable_data() {
		return [
			'only checks the active version' => [
				[
					1 => [
						'number'         => 1,
						'active'         => false,
						'created_time'   => 1234567890,
						'activated_time' => 1234567890,
					],
					2 => [
						'number'         => 2,
						'active'         => true,
						'created_time'   => 1234567900,
						'activated_time' => 1234567900,
					],
				],
				// Active version number
				2,
				// Expected checked version
				2,
			],
			'uses the default version when none are stored' => [
				[
					1 => [
						'number'         => 1,
						'active'         => true,
						'created_time'   => null,
						'activated_time' => null,
					],
				],
				// Active version number, must not be looked up
				null,
				// Expected checked version
				1,
			],
		];
	}

	/**
	 * @dataProvider get_index_versions_settings_diff_for_indexable_data
	 */
	public function test_get_index_versions_settings_diff_for_indexable( $versions, $active_version, $expected_version ) {
		[ $health, $mocked_indexable, $versioning ] = $this->build_settings_diff_health(
			'vip-123-post-1',
			[ 'index.number_of_replicas' => 2 ],
			[ 'index.number_of_replicas' => 1 ]
		);

		$versioning->method( 'get_versions' )->willReturn( $versions );
		if ( null === $active_version ) {
			$versioning->expects( $this->never() )->method( 'get_active_version_number' );
		} else {
			$versioning->method( 'get_active_version_number' )->willReturn( $active_version );
		}

		$versioning->expects( $this->once() )
			->method( 'set_current_version_number' )
			->with( $mocked_indexable, $expected_version );

		$result = $health->get_index_versions_settings_diff_for_indexable( $mocked_indexable );

		$this->assertCount( 1, $result );
		$this->assertEquals( $expected_version, $result[0]['index_version'] );
		$this->assertNotEmpty( $result[0]['diff'] );
	}

	public function heal_index_settings_for_indexable_data() {
		return array(
			'current index version'  => array(
				// Desired index settings from ElasticPress, including settings that are not healed
				array(
					'index.number_of_shards'   => 1,
					'index.number_of_replicas' => 1,
					'index.max_result_window'  => 9000,
					'foo'                      => 'baz',
				),
				// Options
				array(),
			),
			'specific index version' => array(
				// Desired index settings from ElasticPress
				array(
					'index.number_of_shards'   => 1,
					'index.number_of_replicas' => 1,
					'index.max_result_window'  => 9000,
					'foo'                      => 'baz',
				),
				// Options
				array(
					'index_version' => 2,
				),
			),
		);
	}

	/**
	 * @dataProvider heal_index_settings_for_indexable_data
	 */
	public function test_heal_index_settings_for_indexable( $desired_settings, $options ) {
		$search    = $this->boot_search();
		$indexable = $this->getMockBuilder( \ElasticPress\Indexable\Post\Post::class )
			->onlyMethods( array( 'generate_mapping' ) )->getMock();
		$indexable->method( 'generate_mapping' )->willReturn( array( 'settings' => $desired_settings ) );
		$versioning = $search->versioning;
		$versioning->update_versions( $indexable, array(
			1 => array(
				'number' => 1,
				'active' => true,
			),
			2 => array(
				'number' => 2,
				'active' => false,
			),
		) );
		$prior_name     = $indexable->get_index_name();
		$target_version = $options['index_version'] ?? 1;
		$versioning->set_current_version_number( $indexable, $target_version );
		$target_name = $indexable->get_index_name();
		$versioning->reset_current_version_number( $indexable );
		if ( 2 === $target_version ) {
			$this->assertNotSame( $prior_name, $target_name );
		}

		$health                    = new Health( $search );
		$health->elasticsearch     = $this->getMockBuilder( Elasticsearch::class )
			->onlyMethods( array( 'update_index_settings' ) )->getMock();
		$expected_updated_settings = Health::limit_index_settings_to_keys( $desired_settings, Health::INDEX_SETTINGS_HEALTH_AUTO_HEAL_KEYS );
		$health->elasticsearch->expects( $this->once() )->method( 'update_index_settings' )
			->with( $target_name, $expected_updated_settings, false )->willReturn( true );

		$result = $health->heal_index_settings_for_indexable( $indexable, $options );
		$this->assertSame( array(
			'index_name'    => $target_name,
			'index_version' => $target_version,
			'result'        => true,
		), $result );
		$this->assertSame( 1, $versioning->get_current_version_number( $indexable ) );
		$this->assertSame( $prior_name, $indexable->get_index_name() );
	}

	public function limit_index_settings_to_keys_data() {
		return array(
			// Mix of monitored and not monitored keys
			array(
				// Input
				array(
					'foo' => 1,
					'bar' => 2,
					'baz' => 3,
				),
				// Monitored keys
				array(
					'foo',
					'fubar',
				),
				// Expected resulting array
				array(
					'foo' => 1,
				),
			),
		);
	}

	/**
	 * @dataProvider limit_index_settings_to_keys_data
	 */
	public function test_limit_index_settings_to_keys( $input, $keys, $expected ) {
		$limited_settings = Health::limit_index_settings_to_keys( $input, $keys );

		$this->assertEquals( $expected, $limited_settings );
	}

	public function get_index_settings_diff_data() {
		return array(
			// No diff expected, empty arrays
			array(
				// Actual settings of index in Elasticsearch
				array(),
				// Desired index settings from ElasticPress
				array(),
				// Expected diff
				array(),
			),
			// No diff expected, equal arrays
			array(
				// Actual settings of index in Elasticsearch
				array(
					'number_of_shards'   => 1,
					'number_of_replicas' => 2,
				),
				// Desired index settings from ElasticPress
				array(
					'number_of_shards'   => 1,
					'number_of_replicas' => 2,
				),
				// Expected diff
				array(),
			),
			// No diff expected, type juggling
			array(
				// Actual settings of index in Elasticsearch
				array(
					'number_of_shards'   => '1',
					'number_of_replicas' => '2',
				),
				// Desired index settings from ElasticPress
				array(
					'number_of_shards'   => 1,
					'number_of_replicas' => 2,
				),
				// Expected diff
				array(),
			),
			// Diff expected, type juggling
			array(
				// Actual settings of index in Elasticsearch
				array(
					'number_of_shards'   => '1',
					'number_of_replicas' => '2',
				),
				// Desired index settings from ElasticPress
				array(
					'number_of_shards'   => 1,
					'number_of_replicas' => 3,
				),
				// Expected diff
				array(
					'number_of_replicas' => array(
						'expected' => 3,
						'actual'   => '2',
					),
				),
			),
			// Diff expected, mismatched settings
			array(
				// Actual settings of index in Elasticsearch
				array(
					'number_of_shards'   => 1,
					'number_of_replicas' => 2,
					'max_result_window'  => '1000000',
					'foo'                => 'bar',
				),
				// Desired index settings from ElasticPress
				array(
					'number_of_shards'   => 1,
					'number_of_replicas' => 1,
					'max_result_window'  => 9000,
					'foo'                => 'baz',
				),
				// Expected diff
				array(
					'number_of_replicas' => array(
						'expected' => 1,
						'actual'   => 2,
					),
					'max_result_window'  => array(
						'expected' => 9000,
						'actual'   => '1000000',
					),
					'foo'                => array(
						'expected' => 'baz',
						'actual'   => 'bar',
					),
				),
			),
			// Nested settings
			array(
				// Actual settings of index in Elasticsearch
				array(
					'number_of_shards' => 1,
					'routing'          => array(
						'allocation' => array(
							'include' => array(
								'dc' => 'dfw,bur',
							),
						),
					),
				),
				// Desired index settings from ElasticPress
				array(
					'number_of_shards' => 1,
					'routing'          => array(
						'allocation' => array(
							'include' => array(
								'dc' => 'bur',
							),
						),
					),
				),
				// Expected diff
				array(
					'routing' => array(
						'allocation' => array(
							'include' => array(
								'dc' => array(
									'expected' => 'bur',
									'actual'   => 'dfw,bur',
								),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * @dataProvider get_index_settings_diff_data
	 */
	public function test_get_index_settings_diff( $actual, $desired, $expected_diff ) {
		$actual_diff = Health::get_index_settings_diff( $actual, $desired );

		$this->assertEquals( $actual_diff, $expected_diff );
	}

	public function validate_post_index_mapping_data() {
		return [
			// Bad mapping
			[
				// Index name
				'bar-post-1',
				// Mapping
				[
					'bar-post-1' => [
						'mappings' => [],
					],
				],
				// Expected result
				false,
			],
			// Bad mapping
			[
				// Index name
				'bar-post-1',
				// Mapping
				[
					'bar-post-1' => [],
				],
				// Expected result
				false,
			],
			// Good mapping
			[
				// Index name
				'foo-post-1',
				// Mapping
				[
					'foo-post-1' => [
						'mappings' => [
							'_meta' => [
								'mapping_version' => 'foobar',
							],
						],
					],
				],
				// Expected result
				true,
			],
		];
	}

	/**
	 * @dataProvider validate_post_index_mapping_data
	 */
	public function test__validate_post_index_mapping( $index_name, $mapping, $expected_result ) {
		$correct_mapping = Health::validate_post_index_mapping( $index_name, $mapping );
		$this->assertEquals( $expected_result, $correct_mapping );
	}

	/**
	 * @return Indexable&MockObject
	 */
	private function mock_indexable( string $slug = 'foo', bool $with_child_methods = false ): Indexable {
		$builder = $this->getMockBuilder( Indexable::class )->onlyMethods( self::$indexable_methods );
		if ( $with_child_methods ) {
			$builder->addMethods( self::$indexable_children_methods );
		}

		$indexable       = $builder->getMock();
		$indexable->slug = $slug;

		return $indexable;
	}

	/**
	 * A Health whose content validation batches and process tracking are mocked.
	 *
	 * @return Health&MockObject
	 */
	private function mock_content_validation_health( int $abandoned_start_post_id ): Health {
		/** @var Health&MockObject */
		$health = $this->getMockBuilder( Health::class )
			->onlyMethods( [ 'update_validate_content_process', 'remove_validate_content_process', 'get_validate_content_abandoned_process', 'validate_index_posts_content_batch' ] )
			->disableOriginalConstructor()
			->getMock();
		$health->method( 'get_validate_content_abandoned_process' )->willReturn( $abandoned_start_post_id );

		$mocked_indexables = $this->getMockBuilder( Indexables::class )
			->onlyMethods( [ 'get' ] )
			->getMock();
		$mocked_indexables->method( 'get' )->willReturn( $this->mock_indexable() );
		$health->indexables = $mocked_indexables;

		return $health;
	}

	/**
	 * A Health (with mocked Search versioning and Elasticsearch) and a post Indexable whose index has the
	 * $actual settings and whose mapping has the $desired settings.
	 *
	 * @return array{0: Health, 1: Indexable&MockObject, 2: Versioning&MockObject}
	 */
	private function build_settings_diff_health( string $index_name, array $actual, array $desired, bool $index_exists = true ): array {
		/** @var Search&MockObject */
		$mock_search = $this->createMock( Search::class );

		$mock_search->versioning = $this->getMockBuilder( Versioning::class )
			->onlyMethods( [ 'get_versions', 'get_active_version_number', 'set_current_version_number', 'reset_current_version_number' ] )
			->getMock();

		$health = new Health( $mock_search );

		/** @var Elasticsearch&MockObject */
		$health->elasticsearch = $this->getMockBuilder( Elasticsearch::class )
			->onlyMethods( [ 'get_index_settings' ] )
			->getMock();
		$health->elasticsearch->method( 'get_index_settings' )
			->willReturn( [
				$index_name => [
					'settings' => $actual,
				],
			] );

		$mocked_indexable = $this->mock_indexable( 'post' );
		$mocked_indexable->method( 'index_exists' )->willReturn( $index_exists );
		$mocked_indexable->method( 'get_index_name' )->willReturn( $index_name );
		$mocked_indexable->method( 'generate_mapping' )
			->willReturn( [
				'settings' => $desired,
			] );

		return [ $health, $mocked_indexable, $mock_search->versioning ];
	}
}
