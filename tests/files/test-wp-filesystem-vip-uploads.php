<?php

namespace Automattic\VIP\Files;

use PHPUnit\Framework\MockObject\MockObject;
use WP_Error;
use WP_UnitTestCase;

use function Automattic\Test\Utils\get_class_method_as_public;

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

	public function filter_uploads_basedir( $upload_dir ) {
		$upload_dir['basedir'] = '/tmp/uploads';
		return $upload_dir;
	}

	public function test__sanitize_uploads_path__upload_basedir() {
		$test_path               = '/tmp/uploads/file/to/path.txt';
		$expected_sanitized_path = '/wp-content/uploads/file/to/path.txt';

		$test_method = get_class_method_as_public( WP_Filesystem_VIP_Uploads::class, 'sanitize_uploads_path' );

		$actual_sanitized_path = $test_method->invokeArgs( $this->filesystem, [
			$test_path,
		] );

		$this->assertEquals( $expected_sanitized_path, $actual_sanitized_path );
	}

	public function test__sanitize_uploads_path__WP_CONTENT_DIR() {
		$test_path               = WP_CONTENT_DIR . '/uploads/path/to/file.jpg';
		$expected_sanitized_path = '/wp-content/uploads/path/to/file.jpg';

		$test_method = get_class_method_as_public( WP_Filesystem_VIP_Uploads::class, 'sanitize_uploads_path' );

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

	/**
	 * Cover cleanup after both API success and API errors.
	 */
	public function get_upload_results(): array {
		return [
			'success' => [ true ],
			'error'   => [ false ],
		];
	}

	/**
	 * Assert the uploaded temporary file contains the bytes and is then removed.
	 *
	 * @dataProvider get_upload_results
	 */
	public function test__put_contents__params( bool $success ): void {
		$test_content = 'Howdy';
		$test_file    = '/tmp/uploads/file.txt';
		$tmp_file     = null;
		$error        = new WP_Error( 'upload_failed', 'Upload failed.' );

		$this->api_client_mock
			->expects( $this->once() )
			->method( 'upload_file' )
			->willReturnCallback( function ( $local_path, $remote_path ) use ( &$tmp_file, $test_content, $success, $error ) {
				$tmp_file = $local_path;
				$this->assertSame( '/wp-content/uploads/file.txt', $remote_path );
				$this->assertFileExists( $local_path );
				// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown -- Local temporary file.
				$this->assertSame( $test_content, file_get_contents( $local_path ) );
				return $success ? true : $error;
			} );

		try {
			$this->assertSame( $success, $this->filesystem->put_contents( $test_file, $test_content ) );
			$this->assertIsString( $tmp_file, 'The API upload must receive a temporary file.' );
			clearstatcache( true, $tmp_file );
			$this->assertFileDoesNotExist( $tmp_file );
			if ( ! $success ) {
				$this->assertSame( $error, $this->filesystem->errors );
			}
		} finally {
			if ( is_string( $tmp_file ) && file_exists( $tmp_file ) ) {
				unlink( $tmp_file ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink -- Clean up a failed mutation.
			}
		}
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
