<?php

namespace Automattic\VIP\Prometheus;

use Prometheus\Counter;
use Prometheus\RegistryInterface;
use WP_UnitTestCase;

require_once __DIR__ . '/../../prometheus-collectors/class-potential-multi-dataset-queries-collector.php';

class Test_Potential_Multi_Dataset_Queries_Collector extends WP_UnitTestCase {
	public Potential_Multi_Dataset_Queries_Collector $collector;

	public Counter $counter;

	public function setUp(): void {
		parent::setUp();

		$this->collector = new Potential_Multi_Dataset_Queries_Collector();

		$this->counter = $this->getMockBuilder( Counter::class )
			->disableOriginalConstructor()
			->getMock();

		$registry_mock = $this->getMockBuilder( RegistryInterface::class )
			->getMock();
		$registry_mock->method( 'getOrRegisterCounter' )
			->willReturn( $this->counter );

		$this->collector->initialize( $registry_mock );
	}

	/**
	 * @dataProvider data_provider__multi_dataset_queries
	 */
	public function test_collectors_filter_with_multi_dataset_query( $query, $expected_global_table, $expected_multisite_table ): void {
		$this->counter->expects( $this->once() )
			->method( 'inc' )
			->with( [ '1', $expected_global_table, $expected_multisite_table ] );

		$this->collector->query( $query );

		self::assertEquals( 1, 1 );
	}

	/**
	 * @dataProvider data_provider__single_dataset_queries
	 */
	public function test_collectors_filter_without_multi_dataset_query( $query ): void {
		$this->counter->expects( $this->never() )
			->method( 'inc' );

		$this->collector->query( $query );

		self::assertEquals( 1, 1 );
	}

	public function data_provider__multi_dataset_queries() {
		return [
			'SELECT with multiple tables'    => [
				'SELECT * FROM wptests_users, wptests_posts, wptests_123_termmeta',
				'posts',
				'termmeta',
			],
			'SELECT with join'               => [
				'SeLeCt * FROM `wptests_users` JOIN wptests_123_termmeta',
				'users',
				'termmeta',
			],
			'SELECT with subquery'           => [
				'SELECT * FROM wptests_users, (SELECT * FROM wptests_123_termmeta)',
				'users',
				'termmeta',
			],
			'UpDaTe with join'               => [
				'UPDATE wptests_users INNER JOIN `wptests_2_posts` ON (wptests_users.ID = wptests_2_posts.post_author) SET wptests_2_posts.post_author = wptests_users.ID',
				'users',
				'posts',
			],
			'UPDATE on blog table with join' => [
				'UPDATE `wptests_2_posts` JOIN `wptests_users` ON `wptests_users`.ID = `wptests_2_posts`.post_author SET `wptests_2_posts`.post_author = `wptests_users`.ID',
				'users',
				'posts',
			],
			'InSeRt with subquery'           => [
				'INSERT INTO wptests_2_posts (post_author, post_title) SELECT ID, "title" FROM `wptests_users`',
				'users',
				'posts',
			],
		];
	}

	public function data_provider__single_dataset_queries() {
		return [
			'SELECT with multiple tables'    => [
				'SELECT * FROM wptests_users, `wptests_posts`, wptests_termmeta',
			],
			'SELECT with join'               => [
				'SeLeCt * FROM `wptests_users` JOIN wptests_termmeta',
			],
			'SELECT with subquery'           => [
				'SELECT * FROM wptests_users, (SELECT * FROM wptests_termmeta)',
			],
			'UpDaTe with join'               => [
				'UPDATE wptests_users INNER JOIN `wptests_posts` ON (wptests_users.ID = wptests_posts.post_author) SET wptests_posts.post_author = wptests_users.ID',
			],
			'UPDATE on blog table with join' => [
				'UPDATE `wptests_posts` JOIN `wptests_users` ON `wptests_users`.ID = `wptests_posts`.post_author SET `wptests_posts`.post_author = `wptests_users`.ID',
			],
			'InSeRt with subquery'           => [
				'INSERT INTO wptests_posts (post_author, post_title) SELECT ID, "title" FROM `wptests_users`',
			],
		];
	}
}
