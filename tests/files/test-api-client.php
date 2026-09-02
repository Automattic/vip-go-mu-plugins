<?php

namespace Automattic\VIP\Files;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use WP_Error;
use WP_UnitTestCase;

require_once __DIR__ . '/../../files/class-api-client.php';

class API_Client_Test extends WP_UnitTestCase {
	use ArraySubsetAsserts;

	/** @var API_Client|null */
	private $api_client;
	private $http_requests;

	public function setUp(): void {
		parent::setUp();

		$this->init_api_client();

		$this->http_requests = [];
	}

	public function tearDown(): void {
		$this->api_client    = null;
		$this->http_requests = null;

		remove_all_filters( 'pre_http_request' );

		API_Cache::get_instance()->clear_tmp_files();

		parent::tearDown();
	}

	protected function init_api_client() {
		$this->api_client = new API_Client(
			'https://files.go-vip.co',
			123456,
			'super-sekret-token',
			API_Cache::get_instance()
		);
	}

	public function mock_is_file_response( $mocked_response ): void {
		add_filter( 'pre_http_request', function ( $response, $args ) use ( $mocked_response ) {
			if ( isset( $args['headers']['X-Action'] ) && 'file_exists' === $args['headers']['X-Action'] ) {
				return $mocked_response;
			}

			return $response;
		}, 1000, 2 );
	}

	public function mock_http_response( $mocked_response ) {
		add_filter( 'pre_http_request', function ( $response, $args, $url ) use ( $mocked_response ) {
			$this->http_requests[] = [
				'url'  => $url,
				'args' => $args,
			];

			if ( $args['stream'] &&
				! is_wp_error( $mocked_response ) &&
				isset( $mocked_response['response'] ) &&
				200 === $mocked_response['response']['code'] ) {
				// Handle streamed requests
				// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
				file_put_contents( $args['filename'], $mocked_response['body'] );
			}

			return $mocked_response;
		}, 10, 3 );
	}

	/**
	 * Helper function for accessing protected methods.
	 */
	protected static function get_method( $name ) {
		$class  = new \ReflectionClass( __NAMESPACE__ . '\API_Client' );
		$method = $class->getMethod( $name );
		return $method;
	}

	public static function get_property( $object, $name ) {
		$property = new \ReflectionProperty( get_class( $object ), $name );
		return $property;
	}

	public function get_test_data__is_valid_path() {
		return [
			'other path'                                   => [
				'/wp-includes/js/jquery.js',
				false,
			],
			'wp-content other path'                        => [
				'/wp-content/themes/style.css',
				false,
			],
			'wp-content uploads path (with leading slash)' => [
				'/wp-content/uploads/file.jpg',
				true,
			],
			'wp-content uploads path (without leading slash)' => [
				'wp-content/uploads/file.jpg',
				true,
			],
		];
	}

	/**
	 * @dataProvider get_test_data__is_valid_path
	 */
	public function test__is_valid_path( $path, $expected ) {
		$is_valid_path_method = self::get_method( 'is_valid_path' );

		$actual = $is_valid_path_method->invokeArgs( $this->api_client, [
			$path,
		] );

		$this->assertEquals( $expected, $actual );
	}

	public function test__call_api_invalid_path() {
		$expected_error_code = 'invalid-path';
		$this->mock_http_response( [] ); // don't care about the response

		$call_api_method = self::get_method( 'call_api' );

		$actual_response = $call_api_method->invokeArgs( $this->api_client, [
			'/path/to/image.jpg',
			'GET',
		] );

		$this->assertWPError( $actual_response, 'Expected WP_Error object to be returned' );

		$actual_error_code = $actual_response->get_error_code();
		$this->assertEquals( $expected_error_code, $actual_error_code, 'Invalid error code returned' );
	}

