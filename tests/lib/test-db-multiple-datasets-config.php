<?php

require_once __DIR__ . '/mock-wpdb.php';
require_once __DIR__ . '/../../lib/db-multiple-datasets-config.php';

use function Automattic\VIP\DatabaseMultipleDatasetsConfig\dataset_callback;
use function Automattic\VIP\DatabaseMultipleDatasetsConfig\multiple_datasets_pre_get_users_cleanup;

class VIP_DatabaseMultipleDatasetsConfig_Test extends WP_UnitTestCase {
	private Wpdb_Mock $wpdb_mock;

	public function setUp(): void {
		parent::setUp();
		$this->wpdb_mock = new Wpdb_Mock();
	}

	public function test__table_belonging_to_main_site() {
		global $db_datasets;

		$db_datasets = [
			[
				'primary'  => false,
				'name'     => 'ds1',
				'blog_ids' => [ '2' ],
			],
			[
				'primary'  => true,
				'name'     => 'ds2',
				'blog_ids' => [ '3' ],
			],
			[
				'primary'  => false,
				'name'     => 'ds3',
				'blog_ids' => [ '4' ],
			],
		];

		$this->wpdb_mock->table = 'wp_abc';
		// Use the primary dataset
		self::assertEquals( [ 'dataset' => 'ds2' ], dataset_callback( '', $this->wpdb_mock ) );
		self::assertEquals( [ 'wp_abc' => 'ds2' ], $this->wpdb_mock->cached_tables );
	}

	public function test__tables_belonging_to_mapped_subsites() {
		global $db_datasets;

		$db_datasets = [
			[
				'primary'  => true,
				'name'     => 'ds1',
				'blog_ids' => [ '2' ],
			],
			[
				'primary'  => false,
				'name'     => 'ds2',
				'blog_ids' => [ '3', '4' ],
			],
		];

		$this->wpdb_mock->table = 'wp_2_abc';
		self::assertEquals( [ 'dataset' => 'ds1' ], dataset_callback( '', $this->wpdb_mock ) );

		$this->wpdb_mock->table = 'wp_3_def';
		self::assertEquals( [ 'dataset' => 'ds2' ], dataset_callback( '', $this->wpdb_mock ) );

		$this->wpdb_mock->table = 'wp_4_ghi';
		self::assertEquals( [ 'dataset' => 'ds2' ], dataset_callback( '', $this->wpdb_mock ) );

		$expected_cached_tables = [
			'wp_2_abc' => 'ds1',
			'wp_3_def' => 'ds2',
			'wp_4_ghi' => 'ds2',
		];
		self::assertEquals( $expected_cached_tables, $this->wpdb_mock->cached_tables );
	}

	public function test__table_belonging_to_unmapped_subsite() {
		global $db_datasets;

		$db_datasets = [
			[
				'primary'  => true,
				'name'     => 'ds1',
				'blog_ids' => [ '2' ],
			],
			[
				'primary'  => false,
				'name'     => 'ds2',
				'blog_ids' => [ '3', '4' ],
			],
		];

		// Use the latest dataset
		$this->wpdb_mock->table = 'wp_5_abc';
		self::assertEquals( [ 'dataset' => 'ds2' ], dataset_callback( '', $this->wpdb_mock ) );
		self::assertEquals( [ 'wp_5_abc' => 'ds2' ], $this->wpdb_mock->cached_tables );
	}

	public function test__non_prefixed_table() {
		global $db_datasets;

		$db_datasets = [
			[
				'primary'  => false,
				'name'     => 'ds1',
				'blog_ids' => [ '2' ],
			],
			[
				'primary'  => true,
				'name'     => 'ds2',
				'blog_ids' => [ '3' ],
			],
			[
				'primary'  => false,
				'name'     => 'ds3',
				'blog_ids' => [ '4' ],
			],
		];

		// Use the primary dataset
		$this->wpdb_mock->table = 'abc';
		self::assertEquals( [ 'dataset' => 'ds2' ], dataset_callback( '', $this->wpdb_mock ) );
		self::assertEquals( [ 'abc' => 'ds2' ], $this->wpdb_mock->cached_tables );
	}

	public function test__multiple_datasets_pre_get_users__with_has_published_posts_should_throw_warning_and_cleanup_field() {
		set_error_handler(static function ( int $errno, string $errstr ) {
			throw new Exception( $errstr, $errno );
		}, E_USER_NOTICE );

		$this->expectExceptionMessage( 'WP_User_Query was called incorrectly. `has_published_posts` can not be used on sites with multiple datasets, users and posts tables use different DBs.' );

		$wp_user_query = new WP_User_Query( [ 'has_published_posts' => true ] );

		multiple_datasets_pre_get_users_cleanup( $wp_user_query );
		self::assertNull( $wp_user_query->query_vars['has_published_posts'] );

		restore_error_handler();
	}

	/**
	 * @dataProvider orderby_post_count_provider
	 */
	public function test__multiple_datasets_pre_get_users__with_orderby_post_count_should_throw_warning_and_cleanup_field( $orderby, $expected_orderby ) {
		set_error_handler(static function ( int $errno, string $errstr ) {
			throw new Exception( $errstr, $errno );
		}, E_USER_NOTICE );

		$this->expectExceptionMessage( 'WP_User_Query was called incorrectly. `orderby = post_count` can not be used on sites with multiple datasets, users and posts tables use different DBs.' );

		$wp_user_query = new WP_User_Query( [ 'orderby' => $orderby ] );

		multiple_datasets_pre_get_users_cleanup( $wp_user_query );
		self::assertEquals( $expected_orderby, $wp_user_query->query_vars['orderby'] );

		restore_error_handler();
	}

	public function orderby_post_count_provider()
	{
		return [
			[ 'post_count', null ],
			[ [ 'a', 'post_count', 'b' ], [ 'a', 'b' ] ],
			[ [ 'a' => 'desc', 'post_count' => 'asc', 'b' => 'asc' ], [ 'a' => 'desc', 'b' => 'asc' ] ],
		];
	}

	public function test__multiple_datasets_pre_get_users__should_not_throw_warning_for_allowed_multi_dataset_args() {
		$wp_user_query = new WP_User_Query( [ 'orderby' => 'login' ] );

		multiple_datasets_pre_get_users_cleanup( $wp_user_query );
		self::assertEquals( 'login', $wp_user_query->query_vars['orderby'] );
	}
}
