<?php

namespace Automattic\VIP\Files;

use WP_Error;
use WP_UnitTestCase;

class VIP_Filesystem_Test extends WP_UnitTestCase {

	/**
	 * @var     VIP_Filesystem
	 */
	protected $vip_filesystem;

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once( __DIR__ . '/../../files/class-vip-filesystem.php' );

		// make sure needed constants are defined
		if ( ! defined( 'LOCAL_UPLOADS' ) ) {
			define( 'LOCAL_UPLOADS', '/tmp/uploads' );
		}
		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			define( 'WP_CONTENT_DIR', '/tmp/wordpress/wp-content' );
		}
	}

	public function setUp() {
		parent::setUp();

		$this->vip_filesystem = new VIP_Filesystem();

		// add the filters for upload dir tests
		$add_filters = self::get_method( 'add_filters' );
		$add_filters->invoke( $this->vip_filesystem );
	}

	public function tearDown() {
		// remove the filters
		$remove_filters = self::get_method( 'remove_filters' );
		$remove_filters->invoke( $this->vip_filesystem );

		$this->vip_filesystem = null;

		parent::tearDown();
	}

	/**
	 * Helper function for accessing protected methods.
	 */
	protected static function get_method( $name ) {
		$class = new \ReflectionClass( __NAMESPACE__ . '\VIP_Filesystem' );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );
		return $method;
	}

	public function get_test_data__filter_upload_dir() {
		return [
			'local-uploads' => [
				[
					'path' => LOCAL_UPLOADS . '/2019/1',
					'url' => 'http://test.com/wp-content/uploads/2019/1',
					'subdir' => '/2019/1',
					'basedir' => LOCAL_UPLOADS,
				],
				[
					'path' => 'vip://wp-content/uploads/2019/1',
					'url' => 'http://test.com/wp-content/uploads/2019/1',
					'subdir' => '/2019/1',
					'basedir' => 'vip://wp-content/uploads'
				],
			],
			'wp-content' => [
				[
					'path' => WP_CONTENT_DIR . '/uploads/2019/1',
					'url' => 'http://test.com/wp-content/uploads/2019/1',
					'subdir' => '/2019/1',
					'basedir' => WP_CONTENT_DIR . '/uploads'
				],
				[
					'path' => 'vip://wp-content/uploads/2019/1',
					'url' => 'http://test.com/wp-content/uploads/2019/1',
					'subdir' => '/2019/1',
					'basedir' => 'vip://wp-content/uploads'
				],
			],
		];
	}

	/**
	 * @dataProvider get_test_data__filter_upload_dir
	 */
	public function test__filter_upload_dir( $params, $expected ) {
		$actual = $this->vip_filesystem->filter_upload_dir( $params );

		$this->assertEquals( $expected, $actual );
	}

	public function test__filter_filetype_check() {
		$stub = $this->getMockBuilder( VIP_Filesystem::class )
				->setMethods( [ 'check_filetype_with_backend' ] )
				->getMock();

		$stub->expects( $this->once() )
				->method( 'check_filetype_with_backend' )
				->with( 'somefile.jpg' )
				->will( $this->returnValue( true ) );

		$expected = [
			'ext' => '.jpg',
			'type' => 'image/jpeg',
			'proper_filename' => true,
		];
		$actual = $stub->filter_filetype_check( $expected, '/path/to/somefile.jpg', 'somefile.jpg', [] );

		$this->assertEquals( $expected, $actual );
	}

	public function test__filter_filetype_check__invalid_file() {
		$stub = $this->getMockBuilder( VIP_Filesystem::class )
		             ->setMethods( [ 'check_filetype_with_backend' ] )
		             ->getMock();

		$stub->expects( $this->once() )
		     ->method( 'check_filetype_with_backend' )
		     ->with( 'somefile.jpg' )
		     ->will( $this->returnValue( false ) );

		$data = [
			'ext' => '.jpg',
			'type' => 'image/jpeg',
			'proper_filename' => true,
		];
		$expected = [
			'ext' => '',
			'type' => '',
			'proper_filename' => false,
		];
		$actual = $stub->filter_filetype_check( $data, '/path/to/somefile.jpg', 'somefile.jpg', [] );

		$this->assertEquals( $expected, $actual );
	}

	public function test__get_upload_path() {
		$get_upload_path = self::get_method( 'get_upload_path' );

		$actual = $get_upload_path->invoke( $this->vip_filesystem );

		$this->assertNotContains( 'vip://', $actual );
	}
}