	public function test__call_api() {
		$expected_response = [ 'foo' => 'bar' ];
		$this->mock_http_response( $expected_response );

		$call_api_method = self::get_method( 'call_api' );

		$actual_response = $call_api_method->invokeArgs( $this->api_client, [
			'/wp-content/uploads/path/to/image.jpg',
			'POST',
			[
				'headers' => [
					'Another-Header' => 'Yay!',
				],
			],
		] );

		$this->assertEquals( $expected_response, $actual_response, 'Did not get API response returned' );

		$actual_http_request = reset( $this->http_requests );

		$this->assertEquals( 'https://files.go-vip.co/wp-content/uploads/path/to/image.jpg', $actual_http_request['url'], 'Incorrect API URL' );
		$this->assertEquals( 'POST', $actual_http_request['args']['method'], 'Incorrect HTTP method' );
		$this->assertEquals( 10, $actual_http_request['args']['timeout'], 'Incorrect timeout' );

		$this->assertEquals( [
			'X-Client-Site-ID' => 123456,
			'X-Access-Token'   => 'super-sekret-token',
			'Another-Header'   => 'Yay!',
		], $actual_http_request['args']['headers'], 'Incorrect headers' );
	}

	public function test__call_api__user_agent() {
		$original_request_uri   = $_SERVER['REQUEST_URI']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- test context; this is safe
		$_SERVER['REQUEST_URI'] = ' /path?query';

		// Re-initialize so re-generate UA string
		$this->init_api_client();

		$expected_response = [ 'foo' => 'bar' ];
		$this->mock_http_response( $expected_response );

		$call_api_method = self::get_method( 'call_api' );

		$call_api_method->invokeArgs( $this->api_client, [
			'/wp-content/uploads/path/to/image.jpg',
			'POST',
		] );

		$actual_http_request = reset( $this->http_requests );

		$this->assertMatchesRegularExpression( '/^WPVIP\/[^\/]+\/Files; \/path\?query$/', $actual_http_request['args']['user-agent'], 'User-Agent not correctly set' );

		$_SERVER['REQUEST_URI'] = $original_request_uri;
	}

	public function get_test_data__get_api_url() {
		return [
			'path_with_leadingslash'    => [
				'/wp-content/uploads/path/to/image.jpg',
				'https://files.go-vip.co/wp-content/uploads/path/to/image.jpg',
			],
			'path_without_leadingslash' => [
				'wp-content/uploads/another/path/to/image.jpg',
				'https://files.go-vip.co/wp-content/uploads/another/path/to/image.jpg',
			],
		];
	}

	/**
	 * @dataProvider get_test_data__get_api_url
	 */
	public function test__get_api_url( $path, $expected_url ) {
		$get_api_url_method = self::get_method( 'get_api_url' );

		$actual_url = $get_api_url_method->invokeArgs( $this->api_client, [
			$path,
		] );

		$this->assertEquals( $expected_url, $actual_url );
	}

	public function get_test_data__is_file() {
		return [
			'WP_Error'     => [
				new WP_Error( 'oh-no', 'Oh no!' ),
				new WP_Error( 'oh-no', 'Oh no!' ),
			],
			'other-status' => [
				[
					'response' => [
						'code' => 401,
					],
				],
				new WP_Error( 'is_file-failed', 'Failed to check if file `/wp-content/uploads/file.jpg` exists (response code: 401)' ),
			],
			'invalid-file' => [
				[
					'response' => [
						'code' => 404,
					],
				],
				false,
			],
			'valid-file'   => [
				[
					'response' => [
						'code' => 200,
					],
				],
				true,
			],
		];
	}

	/**
	 * @dataProvider get_test_data__is_file
	 */
	public function test__is_file( $mocked_response, $expected_result ) {
		$this->mock_http_response( $mocked_response );

		$actual_result = $this->api_client->is_file( '/wp-content/uploads/file.jpg' );
		$this->assertEquals( $expected_result, $actual_result );
	}

	public function test__is_file__validate_request() {
		$this->mock_http_response( [] ); // don't care about the response

		$this->api_client->is_file( '/wp-content/uploads/file.jpg' );

		$actual_http_request = reset( $this->http_requests );

		$this->assertEquals( 'https://files.go-vip.co/wp-content/uploads/file.jpg', $actual_http_request['url'], 'Incorrect API URL' );
		$this->assertEquals( 'GET', $actual_http_request['args']['method'], 'Incorrect HTTP method' );
		$this->assertArraySubset( [
			'X-Action' => 'file_exists',
		], $actual_http_request['args']['headers'], 'Missing `X-Action` header' );
	}

