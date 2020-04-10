<?php

namespace Automattic\VIP\Files;

class VIP_Filesystem_Stream_Wrapper_Test extends \WP_UnitTestCase {
	private $stream_wrapper;

	private $api_client_mock;

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once( __DIR__ . '/../../files/class-vip-filesystem-stream-wrapper.php' );
	}

	public function setUp() {
		parent::setUp();

		$this->api_client_mock = $this->createMock( Api_Client::class );

		$this->stream_wrapper = new VIP_Filesystem_Stream_Wrapper( $this->api_client_mock ); 
	}

	public function tearDown() {
		$this->stream_wrapper = null;
		$this->api_client_mock = null;

		parent::tearDown();
	}

	/**
	 * Helper function for accessing protected methods.
	 */
	protected static function get_method( $name ) {
		$class = new \ReflectionClass( __NAMESPACE__ . '\VIP_Filesystem_Stream_Wrapper' );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );
		return $method;
	}

	public function test__rename__same_path() {
		$path_from = 'vip://wp-content/uploads/file.txt';
		$path_to = 'vip://wp-content/uploads/file.txt';

		// We bail early so Api_Client should not be touched. 
		$this->api_client_mock
			->expects( $this->never() )
			->method( $this->anything() );

		$actual_result = $this->stream_wrapper->rename( $path_from, $path_to );

		$this->assertTrue( $actual_result, 'Return value from rename() was not true' );
	}

	public function test__rename__sucess() {
		$path_from = 'vip://wp-content/uploads/old.txt';
		$path_to = 'vip://wp-content/uploads/new.txt';

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

}
