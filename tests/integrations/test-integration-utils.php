<?php
/**
 * Test integration version discovery against real temporary filesystem fixtures.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use WP_UnitTestCase;

// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.directory_mkdir, WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink -- Temporary fixtures under get_temp_dir().

// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_mkdir, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents -- Temporary test fixtures.

class VIP_Integration_Utils_Test extends WP_UnitTestCase {
	private string $directory;

	/**
	 * Allocate real version directories without replacing namespace functions.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->directory = get_temp_dir() . 'vip-integration-' . wp_generate_password( 12, false ) . '/';
		mkdir( $this->directory );
		foreach ( [ 'fake-1.2', 'fake-1.11', 'fake-2.5', 'other-1.0' ] as $name ) {
			mkdir( $this->directory . $name );
			file_put_contents( $this->directory . $name . '/fake.php', '<?php' );
		}
	}

	/**
	 * Remove every fixture after each case, including a failing assertion.
	 */
	public function tearDown(): void {
		foreach ( glob( $this->directory . '*' ) as $directory ) {
			wp_delete_file( $directory . '/fake.php' );
			rmdir( $directory );
		}
		rmdir( $this->directory );
		parent::tearDown();
	}

	/**
	 * Discover, order and select only directories matching the requested integration.
	 */
	public function test_get_versions_parses_and_returns_correct_ordered_versions(): void {
		$this->assertSame( [
			'fake-2.5'  => '2.5',
			'fake-1.11' => '1.11',
			'fake-1.2'  => '1.2',
		], get_available_versions( $this->directory, 'fake', 'fake.php' ) );
		$this->assertSame( 'fake-2.5', get_latest_version( $this->directory, 'fake', 'fake.php' ) );
	}

	/**
	 * An absent base directory must produce no versions.
	 */
	public function test_get_versions_returns_empty_array_when_dir_does_not_exist(): void {
		$this->assertSame( [], get_available_versions( $this->directory . 'missing/', 'fake', 'fake.php' ) );
		$this->assertNull( get_latest_version( $this->directory . 'missing/', 'fake', 'fake.php' ) );
	}

	/**
	 * Matching names without the required entry file must remain unavailable.
	 */
	public function test_get_versions_requires_entry_file(): void {
		$this->assertSame( [], get_available_versions( $this->directory, 'fake', 'missing.php' ) );
	}

	/**
	 * Real files outside the fixture remain visible to all integration classes.
	 */
	public function test_native_filesystem_functions_remain_available(): void {
		$this->assertTrue( is_dir( __DIR__ ) );
		$this->assertTrue( file_exists( __FILE__ ) );
		$this->assertContains( basename( __FILE__ ), scandir( __DIR__ ) );
	}

	/**
	 * A real entry file must not make a directory with no matching name eligible.
	 */
	public function test_get_versions_rejects_real_nonmatching_directory(): void {
		$directory = get_temp_dir() . 'vip-version-' . wp_generate_password( 12, false ) . '/';
		mkdir( $directory );
		mkdir( $directory . 'other-1.0' );
		mkdir( $directory . 'fake-2.0' );
		file_put_contents( $directory . 'other-1.0/fake.php', '<?php' );
		file_put_contents( $directory . 'fake-2.0/fake.php', '<?php' );
		try {
			$this->assertFileExists( $directory . 'other-1.0/fake.php' );
			$this->assertSame( [ 'fake-2.0' => '2.0' ], get_available_versions( $directory, 'fake', 'fake.php' ) );
		} finally {
			unlink( $directory . 'other-1.0/fake.php' );
			unlink( $directory . 'fake-2.0/fake.php' );
			rmdir( $directory . 'other-1.0' );
			rmdir( $directory . 'fake-2.0' );
			rmdir( $directory );
		}
	}
}