	public function test__is_file__caches_missing_file_for_the_request() {
		$this->mock_http_response( [
			'response' => [
				'code' => 404,
			],
		] );

		$path = '/wp-content/uploads/missing-file.jpg';

		$this->assertFalse( $this->api_client->is_file( $path ) );
		$this->assertFalse( $this->api_client->is_file( $path ) );
		$this->assertCount( 1, $this->http_requests, 'A repeated missing-file probe should not make another HTTP request.' );
	}

	public function get_test_data__delete_file() {
		return [
			'WP_Error'         => [
				new WP_Error( 'oh-no', 'Oh no!' ),
				new WP_Error( 'oh-no', 'Oh no!' ),
			],

			'delete-failed'    => [
				[
					'response' => [
						'code' => 500,
					],
				],
				new WP_Error( 'delete_file-failed', 'Failed to delete file `/wp-content/uploads/file.jpg` (response code: 500)' ),
			],

			'delete-succeeded' => [
				[
					'response' => [
						'code' => 200,
					],
				],
				true,
			],
		];
	}

	/**
	 * @dataProvider get_test_data__delete_file
	 */
	public function test__delete_file( $mocked_response, $expected_result ) {
		$this->mock_http_response( $mocked_response );

		$actual_result = $this->api_client->delete_file( '/wp-content/uploads/file.jpg' );
		$this->assertEquals( $expected_result, $actual_result );
	}

	public function test__delete_file__validate_request() {
		$this->mock_http_response( [] ); // don't care about the response

		$this->api_client->delete_file( '/wp-content/uploads/delete/this/file.jpg' );

		$actual_http_request = reset( $this->http_requests );

		$this->assertEquals( 'https://files.go-vip.co/wp-content/uploads/delete/this/file.jpg', $actual_http_request['url'], 'Incorrect API URL' );
		$this->assertEquals( 'DELETE', $actual_http_request['args']['method'], 'Incorrect HTTP method' );
	}

	public function get_test_data__get_file() {
		return [
			'WP_Error'            => [
				new WP_Error( 'oh-no', 'Oh no!' ),
				new WP_Error( 'oh-no', 'Oh no!' ),
			],

			'file-does-not-exist' => [
				[
					'response' => [
						'code' => 404,
					],
					'body'     => null,
				],
				new WP_Error( 'file-not-found', 'The requested file `/wp-content/uploads/get_file.jpg` does not exist (response code: 404)' ),
			],

			'other-bad-status'    => [
				[
					'response' => [
						'code' => 500,
					],
					'body'     => null,
				],
				new WP_Error( 'get_file-failed', 'Failed to get file `/wp-content/uploads/get_file.jpg` (response code: 500)' ),
			],

			'file-exists'         => [
				[
					'response' => [
						'code' => 200,
					],
					'body'     => 'these-are-my-file-contents',
				],
				'these-are-my-file-contents',
			],
		];
	}

	/**
	 * @dataProvider get_test_data__get_file
	 */
	public function test__get_file( $mocked_response, $expected_result ) {
		$this->mock_is_file_response( [
			'response' => [
				'code' => 200,
			],
			'body'     => wp_json_encode( [
				'size' => 12345,
			] ),
		] );
		$this->mock_http_response( $mocked_response );

		$file = $this->api_client->get_file( '/wp-content/uploads/get_file.jpg' );

		if ( is_wp_error( $file ) ) {
			$actual_result = $file;
		} else {
			// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
			$actual_result = file_get_contents( $file );
		}

		$this->assertEquals( $expected_result, $actual_result );
	}

	public function test__get_file__validate_request() {
		$this->mock_is_file_response( [
			'response' => [
				'code' => 200,
			],
			'body'     => wp_json_encode( [
				'size' => 12345,
			] ),
		] );

		$this->mock_http_response( [] ); // don't care about the response

		$this->api_client->get_file( '/wp-content/uploads/get/this/file.jpg' );

		$actual_http_request = reset( $this->http_requests );

		$this->assertEquals( 'https://files.go-vip.co/wp-content/uploads/get/this/file.jpg', $actual_http_request['url'], 'Incorrect API URL' );
		$this->assertEquals( 'GET', $actual_http_request['args']['method'], 'Incorrect HTTP method' );
	}

