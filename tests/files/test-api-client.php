<?php

namespace Automattic\VIP\Files;

class API_Client_Test extends \WP_UnitTestCase {
	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once( __DIR__ . '/../../files/class-api-client.php' );
	}

	public function setUp() {
		parent::setUp();

		$this->api_client = new API_Client( 'https://files.go-vip.co', 123456, 'super-sekret-token' );
	}

	public function tearDown() {
		$this->api_client = null;

		parent::tearDown();
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
}
