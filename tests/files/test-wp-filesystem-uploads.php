<?php

namespace Automattic\VIP\Files;

use \WP_Error;

class WP_Filesystem_VIP_Uploads_Test extends \WP_UnitTestCase {
	private $api_client_mock;
	private $filesystem;

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once( __DIR__ . '/../../files/class-wp-filesystem-uploads.php' );
	}

	public function setUp() {
		parent::setUp();

		$this->api_client_mock = $this->createMock( Api_Client::class );

		$this->filesystem = new WP_Filesystem_VIP_Uploads( $this->api_client_mock );
	}

	public function tearDown() {
		$this->api_client_mock = null;
		$this->filesystem = null;

		parent::tearDown();
	}

	public function test__get_contents__error() {
		$this->api_client_mock
			->method( 'get_file' )
			->willReturn( new WP_Error( 'oh-no', 'Oh no!' ) );

		$expected_error_code = 'oh-no';

		$actual_contents = $this->filesystem->get_contents( 'file.txt' );

		$this->assertFalse( $actual_contents, 'Incorrect return value' );

		$actual_error_code = $this->filesystem->errors->get_error_code();
		$this->assertEquals( $expected_error_code, $actual_error_code, 'Incorrect error code' );
	}

	public function test__get_contents__success() {
		$this->api_client_mock
			->method( 'get_file' )
			->willReturn( 'Hello World!' );

		$expected_contents = 'Hello World!';

		$actual_contents = $this->filesystem->get_contents( 'file.txt' );

		$this->assertEquals( $expected_contents, $actual_contents );
	}

	public function test__get_contents_array__error() {
		$this->api_client_mock
			->method( 'get_file' )
			->willReturn( new WP_Error( 'oh-no', 'Oh no!' ) );

		$actual_contents = $this->filesystem->get_contents_array( 'file.txt' );

		$this->assertFalse( $actual_contents, 'Incorrect return value' );
	}

	public function get_test_data__get_contents_array__success() {
		return [
			'empty' => [
				'',
				[],
			],

			'one-line' => [
				'Hello World!',
				[ "Hello World!\n" ],
			],

			'multiple-lines' => [
				"Hello\nWorld\n!",
				[
					"Hello\n",
					"World\n",
					"!\n",
				],
			],

			'newline-at-the-end' => [
				"Hello World!\n",
				[
					"Hello World!\n",
					"\n",
				],
			]
		];
	}

	/**
	 * @dataProvider get_test_data__get_contents_array__success
	 */
	public function test__get_contents_array__success( $api_response, $expected_contents ) {
		$this->api_client_mock
			->method( 'get_file' )
			->willReturn( $api_response );

		$actual_contents = $this->filesystem->get_contents_array( 'file.txt' );

		$this->assertEquals( $expected_contents, $actual_contents );
	}
}
