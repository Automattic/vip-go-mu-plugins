<?php

class VIP_Feed_Cache_Test extends WP_UnitTestCase {
	/**
	 * Number of remote requests made by fetch_feed().
	 *
	 * @var int
	 */
	private $http_requests = 0;

	public function setUp(): void {
		parent::setUp();

		$this->http_requests = 0;

		// Mock remote request made in fetch_feed()
		add_filter( 'pre_http_request', function () {
			++$this->http_requests;

			return [
				'headers'     => [
					'content-type' => 'application/rss+xml; charset=utf-8',
				],
				'cookies'     => [],
				'filename'    => null,
				'response'    => [
					'code'    => 200,
					'message' => 'OK',
				],
				'status_code' => 200,
				'success'     => 1,
				'body'        => file_get_contents( __DIR__ . '/test.rss' ),
			];
		}, 10, 3 );
	}

	public function test__fetch_feed_cache_ignores_build_from_another_container() {
		$this->skip_if_simplepie_cannot_serve_fresh_cache();

		$url = 'https://www.example.com/other-container.rss';

		$this->assertNotWPError( fetch_feed( $url ) );

		$this->set_cached_build( $url, 1 );

		$this->assertNotWPError( fetch_feed( $url ) );

		$this->assertSame( 1, $this->http_requests, 'A build number mismatch should not invalidate the cache' );
	}

	public function test__load_normalizes_build_to_the_value_simplepie_expects() {
		$url = 'https://www.example.com/normalize.rss';

		// Also loads SimplePie and the VIP cache classes
		$this->assertNotWPError( fetch_feed( $url ) );

		$this->set_cached_build( $url, 1 );

		$cache = new VIP_Go_Feed_Cache_Transient( 'wp_transient', md5( $this->get_cache_filename( $url ) ), 'spc' );
		$data  = $cache->load();

		$this->assertIsArray( $data );
		$expected = class_exists( 'SimplePie\Misc' ) ? \SimplePie\Misc::get_build() : SIMPLEPIE_BUILD;
		$this->assertSame( $expected, $data['build'] );
	}

	/**
	 * Simulates the cache being written by a web container where the SimplePie files have a different mtime.
	 *
	 * @param string     $url
	 * @param int|string $build
	 */
	private function set_cached_build( $url, $build ) {
		$cache = new WP_Feed_Cache_Transient( 'wp_transient', md5( $this->get_cache_filename( $url ) ), 'spc' );
		$data  = $cache->load();
		$this->assertIsArray( $data );

		$data['build'] = $build;
		$cache->save( $data );
	}

	private function skip_if_simplepie_cannot_serve_fresh_cache() {
		require_once ABSPATH . WPINC . '/class-simplepie.php';

		// SimplePie 1.8.0 (WP 6.7-6.8) inverts its cache freshness check, so it re-requests unexpired feeds.
		if ( class_exists( 'SimplePie\SimplePie' ) && '1.8.0' === \SimplePie\SimplePie::VERSION ) {
			$this->markTestSkipped( 'SimplePie 1.8.0 never serves an unexpired feed from the cache' );
		}
	}

	/**
	 * This mocks get_cache_filename in class-simplepie.php for WP versions 5.9+
	 *
	 * @see https://core.trac.wordpress.org/changeset/52393
	 * @param string $url
	 * @return string
	 */
	private function get_cache_filename( $url ) {
		$options = [ CURLOPT_TIMEOUT => 3 ];
		$url    .= '#' . urlencode( var_export( $options, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		return $url;
	}
}
