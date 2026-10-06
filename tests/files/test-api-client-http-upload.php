<?php

namespace Automattic\VIP\Files;

use WP_UnitTestCase;

require_once __DIR__ . '/../../files/class-api-client.php';

/**
 * Exercise the real WordPress HTTP transport against a loopback receiver.
 */
class API_Client_HTTP_Upload_Test extends WP_UnitTestCase {
	/**
	 * Upload exact source bytes through the production cURL hook.
	 */
	public function test_upload_streams_source_to_http_receiver(): void {
		if ( ! extension_loaded( 'curl' ) || ! function_exists( 'proc_open' ) ) {
			$this->markTestSkipped( 'This integration requires cURL and proc_open.' );
		}

		$socket = stream_socket_server( 'tcp://127.0.0.1:0' );
		$this->assertIsResource( $socket );
		$address = stream_socket_get_name( $socket, false );
		fclose( $socket );
		$capture = wp_tempnam( 'http-upload-capture' );
		$log     = wp_tempnam( 'http-upload-server' );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Start the scoped local HTTP receiver.
		$process = proc_open(
			[ PHP_BINARY, '-S', $address, __DIR__ . '/../fixtures/files/http-upload-receiver.php' ],
			[
				0 => [ 'pipe', 'r' ],
				1 => [ 'file', $log, 'a' ],
				2 => [ 'file', $log, 'a' ],
			],
			$pipes,
			null,
			[ 'VIP_TEST_UPLOAD_CAPTURE' => $capture ]
		);
		$this->assertIsResource( $process );
		fclose( $pipes[0] );
		$curl_requests = 0;
		$curl_observer = static function () use ( &$curl_requests ) {
			++$curl_requests;
		};
		add_action( 'http_api_curl', $curl_observer );
		try {
			$ready = false;
			for ( $attempt = 0; $attempt < 100; ++$attempt ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- The server may still be starting.
				$connection = @stream_socket_client( 'tcp://' . $address, $errno, $error, 0.05 );
				if ( $connection ) {
					fclose( $connection );
					$ready = true;
					break;
				}
				usleep( 20000 );
			}
			$this->assertTrue( $ready, 'Loopback receiver did not start.' );

			$cache  = API_Cache::get_instance();
			$client = new API_Client( 'http://' . $address, 123, 'test-token', $cache );
			$source = __DIR__ . '/../fixtures/files/stream.txt';
			$target = '/wp-content/uploads/stream.txt';
			$this->assertSame( $target, $client->upload_file( $source, $target ) );
			$this->assertSame( 1, $curl_requests, 'WordPress must use its real cURL transport.' );
			// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- Local capture file.
			$received = json_decode( file_get_contents( $capture ), true );
			$this->assertSame( 'PUT', $received['method'] );
			$this->assertSame( $target, $received['path'] );
			$this->assertSame( "123456789\nend\n", base64_decode( $received['body'], true ) );
			$this->assertSame( strlen( "123456789\nend\n" ), $received['length'] );
		} finally {
			remove_action( 'http_api_curl', $curl_observer );
			proc_terminate( $process );
			proc_close( $process );
			API_Cache::get_instance()->clear_tmp_files();
			unlink( $capture ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink -- Local test fixture.
			unlink( $log ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink -- Local test fixture.
		}
	}
}
