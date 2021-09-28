<?php

namespace Automattic\VIP\Files;

use Yoast\WPTestUtils\WPIntegration\TestCase;

class Curl_Streamer_Test extends TestCase {
	const TEST_FILE_PATH = __DIR__ . '/../fixtures/files/stream.txt';

	private $curl_streamer;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once __DIR__ . '/../../files/class-curl-streamer.php';
	}

	public function set_up() {
		parent::set_up();

		$this->file_stream = fopen( self::TEST_FILE_PATH, 'r' );
		$this->curl_streamer = new Curl_Streamer( self::TEST_FILE_PATH );
		$this->curl_streamer->init();
	}

	public function tear_down() {
		$this->curl_streamer->deinit();
		fclose( $this->file_stream );

		$this->file_stream = null;
		$this->curl_streamer = null;

		parent::tear_down();
	}

	public function test__init() {
		$expected_transport = 'WP_Http_Curl';

		$wp_http = new \WP_Http();
		$actual_transport = $wp_http->_get_first_available_transport( [] );

		$this->assertEquals( $expected_transport, $actual_transport );
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
