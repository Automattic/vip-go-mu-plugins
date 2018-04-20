<?php

namespace Automattic\VIP\Files;

use WP_Error;

class API_Client_Test extends \WP_UnitTestCase {
	private $http_requests;

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once( __DIR__ . '/../../files/class-api-client.php' );
	}

	public function setUp() {
		parent::setUp();

		$this->api_client = new API_Client( 'https://files.go-vip.co', 123456, 'super-sekret-token' );

		$this->http_requests = [];
	}

	public function tearDown() {
		$this->api_client = null;
		$this->http_requests = null;

		remove_all_filters( 'pre_http_request' );

		parent::tearDown();
	}

	public function mock_http_response( $mocked_response ) {
		add_filter( 'pre_http_request', function( $response, $args, $url ) use ( $mocked_response ) {
			$this->http_requests[] = [
				'url' => $url,
				'args' => $args,
			];

			return $mocked_response;
		}, 10, 3 );
	}

	public function get_test_data__get_api_url() {
		return [
			'path_with_leadingslash' => [
				'/path/to/image.jpg',
				'https://files.go-vip.co/path/to/image.jpg',
			],
			'path_without_leadingslash' => [
				'another/path/to/image.jpg',
				'https://files.go-vip.co/another/path/to/image.jpg',
			],
		];
	}

	/**
	 * @dataProvider get_test_data__get_api_url
	 */
	public function test__get_api_url( $path, $expected_url ) {
		$actual_url = $this->api_client->get_api_url( $path );

		$this->assertEquals( $expected_url, $actual_url );
	}

	public function get_test_data__is_file() {
		return [
			'WP_Error' => [
				new WP_Error( 'oh-no', 'Oh no!' ),
				new WP_Error( 'oh-no', 'Oh no!' ),
			],
			'invalid-file' => [
				[
					'response' => [
						'code' => 404,
					]
				],
				false,
			],
			'valid-file' => [
				[
					'response' => [
						'code' => 200,
					]
				],
				true,
			]
		];
	}

	/**
	 * @dataProvider get_test_data__is_file
	 */
	public function test__is_file( $mocked_response, $expected_result ) {
		$this->mock_http_response( $mocked_response );

		$actual_result = $this->api_client->is_file( '/file.jpg' );
		$this->assertEquals( $expected_result, $actual_result );
	}

	public function test__is_file__validate_request() {
		$this->mock_http_response( [] ); // don't care about the response

		$this->api_client->is_file( '/file.jpg' );

		$actual_http_request = reset( $this->http_requests );

		$this->assertEquals( 'https://files.go-vip.co/file.jpg', $actual_http_request['url'], 'Incorrect API URL' );
		$this->assertEquals( 'GET', $actual_http_request['args']['method'], 'Incorrect HTTP method' );
		$this->assertArraySubset( [
			'X-Action' => 'file_exists'
		], $actual_http_request['args']['headers'], 'Missing `X-Action` header' );
	}

	public function get_test_data__delete_file() {
		return [
			'WP_Error' => [
				new WP_Error( 'oh-no', 'Oh no!' ),
				new WP_Error( 'oh-no', 'Oh no!' ),
			],

			'delete-failed' => [
				[
					'response' => [
						'code' => 500,
					]
				],
				new WP_Error( 'delete_file-failed', 'Failed to delete file `/file.jpg` (response code: 500)' ),
			],

			'delete-succeeded' => [
				[
					'response' => [
						'code' => 200,
					]
				],
				true,
			]
		];
	}

	/**
	 * @dataProvider get_test_data__delete_file
	 */
	public function test__delete_file( $mocked_response, $expected_result ) {
		$this->mock_http_response( $mocked_response );

		$actual_result = $this->api_client->delete_file( '/file.jpg' );
		$this->assertEquals( $expected_result, $actual_result );
	}

	public function test__delete_file__validate_request() {
		$this->mock_http_response( [] ); // don't care about the response

		$this->api_client->delete_file( '/delete/this/file.jpg' );

		$actual_http_request = reset( $this->http_requests );

		$this->assertEquals( 'https://files.go-vip.co/delete/this/file.jpg', $actual_http_request['url'], 'Incorrect API URL' );
		$this->assertEquals( 'DELETE', $actual_http_request['args']['method'], 'Incorrect HTTP method' );
	}
}
