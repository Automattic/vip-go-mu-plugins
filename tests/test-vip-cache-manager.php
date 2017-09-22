
<?php

class VIP_Go_Cache_Manager_Test extends WP_UnitTestCase {
	public $cache_manager;

	public function setUp() {
		parent::setUp();

		$this->cache_manager = WPCOM_VIP_Cache_Manager::instance();
	}

	public function get_data_for_queue_purge_url() {
		return [
			// 1: input URL
			// 2: expected response from method
			// 3: expected purge_urls list

			'normal_url' => [
				'https://example.com/path/to/file?query',
				true,
				[ 'https://example.com/path/to/file?query' ],
			],

			'invalid_url' => [
				'badscheme://example.com/path',
				false,
				[],
			],

			'strip_fragment_from_url' => [
				'https://example.com/post#fragment',
				true,
				[ 'https://example.com/post' ],
			],
		];
	}

	/**
 	 * @dataProvider get_data_for_queue_purge_url
 	 */ 
	public function test__queue_purge_url( $queue_url, $expected_output, $expected_urls ) {
		$actual_output = $this->cache_manager->queue_purge_url( $queue_url );

		$this->assertEquals( $expected_output, $actual_output, 'Return value from `queue_purge_url` does not match.' );
		$this->assertEquals( $expected_urls, $this->cache_manager->get_queued_purge_urls(), 'List of queued purge urls do not match' );
	}
}
