<?php

namespace Automattic\VIP\Search;

use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;
use Automattic\Test\Constant_Mocker;
use ElasticPress\Elasticsearch;
use ElasticPress\Indexable;
use ElasticPress\Indexables;
use WP_Error;

require_once __DIR__ . '/../../../../search/search.php';
require_once __DIR__ . '/../../../../search/includes/classes/class-health.php';
require_once __DIR__ . '/../../../../search/elasticpress/includes/classes/Indexables.php';
require_once __DIR__ . '/../../../../search/elasticpress/includes/classes/Elasticsearch.php';

class Health_Test extends WP_UnitTestCase {
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

	/** @var Search */
	private $search_instance;

	public function setUp(): void {
		parent::setUp();
		Constant_Mocker::clear();

		$this->search_instance = new Search();
		$this->search_instance->init();
	}

	public function tearDown(): void {
		Constant_Mocker::clear();
		parent::tearDown();
	}

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

	public function test_filter_expected_post_rows() {
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

			// Protected post
			(object) array(
				'ID'            => 6,
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_password' => 'test',
			),
		);

		$indexed_post_types        = array( 'post' );
		$indexed_post_statuses     = array( 'publish' );
		$protected_content_enabled = false;

		$filtered = Health::filter_expected_post_rows( $rows, $indexed_post_types, $indexed_post_statuses, $protected_content_enabled );

		// Grab just the IDs to make validation simpler
		$filtered_ids = array_values( wp_list_pluck( $filtered, 'ID' ) );

		$expected_ids = array( 1, 5 );

