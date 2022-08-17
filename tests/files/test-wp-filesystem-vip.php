<?php

namespace Automattic\VIP\Files;

use WP_UnitTestCase;
use Automattic\Test\Constant_Mocker;
use Yoast\PHPUnitPolyfills\Polyfills\ExpectPHPException;

require_once __DIR__ . '/../../files/class-wp-filesystem-vip.php';

class WP_Filesystem_VIP_Test extends WP_UnitTestCase {
	private $filesystem;
	private $fs_uploads_mock;
	private $fs_direct_mock;

	public function setUp(): void {
		parent::setUp();

		$this->fs_uploads_mock = $this->createMock( WP_Filesystem_VIP_Uploads::class );
		$this->fs_direct_mock  = $this->createMock( \WP_Filesystem_Direct::class );

		$this->filesystem = new WP_Filesystem_VIP( [
			$this->fs_uploads_mock,
			$this->fs_direct_mock,
		] );
	}

	public function tearDown(): void {
		$this->filesystem = null;

		Constant_Mocker::clear();

		parent::tearDown();
	}

	/**
	 * Helper function for accessing protected methods.
	 */
	protected static function get_method( $name ) {
		$class  = new \ReflectionClass( __NAMESPACE__ . '\WP_Filesystem_VIP' );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );
		return $method;
	}

	public function get_test_data__is_uploads_path() {
		// Not ideal that we are using the constants for our test cases.
		// However, it's the easiest way to ensure consistency across test environments.
		return [
			'invalid other wp-* path'       => [
				'/var/www/file.jpg',
				false,
			],
			'invalid other wp-* path'       => [
				ABSPATH . '/wp-includes/js/jquery.js',
				false,
			],
			'invalid other wp-content path' => [
				WP_CONTENT_DIR . '/themes/twentyseveteen/style.css',
				false,
			],
			'valid uploads path'            => [
				WP_CONTENT_DIR . '/uploads/2018/04/04.jpg',
				true,
			],
		];
	}

	/**
	 * @dataProvider get_test_data__is_uploads_path
	 */
	public function test__is_uploads_path( $file_path, $expected_result ) {
		$is_uploads_path_method = self::get_method( 'is_uploads_path' );

		$actual_result = $is_uploads_path_method->invokeArgs( $this->filesystem, [ $file_path ] );

		$this->assertEquals( $expected_result, $actual_result );
	}

	public function get_test_data__is_tmp_path() {
		return [
			'invalid other path' => [
				'/wp-includes/js/jquery.js',
				false,
			],
			'valid tmp path'     => [
				'/tmp/file.css',
				true,
			],
		];
	}

	/**
	 * @dataProvider get_test_data__is_tmp_path
	 */
	public function test__is_tmp_path( $file_path, $expected_result ) {
		$is_tmp_path_method = self::get_method( 'is_tmp_path' );

		$actual_result = $is_tmp_path_method->invokeArgs( $this->filesystem, [ $file_path ] );

		$this->assertEquals( $expected_result, $actual_result );
	}

	public function get_test_data__is_maintenance_file() {
		return [
			'invalid path' => [
				'/var/log/.maintenance',
				false,
			],
			'valid path'   => [
				ABSPATH . '.maintenance',
				true,
			],
		];
	}

	/**
	 * @dataProvider get_test_data__is_maintenance_file
	 */
	public function test__is_maintenance_file( $file_path, $expected_result ) {
		$is_tmp_path_method = self::get_method( 'is_maintenance_file' );

		$actual_result = $is_tmp_path_method->invokeArgs( $this->filesystem, [ $file_path ] );

		$this->assertEquals( $expected_result, $actual_result );
	}

	public function get_test_data__is_upgrade_path() {
		return [
			'invalid path'                  => [
				'/wp-includes/js/jquery.js',
				false,
			],
			'invalid other wp-content path' => [
				WP_CONTENT_DIR . '/uploads/image.jpg',
				false,
			],
			'valid path'                    => [
				WP_CONTENT_DIR . '/upgrade/.plugin',
				true,
			],
		];
	}

	/**
	 * @dataProvider get_test_data__is_upgrade_path
	 */
	public function test__is_upgrade_path( $file_path, $expected_result ) {
		$is_tmp_path_method = self::get_method( 'is_upgrade_path' );

		$actual_result = $is_tmp_path_method->invokeArgs( $this->filesystem, [ $file_path ] );

		$this->assertEquals( $expected_result, $actual_result );
	}

	public function get_test_data__is_plugins_path() {
		return [
			'invalid path'                  => [
				'/wp-includes/js/jquery.js',
				false,
			],
			'invalid other wp-content path' => [
				WP_CONTENT_DIR . '/uploads/image.jpg',
				false,
			],
			'valid path'                    => [
				WP_CONTENT_DIR . '/plugins/vip/vip.php',
				true,
			],
		];
	}

	/**
	 * @dataProvider get_test_data__is_plugins_path
	 */
	public function test__is_plugins_path( $file_path, $expected_result ) {
		$is_tmp_path_method = self::get_method( 'is_plugins_path' );

		$actual_result = $is_tmp_path_method->invokeArgs( $this->filesystem, [ $file_path ] );

		$this->assertEquals( $expected_result, $actual_result );
	}

	public function get_test_data__is_themes_path() {
		return [
			'invalid path'                  => [
				'',
				false,
			],
			'invalid other wp-content path' => [
				WP_CONTENT_DIR . '/uploads/image.jpg',
				false,
			],
			'valid path'                    => [
				WP_CONTENT_DIR . '/themes/vip/functions.php',
				true,
			],
		];
	}

	/**
	 * @dataProvider get_test_data__is_themes_path
	 */
	public function test__is_themes_path( $file_path, $expected_result ) {
		$is_tmp_path_method = self::get_method( 'is_themes_path' );

		$actual_result = $is_tmp_path_method->invokeArgs( $this->filesystem, [ $file_path ] );

		$this->assertEquals( $expected_result, $actual_result );
	}

	public function get_test_data__is_languages_path() {
		return [
			'invalid path'                  => [
				'',
				false,
			],
			'invalid other wp-content path' => [
				WP_CONTENT_DIR . '/uploads/image.jpg',
				false,
			],
			'valid path'                    => [
				WP_CONTENT_DIR . '/languages/vip/vip-en.mo',
				true,
			],
		];
	}

	/**
	 * @dataProvider get_test_data__is_languages_path
	 */
	public function test__is_languages_path( $file_path, $expected_result ) {
		$is_tmp_path_method = self::get_method( 'is_languages_path' );

		$actual_result = $is_tmp_path_method->invokeArgs( $this->filesystem, [ $file_path ] );

		$this->assertEquals( $expected_result, $actual_result );
	}

	public function test__is_wp_content_subfolder_path() {
		$is_wp_content_subfolder = self::get_method( 'is_wp_content_subfolder_path' );

		$result = $is_wp_content_subfolder->invokeArgs( $this->filesystem, [ WP_CONTENT_DIR . '/test', 'test' ] );

		$this->assertTrue( $result );
	}

	public function test__get_transport_for_path__read() {
		$get_transport_for_path = self::get_method( 'get_transport_for_path' );

		$result = $get_transport_for_path->invokeArgs( $this->filesystem, [ 'test/file/path', 'read' ] );

		$this->assertEquals( $result, $this->fs_direct_mock );
	}

	public function test__get_transport_for_path__uploads_streamwrapper() {
		Constant_Mocker::define( 'VIP_FILESYSTEM_USE_STREAM_WRAPPER', true );

		$get_transport_for_path = self::get_method( 'get_transport_for_path' );

		$result = $get_transport_for_path->invokeArgs( $this->filesystem, [ '/tmp/wordpress/wp-content/uploads/file.file', 'read' ] );

		$this->assertEquals( $result, $this->fs_direct_mock );
	}

	public function test__get_transport_for_path__uploads() {
		$get_transport_for_path = self::get_method( 'get_transport_for_path' );

		$result = $get_transport_for_path->invokeArgs( $this->filesystem, [ '/tmp/wordpress/wp-content/uploads/file.file', 'write' ] );

		$this->assertEquals( $result, $this->fs_uploads_mock );
	}

	public function test__get_transport_for_path__tmp_path() {
		$get_transport_for_path = self::get_method( 'get_transport_for_path' );

		$result = $get_transport_for_path->invokeArgs( $this->filesystem, [ '/tmp/file.file', 'write' ] );

		$this->assertEquals( $result, $this->fs_direct_mock );
	}

	public function test__get_transport_for_path__disallowed_write() {
		$get_transport_for_path = self::get_method( 'get_transport_for_path' );

		$this->expectError();
		$this->expectExceptionMessage( 'The `/test/random/directory/file.file` file cannot be managed by the `Automattic\VIP\Files\WP_Filesystem_VIP` class. Writes are only allowed for the `/tmp/` and `/tmp/wordpress/wp-content/uploads` directories and reads can be performed everywhere.' );

		$result = $get_transport_for_path->invokeArgs( $this->filesystem, [ '/test/random/directory/file.file', 'write' ] );

		$this->assertFalse( $result );
	}

	public function test__get_transport_for_path__non_vip_go_env() {
		Constant_Mocker::define( 'VIP_GO_ENV', false );

		// // Test maintenance file
		$get_transport_for_path = self::get_method( 'get_transport_for_path' );
		$maintenance_result     = $get_transport_for_path->invokeArgs( $this->filesystem, [ ABSPATH . '.maintenance', 'write' ] );
		$this->assertEquals( $maintenance_result, $this->fs_direct_mock );

		// Test WP install
		$wp_install_option_name = 'core_updater.lock';
		update_option( $wp_install_option_name, 'foo_bar' );
		$wp_install_result = $get_transport_for_path->invokeArgs( $this->filesystem, [ '/test/foo/bar', 'write' ] );
		$this->assertEquals( $wp_install_result, $this->fs_direct_mock );
		delete_option( $wp_install_option_name );

		// Test upgrade install
		$upgrade_install_result = $get_transport_for_path->invokeArgs( $this->filesystem, [ WP_CONTENT_DIR . '/upgrade/test.file', 'write' ] );
		$this->assertEquals( $upgrade_install_result, $this->fs_direct_mock );

		// Test plugin install
		$plugin_install_result = $get_transport_for_path->invokeArgs( $this->filesystem, [ WP_CONTENT_DIR . '/plugins/test.file', 'write' ] );
		$this->assertEquals( $plugin_install_result, $this->fs_direct_mock );

		// Test themes install
		$themes_install_result = $get_transport_for_path->invokeArgs( $this->filesystem, [ WP_CONTENT_DIR . '/themes/test.file', 'write' ] );
		$this->assertEquals( $themes_install_result, $this->fs_direct_mock );

		// Test languages install
		$lang_install_result = $get_transport_for_path->invokeArgs( $this->filesystem, [ WP_CONTENT_DIR . '/languages/test.file', 'write' ] );
		$this->assertEquals( $lang_install_result, $this->fs_direct_mock );
	}
}
