<?php
/**
 * Test: Gist Answers Integration.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use Env_Integration_Status;
use WP_UnitTestCase;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing, Squiz.Commenting.FunctionComment.MissingParamComment

class Gist_Answers_Integration_Test extends WP_UnitTestCase {
	/**
	 * A synthetic pinned release, so the tests never touch the real pinned release's folder.
	 */
	public const TEST_VERSION = '0.0.1-test';

	private string $slug = 'gist-answers';

	/**
	 * Directories this test created, removed in reverse order in tearDown().
	 *
	 * @var string[]
	 */
	private array $created_directories = [];

	/**
	 * The real pinned release's entry file, and its hash before the test, or null when it is not installed.
	 */
	private string $real_entry_file;
	private ?string $real_entry_hash = null;

	public function setUp(): void {
		parent::setUp();

		$this->real_entry_file = $this->release_directory( GistAnswersIntegration::VERSION ) . '/gist-answers.php';
		$this->real_entry_hash = file_exists( $this->real_entry_file ) ? hash_file( 'sha256', $this->real_entry_file ) : null;
	}

	public function tearDown(): void {
		foreach ( array_reverse( $this->created_directories ) as $directory ) {
			$plugin_file = $directory . '/gist-answers.php';
			if ( file_exists( $plugin_file ) ) {
				// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink -- Remove the test-owned bundled plugin fixture.
				unlink( $plugin_file );
			}

			// Only an emptied directory is removed, so nothing this test did not write is deleted.
			if ( is_dir( $directory ) && [ '.', '..' ] === scandir( $directory ) ) {
				// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir -- Remove the test-owned bundled plugin fixture directory.
				rmdir( $directory );
			}
		}

		$this->created_directories = [];

		// A real release installed under vip-integrations/ must come through the tests byte-for-byte unchanged.
		if ( null !== $this->real_entry_hash ) {
			$this->assertFileExists( $this->real_entry_file );
			$this->assertSame( $this->real_entry_hash, hash_file( 'sha256', $this->real_entry_file ) );
		}

		parent::tearDown();
	}

	/**
	 * The integration with its pinned VERSION replaced by TEST_VERSION.
	 */
	private function create_integration(): GistAnswersIntegration {
		return new class( $this->slug ) extends GistAnswersIntegration {
			const VERSION = Gist_Answers_Integration_Test::TEST_VERSION;
		};
	}

	private function release_directory( string $version ): string {
		return WPVIP_MU_PLUGIN_DIR . '/vip-integrations/gist-answers-' . $version;
	}

	/**
	 * Create vip-integrations/gist-answers-<version>/gist-answers.php, which defines GIST_ANSWERS_VERSION.
	 *
	 * Fails the test rather than write into a release folder that already exists.
	 */
	private function create_release( string $version ): void {
		$directory = $this->release_directory( $version );
		if ( file_exists( $directory ) ) {
			$this->fail( sprintf( 'Refusing to write a test fixture into %s, which already exists.', $directory ) );
		}

		$parent = dirname( $directory );
		if ( ! file_exists( $parent ) ) {
			wp_mkdir_p( $parent );
			$this->created_directories[] = $parent;
		}

		wp_mkdir_p( $directory );
		$this->created_directories[] = $directory;

		$plugin = <<<PHP
<?php
/**
 * Plugin Name: Gist Answers test fixture
 * Requires at least: 5.0
 */
\Automattic\VIP\Integrations\define( 'GIST_ANSWERS_VERSION', '{$version}' );
PHP;

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents -- Test-owned bundled plugin fixture.
		file_put_contents( $directory . '/gist-answers.php', $plugin );
	}

	public function test_pinned_release_is_chosen_over_a_newer_one(): void {
		$this->create_release( self::TEST_VERSION );
		$this->create_release( '999.0.0-test' );

		$integration = $this->create_integration();

		$this->assertSame( 'gist-answers-' . self::TEST_VERSION, $integration->get_latest_version() );
	}

	public function test_missing_pinned_release_does_not_fall_back_to_another(): void {
		$this->assertDirectoryDoesNotExist( $this->release_directory( self::TEST_VERSION ) );
		$this->create_release( '999.0.1-test' );

		$integration = $this->create_integration();

		$this->assertNull( $integration->get_latest_version() );
	}

	public function test_load_requires_the_pinned_release(): void {
		$this->create_release( self::TEST_VERSION );
		$this->create_release( '999.0.2-test' );

		$integration = $this->create_integration();
		$integration->activate();

		remove_all_actions( 'plugins_loaded' );
		$integration->load();
		do_action( 'plugins_loaded' );

		$this->assertTrue( $integration->is_active() );
		$this->assertSame( self::TEST_VERSION, constant( 'GIST_ANSWERS_VERSION' ) );
	}

	public function test_configure_passes_the_environment_config_to_the_plugin(): void {
		$config = [ 'api_token' => 'test-token' ];

		$integration = new GistAnswersIntegration( $this->slug );
		$integration->set_vip_config(
			new IntegrationVipConfig(
				$this->slug,
				[
					'env' => [
						'status' => Env_Integration_Status::ENABLED,
						'config' => $config,
					],
				]
			)
		);
		$integration->configure();

		$this->assertSame( $config, constant( 'VIP_GIST_ANSWERS_CONFIG' ) );
	}
}
