<?php

namespace Automattic\VIP\Files;

use WP_UnitTestCase;

require_once __DIR__ . '/../../files/class-curl-streamer.php';

class Curl_Streamer_Test extends WP_UnitTestCase {
	const TEST_FILE_PATH = __DIR__ . '/../fixtures/files/stream.txt';

	private $file_stream;
	private $curl_streamer;

	public function setUp(): void {
		parent::setUp();

		$this->file_stream   = fopen( self::TEST_FILE_PATH, 'r' );          // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$this->curl_streamer = new Curl_Streamer( self::TEST_FILE_PATH );   // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_streamer -- FP
		$this->curl_streamer->init();
	}

	public function tearDown(): void {
		$this->curl_streamer->deinit();
		fclose( $this->file_stream );

		$this->file_stream   = null;
		$this->curl_streamer = null;

		parent::tearDown();
	}

	public function test__init_upload() {
		$this->markTestSkipped( 'Cannot get `curl` opts, making this hard to test. We can look into using a test webserver in the future.' );
	}

	public function test__handle_upload() {
		$read_pass_1 = $this->curl_streamer->handle_upload( null, $this->file_stream, 10 );

		$this->assertEquals( "123456789\n", $read_pass_1, 'Incorrect data for 1st pass' );

		$read_pass_2 = $this->curl_streamer->handle_upload( null, $this->file_stream, 10 );

		$this->assertEquals( "end\n", $read_pass_2, 'Incorrect data for 2nd pass' );

		$read_pass_3 = $this->curl_streamer->handle_upload( null, $this->file_stream, 10 );

		$this->assertEquals( '', $read_pass_3, 'Incorrect data for 3rd pass' );
	}
}
