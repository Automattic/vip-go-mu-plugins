<?php

/**
 * Test: Remote Data Blocks Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;
use Automattic\Test\Constant_Mocker;

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
	if ( WPVIP_MU_PLUGIN_DIR . '/vip-integrations/remote-data-blocks-1.2' === $dir ||
		WPVIP_MU_PLUGIN_DIR . '/vip-integrations/remote-data-blocks-1.11' === $dir ||
		WPVIP_MU_PLUGIN_DIR . '/vip-integrations/remote-data-blocks-2.5' === $dir ) {
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
			'not-remote-data-blocks-1.0',
			'remote-data-blocks-abc', // Invalid version format
		];
	}

	// Default behavior - return valid directories
	return [
		'.',
		'..',
		'remote-data-blocks-1.2',
		'remote-data-blocks-1.11',
		'remote-data-blocks-2.5',
		'some-other-directory',
	];
}

function file_exists( $file ) {
	// Valid RDB plugin files
	return (
		WPVIP_MU_PLUGIN_DIR . '/vip-integrations/remote-data-blocks-1.2/remote-data-blocks.php' === $file ||
		WPVIP_MU_PLUGIN_DIR . '/vip-integrations/remote-data-blocks-1.11/remote-data-blocks.php' === $file ||
		WPVIP_MU_PLUGIN_DIR . '/vip-integrations/remote-data-blocks-2.5/remote-data-blocks.php' === $file
	);
}

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing

class Remote_Data_Blocks_Integration_Test extends WP_UnitTestCase {


	private string $slug = 'remote-data-blocks';

	public function tearDown(): void {
		parent::tearDown();

		Constant_Mocker::clear();

		// Clean up any action we might have added
		remove_all_actions( 'plugins_loaded' );

		// Reset our global flag if it was set
		if ( isset( $GLOBALS['_vip_remote_data_blocks_fired_action'] ) ) {
			$GLOBALS['_vip_remote_data_blocks_fired_action'] = false;
		}

		// Reset our mock state
		global $mock_filesystem_state;
		$mock_filesystem_state = null;
	}

	public function test_is_loaded_returns_false_when_not_loaded(): void {
		$remote_data_blocks_integration = new RemoteDataBlocksIntegration( $this->slug );
		$this->assertFalse( $remote_data_blocks_integration->is_loaded() );
	}

	public function test_is_loaded_returns_true_when_constant_defined(): void {
		Constant_Mocker::define( 'REMOTE_DATA_BLOCKS__LOADED', true );
		$remote_data_blocks_integration = new RemoteDataBlocksIntegration( $this->slug );
		$this->assertTrue( $remote_data_blocks_integration->is_loaded() );
	}

	public function test_load_registers_plugin_loaded_hook(): void {
		$remote_data_blocks_integration = new RemoteDataBlocksIntegration( $this->slug );
		$remote_data_blocks_integration->load();

		$this->assertNotFalse( has_action( 'plugins_loaded' ) );
	}

	public function test_load_returns_early_if_plugin_already_loaded(): void {
		Constant_Mocker::define( 'REMOTE_DATA_BLOCKS__LOADED', true );

		/**
		 * Integration mock that expects is_loaded to be called once and return true
		 *
		 * @var MockObject|RemoteDataBlocksIntegration
		 */
		$integration_mock = $this->getMockBuilder( RemoteDataBlocksIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'is_loaded' ] )
			->getMock();

		$integration_mock->expects( $this->once() )
			->method( 'is_loaded' )
			->willReturn( true );

		$integration_mock->load();

		// Manually trigger the plugins_loaded action
		do_action( 'plugins_loaded' );

		// This test passes if we reach here without errors, as the expectation of is_loaded being called once is met
		$this->assertTrue( true );
	}

	public function test_configure_defines_config_constant(): void {
		$remote_data_blocks_integration = new RemoteDataBlocksIntegration( $this->slug );
		$remote_data_blocks_integration->configure();

		$this->assertTrue( defined( 'REMOTE_DATA_BLOCKS_CONFIGS' ) );
		$this->assertEquals( [], constant( 'REMOTE_DATA_BLOCKS_CONFIGS' ) );
	}

	public function test_configure_does_not_redefine_constant(): void {
		Constant_Mocker::define( 'REMOTE_DATA_BLOCKS_CONFIGS', [ 'test' => 'value' ] );

		$remote_data_blocks_integration = new RemoteDataBlocksIntegration( $this->slug );
		$remote_data_blocks_integration->configure();

		$this->assertEquals( [ 'test' => 'value' ], constant( 'REMOTE_DATA_BLOCKS_CONFIGS' ) );
	}

	/**
	 * Test that get_versions correctly parses directory names and returns correct versions.
	 */
	public function test_get_versions_parses_and_returns_correct_ordered_versions(): void {
		$remote_data_blocks_integration = new RemoteDataBlocksIntegration( $this->slug );
		$versions                       = $remote_data_blocks_integration->get_versions();
		
		// Verify the returned versions
		$this->assertIsArray( $versions );
		$this->assertCount( 3, $versions );
		$this->assertEquals( '1.2', $versions['remote-data-blocks-1.2'] );
		$this->assertEquals( '1.11', $versions['remote-data-blocks-1.11'] );
		$this->assertEquals( '2.5', $versions['remote-data-blocks-2.5'] );

		// Check that the versions are correctly ordered by semantic version (2.5 > 1.11 > 1.2)
		$keys = array_keys( $versions );
		$this->assertEquals( 'remote-data-blocks-2.5', $keys[0] );
		$this->assertEquals( 'remote-data-blocks-1.11', $keys[1] );
		$this->assertEquals( 'remote-data-blocks-1.2', $keys[2] );
	}

	/**
	 * Test that get_versions returns an empty array when the directory doesn't exist.
	 */
	public function test_get_versions_returns_empty_array_when_dir_does_not_exist(): void {
		global $mock_filesystem_state;
		$mock_filesystem_state = 'empty';
		
		$remote_data_blocks_integration = new RemoteDataBlocksIntegration( $this->slug );
		$versions                       = $remote_data_blocks_integration->get_versions();
		
		$this->assertIsArray( $versions );
		$this->assertEmpty( $versions );
	}

	/**
	 * Test that get_versions ignores directories that don't match the pattern.
	 */
	public function test_get_versions_ignores_non_matching_directories(): void {
		global $mock_filesystem_state;
		$mock_filesystem_state = 'non_matching';
		
		$remote_data_blocks_integration = new RemoteDataBlocksIntegration( $this->slug );
		$versions                       = $remote_data_blocks_integration->get_versions();
		
		$this->assertIsArray( $versions );
		$this->assertEmpty( $versions );
	}

	/**
	 * @dataProvider wp_version_support_provider
	 */
	public function test_is_supported_wp_version( string $current_wp_version, ?array $config, bool $expected_result ): void {
		/** @var MockObject|RemoteDataBlocksIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( RemoteDataBlocksIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'get_env_config', 'get_wp_version' ] )
			->getMock();

		$integration_mock->method( 'get_env_config' )->willReturn( $config ?? [] );
		$integration_mock->method( 'get_wp_version' )->willReturn( $current_wp_version );
		$this->assertEquals( $expected_result, $integration_mock->is_supported_wp_version() );
	}

	public static function wp_version_support_provider(): array {
		return [
			'no minimum_wp_version in config'   => [ '6.7', [], true ],
			'wp_version less than minimum'      => [ '5.6', [ 'minimum_wp_version' => '6.7' ], false ],
			'wp_version equal to minimum with patch different' => [ '6.7.1', [ 'minimum_wp_version' => '6.7' ], true ],
			'wp_version equal to minimum exact' => [ '6.7', [ 'minimum_wp_version' => '6.7' ], true ],
			'wp_version equal to minimum but more specific' => [ '6.7.0', [ 'minimum_wp_version' => '6.7' ], true ],
		];
	}

	public function test_load_sets_inactive_and_returns_early_if_wp_version_not_supported(): void {
		/** @var MockObject|RemoteDataBlocksIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( RemoteDataBlocksIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'is_loaded', 'is_supported_wp_version', 'get_versions' ] )
			->getMock();

		$integration_mock->activate(); // Set is_active to true before testing load()

		$integration_mock->method( 'is_loaded' )->willReturn( false );
		$integration_mock->method( 'is_supported_wp_version' )->willReturn( false );
		$integration_mock->expects( $this->never() )->method( 'get_versions' );

		$integration_mock->load();
		do_action( 'plugins_loaded' );

		$this->assertFalse( $integration_mock->is_active() );
	}

	public function test_load_sets_inactive_if_wp_version_supported_but_no_versions_found(): void {
		/** @var MockObject|RemoteDataBlocksIntegration $integration_mock */
		$integration_mock = $this->getMockBuilder( RemoteDataBlocksIntegration::class )
			->setConstructorArgs( [ $this->slug ] )
			->onlyMethods( [ 'is_loaded', 'is_supported_wp_version', 'get_versions' ] )
			->getMock();

		$integration_mock->activate(); // Initial state is active
		$this->assertTrue( $integration_mock->is_active(), 'Initial: Integration should be active.' );

		$integration_mock->method( 'is_loaded' )->willReturn( false );
		$integration_mock->method( 'is_supported_wp_version' )->willReturn( true );
		$integration_mock->method( 'get_versions' )->willReturn( [] ); // No versions found

		$integration_mock->load();
		do_action( 'plugins_loaded' );

		$this->assertFalse( $integration_mock->is_active() );
	}
}