	public function test__get_file_content__returns_get_file_error() {
		$this->mock_http_response( [
			'response' => [
				'code' => 404,
			],
		] );

		$result = $this->api_client->get_file_content( '/wp-content/uploads/missing-file.txt' );

		$this->assertWPError( $result );
		$this->assertSame( 'file-not-found', $result->get_error_code() );
	}

	public function get_test_data__upload_timeout() {
		return [
			'empty-file' => [
				0,
				10,
			],

			'1kb'        => [
				1024,
				10,
			],

			'500kb'      => [
				512000,
				11,
			],

			'1GB'        => [
				1073741824,
				2107,
			],
		];
	}

	/**
	 * @dataProvider get_test_data__upload_timeout
	 */
	public function test__calculate_transfer_timeout( $file_size, $expected_timeout ) {
		$calculate_transfer_timeout_method = self::get_method( 'calculate_transfer_timeout' );

		$actual_timeout = $calculate_transfer_timeout_method->invokeArgs( $this->api_client, [
			$file_size,
		] );

		$this->assertEquals( $expected_timeout, $actual_timeout );
	}

	public function test__upload_file__invalid_file() {
		$file_path           = '/path/to/invalid/file.txt';
		$upload_path         = '/wp-content/uploads/file.txt';
		$expected_error_code = 'upload_file-failed-invalid_path';

		$actual_result = $this->api_client->upload_file( $file_path, $upload_path );

		$this->assertWPError( $actual_result, 'WP_Error not returned' );

		$actual_error_code = $actual_result->get_error_code();
		$this->assertEquals( $expected_error_code, $actual_error_code, 'Unexpected error code' );
	}

	public function test__upload_file__validate_request() {
		$this->mock_http_response( [] ); // don't care about the response

		$file_path   = __DIR__ . '/../fixtures/files/upload.jpg';
		$upload_path = '/wp-content/uploads/file.txt';

		$this->api_client->upload_file( $file_path, $upload_path );

		$actual_http_request = reset( $this->http_requests );

		$this->assertEquals( 'https://files.go-vip.co/wp-content/uploads/file.txt', $actual_http_request['url'], 'Incorrect API URL' );

		$this->assertArraySubset( [
			'Content-Type'   => 'image/jpeg',
			'Content-Length' => 13,
			'Connection'     => 'Keep-Alive',
		], $actual_http_request['args']['headers'], 'Missing `Content-*` headers' );

		$this->assertEquals( 10, $actual_http_request['args']['timeout'], 'Incorrect timeout' );
	}

	public function get_test_data__upload_file__errors() {
		return [
			'return-WP_Error' => [
				new WP_Error( 'oh-no', 'Oh no!' ),
				'oh-no',
			],

			'status-204'      => [
				[
					'response' => [
						'code' => 204,
					],
				],
				'upload_file-failed-quota_reached',
			],

			'status-non-200'  => [
				[
					'response' => [
						'code' => 400,
					],
				],
				'upload_file-failed',
			],

			'invalid-json'    => [
				[
					'response' => [
						'code' => 200,
					],
					'body'     => '{{{',    // phpcs:ignore WordPressVIPMinimum.Security.Mustache.OutputNotation -- false positive
				],
				'upload_file-failed-json_decode-error',
			],
		];
	}

	/**
	 * @dataProvider get_test_data__upload_file__errors
	 */
	public function test__upload_file__error( $mocked_response, $expected_error_code ) {
		$this->mock_http_response( $mocked_response );

		$file_path   = __DIR__ . '/../fixtures/files/upload.jpg';
		$upload_path = '/wp-content/uploads/file.txt';

		$actual_result = $this->api_client->upload_file( $file_path, $upload_path );

		$this->assertWPError( $actual_result, 'Not WP_Error object' );

		$actual_error_code = $actual_result->get_error_code();
		$this->assertEquals( $expected_error_code, $actual_error_code, 'Incorrect error code' );
	}

