<?php
/**
 * Test integration version discovery against real temporary filesystem fixtures.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;

// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.directory_mkdir, WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents -- Temporary fixtures under get_temp_dir().

// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_mkdir, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents -- Temporary test fixtures.

class VIP_Integration_Utils_Test extends WP_UnitTestCase {
	private static string $directory;

	/**
	 * Allocate real version directories once; the tests only read them.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		self::$directory = get_temp_dir() . 'vip-integration-' . wp_generate_password( 12, false ) . '/';
		mkdir( self::$directory );
		foreach ( [ 'fake-1.2', 'fake-1.11', 'fake-2.5', 'other-1.0' ] as $name ) {
			mkdir( self::$directory . $name );
			file_put_contents( self::$directory . $name . '/fake.php', '<?php' );
		}
	}

	public static function tearDownAfterClass(): void {
		foreach ( glob( self::$directory . '*' ) as $directory ) {
			wp_delete_file( $directory . '/fake.php' );
			rmdir( $directory );
		}
		rmdir( self::$directory );

		parent::tearDownAfterClass();
	}

	/**
	 * Discover, order and select only directories matching the requested integration.
	 *
	 * The `other-1.0` directory has the entry file but not the requested prefix, so it is never eligible.
	 */
	public function test_get_versions_parses_and_returns_correct_ordered_versions(): void {
		$this->assertSame( [
			'fake-2.5'  => '2.5',
			'fake-1.11' => '1.11',
			'fake-1.2'  => '1.2',
		], get_available_versions( self::$directory, 'fake', 'fake.php' ) );
		$this->assertSame( 'fake-2.5', get_latest_version( self::$directory, 'fake', 'fake.php' ) );
	}

	/**
	 * An absent base directory must produce no versions.
	 */
	public function test_get_versions_returns_empty_array_when_dir_does_not_exist(): void {
		$this->assertSame( [], get_available_versions( self::$directory . 'missing/', 'fake', 'fake.php' ) );
		$this->assertNull( get_latest_version( self::$directory . 'missing/', 'fake', 'fake.php' ) );
	}

	/**
	 * Matching names without the required entry file must remain unavailable.
	 */
	public function test_get_versions_requires_entry_file(): void {
		$this->assertSame( [], get_available_versions( self::$directory, 'fake', 'missing.php' ) );
	}
}
