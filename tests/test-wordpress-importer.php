<?php

use function Automattic\Test\Utils\run_php;

class WordPress_Importer_Loader_Test extends WP_UnitTestCase {
	/**
	 * Import requests load the importer's bundled php-toolkit, which WXR_Parser_XML_Processor and
	 * WP_Import::import_start() depend on.
	 */
	public function test_import_requests_load_toolkit_for_parser_and_url_parsing() {
		$child = run_php( array( __DIR__ . '/fixtures/wordpress-importer/import-request.php', ABSPATH ) );

		$this->assertSame( 0, $child['exit'], $child['stderr'] . $child['stdout'] );
		$this->assertSame(
			array(
				'title' => 'Tom & Jerry',
				'host'  => 'example.com',
			),
			json_decode( $child['stdout'], true ),
			$child['stderr']
		);
	}
}