	public function test__upload_file__success() {
		$this->mock_http_response( [
			'response' => [
				'code' => 200,
			],
			'body'     => '{"filename":"/wp-content/uploads/file.txt","mtime":1234567890,"size":13}',
		] );

		$file_path   = __DIR__ . '/../fixtures/files/upload.jpg';
		$upload_path = '/wp-content/uploads/file.txt';

		$cache = self::get_property( $this->api_client, 'cache' )->getValue( $this->api_client );

		// To test that upload_file() properly clears the cache, we'll set some data to start
		$cache->cache_file_stats( 'wp-content/uploads/file.txt', array(
			'size'  => 0,
			'mtime' => 12345,
		) );

		$actual_result = $this->api_client->upload_file( $file_path, $upload_path );

		$this->assertEquals( $upload_path, $actual_result, 'Invalid result from upload_file()' );

		$cached_stats = $cache->get_file_stats( 'wp-content/uploads/file.txt' );

		$this->assertSame( [
			'mtime' => 1234567890,
			'size'  => 13,
		], $cached_stats, 'Expected the upload response metadata in the file stat cache.' );

		$cached_file = $cache->get_file( 'wp-content/uploads/file.txt' );
		$this->assertNotFalse( $cached_file, 'Expected paths with and without a leading slash to share one cache entry.' );
		$this->assertSame( 13, filesize( $cached_file ) );
	}

	public function get_test_data__get_unique_filename() {
		return [
			'new-unique-filename'   => [
				[
					'response' => [
						'code' => 200,
					],
					'body'     => '{"filename":"uniquename.jpg"}',
				],
				'uniquename.jpg',
			],
			'invalid-type'          => [
				[
					'response' => [
						'code' => 406,
					],
				],
				new WP_Error(
					'invalid-file-type',
					'Failed to generate new unique file name `/wp-content/uploads/file.jpg` (response code: 406)'
				),
			],
			'WP_Error'              => [
				new WP_Error( 'oh-no', 'Oh no!' ),
				new WP_Error( 'oh-no', 'Oh no!' ),
			],
			'file-service-readonly' => [
				[
					'response' => [
						'code' => 503,
					],
				],
				new WP_Error(
					'file-service-readonly',
					__( 'Uploads are temporarily disabled due to platform maintenance. Please try again in a few minutes.' )
				),
			],
		];
	}

	/**
	 * @dataProvider get_test_data__get_unique_filename
	 */
	public function test__get_unique_filename( $mocked_response, $expected_result ) {
		$this->mock_http_response( $mocked_response );

		$actual_result = $this->api_client->get_unique_filename( '/wp-content/uploads/file.jpg' );

		$this->assertEquals( $expected_result, $actual_result );
	}

	public function test__get_unique_filename__validate_request() {
		$this->mock_http_response( [] ); // don't care about the response

		$this->api_client->get_unique_filename( '/wp-content/uploads/file.jpg' );

		$actual_http_request = reset( $this->http_requests );

		$this->assertEquals( 'https://files.go-vip.co/wp-content/uploads/file.jpg', $actual_http_request['url'], 'Incorrect API URL' );
		$this->assertEquals( 'GET', $actual_http_request['args']['method'], 'Incorrect HTTP method' );
		$this->assertArraySubset( [
			'X-Action' => 'unique_filename',
		], $actual_http_request['args']['headers'], 'Missing `X-Action` header' );
	}

	/**
	 * @ticket GH-3174
	 */
	public function test_upload_unknown_mime(): void {
		$this->mock_http_response( [
			'response' => [
				'code' => 200,
			],
			'body'     => '{"filename":"/wp-content/uploads/file.txt"}',
		] );

		$file_path   = __DIR__ . '/../fixtures/files/upload.some-unmatched-extension';
		$upload_path = '/wp-content/uploads/file.some-unmatched-extension';

		$result = $this->api_client->upload_file( $file_path, $upload_path );
		self::assertNotInstanceOf( WP_Error::class, $result );
	}

	/**
	 * Records every outbound request so a test can assert how many were made.
	 *
	 * @return array<int,string> Filled with the X-Action of each request, in order.
	 */
	private function &record_requests( $responder ) {
		$actions = [];
		add_filter( 'pre_http_request', function ( $response, $args, $url ) use ( &$actions, $responder ) {
			$action    = $args['headers']['X-Action'] ?? 'download';
			$actions[] = $action;
			return $responder( $action, $args, $url );
		}, 10, 3 );

		return $actions;
	}

