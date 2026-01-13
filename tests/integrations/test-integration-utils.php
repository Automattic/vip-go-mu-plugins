<?php
/**
 * Test: Integration Utils
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.FunctionComment.MissingParamComment
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler

use ErrorException;
use WP_UnitTestCase;

// Define mocks for PHP built-in functions in the same namespace
function is_dir( $dir ) {
	// Mock implementation for different test cases
	global $mock_filesystem_state;
	if ( isset( $mock_filesystem_state ) && 'empty' === $mock_filesystem_state ) {
		return false; // Directory doesn't exist
	}

	if ( isset( $mock_filesystem_state ) && 'non_matching' === $mock_filesystem_state ) {
		return true; // All directories exist
	}

	// Default behavior - base dir exists, subdirs depending on naming
	if ( WPVIP_MU_PLUGIN_DIR . '/vip-integrations/' === $dir ) {
		return true;
	}
	
	// Valid version directories
	if ( WPVIP_MU_PLUGIN_DIR . '/vip-integrations/fake-1.2' === $dir ||
		WPVIP_MU_PLUGIN_DIR . '/vip-integrations/fake-1.11' === $dir ||
		WPVIP_MU_PLUGIN_DIR . '/vip-integrations/fake-2.5' === $dir ) {
		return true;
	}
	
	return false;
}

function scandir( $dir ) {
	// Mock implementation for different test cases
	global $mock_filesystem_state;

	if ( WPVIP_MU_PLUGIN_DIR . '/vip-integrations/' !== $dir ) {
		return [ '.', '..' ];
	}

	if ( isset( $mock_filesystem_state ) && 'empty' === $mock_filesystem_state ) {
		return [ '.', '..' ]; // Empty directory
	}

	if ( isset( $mock_filesystem_state ) && 'non_matching' === $mock_filesystem_state ) {
		return [
			'.',
			'..',
			'some-other-directory',
			'not-fake-1.0',
			'fake-abc', // Invalid version format
		];
	}

	// Default behavior - return valid directories
	return [
		'.',
		'..',
		'fake-1.2',
		'fake-1.11',
		'fake-2.5',
		'some-other-directory',
	];
}

function file_exists( $file ) {
	// Valid RDB plugin files
	return (
		WPVIP_MU_PLUGIN_DIR . '/vip-integrations/fake-1.2/fake.php' === $file ||
		WPVIP_MU_PLUGIN_DIR . '/vip-integrations/fake-1.11/fake.php' === $file ||
		WPVIP_MU_PLUGIN_DIR . '/vip-integrations/fake-2.5/fake.php' === $file
	);
}

require_once __DIR__ . '/fake-integration.php';

class VIP_Integration_Utils_Test extends WP_UnitTestCase {
	private $original_error_reporting;

	public function setUp(): void {
		parent::setUp();

		$this->original_error_reporting = error_reporting();
		set_error_handler( static function ( int $errno, string $errstr ) {
			if ( error_reporting() & $errno ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI
				throw new ErrorException( $errstr, $errno ); // NOSONAR
			}

			return false;
		}, E_USER_WARNING );
	}

	public function tearDown(): void {
		restore_error_handler();
		error_reporting( $this->original_error_reporting );
		parent::tearDown();
	}

	/**
	 * Test that get_versions correctly parses directory names and returns correct versions.
	 */
	public function test_get_versions_parses_and_returns_correct_ordered_versions(): void {
		global $mock_filesystem_state;
		$mock_filesystem_state = 'full';

		$versions = get_available_versions( WPVIP_MU_PLUGIN_DIR . '/vip-integrations/', 'fake', 'fake.php' );
		
		// Verify the returned versions
		$this->assertIsArray( $versions );
		$this->assertCount( 3, $versions );
		$this->assertEquals( '1.2', $versions['fake-1.2'] );
		$this->assertEquals( '1.11', $versions['fake-1.11'] );
		$this->assertEquals( '2.5', $versions['fake-2.5'] );

		// Check that the versions are correctly ordered by semantic version (2.5 > 1.11 > 1.2)
		$keys = array_keys( $versions );
		$this->assertEquals( 'fake-2.5', $keys[0] );
		$this->assertEquals( 'fake-1.11', $keys[1] );
		$this->assertEquals( 'fake-1.2', $keys[2] );
	}

	/**
	 * Test that get_versions returns an empty array when the directory doesn't exist.
	 */
	public function test_get_versions_returns_empty_array_when_dir_does_not_exist(): void {
		global $mock_filesystem_state;
		$mock_filesystem_state = 'empty';
		
		$versions = get_available_versions( WPVIP_MU_PLUGIN_DIR . '/vip-integrations/', 'fake', 'fake.php' );
		
		$this->assertIsArray( $versions );
		$this->assertEmpty( $versions );
	}

	/**
	 * Test that get_versions ignores directories that don't match the pattern.
	 */
	public function test_get_versions_ignores_non_matching_directories(): void {
		global $mock_filesystem_state;
		$mock_filesystem_state = 'non_matching';
		
		$versions = get_available_versions( WPVIP_MU_PLUGIN_DIR . '/vip-integrations/', 'fake', 'fake.php' );
		
		$this->assertIsArray( $versions );
		$this->assertEmpty( $versions );
	}

	/**
	 * Test that get_latest_version returns the latest version.
	 */
	public function test_get_latest_version_returns_latest_version(): void {
		global $mock_filesystem_state;
		$mock_filesystem_state = 'full';

		$latest_version = get_latest_version( WPVIP_MU_PLUGIN_DIR . '/vip-integrations/', 'fake', 'fake.php' );

		$this->assertEquals( 'fake-2.5', $latest_version );
	}

	/**
	 * Test that get_latest_version returns null when no versions are found.
	 */
	public function test_get_latest_version_returns_null_when_no_versions_are_found(): void {
		global $mock_filesystem_state;
		$mock_filesystem_state = 'empty';

		$latest_version = get_latest_version( WPVIP_MU_PLUGIN_DIR . '/vip-integrations/', 'fake', 'fake.php' );

		$this->assertNull( $latest_version );
	}
}
