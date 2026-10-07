<?php

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting

namespace Automattic\VIP\Files;

use Automattic\Test\Constant_Mocker;
use Automattic\Test\Utils\Captures_Errors;
use WP_Filesystem_Base;
use WP_Filesystem_Direct;
use WP_Filesystem_VIP;
use WP_UnitTestCase;

use function Automattic\Test\Utils\get_class_method_as_public;

require_once __DIR__ . '/../../files/class-wp-filesystem-vip.php';

class WP_Filesystem_VIP_Test extends WP_UnitTestCase {
	use Captures_Errors;

	private $filesystem;
	private $fs_uploads_mock;
	private $fs_direct_mock;

	public function setUp(): void {
		parent::setUp();
		Constant_Mocker::define( 'LOCAL_UPLOADS', '/tmp/uploads' );
		Constant_Mocker::define( 'WP_CONTENT_DIR', '/tmp/wordpress/wp-content' );

		$this->fs_uploads_mock = $this->createMock( WP_Filesystem_VIP_Uploads::class );
		$this->fs_direct_mock  = $this->createMock( WP_Filesystem_Direct::class );

		$this->filesystem = new WP_Filesystem_VIP( [
			$this->fs_uploads_mock,
			$this->fs_direct_mock,
		] );
	}

	public function tearDown(): void {
		$this->filesystem = null;

		parent::tearDown();
	}

	private function get_transport_for_path( string $file_path, string $context ) {
		return get_class_method_as_public( WP_Filesystem_VIP::class, 'get_transport_for_path' )->invoke( $this->filesystem, $file_path, $context );
	}

	public function get_test_data__path_checks() {
		// Not ideal that we are using the constants for our test cases.
		// However, it's the easiest way to ensure consistency across test environments.
		return [
			'uploads: other wp-* path'         => [ 'is_uploads_path', [ '/var/www/file.jpg' ], false ],
			'uploads: ABSPATH path'            => [ 'is_uploads_path', [ ABSPATH . '/wp-includes/js/jquery.js' ], false ],
			'uploads: other wp-content path'   => [ 'is_uploads_path', [ WP_CONTENT_DIR . '/themes/twentyseveteen/style.css' ], false ],
			'uploads: valid path'              => [ 'is_uploads_path', [ WP_CONTENT_DIR . '/uploads/2018/04/04.jpg' ], true ],
			'tmp: other path'                  => [ 'is_tmp_path', [ '/wp-includes/js/jquery.js' ], false ],
			'tmp: valid path'                  => [ 'is_tmp_path', [ '/tmp/file.css' ], true ],
			'maintenance: invalid path'        => [ 'is_maintenance_file', [ '/var/log/.maintenance' ], false ],
			'maintenance: valid path'          => [ 'is_maintenance_file', [ ABSPATH . '.maintenance' ], true ],
			'upgrade: invalid path'            => [ 'is_upgrade_path', [ '/wp-includes/js/jquery.js' ], false ],
			'upgrade: other wp-content path'   => [ 'is_upgrade_path', [ WP_CONTENT_DIR . '/uploads/image.jpg' ], false ],
			'upgrade: valid path'              => [ 'is_upgrade_path', [ WP_CONTENT_DIR . '/upgrade/.plugin' ], true ],
			'plugins: invalid path'            => [ 'is_plugins_path', [ '/wp-includes/js/jquery.js' ], false ],
			'plugins: other wp-content path'   => [ 'is_plugins_path', [ WP_CONTENT_DIR . '/uploads/image.jpg' ], false ],
			'plugins: valid path'              => [ 'is_plugins_path', [ WP_CONTENT_DIR . '/plugins/vip/vip.php' ], true ],
			'themes: invalid path'             => [ 'is_themes_path', [ '' ], false ],
			'themes: other wp-content path'    => [ 'is_themes_path', [ WP_CONTENT_DIR . '/uploads/image.jpg' ], false ],
			'themes: valid path'               => [ 'is_themes_path', [ WP_CONTENT_DIR . '/themes/vip/functions.php' ], true ],
			'languages: invalid path'          => [ 'is_languages_path', [ '' ], false ],
			'languages: other wp-content path' => [ 'is_languages_path', [ WP_CONTENT_DIR . '/uploads/image.jpg' ], false ],
			'languages: valid path'            => [ 'is_languages_path', [ WP_CONTENT_DIR . '/languages/vip/vip-en.mo' ], true ],
			'wp-content subfolder: valid path' => [ 'is_wp_content_subfolder_path', [ WP_CONTENT_DIR . '/test', 'test' ], true ],
		];
	}

	/**
	 * @dataProvider get_test_data__path_checks
	 */
	public function test__path_checks( $method, $args, $expected_result ) {
		$actual_result = get_class_method_as_public( WP_Filesystem_VIP::class, $method )->invokeArgs( $this->filesystem, $args );

		$this->assertEquals( $expected_result, $actual_result );
	}

