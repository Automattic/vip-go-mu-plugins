<?php

namespace Automattic\VIP\Files;

use PHPUnit\Framework\MockObject\MockObject;
use WP_Error;
use WP_UnitTestCase;

require_once __DIR__ . '/../../files/class-vip-filesystem-stream-wrapper.php';
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
		$method->setAccessible( true );
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

	public function test__rename__sucess() {
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

		// fopen() - create empty file
		$this->api_client_mock
			->expects( self::once() )
			->method( 'get_file' )
			->with( $path )
			->willReturn( new WP_Error( 'file-not-found', 'error' ) );

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

		// fopen() - create empty file
		$this->api_client_mock
			->expects( self::once() )
			->method( 'get_file' )
			->with( $path )
			->willReturn( new WP_Error( 'file-not-found', 'error' ) );

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
		$this->assertContains( $test_file, $local_files );

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
	 * Test wildcard pattern matching functionality
	 */
	public function test_wildcard_pattern_matching() {
		// Set up the API client mock
		$this->api_client_mock = $this->createMock( API_Client::class );
		$this->stream_wrapper  = new VIP_Filesystem_Local_Stream_Wrapper( $this->api_client_mock );
		$this->stream_wrapper->register();
		$this->should_unregister = true;

		// Test adding a wildcard pattern to the local files list
		$pattern = 'vip://wp-content/uploads/*.jpg';
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::add_local_file( $pattern ) );

		// Test if files matching the pattern are recognized as local files
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/image1.jpg' ) );
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/subfolder/image2.jpg' ) );
		$this->assertFalse( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/document.pdf' ) );

		// Test different pattern types

		// Question mark wildcard
		$pattern_question = 'vip://wp-content/uploads/image?.jpg';
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::add_local_file( $pattern_question ) );
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/image1.jpg' ) );
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/imageA.jpg' ) );
		$this->assertFalse( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/image10.jpg' ) );

		// Character class wildcards
		$pattern_char_class = 'vip://wp-content/uploads/file[0-9].txt';
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::add_local_file( $pattern_char_class ) );
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/file1.txt' ) );
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/file5.txt' ) );
		$this->assertFalse( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/fileA.txt' ) );

		// Negative character class
		$pattern_neg_class = 'vip://wp-content/uploads/doc[!0-9]*.pdf';
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::add_local_file( $pattern_neg_class ) );
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/docA.pdf' ) );
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/docB-draft.pdf' ) );
		$this->assertFalse( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/doc1.pdf' ) );

		// Test removing a pattern
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::remove_local_file( $pattern ) );
		$this->assertFalse( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/new-image.jpg' ) );

		// Clean up
		if ( $this->should_unregister ) {
			stream_wrapper_unregister( VIP_Filesystem_Local_Stream_Wrapper::DEFAULT_PROTOCOL );
			$this->should_unregister = false;
		}
	}

	/**
	 * Test O(1) lookup for exact matches versus pattern matching
	 */
	public function test_lookup_performance() {
		// Set up the API client mock
		$this->api_client_mock = $this->createMock( API_Client::class );
		$this->stream_wrapper  = new VIP_Filesystem_Local_Stream_Wrapper( $this->api_client_mock );
		$this->stream_wrapper->register();
		$this->should_unregister = true;

		// Add both exact paths and patterns
		$exact_path = 'vip://wp-content/uploads/exact-file.txt';
		$pattern    = 'vip://wp-content/uploads/pattern-*.txt';

		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::add_local_file( $exact_path ) );
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::add_local_file( $pattern ) );

		// Test that both matching methods work
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( $exact_path ) );
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::is_local_file( 'vip://wp-content/uploads/pattern-123.txt' ) );

		// Use reflection to access the local_files_map and local_file_patterns properties
		$reflector            = new \ReflectionClass( VIP_Filesystem_Local_Stream_Wrapper::class );
		$local_files_map_prop = $reflector->getProperty( 'local_files_map' );
		$local_files_map_prop->setAccessible( true );
		$local_file_patterns_prop = $reflector->getProperty( 'local_file_patterns' );
		$local_file_patterns_prop->setAccessible( true );

		// Verify that exact path is in the hash map
		$local_files_map = $local_files_map_prop->getValue();
		$this->assertArrayHasKey( $exact_path, $local_files_map );

		// Verify that pattern is in the patterns hash map (not array)
		$local_file_patterns = $local_file_patterns_prop->getValue();
		$this->assertArrayHasKey( $pattern, $local_file_patterns );
		
		// Test O(1) removes
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::remove_local_file( $pattern ) );
		$local_file_patterns = $local_file_patterns_prop->getValue();
		$this->assertEmpty( $local_file_patterns );
		
		$this->assertTrue( VIP_Filesystem_Local_Stream_Wrapper::remove_local_file( $exact_path ) );
		$local_files_map = $local_files_map_prop->getValue();
		$this->assertEmpty( $local_files_map );

		// Clean up
		if ( $this->should_unregister ) {
			stream_wrapper_unregister( VIP_Filesystem_Local_Stream_Wrapper::DEFAULT_PROTOCOL );
			$this->should_unregister = false;
		}
	}
}
