<?php

namespace Automattic\VIP\Files;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use WP_Error;
use WP_UnitTestCase;
use Yoast\PHPUnitPolyfills\Polyfills\AssertionRenames;

require_once __DIR__ . '/../../files/class-api-client.php';

class API_Client_Test extends WP_UnitTestCase {
	use ArraySubsetAsserts;
	use AssertionRenames;

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

	private function init_api_client() {
		$this->api_client = new API_Client(
			'https://files.go-vip.co',
			123456,
			'super-sekret-token',
			API_Cache::get_instance()
		);
	}

	public function mock_http_response( $mocked_response ) {
		add_filter( 'pre_http_request', function( $response, $args, $url ) use ( $mocked_response ) {
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
		$method->setAccessible( true );
		return $method;
	}

	public static function get_property( $object, $name ) {
		$property = new \ReflectionProperty( get_class( $object ), $name );
		$property->setAccessible( true );
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
		$this->mock_http_response( [] ); // don't care about the response

		$this->api_client->get_file( '/wp-content/uploads/get/this/file.jpg' );

		$actual_http_request = reset( $this->http_requests );

		$this->assertEquals( 'https://files.go-vip.co/wp-content/uploads/get/this/file.jpg', $actual_http_request['url'], 'Incorrect API URL' );
		$this->assertEquals( 'GET', $actual_http_request['args']['method'], 'Incorrect HTTP method' );
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
	public function test__calculate_upload_timeout( $file_size, $expected_timeout ) {
		$calculate_upload_timeout_method = self::get_method( 'calculate_upload_timeout' );

		$actual_timeout = $calculate_upload_timeout_method->invokeArgs( $this->api_client, [
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
			'body'     => '{"filename":"/wp-content/uploads/file.txt"}',
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

		// Should be cleared out of stats cache
		$this->assertFalse( $cached_stats, 'Expected false from the file stat cache after upload' );
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
					__( 'Uploads are temporarily disabled due to Files service maintenance. Please try again later.' )
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
}
