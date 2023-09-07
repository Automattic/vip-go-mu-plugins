<?php

class VIP_Feed_Cache_Test extends WP_UnitTestCase {
	public function test__fetch_feed_build() {
		// Mock remote request made in fetch_feed()
		add_filter( 'pre_http_request', function () {
			return [
				'headers'     => [
					'content-type' => 'application/rss+xml; charset=utf-8',
				],
				'cookies'     => [],
				'filename'    => null,
				'response'    => 200,
				'status_code' => 200,
				'success'     => 1,
				'body'        => file_get_contents( __DIR__ . '/test.rss' ),
			];
		}, 10, 3 );

		$url   = 'https://www.example.com/test.rss';
		$feed  = fetch_feed( $url );
		$build = $feed->data['build'];
		$this->assertIsNumeric( $build );

		$transient = get_transient( 'feed_' . md5( $this->get_cache_filename( $url ) ) );
		$this->assertEquals( $transient['build'], $build );
	}

	/**
	 * This mocks get_cache_filename in class-simplepie.php for WP versions 5.9+
	 *
	 * @see https://core.trac.wordpress.org/changeset/52393
	 * @param $url string
	 * @return $url string
	 */
	private function get_cache_filename( $url ) {
		global $wp_version;
		if ( $wp_version >= '5.9' ) {
			$options                    = [];
			$options[ CURLOPT_TIMEOUT ] = 3;
			if ( ! empty( $options ) ) {
				ksort( $options );
				$url .= '#' . urlencode( var_export( $options, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
			}
		}
		return $url;
	}
}
