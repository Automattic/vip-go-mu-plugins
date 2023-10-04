<?php

namespace Automattic\VIP\Files;

use Automattic\Test\Constant_Mocker;
use ErrorException;
use WP_Error;
use WP_Filesystem_Base;
use WP_Filesystem_Direct;
use WP_UnitTestCase;

require_once __DIR__ . '/../../files/class-vip-filesystem.php';
VIP_Filesystem_Test::configure_constant_mocker();

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting

class VIP_Filesystem_Test extends WP_UnitTestCase {
	const TEST_IMAGE_PATH = VIP_GO_MUPLUGINS_TESTS__DIR__ . '/fixtures/image.jpg';

	/**
	 * @var     VIP_Filesystem
	 */
	protected $vip_filesystem;

	/** @var int */
	private $original_error_reporting;

	public static $actor;

	public static function configure_constant_mocker(): void {
		Constant_Mocker::clear();
		define( 'LOCAL_UPLOADS', '/wp/uploads' );
		define( 'WP_CONTENT_DIR', '/wp/wordpress/wp-content' );
	}

	public function setUp(): void {
		parent::setUp();

		self::$actor = null;
		self::configure_constant_mocker();

		$this->vip_filesystem = new VIP_Filesystem();

		// add the filters for upload dir tests
		$add_filters = self::get_method( 'add_filters' );
		$add_filters->invoke( $this->vip_filesystem );

		$this->original_error_reporting = error_reporting();
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		set_error_handler( static function ( int $errno, string $errstr ) {
			if ( $errno & error_reporting() ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI
				throw new ErrorException( $errstr, $errno );
			}

			return false;
		}, E_ALL );
	}

