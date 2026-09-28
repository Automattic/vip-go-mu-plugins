<?php

namespace Automattic\VIP\Files;

use PHPUnit\Framework\MockObject\MockObject;
use WP_Error;
use WP_UnitTestCase;

// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_trigger_error, WordPress.WP.AlternativeFunctions.file_system_read_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fread, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fwrite,WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_rename, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_content, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
/**
 * @runInSeparateProcess
 */
class VIP_Filesystem_Local_Stream_Wrapper_Test extends WP_UnitTestCase {
	/** @var VIP_Filesystem_Local_Stream_Wrapper */
	private $stream_wrapper;

	/** @var MockObject&Api_Client */
	private $api_client_mock;

	private $errors = [];

	private $should_unregister = false;

	public function setUp(): void {
		parent::setUp();

		require_once WPMU_PLUGIN_DIR . '/files/class-vip-filesystem-local-stream-wrapper.php';

		/** @var MockObject&Api_Client */
		$this->api_client_mock = $this->createMock( Api_Client::class );

		$this->stream_wrapper = new VIP_Filesystem_Local_Stream_Wrapper( $this->api_client_mock );

		if ( ! in_array( VIP_Filesystem_Local_Stream_Wrapper::DEFAULT_PROTOCOL, stream_get_wrappers(), true ) ) {
			$this->should_unregister = true;
			$this->stream_wrapper->register();
		}

		VIP_Filesystem_Local_Stream_Wrapper::$default_client = $this->api_client_mock;

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		set_error_handler( [ $this, 'errorHandler' ] );
	}

	public function tearDown(): void {
		if ( $this->should_unregister ) {
			stream_wrapper_unregister( VIP_Filesystem_Local_Stream_Wrapper::DEFAULT_PROTOCOL );
		}

		VIP_Filesystem_Local_Stream_Wrapper::$default_client = null;

		$this->stream_wrapper  = null;
		$this->api_client_mock = null;

		$this->errors = [];

		restore_error_handler();

		parent::tearDown();
	}

	/**
	 * Helper function for accessing protected methods.
	 */
	protected static function get_method( $name ) {
		$class  = new \ReflectionClass( __NAMESPACE__ . '\VIP_Filesystem_Local_Stream_Wrapper' );
		$method = $class->getMethod( $name );
		return $method;
	}

	/**
	 * Helper functions to test for trigger_error calls
	 */
	public function errorHandler( $errno, $errstr, $errfile, $errline ) {
		$this->errors[] = compact( 'errno', 'errstr', 'errfile', 'errline' );
	}