	private function download_responder( $code = 200, $body = 'payload', $last_modified = 'Mon, 25 Aug 2026 12:00:00 GMT' ) {
		return function ( $action, $args ) use ( $code, $body, $last_modified ) {
			if ( 200 === $code && ! empty( $args['stream'] ) ) {
				// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
				file_put_contents( $args['filename'], $body );
			}

			return [
				'response' => [ 'code' => $code ],
				'headers'  => [ 'last-modified' => $last_modified ],
				'body'     => 200 === $code ? '' : '',
			];
		};
	}

	public function test__get_file__uses_a_single_request() {
		$actions = &$this->record_requests( $this->download_responder() );

		$file = $this->api_client->get_file( '/wp-content/uploads/single.txt' );

		self::assertNotInstanceOf( WP_Error::class, $file );
		self::assertSame( [ 'download' ], $actions, 'A cold read should download once and ask nothing else.' );
	}

	public function test__get_file__caches_metadata_from_the_download() {
		$actions = &$this->record_requests( $this->download_responder( 200, 'payload' ) );

		$this->api_client->get_file( '/wp-content/uploads/meta.txt' );

		$info   = [];
		$exists = $this->api_client->is_file( '/wp-content/uploads/meta.txt', $info );

		self::assertTrue( $exists );
		self::assertSame( [ 'download' ], $actions, 'Metadata should come from the download, not a second request.' );
		self::assertSame( strlen( 'payload' ), $info['size'] );
		self::assertSame( strtotime( 'Mon, 25 Aug 2026 12:00:00 GMT' ), $info['mtime'] );
	}

	public function test__get_file__caches_a_missing_file() {
		$actions = &$this->record_requests( $this->download_responder( 404 ) );

		$result = $this->api_client->get_file( '/wp-content/uploads/gone.txt' );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'file-not-found', $result->get_error_code() );

		$info = [];
		self::assertFalse( $this->api_client->is_file( '/wp-content/uploads/gone.txt', $info ) );
		self::assertFalse( $this->api_client->is_file( '/wp-content/uploads/gone.txt', $info ) );