	public function tearDown(): void {
		restore_error_handler();
		error_reporting( $this->original_error_reporting );
		Constant_Mocker::clear();

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
		$class  = new \ReflectionClass( __NAMESPACE__ . '\VIP_Filesystem' );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );
		return $method;
	}

	/**
	 * Helper function to get upload path created from VIP_Filesystem
	 */
	private function get_upload_path() {
		$upload_dir_path = wp_get_upload_dir()['path'];
		return substr( $upload_dir_path, strlen( VIP_Filesystem::PROTOCOL . '://' ) );
	}

	public function get_test_data__filter_upload_dir() {
		return [
			'local-uploads' => [
				[
					'path'    => constant( 'LOCAL_UPLOADS' ) . '/2019/1',
					'url'     => 'http://test.com/wp-content/uploads/2019/1',
					'subdir'  => '/2019/1',
					'basedir' => constant( 'LOCAL_UPLOADS' ),
				],
				[
					'path'    => 'vip://wp-content/uploads/2019/1',
					'url'     => 'http://test.com/wp-content/uploads/2019/1',
					'subdir'  => '/2019/1',
					'basedir' => 'vip://wp-content/uploads',
				],
			],
			'wp-content'    => [
				[
					'path'    => constant( 'WP_CONTENT_DIR' ) . '/uploads/2019/1',
					'url'     => 'http://test.com/wp-content/uploads/2019/1',
					'subdir'  => '/2019/1',
					'basedir' => constant( 'WP_CONTENT_DIR' ) . '/uploads',
				],
				[
					'path'    => 'vip://wp-content/uploads/2019/1',
					'url'     => 'http://test.com/wp-content/uploads/2019/1',
					'subdir'  => '/2019/1',
					'basedir' => 'vip://wp-content/uploads',
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

	public function test__get_upload_path() {
		$get_upload_path = self::get_method( 'get_upload_path' );

		$actual = $get_upload_path->invoke( $this->vip_filesystem );

		$this->assertStringNotContainsString( 'vip://', $actual );
	}

	public function get_test_data__clean_file_path() {
		return [
			'dirty path' => [
				'vip://wp-content/uploads/vip://wp-content/uploads/2019/01/IMG_4115.jpg?resize=768,768',
				'vip://wp-content/uploads/2019/01/IMG_4115.jpg',
				'wp-content/uploads/2019/01/foo.jpg?resize=100,100',
				'wp-content/uploads/2019/01/foo.jpg',
			],
			'clean path' => [
				'vip://wp-content/uploads/2019/01/IMG_4115.jpg',
				'vip://wp-content/uploads/2019/01/IMG_4115.jpg',
				'wp-content/uploads/2019/01/foo.jpg',
				'wp-content/uploads/2019/01/foo.jpg',
			],
		];
	}

	/**
	 * @dataProvider get_test_data__clean_file_path
	 */
	public function test__clean_file_path( $file_path, $expected ) {
		Constant_Mocker::undefine( 'WP_CONTENT_DIR' );
		Constant_Mocker::define( 'WP_CONTENT_DIR', WP_CONTENT_DIR );

		$clean_file_path = self::get_method( 'clean_file_path' );

		$actual = $clean_file_path->invokeArgs( $this->vip_filesystem, [ $file_path ] );

		$this->assertEquals( $expected, $actual );
	}

	public function get_test_data__get_file_uri_path() {
		return [
			'with query args' => [
				'vip://wp-content/uploads/2019/01/IMG_4115.jpg?resize=768,768',
				'/wp-content/uploads/2019/01/IMG_4115.jpg',
			],
			'clean path'      => [
				'vip://wp-content/uploads/2019/01/IMG_4115.jpg',
				'/wp-content/uploads/2019/01/IMG_4115.jpg',
			],
		];
	}

	/**
	 * @dataProvider get_test_data__get_file_uri_path
	 */
	public function test__get_file_uri_path( $file_path, $expected ) {
		$get_file_uri_path = self::get_method( 'get_file_uri_path' );

		$actual = $get_file_uri_path->invokeArgs( $this->vip_filesystem, [ $file_path ] );

		$this->assertEquals( $expected, $actual );
	}

	public function get_test_data__filter_get_attached_file() {
		$uploads = wp_get_upload_dir();
		return [
			'proper file path'    => [
				[
					'file'          => 'vip://wp-content/uploads/2019/01/IMG_4115.jpg',
					'attachment_id' => 1,
				],
				'vip://wp-content/uploads/2019/01/IMG_4115.jpg',
			],
			'corrupted file path' => [
				[
					'file'          => 'vip://wp-content/uploads/' . $uploads['baseurl'] . '/2019/01/IMG_4115.jpg',
					'attachment_id' => 1,
				],
				'vip://wp-content/uploads/2019/01/IMG_4115.jpg',
			],
		];
	}

	/**
	 * @dataProvider get_test_data__filter_get_attached_file
	 */
	public function test__filter_get_attached_file( $args, $expected ) {
		$actual = $this->vip_filesystem->filter_get_attached_file( $args['file'], $args['attachment_id'] );

		$this->assertEquals( $expected, $actual );
	}

	public function get_test_data__filter_wp_generate_attachment_metadata() {
		return [
			'filesize-not-set'     => [
				[],
				[
					'filesize' => 6941712,
				],
			],

			'filesize-already-set' => [
				[
					'filesize' => 1234,
				],
				[
					'filesize' => 1234,
				],
			],
		];
	}

	/**
	 * @dataProvider get_test_data__filter_wp_generate_attachment_metadata
	 */
	public function test__filter_wp_generate_attachment_metadata( $initial_metadata, $expected_metadata ) {
		// Remove filters as they conflict with the logic in our filter function below.
		// We don't have a test-specific wrapper that we can fall back to.
		$remove_filters = self::get_method( 'remove_filters' );
		$remove_filters->invoke( $this->vip_filesystem );

		$attachment_id = $this->factory()->attachment->create_upload_object( self::TEST_IMAGE_PATH );

		$actual_metadata = $this->vip_filesystem->filter_wp_generate_attachment_metadata( $initial_metadata, $attachment_id );

		$this->assertEquals( $expected_metadata, $actual_metadata );
	}

	public function test__filter_validate_file__valid_file() {
		$file     = [
			'name' => 'testfile.txt',
		];
		$basepath = $this->get_upload_path();

		$stub = $this->getMockBuilder( VIP_Filesystem::class )
				->setMethods( [ 'validate_file_unique_name', 'validate_file_type' ] )
				->getMock();
		
		$stub->expects( $this->once() )
				->method( 'validate_file_unique_name' )
				->will( $this->returnValue( true ) );

		$stub->expects( $this->once() )
				->method( 'validate_file_type' )
				->with( $basepath . '/' . $file['name'] )
				->will( $this->returnValue( true ) );

		$actual = $stub->filter_validate_file( $file );

		$this->assertEquals( $file['name'], $actual['name'] );
		$this->assertArrayNotHasKey( 'error', $actual );
	}

	public function test__filter_validate_file__file_exists() {
		$file     = [
			'name' => 'testfile.txt',
		];
		$basepath = $this->get_upload_path();

		$stub = $this->getMockBuilder( VIP_Filesystem::class )
				->setMethods( [ 'validate_file_unique_name', 'validate_file_type' ] )
				->getMock();

		$stub->expects( $this->once() )
				->method( 'validate_file_unique_name' )
				->with( $file['name'], $basepath . '/' )
				->will( $this->returnValue( 'testfile_1696439069.txt' ) );
		
		$stub->expects( $this->once() )
				->method( 'validate_file_type' );

		$actual = $stub->filter_validate_file( $file );

		$this->assertNotEquals( $file['name'], $actual['name'] );
	}

	public function test__filter_validate_file__invalid_file_length() {
		$file = [
			'name' => 'abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz.txt',
		];

		$stub = $this->getMockBuilder( VIP_Filesystem::class )
				->setMethods( [ 'validate_file_unique_name', 'validate_file_type' ] )
				->getMock();
		
		$stub->expects( $this->once() )
				->method( 'validate_file_unique_name' )
				->will( $this->returnValue( true ) );

		$stub->expects( $this->once() )
				->method( 'validate_file_type' );

		$actual = $stub->filter_validate_file( $file );

		$this->assertArrayHasKey( 'error', $actual );
		$this->assertEquals(
			'The file name and path cannot exceed 255 characters. Please rename the file to something shorter and try again.',
			$actual['error']
		);
	}

	public function test__filter_validate_file__invalid_file_type() {
		$file     = [
			'name' => 'testfile.exe',
		];
		$basepath = $this->get_upload_path();

		$stub = $this->getMockBuilder( VIP_Filesystem::class )
				->setMethods( [ 'validate_file_unique_name', 'validate_file_type' ] )
				->getMock();

		$stub->expects( $this->once() )
				->method( 'validate_file_unique_name' )
				->will( $this->returnValue( true ) );

		$stub->expects( $this->once() )
				->method( 'validate_file_type' )
				->with( $basepath . '/' . $file['name'] )
				->will( $this->returnValue( new WP_Error( 'invalid-file-type', 'Failed to generate new unique file name `testfile.exe` (response code: 400)' ) ) );

		$actual = $stub->filter_validate_file( $file );

		$this->assertArrayHasKey( 'error', $actual );
		$this->assertEquals(
			'Failed to generate new unique file name `testfile.exe` (response code: 400)',
			$actual['error']
		);
	}

	/**
	 * @dataProvider data_get_transport_for_path
	 */
	public function test_get_transport_for_path( string $file, string $expected ): void {
		$direct = new class( '' ) extends WP_Filesystem_Direct {
			public function put_contents( $file, $contents, $mode = false ) {
				VIP_Filesystem_Test::$actor = 'direct';
				return true;
			}
		};

		$uploads = new class() extends WP_Filesystem_Base {
			public function put_contents( $file, $contents, $mode = false ) {
				VIP_Filesystem_Test::$actor = 'uploads';
				return true;
			}
		};

		$vipfs  = new WP_Filesystem_VIP( [ $uploads, $direct ] );
		$result = $vipfs->put_contents( $file, 'xxx' );
		self::assertTrue( $result );
		self::assertEquals( $expected, self::$actor );
		self::assertEmpty( $vipfs->errors->get_error_messages() );
	}

	public function data_get_transport_for_path(): iterable {
		return [
			[ ABSPATH . '.maintenance', 'direct' ],
			[ get_temp_dir() . '/test.txt', 'direct' ],
			[ constant( 'WP_CONTENT_DIR' ) . '/upgrade/test.txt', 'direct' ],
			[ constant( 'WP_CONTENT_DIR' ) . '/upgrade-temp-backup/test.txt', 'direct' ],
			[ constant( 'WP_CONTENT_DIR' ) . '/themes/test.txt', 'direct' ],
			[ constant( 'WP_CONTENT_DIR' ) . '/plugins/test.txt', 'direct' ],
			[ constant( 'WP_CONTENT_DIR' ) . '/languages/test.txt', 'direct' ],
		];
	}
}
