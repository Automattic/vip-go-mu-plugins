<?php

use Yoast\PHPUnitPolyfills\Polyfills\ExpectPHPException;

class VIP_Go_Cache_Manager_Test extends WP_UnitTestCase {
	use ExpectPHPException;

	public $cache_manager;

	public function setUp(): void {
		parent::setUp();

		$this->cache_manager = WPCOM_VIP_Cache_Manager::instance();
		$this->cache_manager->init();
		$this->cache_manager->clear_queued_purge_urls();
	}

	public function get_data_for_valid_queue_purge_url_test() {
		return [
			// 1: input URL
			// 2: array of expected purge_urls list

			'normal_url'                     => [
				'http://example.com/path/to/files',
				[ 'http://example.com/path/to/files' ],
			],

			'strip_querystring'              => [
				'https://example.com/path/to/file?query',
				[ 'https://example.com/path/to/file' ],
			],

			'strip_fragment'                 => [
				'https://example.com/post#fragment',
				[ 'https://example.com/post' ],
			],

			'strip_querystring_and_fragment' => [
				'https://example.com/post?query#fragment',
				[ 'https://example.com/post' ],
			],
		];
	}

	public function get_data_for_invalid_queue_purge_url_test() {
		return [
			'invalid_scheme' => [
				'badscheme://example.com/path',
			],
		];
	}

	/**
	 * Tests valid URL inputs for `queue_purge_url`
	 *
	 * @dataProvider get_data_for_valid_queue_purge_url_test
	 */
	public function test__valid__queue_purge_url( $queue_url, $expected_urls ) {
		$actual_output = $this->cache_manager->queue_purge_url( $queue_url );

		$this->assertTrue( $actual_output, 'Return value from `queue_purge_url` does not match.' );
		$this->assertEquals( $expected_urls, $this->cache_manager->get_queued_purge_urls(), 'List of queued purge urls do not match' );
	}

	/**
	 * Tests invalid URL inputs for `queue_purge_url`
	 *
	 * They are all expected to return false, queue nothing, and throw a warning.
	 *
	 * @dataProvider get_data_for_invalid_queue_purge_url_test
	 */
	public function test__invalid__queue_purge_url( $queue_url ) {
		$this->expectWarning();

		$actual_output = $this->cache_manager->queue_purge_url( $queue_url );

		$this->assertFalse( $actual_output, 'Return value from `queue_purge_url` should be false.' );
		$this->assertEmpty( $this->cache_manager->get_queued_purge_urls(), 'List of queued purge urls should be empty' );
	}

	public function test__page_for_posts_post_purge_url() {
		$page_for_posts = $this->factory->post->create_and_get(
			[
				'post_type'  => 'page',
				'post_title' => 'blog-archive',
			]
		);
		update_option( 'page_for_posts', $page_for_posts->ID );
		$permalink = get_permalink( $page_for_posts );

		$post = (array) $this->factory->post->create_and_get( [ 'post_title' => 'test post' ] );

		$post['post_title'] = 'updated';

		wp_update_post( $post );

		$this->assertIsArray( $this->cache_manager->get_queued_purge_urls(), 'Queued purge urls variable is an array' );

		$this->assertContains( $permalink, $this->cache_manager->get_queued_purge_urls(), 'Queued purge urls should contain page_for_posts permlink' );
	}
}