		self::assertSame( [ 'download' ], $actions, 'A known-missing file should not be asked about again.' );
	}

	public function test__is_file__asks_once_for_repeated_questions() {
		$actions = &$this->record_requests( function () {
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [
					'size'  => 19,
					'mtime' => 1700000000,
				] ),
			];
		} );

		// Every one of the four PHP stat functions lands on this method.
		$info = [];
		for ( $i = 0; $i < 4; $i++ ) {
			self::assertTrue( $this->api_client->is_file( '/wp-content/uploads/four.txt', $info ) );
		}

		self::assertSame( [ 'file_exists' ], $actions, 'Four questions about one file should cost one request.' );
		self::assertSame( 19, $info['size'] );
		self::assertSame( 1700000000, $info['mtime'] );
	}

	public function test__upload_file__releases_the_upload_body_callback_when_the_transport_throws() {
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_tempnam -- already scoped to get_temp_dir().
		$local_path = tempnam( get_temp_dir(), 'vip-upload-' );
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
		file_put_contents( $local_path, 'payload' );

		// phpcs:ignore WordPressVIPMinimum.Hooks.AlwaysReturnInFilter.MissingReturnStatement -- throwing is the condition under test.
		add_filter( 'pre_http_request', function () {
			throw new \RuntimeException( 'transport exploded' );
		}, 10, 3 );

		$threw = false;
		try {
			$this->api_client->upload_file( $local_path, '/wp-content/uploads/boom.txt' );
		} catch ( \RuntimeException $e ) {
			$threw = true;
		}

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
		unlink( $local_path );

		self::assertTrue( $threw, 'The transport exception should propagate.' );
		self::assertFalse(
			has_action( 'http_api_curl' ),
			'Curl_Streamer must not outlive the request; a leaked callback would stream this file into later outbound requests.'
		);
	}

	public function test__get_file__applies_a_stall_guard_and_releases_it() {
		$applied = [];
		add_action( 'http_api_curl', function () use ( &$applied ) {
			$applied[] = true;
		} );

		$this->mock_http_response( [
			'response' => [ 'code' => 200 ],
			'body'     => 'payload',
		] );

		$this->api_client->get_file( '/wp-content/uploads/guarded.txt' );

		// The stall guard is attached for this request only.
		self::assertFalse(
			$this->has_non_test_curl_action(),
			'The download stall guard must not stay registered after the request.'
		);
	}

	public function test__get_file__releases_the_stall_guard_when_the_transport_throws() {
		// phpcs:ignore WordPressVIPMinimum.Hooks.AlwaysReturnInFilter.MissingReturnStatement -- throwing is the condition under test.
		add_filter( 'pre_http_request', function () {
			throw new \RuntimeException( 'transport exploded' );
		}, 10, 3 );

		$threw = false;
		try {
			$this->api_client->get_file( '/wp-content/uploads/boom.txt' );
		} catch ( \RuntimeException $e ) {
			$threw = true;
		}

		self::assertTrue( $threw, 'The transport exception should propagate.' );
		self::assertFalse(
			$this->has_non_test_curl_action(),
			'A thrown request must not leave the stall guard applying to later requests.'
		);
	}

	/**
	 * True when something other than this test class is hooked to http_api_curl.
	 */
	private function has_non_test_curl_action() {
		global $wp_filter;

		if ( empty( $wp_filter['http_api_curl'] ) ) {
			return false;
		}

		foreach ( $wp_filter['http_api_curl']->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( $callback['function'] instanceof \Closure ) {
					$bound = ( new \ReflectionFunction( $callback['function'] ) )->getClosureThis();
					if ( $bound instanceof self ) {
						continue; // Registered by the test itself.
					}
				}
				return true;
			}
		}

		return false;
	}

	public function test__get_file__scales_the_timeout_from_a_known_size() {
		$timeouts = [];
		add_filter( 'pre_http_request', function ( $response, $args ) use ( &$timeouts ) {
			if ( empty( $args['headers']['X-Action'] ) ) {
				$timeouts[] = $args['timeout'];
				// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
				file_put_contents( $args['filename'], 'payload' );
				return [
					'response' => [ 'code' => 200 ],
					'body'     => '',
				];
			}

			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( [
					'size'  => 10 * MB_IN_BYTES,
					'mtime' => 1700000000,
				] ),
			];
		}, 10, 2 );

		// Pin the upload limit so the expected values do not depend on php.ini.
		// It has to exceed the known file size below for the ordering to mean anything.
		add_filter( 'upload_size_limit', function () {
			return 4 * GB_IN_BYTES;
		} );

		// Cold: nothing known about the file, so allow a full-size transfer.
		$this->api_client->get_file( '/wp-content/uploads/cold.txt' );

		// Warm: a stat supplied the size, so the timeout scales to it.
		$info = [];
		$this->api_client->is_file( '/wp-content/uploads/warm.txt', $info );
		$this->api_client->get_file( '/wp-content/uploads/warm.txt' );

		$scale = self::get_method( 'calculate_transfer_timeout' );
		$cold  = $scale->invokeArgs( $this->api_client, [ 4 * GB_IN_BYTES ] );
		$warm  = $scale->invokeArgs( $this->api_client, [ 10 * MB_IN_BYTES ] );

		remove_all_filters( 'upload_size_limit' );

		self::assertSame(
			$cold,
			$timeouts[0],
			'A cold download should allow what an upload of the largest permitted file gets.'
		);
		self::assertSame(
			$warm,
			$timeouts[1],
			'A download with a known size should scale to that size.'
		);
		self::assertLessThan(
			$cold,
			$warm,
			'A known 10 MiB file should get less time than an unknown file under a 4 GiB limit.'
		);
	}

	public function test__get_file__sizes_an_unknown_download_from_the_upload_limit() {
		$timeouts = [];
		add_filter( 'pre_http_request', function ( $response, $args ) use ( &$timeouts ) {
			$timeouts[] = $args['timeout'];
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
			file_put_contents( $args['filename'], 'payload' );
			return [
				'response' => [ 'code' => 200 ],
				'body'     => '',
			];
		}, 10, 2 );

		add_filter( 'upload_size_limit', function () {
			return 64 * MB_IN_BYTES;
		} );

		$this->api_client->get_file( '/wp-content/uploads/unknown.txt' );

		remove_all_filters( 'upload_size_limit' );

		$scale = self::get_method( 'calculate_transfer_timeout' );
		self::assertSame(
			$scale->invokeArgs( $this->api_client, [ 64 * MB_IN_BYTES ] ),
			$timeouts[0],
			'An unknown-size download should follow the upload_size_limit filter.'
		);
	}
}