		$this->assertEquals( $expected_ids, $filtered_ids );
	}

	public function test_filter_expected_post_rows__protected_content() {
		$rows = array(
			(object) array(
				'ID'            => 1,
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_password' => '',
			),

			// Protected post
			(object) array(
				'ID'            => 6,
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_password' => 'test',
			),
		);

		$indexed_post_types        = array( 'post' );
		$indexed_post_statuses     = array( 'publish' );
		$protected_content_enabled = true;

		$filtered = Health::filter_expected_post_rows( $rows, $indexed_post_types, $indexed_post_statuses, $protected_content_enabled );

		// Grab just the IDs to make validation simpler
		$filtered_ids = array_values( wp_list_pluck( $filtered, 'ID' ) );

		$expected_ids = array( 1, 6 );

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

	public function test_get_document_ids_for_batch() {
		$ids = Health::get_document_ids_for_batch( 1, 5 );

		$expected_ids = array( 1, 2, 3, 4, 5 );

		$this->assertEquals( $expected_ids, $ids );
	}

	public function test_get_last_post_id() {
		$post = $this->factory()->post->create_and_get( [ 'post_status' => 'publish' ] );

		$last_db_post_id = $post->ID;
		$last_es_post_id = 0;

		$last_post_id = Health::get_last_post_id();

		$this->assertEquals( $last_post_id, max( $last_db_post_id, $last_es_post_id ) );
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

		// Should be false since there are no inconsitencies in the test data
		$this->assertEquals( $diff, $expected_diff );
	}

	public function test_get_index_entity_count_from_elastic_search__returns_result() {
		$health         = new Health( $this->search_instance );
		$expected_count = 42;

		/** @var Indexable&MockObject */
		$mocked_indexable = $this->getMockBuilder( Indexable::class )
			->onlyMethods( self::$indexable_methods )
			->addMethods( self::$indexable_children_methods )
			->getMock();

		$mocked_indexable->slug = 'foo';

		$mocked_indexable->method( 'query_es' )
			->willReturn( [
				'found_documents' => [
					'value' => $expected_count,
				],
			] );

		$result = $health->get_index_entity_count_from_elastic_search( [], $mocked_indexable );

		$this->assertEquals( $result, $expected_count );
	}

	public function test_get_index_entity_count_from_elastic_search__exception() {
		$health = new Health( $this->search_instance );

		/** @var Indexable&MockObject */
		$mocked_indexable = $this->getMockBuilder( Indexable::class )
			->onlyMethods( self::$indexable_methods )
			->addMethods( self::$indexable_children_methods )
			->getMock();

		$mocked_indexable->slug = 'foo';

		$mocked_indexable->method( 'query_es' )
			->willThrowException( new \Exception() );

		$result = $health->get_index_entity_count_from_elastic_search( [], $mocked_indexable );

		$this->assertTrue( is_wp_error( $result ) );
	}

	public function test_get_index_entity_count_from_elastic_search__failed_query() {
		$health = new Health( $this->search_instance );

		/** @var Indexable&MockObject */
		$mocked_indexable = $this->getMockBuilder( Indexable::class )
			->onlyMethods( self::$indexable_methods )
			->addMethods( self::$indexable_children_methods )
			->getMock();

		$mocked_indexable->slug = 'foo';

		$mocked_indexable->method( 'query_es' )
			->willReturn( false );

		$result = $health->get_index_entity_count_from_elastic_search( [], $mocked_indexable );

		$this->assertTrue( is_wp_error( $result ) );
	}

	public function test_validate_index_entity_count__failed_ES_should_pass_error() {
		$error = new WP_Error( 'test error' );

		/** @var Indexable&MockObject */
		$mocked_indexable = $this->getMockBuilder( Indexable::class )
			->onlyMethods( self::$indexable_methods )
			->getMock();

		$mocked_indexable->slug = 'foo';
		$mocked_indexable->method( 'index_exists' )->willReturn( true );

		/** @var Health&MockObject */
		$patrtially_mocked_health = $this->getMockBuilder( Health::class )
			->setConstructorArgs( [ $this->search_instance ] )
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

		/** @var Indexable&MockObject */
		$mocked_indexable = $this->getMockBuilder( Indexable::class )
			->onlyMethods( self::$indexable_methods )
			->getMock();

		$mocked_indexable->slug = $expected_result['entity'];
		$mocked_indexable->method( 'query_db' )
			->willReturn( [
				'total_objects' => $expected_result['db_total'],
			] );
		$mocked_indexable->method( 'index_exists' )->willReturn( true );

		/** @var Health&MockObject */
		$patrtially_mocked_health = $this->getMockBuilder( Health::class )
			->setConstructorArgs( [ $this->search_instance ] )
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

		/** @var Indexable&MockObject */
		$mocked_indexable = $this->getMockBuilder( Indexable::class )
			->onlyMethods( self::$indexable_methods )
			->getMock();
		$mocked_indexable->method( 'index_exists' )->willReturn( true );
		$mocked_indexable->slug = $expected_result['entity'];

		/** @var Health&MockObject */
		$patrtially_mocked_health = $this->getMockBuilder( Health::class )
			->setConstructorArgs( [ $this->search_instance ] )
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

		/** @var Indexable&MockObject */
		$mocked_indexable = $this->getMockBuilder( Indexable::class )
			->onlyMethods( self::$indexable_methods )
			->getMock();
		$mocked_indexable->method( 'index_exists' )->willReturn( false );
		$mocked_indexable->slug = $expected_result['entity'];

		$health = new Health( $this->search_instance );
		$result = $health->validate_index_entity_count( [], $mocked_indexable );

		$this->assertEquals( $result, $expected_result );
	}

	public function test_validate_index_posts_content__ongoing_results_in_error() {
		/** @var Health&MockObject */
		$patrtially_mocked_health = $this->getMockBuilder( Health::class )
			->onlyMethods( [ 'is_validate_content_ongoing' ] )
			->disableOriginalConstructor()
			->getMock();

		$patrtially_mocked_health->method( 'is_validate_content_ongoing' )
			->willReturn( true );

		$result = $patrtially_mocked_health->validate_index_posts_content( 1, null, null, null, false, false, false );

		$this->assertTrue( is_wp_error( $result ) );
	}

	public function test_validate_index_posts_content__should_set_and_clear_lock() {
		/** @var Health&MockObject */
		$patrtially_mocked_health = $this->getMockBuilder( Health::class )
			->onlyMethods( [ 'set_validate_content_lock', 'remove_validate_content_lock', 'validate_index_posts_content_batch' ] )
			->disableOriginalConstructor()
			->getMock();
		$patrtially_mocked_health->method( 'validate_index_posts_content_batch' )->willReturn( [] );

		/** @var Indexables&MockObject */
		$mocked_indexables = $this->getMockBuilder( Indexables::class )
			->onlyMethods( [ 'get' ] )
			->getMock();

		$patrtially_mocked_health->indexables = $mocked_indexables;

		/** @var Indexable&MockObject */
		$mocked_indexable = $this->getMockBuilder( Indexable::class )
			->onlyMethods( self::$indexable_methods )
			->getMock();

		$mocked_indexables->method( 'get' )->willReturn( $mocked_indexable );

		$patrtially_mocked_health->expects( $this->once() )->method( 'set_validate_content_lock' );
		$patrtially_mocked_health->expects( $this->once() )->method( 'remove_validate_content_lock' );

		$patrtially_mocked_health->validate_index_posts_content( 1, null, null, null, false, false, false );
	}

	public function test_validate_index_posts_content__should_set_and_clear_last_processed() {
		$options = [
			'start_post_id' => 1,
			'last_post_id'  => 100,
			'batch_size'    => 50,
		];

		/** @var Health&MockObject */
		$patrtially_mocked_health = $this->getMockBuilder( Health::class )
			->onlyMethods( [ 'update_validate_content_process', 'remove_validate_content_process', 'validate_index_posts_content_batch' ] )
			->disableOriginalConstructor()
			->getMock();
		$patrtially_mocked_health->method( 'validate_index_posts_content_batch' )->willReturn( [] );

		$mocked_indexables                    = $this->getMockBuilder( Indexables::class )
			->onlyMethods( [ 'get' ] )
			->getMock();
		$patrtially_mocked_health->indexables = $mocked_indexables;

		$mocked_indexable = $this->getMockBuilder( Indexable::class )
			->onlyMethods( self::$indexable_methods )
			->getMock();

		$mocked_indexables->method( 'get' )->willReturn( $mocked_indexable );

		$patrtially_mocked_health->expects( $this->exactly( 2 ) )
			->method( 'update_validate_content_process' )
			->willReturnMap([
				[ $options['start_post_id'], null ],
				[ $options['start_post_id'] + $options['batch_size'], null ],
			]);

		$patrtially_mocked_health->expects( $this->once() )->method( 'remove_validate_content_process' );

		$patrtially_mocked_health->validate_index_posts_content( $options );
	}

	public function test_validate_index_posts_content__should_not_interact_with_process_if_parallel_run() {
		/** @var Health&MockObject */
		$patrtially_mocked_health = $this->getMockBuilder( Health::class )
			->onlyMethods( [ 'update_validate_content_process', 'remove_validate_content_process', 'validate_index_posts_content_batch' ] )
			->disableOriginalConstructor()
			->getMock();
		$patrtially_mocked_health->method( 'validate_index_posts_content_batch' )->willReturn( [] );

		$mocked_indexables                    = $this->getMockBuilder( Indexables::class )
			->onlyMethods( [ 'get' ] )
			->getMock();
		$patrtially_mocked_health->indexables = $mocked_indexables;

		$mocked_indexable = $this->getMockBuilder( Indexable::class )
			->onlyMethods( self::$indexable_methods )
			->getMock();

		$mocked_indexables->method( 'get' )->willReturn( $mocked_indexable );

		$patrtially_mocked_health->expects( $this->never() )->method( 'update_validate_content_process' );

		$patrtially_mocked_health->expects( $this->never() )->method( 'remove_validate_content_process' );


		$patrtially_mocked_health->validate_index_posts_content( [ 'force_parallel_execution' => true ] );
	}

	public function test_validate_index_posts_content__should_not_interact_with_process_if_non_default_start_id_is_sent_in() {
		/** @var Health&MockObject */
		$patrtially_mocked_health = $this->getMockBuilder( Health::class )
			->onlyMethods( [ 'update_validate_content_process', 'remove_validate_content_process', 'validate_index_posts_content_batch' ] )
			->disableOriginalConstructor()
			->getMock();
		$patrtially_mocked_health->method( 'validate_index_posts_content_batch' )->willReturn( [] );

		$mocked_indexables                    = $this->getMockBuilder( Indexables::class )
			->onlyMethods( [ 'get' ] )
			->getMock();
		$patrtially_mocked_health->indexables = $mocked_indexables;

		$mocked_indexable = $this->getMockBuilder( Indexable::class )
			->onlyMethods( self::$indexable_methods )
			->getMock();

		$mocked_indexables->method( 'get' )->willReturn( $mocked_indexable );

		$patrtially_mocked_health->expects( $this->never() )->method( 'update_validate_content_process' );

		$patrtially_mocked_health->expects( $this->never() )->method( 'remove_validate_content_process' );


		$patrtially_mocked_health->validate_index_posts_content( [ 'start_post_id' => 25 ] );
	}

	public function test_validate_index_posts_content__pick_up_after_interuption() {
		$interrupted_post_id = 5;
		$start_post_id       = 1;

		/** @var Health&MockObject */
		$patrtially_mocked_health = $this->getMockBuilder( Health::class )
			->onlyMethods( [ 'update_validate_content_process', 'remove_validate_content_process', 'get_validate_content_abandoned_process', 'validate_index_posts_content_batch' ] )
			->disableOriginalConstructor()
			->getMock();
		$patrtially_mocked_health->method( 'validate_index_posts_content_batch' )->willReturn( [] );
		$patrtially_mocked_health->method( 'get_validate_content_abandoned_process' )->willReturn( $interrupted_post_id );

		$mocked_indexables                    = $this->getMockBuilder( Indexables::class )
			->onlyMethods( [ 'get' ] )
			->getMock();
		$patrtially_mocked_health->indexables = $mocked_indexables;

		$mocked_indexable = $this->getMockBuilder( Indexable::class )
			->onlyMethods( self::$indexable_methods )
			->getMock();

		$mocked_indexables->method( 'get' )->willReturn( $mocked_indexable );

		$patrtially_mocked_health->expects( $this->once() )
			->method( 'validate_index_posts_content_batch' )
			->with( $this->anything(), $interrupted_post_id, $this->anything(), $this->anything() );


		$patrtially_mocked_health->validate_index_posts_content( $start_post_id, null, null, null, false, false, false );
	}

	public function test_validate_index_posts_content__do_not_pick_up_after_interuption_when_running_in_parallel() {
		$interrupted_post_id = 5;
		$start_post_id       = 1;

		/** @var Health&MockObject */
		$patrtially_mocked_health = $this->getMockBuilder( Health::class )
			->onlyMethods( [ 'update_validate_content_process', 'remove_validate_content_process', 'get_validate_content_abandoned_process', 'validate_index_posts_content_batch' ] )
			->disableOriginalConstructor()
			->getMock();
		$patrtially_mocked_health->method( 'validate_index_posts_content_batch' )->willReturn( [] );
		$patrtially_mocked_health->method( 'get_validate_content_abandoned_process' )->willReturn( $interrupted_post_id );

		$mocked_indexables                    = $this->getMockBuilder( Indexables::class )
			->onlyMethods( [ 'get' ] )
			->getMock();
		$patrtially_mocked_health->indexables = $mocked_indexables;

		$mocked_indexable = $this->getMockBuilder( Indexable::class )
			->onlyMethods( self::$indexable_methods )
			->getMock();

		$mocked_indexables->method( 'get' )->willReturn( $mocked_indexable );

		$patrtially_mocked_health->expects( $this->once() )
			->method( 'validate_index_posts_content_batch' )
			->with( $this->anything(), $start_post_id, $this->anything(), $this->anything() );

		$patrtially_mocked_health->validate_index_posts_content( [
			'start_post_id'            => $start_post_id,
			'force_parallel_execution' => true,
		] );
	}

	public function test_validate_index_posts_content__do_not_pick_up_after_interuption_when_non_default_start_post_id() {
		$interrupted_post_id = 5;
		$start_post_id       = 2;

		/** @var Health&MockObject */
		$patrtially_mocked_health = $this->getMockBuilder( Health::class )
			->onlyMethods( [ 'update_validate_content_process', 'remove_validate_content_process', 'get_validate_content_abandoned_process', 'validate_index_posts_content_batch' ] )
			->disableOriginalConstructor()
			->getMock();
		$patrtially_mocked_health->method( 'validate_index_posts_content_batch' )->willReturn( [] );
		$patrtially_mocked_health->method( 'get_validate_content_abandoned_process' )->willReturn( $interrupted_post_id );

		$mocked_indexables                    = $this->getMockBuilder( Indexables::class )
			->onlyMethods( [ 'get' ] )
			->getMock();
		$patrtially_mocked_health->indexables = $mocked_indexables;

		$mocked_indexable = $this->getMockBuilder( Indexable::class )
			->onlyMethods( self::$indexable_methods )
			->getMock();

		$mocked_indexables->method( 'get' )->willReturn( $mocked_indexable );

		$patrtially_mocked_health->expects( $this->once() )
			->method( 'validate_index_posts_content_batch' )
			->with( $this->anything(), $start_post_id, $this->anything(), $this->anything() );

		$patrtially_mocked_health->validate_index_posts_content( [ 'start_post_id' => $start_post_id ] );
	}

	public function get_index_settings_diff_for_indexable_data() {
		return array(
			// No diff expected, empty arrays
			array(
				// Actual settings of index in Elasticsearch
				array(),
				// Desired index settings from ElasticPress
				array(),
				// Options
				array(),
				// Expected diff
				array(),
			),
			// No diff expected, equal arrays
			array(
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
			// No diff expected, type juggling
			array(
				// Actual settings of index in Elasticsearch
				array(
					'index.number_of_shards'   => '1',
					'index.number_of_replicas' => '2',
				),
				// Desired index settings from ElasticPress
				array(
					'index.number_of_shards'   => 1,
					'index.number_of_replicas' => 2,
				),
				// Options
				array(),
				// Expected diff
				array(),
			),
			// Diff expected, type juggling
			array(
				// Actual settings of index in Elasticsearch
				array(
					'index.number_of_shards'   => '1',
					'index.number_of_replicas' => '2',
				),
				// Desired index settings from ElasticPress
				array(
					'index.number_of_shards'   => 1,
					'index.number_of_replicas' => 3,
				),
				// Options
				array(),
				// Expected diff
				array(
					'index.number_of_replicas' => array(
						'expected' => 3,
						'actual'   => '2',
					),
				),
			),
			// Diff expected, mismatched settings
			array(
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
			// Diff expected, mismatched settings with specific index version
			array(
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
					'version_number' => 2,
				),
				// Expected diff
				array(
					'index.number_of_replicas' => array(
						'expected' => 1,
						'actual'   => 2,
					),
				),
			),
		);
	}

	/**
	 * @dataProvider get_index_settings_diff_for_indexable_data
	 * @processIsolation true
	 */
	public function test_get_index_settings_diff_for_indexable( $actual, $desired, $options, $expected_diff ) {
		$index_name = 'vip-123-post-1';
		// Mock search and the versioning instance
		/** @var Search&MockObject */
		$mock_search = $this->createMock( Search::class );

		$mock_search->versioning = $this->getMockBuilder( Versioning::class )
			->onlyMethods( [ 'set_current_version_number', 'reset_current_version_number' ] )
			->getMock();

		$health = new Health( $mock_search );

		/** @var Indexable&MockObject */
		$mocked_indexable = $this->getMockBuilder( Indexable::class )
			->onlyMethods( self::$indexable_methods )
			->getMock();

		$mocked_indexable->slug = 'post';
		$mocked_indexable->method( 'index_exists' )->willReturn( true );
		$mocked_indexable->method( 'get_index_name' )->willReturn( $index_name );

		/** @var Elasticsearch&MockObject */
		$health->elasticsearch = $this->getMockBuilder( Elasticsearch::class )
			->onlyMethods( [ 'get_index_settings' ] )
			->getMock();

		$health->elasticsearch->method( 'get_index_settings' )
			->willReturn( [
				'vip-123-post-1' => [
					'settings' => [
						$actual,
					],
				],
			] );

		$mocked_indexable->method( 'generate_mapping' )
			->willReturn( [
				'settings' => [ $desired ],
			] );

		$actual_result = $health->get_index_settings_diff_for_indexable( $mocked_indexable, $options );

		$expected_result = [];
		if ( ! empty( $actual_result ) ) {
			$expected_result = [
				'diff'          => $expected_diff,
				'index_version' => 1,
				'index_name'    => $index_name,
			];
		}

		$this->assertEquals( $actual_result, $expected_result );
	}

	public function test_get_index_settings_diff_for_indexable_without_index() {
		$options = [ 'version_number' => 2 ];
		$actual  = [ 'index.number_of_shards' => 1 ];
		$desired = [ 'index.number_of_shards' => 2 ];
		// Mock search and the versioning instance
		/** @var Search&MockObject */
		$mock_search = $this->createMock( Search::class );

		$mock_search->versioning = $this->getMockBuilder( Versioning::class )
			->onlyMethods( [ 'set_current_version_number', 'reset_current_version_number' ] )
			->getMock();

		$health = new Health( $mock_search );

		/** @var Indexable&MockObject */
		$mocked_indexable = $this->getMockBuilder( Indexable::class )
			->onlyMethods( self::$indexable_methods )
			->addMethods( self::$indexable_children_methods )
			->getMock();

		$mocked_indexable->slug = 'post';
		$mocked_indexable->method( 'index_exists' )->willReturn( false );

		$mocked_indexable->method( 'get_index_settings' )
			->willReturn( $actual );

		$mocked_indexable->method( 'generate_mapping' )
			->willReturn( [ 'settings' => $desired ] );

		$actual_diff = $health->get_index_settings_diff_for_indexable( $mocked_indexable, $options );

		$this->assertEmpty( $actual_diff );
	}

	public function heal_index_settings_for_indexable_data() {
		return array(
			// Regular healing
			array(
				// Desired index settings from ElasticPress
				array(
					'index.number_of_shards'   => 1,
					'index.number_of_replicas' => 2,
					'index.max_result_window'  => 9000,
				),
				// Options
				array(),
			),
			// Includes unhealed settings
			array(
				// Desired index settings from ElasticPress
				array(
					'index.number_of_shards'   => 1,
					'index.number_of_replicas' => 1,
					'index.max_result_window'  => 9000,
					'foo'                      => 'baz',
				),
				// Options
				array(),
			),
			// With specific index version
			array(
				// Desired index settings from ElasticPress
				array(
					'index.number_of_shards'   => 1,
					'index.number_of_replicas' => 1,
					'index.max_result_window'  => 9000,
					'foo'                      => 'baz',
				),
				// Options
				array(
					'version_number' => 2,
				),
			),
		);
	}

	/**
	 * @dataProvider heal_index_settings_for_indexable_data
	 * @processIsolation true
	 */
	public function test_heal_index_settings_for_indexable( $desired_settings, $options ) {
		$index_name = 'foo-index-name';
		// Mock search and the versioning instance
		/** @var Search&MockObject */
		$mock_search = $this->createMock( Search::class );

		/** @var Versioning&MockObject */
		$versioning = $this->getMockBuilder( Versioning::class )
			->enableProxyingToOriginalMethods()
			->onlyMethods( [ 'get_current_version_number', 'set_current_version_number', 'reset_current_version_number' ] )
			->getMock();

		/** @var Versioning&MockObject */
		$mock_search->versioning = $versioning;

		// If we're healing a specific version, make sure we actually switch
		if ( isset( $options['index_version'] ) ) {
			$mock_search->versioning->expects( $this->once() )
				->method( 'set_current_version_number' )
				->with( $options['index_version'] );

			$mock_search->versioning->expects( $this->once() )
				->method( 'reset_current_version_number' );
		}

		$versioning->method( 'get_current_version_number' )->willReturn( $options['index_version'] ?? 1 );

		$health = new Health( $mock_search );

		/** @var Indexable&MockObject */
		$mocked_indexable = $this->getMockBuilder( Indexable::class )
			->onlyMethods( self::$indexable_methods )
			->getMock();

		$mocked_indexable->slug = 'post';

		$mocked_indexable->method( 'get_index_name' )
			->willReturn( $index_name );

		$mocked_indexable->method( 'generate_mapping' )
			->willReturn( [ 'settings' => $desired_settings ] );

		/** @var Elasticsearch&MockObject */
		$health->elasticsearch = $this->getMockBuilder( Elasticsearch::class )
			->onlyMethods( [ 'update_index_settings' ] )
			->getMock();

		$health->elasticsearch->method( 'update_index_settings' )
			->willReturn( true );

		// Expected updated settings
		$expected_updated_settings = Health::limit_index_settings_to_keys( $desired_settings, Health::INDEX_SETTINGS_HEALTH_AUTO_HEAL_KEYS );

		$health->elasticsearch->expects( $this->once() )
			->method( 'update_index_settings' )
			->with( $index_name, $expected_updated_settings, false );

		$result = $health->heal_index_settings_for_indexable( $mocked_indexable, $options );

		$expected_result = array(
			'index_name'    => $index_name,
			'index_version' => $options['index_version'] ?? 1,
			'result'        => true,
		);

		$this->assertEquals( $expected_result, $result );
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
		$health = new Health( $this->search_instance );

		$limited_settings = $health->limit_index_settings_to_keys( $input, $keys );

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
		$health = new Health( $this->search_instance );

		$actual_diff = $health->get_index_settings_diff( $actual, $desired );

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
}