	public function assertError( $errstr, $errno ) {
		foreach ( $this->errors as $error ) {
			if ( $error['errstr'] === $errstr
				&& $error['errno'] === $errno ) {
				return;
			}
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		$this->fail( 'Error with level ' . $errno . " and message '" . $errstr . "' not found in " . var_export( $this->errors, true ) );
	}

	public function test__rename__same_path() {
		$path_from = 'vip://wp-content/uploads/file.txt';
		$path_to   = 'vip://wp-content/uploads/file.txt';

		// We bail early so Api_Client should not be touched.
		$this->api_client_mock
			->expects( $this->never() )
			->method( $this->anything() );

		$actual_result = $this->stream_wrapper->rename( $path_from, $path_to );

		$this->assertTrue( $actual_result, 'Return value from rename() was not true' );
	}

	public function test__rename__success() {
		$path_from = 'vip://wp-content/uploads/old.txt';
		$path_to   = 'vip://wp-content/uploads/new.txt';

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_tempnam
		$tmp_file = tempnam( sys_get_temp_dir(), 'phpunit' );

		$this->api_client_mock
			->expects( $this->once() )
			->method( 'get_file' )
			->with( 'wp-content/uploads/old.txt' )
			->willReturn( $tmp_file );

		$this->api_client_mock
			->expects( $this->once() )
			->method( 'upload_file' )
			->with( $tmp_file, 'wp-content/uploads/new.txt' )
			->willReturn( '/wp-content/uploads/new.txt' );

		$this->api_client_mock
			->expects( $this->once() )
			->method( 'delete_file' )
			->with( 'wp-content/uploads/old.txt' )
			->willReturn( true );

		$actual_result = $this->stream_wrapper->rename( $path_from, $path_to );

		$this->assertTrue( $actual_result );
	}

	public function test__rename__local_to_remote_uploads_the_local_file_directly() {
		$path_from = 'vip://wp-content/uploads/chunks/upload.part';
		$path_to   = 'vip://wp-content/uploads/upload.zip';
		$content   = 'assembled chunk content';

		VIP_Filesystem_Local_Stream_Wrapper::add_local_file( $path_from );
		file_put_contents( $path_from, $content );

		$this->api_client_mock
			->expects( self::never() )
			->method( 'get_file' );

		$this->api_client_mock
			->expects( self::once() )
			->method( 'upload_file' )
			->with(
				$this->callback( function ( $local_path ) use ( $content ) {
					return is_string( $local_path ) && file_get_contents( $local_path ) === $content;
				} ),
				'wp-content/uploads/upload.zip'
			)
			->willReturn( '/wp-content/uploads/upload.zip' );

		$this->assertTrue( $this->stream_wrapper->rename( $path_from, $path_to ) );
		$this->assertFalse( file_exists( $path_from ) );

		VIP_Filesystem_Local_Stream_Wrapper::remove_local_file( $path_from );
	}

	public function test__rename__remote_to_local_copies_the_downloaded_file() {
		$path_from = 'vip://wp-content/uploads/remote.txt';
		$path_to   = 'vip://wp-content/uploads/cache/remote.tmp';
		$content   = 'remote file content';
		$tmp_file  = tempnam( sys_get_temp_dir(), 'phpunit' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_tempnam
		file_put_contents( $tmp_file, $content );

		VIP_Filesystem_Local_Stream_Wrapper::add_local_file( $path_to );

		$this->api_client_mock
			->expects( self::once() )
			->method( 'get_file' )
			->with( 'wp-content/uploads/remote.txt' )
			->willReturn( $tmp_file );

		$this->api_client_mock
			->expects( self::once() )
			->method( 'delete_file' )
			->with( 'wp-content/uploads/remote.txt' )
			->willReturn( true );

		$this->assertTrue( $this->stream_wrapper->rename( $path_from, $path_to ) );
		$this->assertSame( $content, file_get_contents( $path_to ) );

		unlink( $path_to );
		unlink( $tmp_file );
		VIP_Filesystem_Local_Stream_Wrapper::remove_local_file( $path_to );
	}

	public function get_test_data__validate_valid_mode() {
		return [
			'read mode'   => [ 'r' ],
			'write mode'  => [ 'w' ],
			'append mode' => [ 'a' ],
			'x mode'      => [ 'x' ],
		];
	}

	/**
	 * @dataProvider get_test_data__validate_valid_mode
	 */
	public function test__validate__valid_mode( $mode ) {
		$this->assertTrue( $this->stream_wrapper->validate( '/test/path', $mode ) );
	}

	public function get_test_data__validate_invalid_mode() {
		return [
			'c mode' => [ 'c' ],
			'e mode' => [ 'e' ],
		];
	}

	/**
	 * @dataProvider get_test_data__validate_invalid_mode
	 */
	public function test__validate__invalid_mode( $mode ) {
		$result = $this->stream_wrapper->validate( '/test/path', $mode );

		$this->assertError( esc_html( "Mode not supported: { $mode }. Use one 'r', 'w', 'a', or 'x'." ), E_USER_NOTICE );
		$this->assertFalse( $result );
	}

	public function test__validate__x_mode_file_doesnt_exist() {
		$path = '/wp-content/uploads/test.txt';

		$this->api_client_mock
			->expects( $this->once() )
			->method( 'is_file' )
			->with( $path, [] )
			->willReturn( false );

		$this->assertTrue( $this->stream_wrapper->validate( $path, 'x' ) );
	}

	public function test__validate__x_mode_file_already_exist() {
		$path = '/wp-content/uploads/test.txt';

		$this->api_client_mock
			->expects( $this->once() )
			->method( 'is_file' )
			->with( $path, [] )
			->willReturn( true );

		$this->assertFalse( $this->stream_wrapper->validate( $path, 'x' ) );
	}

	public function test__validate__x_mode_is_file_request_error() {
		$path = '/wp-content/uploads/test.txt';

		$this->api_client_mock
			->expects( $this->once() )
			->method( 'is_file' )
			->with( $path, [] )
			->willReturn( new WP_Error( 'is-file-error', 'Test error' ) );

		$this->assertFalse( $this->stream_wrapper->validate( $path, 'x' ) );
		$this->assertError( "fopen mode validation failed for mode x on path $path with error: Test error #vip-go-streams", E_USER_WARNING );
	}

	public function test_open_non_existing_file_ro(): void {
		$ignore = null;
		$path   = 'wp-content/uploads/non-existing-asset.jpg';
		$this->api_client_mock->expects( self::once() )->method( 'get_file' )->with( $path )->willReturn( new WP_Error( 'file-not-found', 'error' ) );
		$actual = $this->stream_wrapper->stream_open( 'vip://' . $path, 'r', 0, $ignore );

		self::assertFalse( $actual );
	}

	public function test_open_write_mode_does_not_fetch_existing_file(): void {
		$path = 'wp-content/uploads/overwrite.txt';

		$this->api_client_mock
			->expects( self::never() )
			->method( 'get_file' );

		$this->api_client_mock
			->expects( self::once() )
			->method( 'cache_file_stats' )
			->with(
				$path,
				$this->callback( function ( $stats ) {
					return 0 === $stats['size'] && is_int( $stats['mtime'] );
				} )
			);

		$this->api_client_mock
			->expects( self::once() )
			->method( 'upload_file' )
			->with( $this->anything(), $path )
			->willReturn( '/wp-content/uploads/overwrite.txt' );

		self::assertTrue( $this->stream_wrapper->stream_open( 'vip://' . $path, 'w', 0 ) );
		self::assertTrue( $this->stream_wrapper->stream_close() );
	}

	public function test_file_put_contents_overwrites_without_a_remote_read(): void {
		$path    = 'wp-content/uploads/overwrite.txt';
		$content = 'replacement content';

		$this->api_client_mock
			->expects( self::never() )
			->method( 'get_file' );

		$this->api_client_mock
			->expects( self::once() )
			->method( 'cache_file_stats' );

		$this->api_client_mock
			->expects( self::once() )
			->method( 'upload_file' )
			->with(
				$this->callback( function ( $local_path ) use ( $content ) {
					return file_get_contents( $local_path ) === $content;
				} ),
				$path
			)
			->willReturn( '/wp-content/uploads/overwrite.txt' );

		self::assertSame( strlen( $content ), file_put_contents( 'vip://' . $path, $content ) );
	}

	/**
	 * @ticket CANTINA-911
	 */
	public function test_touch_non_existing_file(): void {
		$path     = 'wp-content/uploads/non-existing-file.jpg';
		$vip_path = 'vip://' . $path;

		// file_exists() check
		$this->api_client_mock
			->expects( self::once() )
			->method( 'is_file' )
			->with( $path, $this->anything() )
			->willReturn( false );

		// fopen() in write mode creates an empty local buffer without a remote read.
		$this->api_client_mock
			->expects( self::never() )
			->method( 'get_file' );

		// flush() when closing the file
		$this->api_client_mock
			->expects( self::once() )
			->method( 'upload_file' )
			->with( $this->anything(), $path )
			->willReturn( true );

		$this->api_client_mock->expects( self::never() )->method( 'get_file_content' );

		$actual = $this->stream_wrapper->stream_metadata( $vip_path, STREAM_META_TOUCH, [ $vip_path, null ] );
		self::assertTrue( $actual );
	}

	/**
	 * @ticket CANTINA-911
	 */
	public function test_touch_existing_file(): void {
		$path     = 'wp-content/uploads/existing-file.jpg';
		$vip_path = 'vip://' . $path;

		// file_exists() check
		$this->api_client_mock
			->expects( self::once() )
			->method( 'is_file' )
			->with( $path, $this->anything() )
			->willReturn( true );

		// No fopen()
		$this->api_client_mock->expects( self::never() )->method( 'get_file' );

		// No flush()
		$this->api_client_mock->expects( self::never() )->method( 'upload_file' );

		$this->api_client_mock->expects( self::never() )->method( 'get_file_content' );

		$actual = $this->stream_wrapper->stream_metadata( $vip_path, STREAM_META_TOUCH, [ $vip_path, null ] );
		self::assertTrue( $actual );
	}

	public function test_touch_fclose_failure(): void {
		$path     = 'wp-content/uploads/non-existing-file.php';
		$vip_path = 'vip://' . $path;

		// file_exists() check
		$this->api_client_mock
			->expects( self::once() )
			->method( 'is_file' )
			->with( $path, $this->anything() )
			->willReturn( false );

		// fopen() in write mode creates an empty local buffer without a remote read.
		$this->api_client_mock
			->expects( self::never() )
			->method( 'get_file' );

		// flush() when closing the file
		$this->api_client_mock
			->expects( self::once() )
			->method( 'upload_file' )
			->with( $this->anything(), $path )
			->willReturn( new WP_Error( 'upload_file-failed' ) );

		$this->api_client_mock->expects( self::never() )->method( 'get_file_content' );

		$actual = $this->stream_wrapper->stream_metadata( $vip_path, STREAM_META_TOUCH, [ $vip_path, null ] );
		self::assertFalse( $actual );
	}

	/**
	 * Test local files functionality
	 */
	public function test_local_files() {
		// Set up the API client mock
		$this->api_client_mock = $this->createMock( API_Client::class );
		$this->stream_wrapper  = new VIP_Filesystem_Local_Stream_Wrapper( $this->api_client_mock );
		$this->stream_wrapper->register();
		$this->should_unregister = true;

		// Test adding a file to the local files list
		$test_file = 'vip://wp-content/uploads/test-local-file.txt';
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::add_local_file( $test_file ) );

		// Test getting the local files list
		$local_files = VIP_Filesystem_Local_Stream_Wrapper::get_local_files();

		$this->arrayHasKey( $test_file, $local_files );

		// Test checking if a file is in the local files list
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( $test_file ) );
		$this->assertFalse( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/non-local-file.txt' ) );

		// Test file operations with local files
		$content = 'Test content for local file';

		// Test writing to a local file
		$fp = fopen( $test_file, 'w' );
		$this->assertNotFalse( $fp );
		$bytes_written = fwrite( $fp, $content );
		$this->assertEquals( strlen( $content ), $bytes_written );
		$this->assertTrue( fflush( $fp ) );
		$this->assertSame( 0, fseek( $fp, 0 ) );
		$this->assertSame( 0, ftell( $fp ) );
		fclose( $fp );

		// Test reading from a local file
		$fp = fopen( $test_file, 'r' );
		$this->assertNotFalse( $fp );
		$read_content = fread( $fp, 1024 );
		$this->assertEquals( $content, $read_content );
		fclose( $fp );

		// Test file_get_contents and file_put_contents
		$this->assertEquals( $content, file_get_contents( $test_file ) );
		$new_content = 'Updated content';
		$this->assertNotFalse( file_put_contents( $test_file, $new_content ) );
		$this->assertEquals( $new_content, file_get_contents( $test_file ) );

		// Test file_exists
		$this->assertTrue( file_exists( $test_file ) );

		// Test copy
		$copy_file = 'vip://wp-content/uploads/test-local-file-copy.txt';
		VIP_Filesystem_Local_Stream_Wrapper::add_local_file( $copy_file );
		$this->assertTrue( copy( $test_file, $copy_file ) );
		$this->assertEquals( $new_content, file_get_contents( $copy_file ) );

		// Test rename
		$rename_file = 'vip://wp-content/uploads/test-local-file-renamed.txt';
		VIP_Filesystem_Local_Stream_Wrapper::add_local_file( $rename_file );
		$this->assertTrue( rename( $copy_file, $rename_file ) );
		$this->assertFalse( file_exists( $copy_file ) );
		$this->assertTrue( file_exists( $rename_file ) );
		$this->assertEquals( $new_content, file_get_contents( $rename_file ) );

		// Test unlink
		$this->assertTrue( unlink( $test_file ) );
		$this->assertFalse( file_exists( $test_file ) );
		$this->assertTrue( unlink( $rename_file ) );
		$this->assertFalse( file_exists( $rename_file ) );

		// Test removing a file from the local files list
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::remove_local_file( $test_file ) );
		$this->assertFalse( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( $test_file ) );

		// Clean up
		if ( $this->should_unregister ) {
			stream_wrapper_unregister( VIP_Filesystem_Local_Stream_Wrapper::DEFAULT_PROTOCOL );
			$this->should_unregister = false;
		}
	}

