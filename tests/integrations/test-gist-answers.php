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
	private string $slug = 'gist-answers';

	/**
	 * Plugin files created by a test, removed in tearDown().
	 *
	 * @var string[]
	 */
	private array $fixtures = [];

	public function tearDown(): void {
		foreach ( $this->fixtures as $plugin_file ) {
			if ( file_exists( $plugin_file ) ) {
				// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink -- Remove the test-owned bundled plugin fixture.
				unlink( $plugin_file );
			}

			if ( is_dir( dirname( $plugin_file ) ) ) {
				// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir -- Remove the test-owned bundled plugin fixture directory.
				rmdir( dirname( $plugin_file ) );
			}
		}

		$this->fixtures = [];

		parent::tearDown();
	}

	/**
	 * Create vip-integrations/gist-answers-<version>/gist-answers.php, which defines GIST_ANSWERS_VERSION.
	 */
	private function create_release( string $version ): void {
		$directory = WPVIP_MU_PLUGIN_DIR . '/vip-integrations/gist-answers-' . $version;
		wp_mkdir_p( $directory );

		$plugin = <<<PHP
<?php
/**
 * Plugin Name: Gist Answers test fixture
 * Requires at least: 5.0
 */
\Automattic\VIP\Integrations\define( 'GIST_ANSWERS_VERSION', '{$version}' );
PHP;

		$plugin_file = $directory . '/gist-answers.php';
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents -- Test-owned bundled plugin fixture.
		file_put_contents( $plugin_file, $plugin );

		$this->fixtures[] = $plugin_file;
	}

	public function test_pinned_release_is_chosen_over_a_newer_one(): void {
		$this->create_release( GistAnswersIntegration::VERSION );
		$this->create_release( '999.0.0' );

		$integration = new GistAnswersIntegration( $this->slug );

		$this->assertSame( 'gist-answers-' . GistAnswersIntegration::VERSION, $integration->get_latest_version() );
	}

	public function test_missing_pinned_release_does_not_fall_back_to_another(): void {
		$this->create_release( '999.0.1' );

		$integration = new GistAnswersIntegration( $this->slug );

		$this->assertNull( $integration->get_latest_version() );
	}

	public function test_load_requires_the_pinned_release(): void {
		$this->create_release( GistAnswersIntegration::VERSION );
		$this->create_release( '999.0.2' );

		$integration = new GistAnswersIntegration( $this->slug );
		$integration->activate();

		remove_all_actions( 'plugins_loaded' );
		$integration->load();
		do_action( 'plugins_loaded' );

		$this->assertTrue( $integration->is_active() );
		$this->assertSame( GistAnswersIntegration::VERSION, constant( 'GIST_ANSWERS_VERSION' ) );
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
