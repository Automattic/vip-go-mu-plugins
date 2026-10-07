<?php

require_once __DIR__ . '/mock-wpdb.php';
require_once __DIR__ . '/../../lib/db-multiple-datasets-config.php';

use PHPUnit\Framework\TestCase;

use function Automattic\VIP\DatabaseMultipleDatasetsConfig\dataset_callback;

class VIP_DatabaseMultipleDatasetsConfig_Test extends TestCase {
	private const PRIMARY_IN_THE_MIDDLE = [
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

	private const PRIMARY_FIRST = [
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

	/** @var array{0: bool, 1: mixed} Whether $db_datasets was set before the test, and its value. */
	private array $original_db_datasets;

	public function setUp(): void {
		parent::setUp();
		$this->original_db_datasets = [ array_key_exists( 'db_datasets', $GLOBALS ), $GLOBALS['db_datasets'] ?? null ];
	}

	public function tearDown(): void {
		[ $was_set, $value ] = $this->original_db_datasets;
		if ( $was_set ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the test fixture.
			$GLOBALS['db_datasets'] = $value;
		} else {
			unset( $GLOBALS['db_datasets'] );
		}
		parent::tearDown();
	}

	public function data_dataset_callback(): array {
		return [
			'table belonging to main site uses the primary dataset' => [
				self::PRIMARY_IN_THE_MIDDLE,
				[ 'wp_abc' => 'ds2' ],
			],
			'tables belonging to mapped subsites'         => [
				self::PRIMARY_FIRST,
				[
					'wp_2_abc' => 'ds1',
					'wp_3_def' => 'ds2',
					'wp_4_ghi' => 'ds2',
				],
			],
			'table belonging to unmapped subsite uses the latest dataset' => [
				self::PRIMARY_FIRST,
				[ 'wp_5_abc' => 'ds2' ],
			],
			'non-prefixed table uses the primary dataset' => [
				self::PRIMARY_IN_THE_MIDDLE,
				[ 'abc' => 'ds2' ],
			],
		];
	}

	/**
	 * @dataProvider data_dataset_callback
	 */
	public function test__dataset_callback( array $datasets, array $expected_datasets_by_table ) {
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Configuration read by the callback.
		$GLOBALS['db_datasets'] = $datasets;
		$wpdb_mock              = new Wpdb_Mock();

		foreach ( $expected_datasets_by_table as $table => $expected_dataset ) {
			$wpdb_mock->table = $table;
			self::assertEquals( [ 'dataset' => $expected_dataset ], dataset_callback( '', $wpdb_mock ) );
		}

		self::assertEquals( $expected_datasets_by_table, $wpdb_mock->cached_tables );
	}
}