	/**
	 * Test wildcard pattern matching capabilities
	 */
	public function test_wildcard_matching() {
		// Set up the API client mock
		$this->api_client_mock = $this->createMock( API_Client::class );
		$this->stream_wrapper  = new VIP_Filesystem_Local_Stream_Wrapper( $this->api_client_mock );
		$this->stream_wrapper->register();
		$this->should_unregister = true;

		// Add a wildcard pattern for image files
		$image_pattern = 'vip://wp-content/uploads/*.jpg';
		VIP_Filesystem_Local_Stream_Wrapper::add_local_file( $image_pattern );

		// Test matching against pattern
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/image.jpg' ) );
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/profile.jpg' ) );
		$this->assertFalse( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/document.pdf' ) );

		// Add a pattern with question mark wildcard
		$question_pattern = 'vip://wp-content/uploads/file-?.txt';
		VIP_Filesystem_Local_Stream_Wrapper::add_local_file( $question_pattern );

		// Test question mark wildcard
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/file-1.txt' ) );
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/file-A.txt' ) );
		$this->assertFalse( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/file-12.txt' ) );

		// Add a pattern with character class
		$char_class_pattern = 'vip://wp-content/uploads/[a-z]*.pdf';
		VIP_Filesystem_Local_Stream_Wrapper::add_local_file( $char_class_pattern );

		// Test character class pattern
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/document.pdf' ) );
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/abc.pdf' ) );
		$this->assertFalse( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/123.pdf' ) );

		// Cleanup
		VIP_Filesystem_Local_Stream_Wrapper::remove_local_file( $image_pattern );
		VIP_Filesystem_Local_Stream_Wrapper::remove_local_file( $question_pattern );
		VIP_Filesystem_Local_Stream_Wrapper::remove_local_file( $char_class_pattern );

		if ( $this->should_unregister ) {
			stream_wrapper_unregister( VIP_Filesystem_Local_Stream_Wrapper::DEFAULT_PROTOCOL );
			$this->should_unregister = false;
		}
	}

	/**
	 * Test O(1) lookup optimizations
	 */
	public function test_lookup_optimization() {
		// Set up the API client mock
		$this->api_client_mock = $this->createMock( API_Client::class );
		$this->stream_wrapper  = new VIP_Filesystem_Local_Stream_Wrapper( $this->api_client_mock );
		$this->stream_wrapper->register();
		$this->should_unregister = true;

		// Clean existing files before testing
		$existing_files = VIP_Filesystem_Local_Stream_Wrapper::get_local_files();
		foreach ( $existing_files as $path => $value ) {
			VIP_Filesystem_Local_Stream_Wrapper::remove_local_file( $path );
		}

		// First, verify no files are registered
		$this->assertFalse( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/exact-file.txt' ) );
		$this->assertFalse( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/pattern-123.txt' ) );

		// Add exact file path
		$exact_path = 'vip://wp-content/uploads/exact-file.txt';
		VIP_Filesystem_Local_Stream_Wrapper::add_local_file( $exact_path );

		// Verify exact file is now recognized
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( $exact_path ) );

		// Add wildcard pattern
		$pattern = 'vip://wp-content/uploads/pattern-*.txt';
		VIP_Filesystem_Local_Stream_Wrapper::add_local_file( $pattern );

		// Verify pattern-matched files are recognized (O(1) lookup optimization)
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/pattern-123.txt' ) );
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/pattern-abc.txt' ) );
		$this->assertFalse( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/different-123.txt' ) );

		// Test removing files
		VIP_Filesystem_Local_Stream_Wrapper::remove_local_file( $exact_path );
		$this->assertFalse( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( $exact_path ) );

		VIP_Filesystem_Local_Stream_Wrapper::remove_local_file( $pattern );
		$this->assertFalse( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/pattern-123.txt' ) );

		if ( $this->should_unregister ) {
			stream_wrapper_unregister( VIP_Filesystem_Local_Stream_Wrapper::DEFAULT_PROTOCOL );
			$this->should_unregister = false;
		}
	}

	public function test_filename_substring_matching() {
		$this->assertFalse( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/.htaccess' ) );

		VIP_Filesystem_Local_Stream_Wrapper::add_local_file( '.htaccess' );
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/.htaccess' ) );
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/cache/.htaccess.backup' ) );
		$this->assertFalse( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/cache/htaccess.txt' ) );

		VIP_Filesystem_Local_Stream_Wrapper::remove_local_file( '.htaccess' );
		$this->assertFalse( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/.htaccess' ) );
	}

	public function test__global_helpers__add() {
		// Add files using the global helper
		$result = wpvip_fs_local_file_add( 'vip://wp-content/uploads/direct.txt' );

		$this->assertTrue( $result );

		// Verify it was added to the stream wrapper
		$local_files = VIP_Filesystem_Local_Stream_Wrapper::get_local_files();
		$this->assertArrayHasKey( 'vip://wp-content/uploads/direct.txt', $local_files );
	}

	public function test__global_helpers__remove() {
		// Add a file first
		VIP_Filesystem_Local_Stream_Wrapper::add_local_file( 'vip://wp-content/uploads/to-remove.txt' );

		// Verify it exists
		$local_files = VIP_Filesystem_Local_Stream_Wrapper::get_local_files();
		$this->assertArrayHasKey( 'vip://wp-content/uploads/to-remove.txt', $local_files );

		// Remove it using global helper
		$result = wpvip_fs_local_file_remove( 'vip://wp-content/uploads/to-remove.txt' );

		$this->assertTrue( $result );

		// Verify it was removed
		$local_files = VIP_Filesystem_Local_Stream_Wrapper::get_local_files();
		$this->assertArrayNotHasKey( 'vip://wp-content/uploads/to-remove.txt', $local_files );
	}

	public function test__global_helpers__list() {
		VIP_Filesystem_Local_Stream_Wrapper::add_local_file( 'vip://file1.txt' );
		VIP_Filesystem_Local_Stream_Wrapper::add_local_file( 'vip://file2.txt' );

		$list = wpvip_fs_local_file_list();

		$this->assertArrayHasKey( 'vip://file1.txt', $list );
		$this->assertArrayHasKey( 'vip://file2.txt', $list );
	}

	public function test__global_helpers__invalid_input() {
		// Test with empty string
		$result = wpvip_fs_local_file_add( '' );
		$this->assertFalse( $result );

		// Test with non-string
		$result = wpvip_fs_local_file_add( null );
		$this->assertFalse( $result );

		$result = wpvip_fs_local_file_add( 123 );
		$this->assertFalse( $result );
	}

	public function test__global_helpers__pattern_matching() {
		// Add a wildcard pattern
		wpvip_fs_local_file_add( 'vip://wp-content/uploads/cache/*.json' );

		// Check if it's recognized as a pattern
		$local_files = VIP_Filesystem_Local_Stream_Wrapper::get_local_files();
		$this->assertArrayHasKey( 'vip://wp-content/uploads/cache/*.json', $local_files );

		// Test that a matching file is recognized
		$is_local = VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/cache/data.json' );
		$this->assertTrue( $is_local );

		// Test that a non-matching file is not recognized
		$is_local = VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/cache/data.txt' );
		$this->assertFalse( $is_local );
	}
}
