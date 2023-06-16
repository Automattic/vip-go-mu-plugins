<?php

namespace Automattic\VIP\Search;

use WP_UnitTestCase;

require_once __DIR__ . '/../../search/search.php';
require_once __DIR__ . '/../../search/includes/classes/class-versioning.php';
require_once __DIR__ . '/../../search/elasticpress/elasticpress.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class Search_Dev_Tools_Test extends WP_UnitTestCase {
	/**
	 * Make tests run in separate processes since we're testing state
	 * related to plugin init, including various constants.
	 */

	public function setUp(): void {
		$this->search_instance = new \Automattic\VIP\Search\Search();

		require_once __DIR__ . '/../../search/search-dev-tools/search-dev-tools.php';
	}

	public function data_provider_endpoint_urls() {
		return [
			[
				'input'    => 'http://vip-search:9200/vip-123-post-1/_search',
				'expected' => true,
			],
			[
				'input'    => 'http://vip-search:9200/vip-123-post-1,vip-123-post-post-2-v1/_search',
				'expected' => true,
			],
			[
				'input'    => 'http://vip-search:9200/vip-123-post-v1,vip-3456-post-2-v1/_search',
				'expected' => new \WP_Error( 'rest_invalid_param', sprintf( '%s is not a valid allowed URL', 'url' ) ),
			],
			[
				'input'    => 'http://vip-search:9200/vip-2345-post-v1/_search',
				'expected' => new \WP_Error( 'rest_invalid_param', sprintf( '%s is not a valid allowed URL', 'url' ) ),
			],
			[
				'input'    => 'http://vip-search:9200/restricted/_endpoint',
				'expected' => new \WP_Error( 'rest_invalid_param', sprintf( '%s is not a valid allowed URL', 'url' ) ),
			],
			[
				'input'    => 'notavalidurl',
				'expected' => new \WP_Error( 'rest_invalid_param', sprintf( '%s is not a valid allowed URL', 'url' ) ),
			],
		];
	}

	/**
	 * @dataProvider data_provider_endpoint_urls
	 */
	public function test__url_validation( $input, $expected ) {
		$val = \Automattic\VIP\Search\Dev_Tools\rest_endpoint_url_validate_callback( $input, new \WP_Rest_Request( 'POST' ), 'url' );
		$this->assertEquals( $val, $expected, 'URL validation failed' );
	}
}