	public function get_test_data__get_transport_for_path() {
		return [
			'read'                         => [ 'test/file/path', 'read', 'direct' ],
			'uploads with stream wrapper'  => [ '/tmp/wordpress/wp-content/uploads/file.file', 'read', 'direct', [ 'VIP_FILESYSTEM_USE_STREAM_WRAPPER' => true ] ],
			'uploads'                      => [ '/tmp/wordpress/wp-content/uploads/file.file', 'write', 'uploads' ],
			'tmp path'                     => [ '/tmp/file.file', 'write', 'direct' ],
			// Outside VIP Go, WP-CLI and core/plugin/theme/language installs can write directly.
			'non-VIP: maintenance file'    => [ ABSPATH . '.maintenance', 'write', 'direct', [ 'VIP_GO_ENV' => false ] ],
			'non-VIP: upgrade install'     => [ WP_CONTENT_DIR . '/upgrade/test.file', 'write', 'direct', [ 'VIP_GO_ENV' => false ] ],
			'non-VIP: upgrade temp backup' => [ WP_CONTENT_DIR . '/upgrade-temp-backup/test.file', 'write', 'direct', [ 'VIP_GO_ENV' => false ] ],
			'non-VIP: plugin install'      => [ WP_CONTENT_DIR . '/plugins/test.file', 'write', 'direct', [ 'VIP_GO_ENV' => false ] ],
			'non-VIP: themes install'      => [ WP_CONTENT_DIR . '/themes/test.file', 'write', 'direct', [ 'VIP_GO_ENV' => false ] ],
			'non-VIP: languages install'   => [ WP_CONTENT_DIR . '/languages/test.file', 'write', 'direct', [ 'VIP_GO_ENV' => false ] ],
		];
	}

	/**
	 * @dataProvider get_test_data__get_transport_for_path
	 */
	public function test__get_transport_for_path( $file_path, $context, $expected_transport, $constants = [] ) {
		foreach ( $constants as $constant => $value ) {
			Constant_Mocker::define( $constant, $value );
		}

		$result = $this->get_transport_for_path( $file_path, $context );

		$this->assertSame( 'uploads' === $expected_transport ? $this->fs_uploads_mock : $this->fs_direct_mock, $result );
	}

	public function test__get_transport_for_path__non_vip_go_env_core_update() {
		Constant_Mocker::define( 'VIP_GO_ENV', false );

		// WP-CLI doesn't set WP_INSTALLING, so core updates are detected through the upgrade lock.
		update_option( 'core_updater.lock', 'foo_bar' );

		$this->assertSame( $this->fs_direct_mock, $this->get_transport_for_path( '/test/foo/bar', 'write' ) );
	}

	public function test__get_transport_for_path__disallowed_write() {
		[ $result, $warnings ] = $this->capture_errors( fn() => $this->get_transport_for_path( '/test/random/directory/file.file', 'write' ) );

		self::assertFalse( $result );
		self::assertSame( [ 'The `/test/random/directory/file.file` file cannot be managed by the `Automattic\VIP\Files\WP_Filesystem_VIP` class. Writes are only allowed for the `/tmp/` and `/tmp/wordpress/wp-content/uploads` directories and reads can be performed everywhere.' ], $warnings );
	}

	public function test__put_contents__uses_transport_for_path() {
		$this->fs_uploads_mock->expects( $this->never() )->method( 'put_contents' );
		$this->fs_direct_mock->expects( $this->once() )
			->method( 'put_contents' )
			->with( ABSPATH . '.maintenance', 'xxx' )
			->willReturn( true );

		$this->assertTrue( $this->filesystem->put_contents( ABSPATH . '.maintenance', 'xxx' ) );
		$this->assertEmpty( $this->filesystem->errors->get_error_messages() );
	}

	public function test_move_with_no_filesystem(): void {
		global $wp_filesystem;
		$save_wp_filesystem = $wp_filesystem;

		try {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- This is the point of the test.
			$wp_filesystem = null;

			$ok = WP_Filesystem();
			self::assertTrue( $ok );

			self::assertInstanceOf( WP_Filesystem_VIP::class, $wp_filesystem );
			/** @var WP_Filesystem_Base $wp_filesystem */

			$tmp      = get_temp_dir();
			$source   = $tmp . 'source.txt';
			$dest     = $tmp . 'dest.txt';
			$original = error_reporting();
			try {
				$actual = $wp_filesystem->move( $source, $dest );
			} finally {
				error_reporting( $original );
			}

			self::assertFalse( $actual );
		} finally {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$wp_filesystem = $save_wp_filesystem;
		}
	}
}
