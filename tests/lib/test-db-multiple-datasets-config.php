<?php

require_once __DIR__ . '/mock-wpdb.php';
require_once __DIR__ . '/../../lib/db-multiple-datasets-config.php';

use function Automattic\VIP\DatabaseMultipleDatasetsConfig\dataset_callback;

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
}
