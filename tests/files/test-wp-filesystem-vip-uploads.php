<?php

namespace Automattic\VIP\Files;

use PHPUnit\Framework\MockObject\MockObject;
use WP_Error;
use WP_UnitTestCase;

require_once __DIR__ . '/../../files/class-wp-filesystem-vip-uploads.php';

class WP_Filesystem_VIP_Uploads_Test extends WP_UnitTestCase {
	private $api_client_mock;
	private $filesystem;

	public function setUp(): void {
		parent::setUp();

		/** @var MockObject&Api_Client */
		$this->api_client_mock = $this->createMock( Api_Client::class );

		$this->filesystem = new WP_Filesystem_VIP_Uploads( $this->api_client_mock );

		add_filter( 'upload_dir', [ $this, 'filter_uploads_basedir' ] );
	}

	public function tearDown(): void {
		remove_filter( 'upload_dir', [ $this, 'filter_uploads_basedir' ] );

		$this->api_client_mock = null;
		$this->filesystem      = null;

		parent::tearDown();
	}

	/**
	 * Helper function for accessing protected methods.
	 */
	protected static function get_method( $name ) {
		$class  = new \ReflectionClass( __NAMESPACE__ . '\WP_Filesystem_VIP_Uploads' );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );
		return $method;
	}

	public function filter_uploads_basedir( $upload_dir ) {
		$upload_dir['basedir'] = '/tmp/uploads';
		return $upload_dir;
	}

	public function test__sanitize_uploads_path__upload_basedir() {
		$test_path               = '/tmp/uploads/file/to/path.txt';
		$expected_sanitized_path = '/wp-content/uploads/file/to/path.txt';

		$test_method = $this->get_method( 'sanitize_uploads_path' );

		$actual_sanitized_path = $test_method->invokeArgs( $this->filesystem, [
			$test_path,
		] );

		$this->assertEquals( $expected_sanitized_path, $actual_sanitized_path );
	}

	public function test__sanitize_uploads_path__WP_CONTENT_DIR() {
		$test_path               = WP_CONTENT_DIR . '/uploads/path/to/file.jpg';
		$expected_sanitized_path = '/wp-content/uploads/path/to/file.jpg';

		$test_method = $this->get_method( 'sanitize_uploads_path' );

		$actual_sanitized_path = $test_method->invokeArgs( $this->filesystem, [
			$test_path,
		] );

		$this->assertEquals( $expected_sanitized_path, $actual_sanitized_path );
	}

	public function test__get_contents__error() {
		$this->api_client_mock
			->method( 'get_file_content' )
			->willReturn( new WP_Error( 'oh-no', 'Oh no!' ) );

		$expected_error_code = 'oh-no';

		$actual_contents = $this->filesystem->get_contents( 'file.txt' );

		$this->assertFalse( $actual_contents, 'Incorrect return value' );

		$actual_error_code = $this->filesystem->errors->get_error_code();
		$this->assertEquals( $expected_error_code, $actual_error_code, 'Incorrect error code' );
	}

	public function test__get_contents__success() {
		$this->api_client_mock
			->method( 'get_file_content' )
			->with( '/wp-content/uploads/file.txt' )
			->willReturn( 'Hello World!' );

		$expected_contents = 'Hello World!';

		$actual_contents = $this->filesystem->get_contents( '/tmp/uploads/file.txt' );

		$this->assertEquals( $expected_contents, $actual_contents );
	}

	public function test__get_contents_array__error() {
		$this->api_client_mock
			->method( 'get_file_content' )
			->willReturn( new WP_Error( 'oh-no', 'Oh no!' ) );

		$actual_contents = $this->filesystem->get_contents_array( 'file.txt' );

		$this->assertFalse( $actual_contents, 'Incorrect return value' );
	}

	public function get_test_data__get_contents_array__success() {
		return [
			'empty'              => [
				'',
				[],
			],

			'one-line'           => [
				'Hello World!',
				[ "Hello World!\n" ],
			],

			'multiple-lines'     => [
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
			],
		];
	}

	/**
	 * @dataProvider get_test_data__get_contents_array__success
	 */
	public function test__get_contents_array__success( $api_response, $expected_contents ) {
		$this->api_client_mock
			->method( 'get_file_content' )
			->with( '/wp-content/uploads/file.txt' )
			->willReturn( $api_response );

		$actual_contents = $this->filesystem->get_contents_array( '/tmp/uploads/file.txt' );

		$this->assertEquals( $expected_contents, $actual_contents );
	}

	public function test__put_contents__params() {
		$test_content = 'Howdy';
		$test_file    = '/tmp/uploads/file.txt';

		$tmp_file = false;

		$this->api_client_mock
			->method( 'upload_file' )
			->with(
				$this->callback( function( $local_path ) use ( $test_content ) {
					// Verify contents of the file
					// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
					$tmp_file_contents = file_get_contents( $local_path );
					return $test_content === $tmp_file_contents;
				} ),
				$this->equalTo( '/wp-content/uploads/file.txt' )
			)
			->willReturn( true );

		$this->filesystem->put_contents( $test_file, $test_content );

		$tmp_file_exists = file_exists( $tmp_file );
		$this->assertFalse( $tmp_file_exists, 'Temp file was not deleted' );
	}

	public function get_test_data__is_dir() {
		return [
			'file'                          => [
				'/wp-content/uploads/file.jpg',
				false,
			],

			'file with trailing period'     => [
				'/wp-content/uploads/file.',
				false,
			],

			'file with leading period'      => [
				'/wp-content/uploads/.file',
				false,
			],

			'directory'                     => [
				'/wp-content/uploads/folder',
				true,
			],

			'directory with trailing slash' => [
				'/wp-content/uploads/folder/',
				true,
			],
		];
	}

	/**
	 * @dataProvider get_test_data__is_dir
	 */
	public function test__is_dir( $test_path, $expected_result ) {
		$actual_result = $this->filesystem->is_dir( $test_path );

		$this->assertEquals( $expected_result, $actual_result );
	}
}
