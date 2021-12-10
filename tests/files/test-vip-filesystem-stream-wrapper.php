<?php

namespace Automattic\VIP\Files;

use PHPUnit\Framework\MockObject\MockObject;
use WP_Error;
use WP_UnitTestCase;

require_once __DIR__ . '/../../files/class-vip-filesystem-stream-wrapper.php';

class VIP_Filesystem_Stream_Wrapper_Test extends WP_UnitTestCase {
	private $stream_wrapper;

	/** @var MockObject&Api_Client */
	private $api_client_mock;

	private $errors = [];

	public function setUp(): void {
		parent::setUp();

		/** @var MockObject&Api_Client */
		$this->api_client_mock = $this->createMock( Api_Client::class );

		$this->stream_wrapper = new VIP_Filesystem_Stream_Wrapper( $this->api_client_mock );

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions
		set_error_handler( [ $this, 'errorHandler' ] );
	}

	public function tearDown(): void {
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
		$class  = new \ReflectionClass( __NAMESPACE__ . '\VIP_Filesystem_Stream_Wrapper' );
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
}
