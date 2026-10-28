<?php
/**
 * Test: Generic integrations, loaded from the active-integrations mapping.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

// phpcs:disable Squiz.Commenting.ClassComment.Missing, Squiz.Commenting.FunctionComment.Missing, Squiz.Commenting.VariableComment.Missing
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.directory_mkdir, WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents -- temporary fixture files
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export -- writes a PHP fixture

use Automattic\Test\Constant_Mocker;
use Automattic\VIP\Feature;
use ReflectionClass;
use WP_UnitTestCase;

use function Automattic\Test\Utils\get_class_method_as_public;

require_once __DIR__ . '/fake-integration.php';

class Generic_Integrations_Test extends WP_UnitTestCase {
	private const SITE_ID = 1234;

	private array $original_feature_ids;
	private array $original_feature_percentages;
	private array $original_feature_envs;

	/** @var array<string,mixed> Feature's statics as declared in class-feature.php. */
	private array $declared_feature_defaults;

	/** @var string[] */
	private array $warnings = [];

	/** @var string[] */
	private array $temp_dirs = [];

	public function setUp(): void {
		parent::setUp();

		$this->original_feature_ids         = Feature::$feature_ids;
		$this->original_feature_percentages = Feature::$feature_percentages;
		$this->original_feature_envs        = Feature::$feature_envs;

		// Other suites (e.g. test-class-feature.php) overwrite Feature's statics without restoring
		// them, so start every test from the values declared in class-feature.php.
		$this->declared_feature_defaults = ( new ReflectionClass( Feature::class ) )->getDefaultProperties();
		Feature::$feature_ids            = $this->declared_feature_defaults['feature_ids'];
		Feature::$feature_percentages    = $this->declared_feature_defaults['feature_percentages'];
		Feature::$feature_envs           = $this->declared_feature_defaults['feature_envs'];

		Constant_Mocker::define( 'FILES_CLIENT_SITE_ID', self::SITE_ID );

		$this->warnings = [];
		set_error_handler( function ( int $errno, string $errstr ) {
			$this->warnings[] = $errstr;
			return true;
		}, E_USER_WARNING );
	}

	public function tearDown(): void {
		restore_error_handler();

		Feature::$feature_ids         = $this->original_feature_ids;
		Feature::$feature_percentages = $this->original_feature_percentages;
		Feature::$feature_envs        = $this->original_feature_envs;

		Constant_Mocker::clear();
		remove_all_filters( 'vip_integrations_pre_load_active_integrations' );
		remove_all_filters( 'vip_integrations_pre_load_config' );

		foreach ( $this->temp_dirs as $dir ) {
			array_map( 'unlink', glob( $dir . '/*/*' ) ?: [] );
			array_map( 'rmdir', glob( $dir . '/*', GLOB_ONLYDIR ) ?: [] );
			array_map( 'unlink', glob( $dir . '/*' ) ?: [] );
			rmdir( $dir );
		}

		parent::tearDown();
	}

	private function enable_feature_for_site(): void {
		Feature::$feature_ids[ GENERIC_INTEGRATIONS_FEATURE ] = [ self::SITE_ID => true ];
	}

	private function entry( string $folder = 'acme-sync', array $overrides = [] ): array {
		return array_merge(
			[
				'folder'           => $folder,
				'entry_file'       => $folder . '.php',
				'version'          => '1.2.0',
				'version_strategy' => 'latest',
				'scope'            => 'site',
			],
			$overrides
		);
	}

	private function use_mapping( $mapping ): void {
		add_filter( 'vip_integrations_pre_load_active_integrations', fn() => $mapping );
	}

	private function temp_dir(): string {
		$dir = sys_get_temp_dir() . '/vip-generic-integrations-' . wp_generate_password( 8, false );
		mkdir( $dir );
		$this->temp_dirs[] = $dir;

		return $dir;
	}

	/*
	|--------------------------------------------------------------------------
	| Kill switch
	|--------------------------------------------------------------------------
	*/

	public function test__feature_is_off_by_default(): void {
		// What class-feature.php declares, whatever earlier tests did to the statics.
		$this->assertArrayHasKey( GENERIC_INTEGRATIONS_FEATURE, $this->declared_feature_defaults['feature_ids'] );
		$this->assertSame( [], $this->declared_feature_defaults['feature_ids'][ GENERIC_INTEGRATIONS_FEATURE ] );
		$this->assertArrayNotHasKey( GENERIC_INTEGRATIONS_FEATURE, $this->declared_feature_defaults['feature_percentages'] );
		$this->assertArrayNotHasKey( GENERIC_INTEGRATIONS_FEATURE, $this->declared_feature_defaults['feature_envs'] );

		$this->assertFalse( generic_integrations_enabled() );
	}

	public function test__feature_is_on_for_an_enabled_environment(): void {
		$this->enable_feature_for_site();

		$this->assertTrue( generic_integrations_enabled() );
	}

	public function test__feature_stays_off_for_other_environments(): void {
		Feature::$feature_ids[ GENERIC_INTEGRATIONS_FEATURE ] = [ self::SITE_ID + 1 => true ];

		$this->assertFalse( generic_integrations_enabled() );
	}

	public function test__feature_is_off_for_an_environment_disabled_by_id(): void {
		Feature::$feature_ids[ GENERIC_INTEGRATIONS_FEATURE ]         = [ self::SITE_ID => false ];
		Feature::$feature_percentages[ GENERIC_INTEGRATIONS_FEATURE ] = 1;

		$this->assertFalse( generic_integrations_enabled() );
	}

	public function test__register_reads_nothing_and_registers_nothing_while_off(): void {
		$read = false;
		add_filter( 'vip_integrations_pre_load_active_integrations', function () use ( &$read ) {
			$read = true;
			return [
				'format'       => 1,
				'integrations' => [ 'acme-sync' => $this->entry() ],
			];
		} );
		$integrations = new Integrations();

		register_generic_integrations( $integrations );

		$this->assertFalse( $read );
		$this->assertSame( [], $integrations->get_all_integrations() );
	}

	public function test__register_registers_each_entry_while_on(): void {
		$this->enable_feature_for_site();
		$this->use_mapping( [
			'format'       => 1,
			'integrations' => [
				'acme-sync' => $this->entry(),
				'beta-sync' => $this->entry( 'beta-sync' ),
			],
		] );
		$integrations = new Integrations();

		register_generic_integrations( $integrations );

		$this->assertSame( [ 'acme-sync', 'beta-sync' ], array_keys( $integrations->get_all_integrations() ) );
		$this->assertInstanceOf( GenericIntegration::class, $integrations->get_integration( 'acme-sync' ) );
		$this->assertSame( 'beta-sync.php', $integrations->get_integration( 'beta-sync' )->get_entry_file() );
		$this->assertSame( [], $this->warnings );
	}

	public function test__register_keeps_a_hand_written_integration_with_the_same_slug(): void {
		$this->enable_feature_for_site();
		$this->use_mapping( [
			'format'       => 1,
			'integrations' => [ 'fake' => $this->entry( 'fake' ) ],
		] );
		$integrations = new Integrations();
		$hand_written = new FakeIntegration( 'fake' );
		$integrations->register( $hand_written );

		register_generic_integrations( $integrations );

		$this->assertSame( $hand_written, $integrations->get_integration( 'fake' ) );
		$this->assertSame( [], $this->warnings );
	}

	/*
	|--------------------------------------------------------------------------
	| Mapping
	|--------------------------------------------------------------------------
	*/

	public function test__mapping_keeps_only_the_keys_the_loader_uses(): void {
		$this->use_mapping( [
			'format'       => 1,
			'integrations' => [ 'acme-sync' => $this->entry( 'acme-sync', [ 'extra' => 'ignored' ] ) ],
		] );

		$this->assertSame( [ 'acme-sync' => $this->entry() ], get_active_integrations_mapping() );
	}

	public function data_unusable_mappings(): array {
		return [
			'no file'                         => [ null ],
			'a file with no return'           => [ 1 ],
			'not an array'                    => [ 'mapping' ],
			'no integrations'                 => [ [ 'format' => 1 ] ],
			'integrations that are not a map' => [
				[
					'format'       => 1,
					'integrations' => 'acme-sync',
				],
			],
		];
	}

	/**
	 * @dataProvider data_unusable_mappings
	 */
	public function test__an_unusable_mapping_gives_no_entries( $mapping ): void {
		if ( null !== $mapping ) {
			$this->use_mapping( $mapping );
		} else {
			Constant_Mocker::define( 'WPVIP_INTEGRATIONS_CONFIG_DIR', $this->temp_dir() );
		}

		$this->assertSame( [], get_active_integrations_mapping() );
		$this->assertSame( [], $this->warnings );
	}

	public function test__a_mapping_without_a_format_is_read_as_format_1(): void {
		$this->use_mapping( [ 'integrations' => [ 'acme-sync' => $this->entry() ] ] );

		$this->assertSame( [ 'acme-sync' => $this->entry() ], get_active_integrations_mapping() );
		$this->assertSame( [], $this->warnings );
	}

	public function data_invalid_formats(): array {
		return [
			'0'            => [ 0 ],
			'a string'     => [ '1' ],
			'a negative'   => [ -1 ],
			'not a scalar' => [ [ 1 ] ],
		];
	}

	/**
	 * @dataProvider data_invalid_formats
	 */
	public function test__a_mapping_with_an_invalid_format_is_still_read_with_a_warning( $format ): void {
		$this->use_mapping( [
			'format'       => $format,
			'integrations' => [ 'acme-sync' => $this->entry() ],
		] );

		$this->assertSame( [ 'acme-sync' => $this->entry() ], get_active_integrations_mapping() );
		$this->assertCount( 1, $this->warnings );
		$this->assertStringContainsString( 'invalid format', $this->warnings[0] );
	}

	public function test__a_newer_mapping_is_read_with_a_warning(): void {
		$this->use_mapping( [
			'format'       => ACTIVE_INTEGRATIONS_FORMAT + 1,
			'top_level'    => 'ignored',
			'integrations' => [
				'acme-sync' => $this->entry( 'acme-sync', [ 'added_later' => [ 'ignored' ] ] ),
			],
		] );

		$this->assertSame( [ 'acme-sync' => $this->entry() ], get_active_integrations_mapping() );
		$this->assertCount( 1, $this->warnings );
		$this->assertStringContainsString( 'format 2', $this->warnings[0] );
	}

	public function test__an_older_entry_without_the_optional_keys_gets_their_defaults(): void {
		$entry = $this->entry();
		unset( $entry['version_strategy'], $entry['scope'] );
		$this->use_mapping( [
			'format'       => 1,
			'integrations' => [ 'acme-sync' => $entry ],
		] );

		$entries = get_active_integrations_mapping();

		$this->assertSame( 'latest', $entries['acme-sync']['version_strategy'] );
		$this->assertSame( 'site', $entries['acme-sync']['scope'] );
		$this->assertSame( [], $this->warnings );
	}

	public function test__only_keys_in_every_format_are_required(): void {
		$this->assertSame( [ 'folder', 'entry_file', 'version' ], array_keys( ACTIVE_INTEGRATION_REQUIRED_KEYS ) );
	}

	public function data_invalid_entries(): array {
		return [
			'not an array'                  => [ 'acme-sync', 'acme-sync' ],
			'an invalid slug'               => [ 'Acme_Sync', $this->entry() ],
			'a slug longer than 30'         => [ str_repeat( 'a', 31 ), $this->entry() ],
			'a slug with a newline'         => [ "acme-sync\n", $this->entry() ],
			'a missing folder'              => [ 'acme-sync', array_diff_key( $this->entry(), [ 'folder' => true ] ) ],
			'a missing entry file'          => [ 'acme-sync', array_diff_key( $this->entry(), [ 'entry_file' => true ] ) ],
			'a missing version'             => [ 'acme-sync', array_diff_key( $this->entry(), [ 'version' => true ] ) ],
			'a null version strategy'       => [ 'acme-sync', $this->entry( 'acme-sync', [ 'version_strategy' => null ] ) ],
			'a folder with a path'          => [ 'acme-sync', $this->entry( 'acme-sync', [ 'folder' => '../acme' ] ) ],
			'a folder with a newline'       => [ 'acme-sync', $this->entry( 'acme-sync', [ 'folder' => "acme-sync\n" ] ) ],
			'an entry file with a path'     => [ 'acme-sync', $this->entry( 'acme-sync', [ 'entry_file' => '../../wp-config.php' ] ) ],
			'an entry file that isn\'t php' => [ 'acme-sync', $this->entry( 'acme-sync', [ 'entry_file' => 'acme-sync.inc' ] ) ],
			'a version that isn\'t semver'  => [ 'acme-sync', $this->entry( 'acme-sync', [ 'version' => 'latest' ] ) ],
			'a version strategy'            => [ 'acme-sync', $this->entry( 'acme-sync', [ 'version_strategy' => 'Latest!' ] ) ],
			'an unknown scope'              => [ 'acme-sync', $this->entry( 'acme-sync', [ 'scope' => 'org' ] ) ],
			'a value that isn\'t a string'  => [ 'acme-sync', $this->entry( 'acme-sync', [ 'version' => 1.2 ] ) ],
		];
	}

	/**
	 * @dataProvider data_invalid_entries
	 */
	public function test__an_invalid_entry_is_skipped_with_a_warning( string $slug, $entry ): void {
		$this->use_mapping( [
			'format'       => 1,
			'integrations' => [
				$slug       => $entry,
				'beta-sync' => $this->entry( 'beta-sync' ),
			],
		] );

		$this->assertSame( [ 'beta-sync' ], array_keys( get_active_integrations_mapping() ) );
		$this->assertCount( 1, $this->warnings );
	}

	public function test__mapping_is_read_from_the_integrations_config_dir(): void {
		$dir = $this->temp_dir();
		Constant_Mocker::define( 'WPVIP_INTEGRATIONS_CONFIG_DIR', $dir );
		file_put_contents(
			$dir . '/' . ACTIVE_INTEGRATIONS_FILE,
			'<?php return ' . var_export( [
				'format'       => 1,
				'integrations' => [ 'acme-sync' => $this->entry() ],
			], true ) . ';' // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		);

		$this->assertSame( [ 'acme-sync' => $this->entry() ], get_active_integrations_mapping() );
	}

	public function test__mapping_is_read_from_the_default_config_dir_without_the_constant(): void {
		// Nothing is there in the test environment.
		$this->assertNull( read_active_integrations_file() );
	}

	public function test__a_mapping_file_with_no_return_gives_no_entries(): void {
		$dir = $this->temp_dir();
		Constant_Mocker::define( 'WPVIP_INTEGRATIONS_CONFIG_DIR', $dir );
		// What vip-wpcloud-externals' static file does when VIP_ACTIVE_INTEGRATIONS isn't defined.
		file_put_contents( $dir . '/' . ACTIVE_INTEGRATIONS_FILE, "<?php\nif ( false ) {\n\treturn [];\n}\n" );

		$this->assertSame( [], get_active_integrations_mapping() );
	}

	/*
	|--------------------------------------------------------------------------
	| GenericIntegration
	|--------------------------------------------------------------------------
	*/

	public function data_version_selection(): array {
		$versions = [
			'acme-sync-2.0' => '2.0',
			'acme-sync-1.2' => '1.2',
			'acme-sync-1.1' => '1.1',
		];

		return [
			'latest takes the newest'                      => [ 'latest', '1.2.0', $versions, 'acme-sync-2.0' ],
			'a pinned version matches its major.minor'     => [ 'semantic_versioning', '1.2.0', $versions, 'acme-sync-1.2' ],
			'a pinned version matches exactly first'       => [ 'semantic_versioning', '1.1.0', [ 'acme-sync-1.1.0' => '1.1.0' ] + $versions, 'acme-sync-1.1.0' ],
			'a pinned version with no bundle takes newest' => [ 'semantic_versioning', '3.0.0', $versions, 'acme-sync-2.0' ],
			'no bundles'                                   => [ 'latest', '1.2.0', [], null ],
		];
	}

	/**
	 * @dataProvider data_version_selection
	 */
	public function test__selects_the_bundle_folder( string $strategy, string $version, array $versions, ?string $expected ): void {
		$integration = new GenericIntegration(
			'acme-sync',
			$this->entry( 'acme-sync', [
				'version'          => $version,
				'version_strategy' => $strategy,
			] )
		);

		$this->assertSame( $expected, $integration->get_selected_version_folder( $versions ) );
	}

	public function test__only_counts_bundles_of_its_own_folder(): void {
		$integration = new GenericIntegration( 'sync', $this->entry( 'sync' ) );

		$this->assertSame(
			[ 'sync-1.0' => '1.0' ],
			$integration->filter_versions( [
				'acme-sync-2.0' => 'acme-2.0',
				'sync-1.0'      => '1.0',
				'sync-abc'      => 'abc',
			] )
		);
	}

	public function test__has_no_load_path_without_a_bundle(): void {
		$integration = $this->getMockBuilder( GenericIntegration::class )
			->setConstructorArgs( [ 'acme-sync', $this->entry() ] )
			->onlyMethods( [ 'get_versions' ] )
			->getMock();
		$integration->method( 'get_versions' )->willReturn( [] );

		$this->assertNull( $integration->get_load_path() );
	}

	public function test__is_not_loaded_until_its_plugin_file_is_included(): void {
		// A slug no other test uses: an included file stays included for the rest of the run.
		$integration = new GenericIntegration( 'loaded-sync', $this->entry( 'loaded-sync' ) );
		$this->assertFalse( $integration->is_loaded() );

		$dir = $this->temp_dir();
		mkdir( $dir . '/loaded-sync' );
		file_put_contents( $dir . '/loaded-sync/loaded-sync.php', '<?php' );
		require_once $dir . '/loaded-sync/loaded-sync.php';

		$this->assertTrue( $integration->is_loaded() );
	}

	public function test__a_bundle_from_vip_integrations_does_not_count_as_loaded(): void {
		$integration = new GenericIntegration( 'bundled-sync', $this->entry( 'bundled-sync' ) );

		$dir = $this->temp_dir();
		mkdir( $dir . '/bundled-sync-1.2' );
		file_put_contents( $dir . '/bundled-sync-1.2/bundled-sync.php', '<?php' );
		require_once $dir . '/bundled-sync-1.2/bundled-sync.php';

		$this->assertFalse( $integration->is_loaded() );
	}

	/**
	 * A GenericIntegration whose bundles are the given folders in a temp dir, each with an entry
	 * file when `$with_entry_file`.
	 */
	private function integration_with_bundles( string $slug, array $versions, bool $with_entry_file = true ): GenericIntegration {
		$dir = $this->temp_dir();
		foreach ( array_keys( $versions ) as $folder ) {
			mkdir( $dir . '/' . $folder );
			if ( $with_entry_file ) {
				file_put_contents( $dir . '/' . $folder . '/' . $slug . '.php', '<?php' );
			}
		}

		$integration = $this->getMockBuilder( GenericIntegration::class )
			->setConstructorArgs( [ $slug, $this->entry( $slug ) ] )
			->onlyMethods( [ 'get_versions', 'get_bundle_path' ] )
			->getMock();
		$integration->method( 'get_versions' )->willReturn( $versions );
		$integration->method( 'get_bundle_path' )->willReturnCallback( fn( $folder ) => $dir . '/' . $folder . '/' . $slug . '.php' );

		return $integration;
	}

	public function test__load_loads_the_bundle_on_plugins_loaded(): void {
		$integration = new GenericIntegration( 'acme-sync', $this->entry() );
		$integration->load();

		$this->assertSame( 1, has_action( 'plugins_loaded', [ $integration, 'load_bundle' ] ) );
	}

	public function test__has_the_load_path_of_the_selected_bundle(): void {
		$integration = $this->integration_with_bundles( 'path-sync', [
			'path-sync-2.0' => '2.0',
			'path-sync-1.2' => '1.2',
		] );

		$this->assertStringEndsWith( '/path-sync-2.0/path-sync.php', $integration->get_load_path() );
	}

	public function test__has_no_load_path_when_the_bundle_has_no_entry_file(): void {
		$integration = $this->integration_with_bundles( 'empty-sync', [ 'empty-sync-1.2' => '1.2' ], false );

		$this->assertNull( $integration->get_load_path() );
	}

	public function test__load_bundle_requires_the_selected_bundle(): void {
		// A slug no other test uses: an included file stays included for the rest of the run.
		$integration = $this->integration_with_bundles( 'required-sync', [ 'required-sync-1.2' => '1.2' ] );
		$integration->activate();

		$integration->load_bundle();

		$this->assertContains( $integration->get_load_path(), get_included_files() );
		$this->assertTrue( $integration->is_active() );
	}

	public function test__load_bundle_deactivates_without_a_bundle(): void {
		$integration = $this->integration_with_bundles( 'nothing-sync', [] );
		$integration->activate();

		$integration->load_bundle();

		$this->assertFalse( $integration->is_active() );
	}

	public function test__load_bundle_leaves_a_plugin_loaded_some_other_way(): void {
		$dir = $this->temp_dir();
		mkdir( $dir . '/elsewhere-sync' );
		file_put_contents( $dir . '/elsewhere-sync/elsewhere-sync.php', '<?php' );
		require_once $dir . '/elsewhere-sync/elsewhere-sync.php';

		$integration = $this->integration_with_bundles( 'elsewhere-sync', [ 'elsewhere-sync-1.2' => '1.2' ] );

		$integration->load_bundle();

		$this->assertNotContains( $integration->get_load_path(), get_included_files() );
	}

	public function test__bundle_path_is_in_vip_integrations(): void {
		$integration = new GenericIntegration( 'acme-sync', $this->entry() );

		$this->assertSame(
			WPVIP_MU_PLUGIN_DIR . '/vip-integrations/acme-sync-1.2/acme-sync.php',
			$integration->get_bundle_path( 'acme-sync-1.2' )
		);
	}

	public function test__has_no_versions_without_bundles_in_vip_integrations(): void {
		$this->assertSame( [], ( new GenericIntegration( 'no-bundle-sync', $this->entry( 'no-bundle-sync' ) ) )->get_versions() );
	}

	public function test__exposes_its_mapping_entry(): void {
		$integration = new GenericIntegration(
			'acme-sync',
			$this->entry( 'acme-sync', [
				'version_strategy' => 'semantic_versioning',
				'scope'            => 'network',
			] )
		);

		$this->assertSame(
			[ 'acme-sync', 'acme-sync.php', '1.2.0', 'semantic_versioning', 'network' ],
			[
				$integration->get_folder(),
				$integration->get_entry_file(),
				$integration->get_version(),
				$integration->get_version_strategy(),
				$integration->get_scope(),
			]
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Config
	|--------------------------------------------------------------------------
	*/

	public function test__only_a_generic_integration_gets_the_config_with_the_fallback(): void {
		$integrations = new Integrations();
		$integrations->register( new FakeIntegration( 'fake' ) );
		$integrations->register( new GenericIntegration( 'acme-sync', $this->entry() ) );
		$method = get_class_method_as_public( Integrations::class, 'get_integration_vip_config' );

		$this->assertInstanceOf( GenericIntegrationVipConfig::class, $method->invoke( $integrations, 'acme-sync' ) );
		$this->assertSame( IntegrationVipConfig::class, get_class( $method->invoke( $integrations, 'fake' ) ) );
	}

	public function test__config_falls_back_to_the_vip_integrations_constant(): void {
		$config = [
			'type' => 'acme-sync',
			'env'  => [
				'status' => 'enabled',
				'config' => [ 'key' => 'value' ],
			],
		];
		Constant_Mocker::define( 'VIP_INTEGRATIONS', [ 'acme-sync' => $config ] );

		$vip_config = new GenericIntegrationVipConfig( 'acme-sync' );

		$this->assertTrue( $vip_config->is_active_via_vip() );
		$this->assertSame( [ 'key' => 'value' ], $vip_config->get_env_config() );
	}

	public function test__a_hand_written_integration_does_not_fall_back_to_the_constant(): void {
		Constant_Mocker::define( 'VIP_INTEGRATIONS', [ 'fake' => [ 'env' => [ 'status' => 'enabled' ] ] ] );

		$this->assertFalse( ( new IntegrationVipConfig( 'fake' ) )->is_active_via_vip() );
	}

	public function test__config_from_the_file_wins_over_the_constant(): void {
		Constant_Mocker::define( 'VIP_INTEGRATIONS', [ 'acme-sync' => [ 'env' => [ 'status' => 'enabled' ] ] ] );
		add_filter( 'vip_integrations_pre_load_config', fn() => [ 'env' => [ 'status' => 'disabled' ] ] );

		$this->assertFalse( ( new GenericIntegrationVipConfig( 'acme-sync' ) )->is_active_via_vip() );
	}

	public function data_no_fallback(): array {
		return [
			'no constant'               => [ null ],
			'a constant without it'     => [ [ 'beta-sync' => [ 'env' => [ 'status' => 'enabled' ] ] ] ],
			'a constant that is a list' => [ 'acme-sync' ],
			'a slice that is not a map' => [ [ 'acme-sync' => 'enabled' ] ],
		];
	}

	/**
	 * @dataProvider data_no_fallback
	 */
	public function test__config_is_empty_without_a_usable_fallback( $constant ): void {
		if ( null !== $constant ) {
			Constant_Mocker::define( 'VIP_INTEGRATIONS', $constant );
		}

		$this->assertFalse( ( new GenericIntegrationVipConfig( 'acme-sync' ) )->is_active_via_vip() );
	}

	/*
	|--------------------------------------------------------------------------
	| Config constant
	|--------------------------------------------------------------------------
	*/

	public function data_config_constant_names(): array {
		return [
			'one word'      => [ 'acme', 'VIP_ACME_CONFIG' ],
			'hyphenated'    => [ 'acme-sync', 'VIP_ACME_SYNC_CONFIG' ],
			'with a number' => [ 'acme-sync-2', 'VIP_ACME_SYNC_2_CONFIG' ],
		];
	}

	/**
	 * @dataProvider data_config_constant_names
	 */
	public function test__config_constant_is_derived_from_the_slug( string $slug, string $expected ): void {
		$this->assertSame( $expected, ( new GenericIntegration( $slug, $this->entry( $slug ) ) )->get_config_constant_name() );
	}

	private function configured_integration( string $scope, array $config ): GenericIntegration {
		add_filter( 'vip_integrations_pre_load_config', fn() => $config );

		$integration = new GenericIntegration( 'acme-sync', $this->entry( 'acme-sync', [ 'scope' => $scope ] ) );
		$integration->set_vip_config( new GenericIntegrationVipConfig( 'acme-sync' ) );

		return $integration;
	}

	private function acme_sync_config(): array {
		return [
			'env'           => [
				'status' => 'enabled',
				'config' => [
					'api_key' => 'env-key',
					'retries' => 3,
					'enabled' => false,
				],
			],
			'network_sites' => [
				get_current_blog_id() => [
					'status' => 'enabled',
					'config' => [ 'api_key' => 'site-key' ],
				],
			],
		];
	}

	public function test__configure_defines_the_constant_from_the_env_config(): void {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'On multisite a site integration reads the network site config.' );
		}

		$this->configured_integration( 'site', $this->acme_sync_config() )->configure();

		// Values keep the types GOOP wrote.
		$this->assertSame(
			[
				'api_key' => 'env-key',
				'retries' => 3,
				'enabled' => false,
			],
			Constant_Mocker::constant( 'VIP_ACME_SYNC_CONFIG' )
		);
	}

	public function test__configure_gives_a_site_integration_its_network_site_config_on_multisite(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Only on multisite.' );
		}

		$this->configured_integration( 'site', $this->acme_sync_config() )->configure();

		$this->assertSame( [ 'api_key' => 'site-key' ], Constant_Mocker::constant( 'VIP_ACME_SYNC_CONFIG' ) );
	}

	public function test__configure_gives_a_network_integration_the_env_config(): void {
		$this->configured_integration( 'network', $this->acme_sync_config() )->configure();

		$this->assertSame( 'env-key', Constant_Mocker::constant( 'VIP_ACME_SYNC_CONFIG' )['api_key'] );
	}

	public function test__configure_defines_an_empty_config_when_there_is_none(): void {
		$this->configured_integration( 'network', [ 'env' => [ 'status' => 'enabled' ] ] )->configure();

		$this->assertSame( [], Constant_Mocker::constant( 'VIP_ACME_SYNC_CONFIG' ) );
	}

	public function test__configure_keeps_a_constant_that_is_already_defined(): void {
		Constant_Mocker::define( 'VIP_ACME_SYNC_CONFIG', [ 'api_key' => 'from-vip-config' ] );

		$this->configured_integration( 'network', $this->acme_sync_config() )->configure();

		$this->assertSame( [ 'api_key' => 'from-vip-config' ], Constant_Mocker::constant( 'VIP_ACME_SYNC_CONFIG' ) );
	}

	public function test__activating_platform_integrations_defines_the_constant(): void {
		$this->enable_feature_for_site();
		$this->use_mapping( [ 'integrations' => [ 'acme-sync' => $this->entry( 'acme-sync', [ 'scope' => 'network' ] ) ] ] );
		add_filter( 'vip_integrations_pre_load_config', fn( $config, $path, $slug ) => 'acme-sync' === $slug ? $this->acme_sync_config() : $config, 10, 3 );

		$integrations = new Integrations();
		register_generic_integrations( $integrations );
		$integrations->activate_platform_integrations();

		$this->assertTrue( $integrations->get_integration( 'acme-sync' )->is_active() );
		$this->assertSame( 'env-key', Constant_Mocker::constant( 'VIP_ACME_SYNC_CONFIG' )['api_key'] );
	}

	/*
	|--------------------------------------------------------------------------
	| Load outcome
	|--------------------------------------------------------------------------
	*/

	public function test__status_is_inactive_before_activation(): void {
		$status = ( new GenericIntegration( 'acme-sync', $this->entry() ) )->get_runtime_status();

		$this->assertSame(
			[
				'slug'             => 'acme-sync',
				'folder'           => 'acme-sync',
				'version'          => '1.2.0',
				'version_strategy' => 'latest',
				'scope'            => 'site',
				'active'           => false,
				'status'           => 'inactive',
				'bundle'           => null,
			],
			$status
		);
	}

	public function test__status_is_pending_until_the_bundle_is_loaded(): void {
		$integration = new GenericIntegration( 'acme-sync', $this->entry() );
		$integration->activate();

		$this->assertSame( 'pending', $integration->get_runtime_status()['status'] );
	}

	public function test__status_reports_the_bundle_it_loaded(): void {
		// A slug no other test uses: an included file stays included for the rest of the run.
		$integration = $this->integration_with_bundles( 'status-sync', [
			'status-sync-2.0' => '2.0',
			'status-sync-1.2' => '1.2',
		] );
		$integration->activate();

		$integration->load_bundle();

		$status = $integration->get_runtime_status();
		$this->assertTrue( $status['active'] );
		$this->assertSame( 'loaded', $status['status'] );
		$this->assertSame( 'status-sync-2.0', $status['bundle'] );
	}

	public function test__status_reports_a_missing_bundle_and_deactivates(): void {
		$integration = $this->integration_with_bundles( 'missing-sync', [ 'missing-sync-1.2' => '1.2' ], false );
		$integration->activate();

		$integration->load_bundle();

		$status = $integration->get_runtime_status();
		$this->assertFalse( $status['active'] );
		$this->assertSame( 'missing_bundle', $status['status'] );
		$this->assertNull( $status['bundle'] );
	}

	public function test__status_reports_a_plugin_loaded_some_other_way(): void {
		$dir = $this->temp_dir();
		mkdir( $dir . '/elsewhere-sync' );
		file_put_contents( $dir . '/elsewhere-sync/elsewhere-sync.php', '<?php' );
		require_once $dir . '/elsewhere-sync/elsewhere-sync.php';

		$integration = $this->integration_with_bundles( 'elsewhere-sync', [ 'elsewhere-sync-1.2' => '1.2' ] );
		$integration->load_bundle();

		$this->assertSame( 'already_loaded', $integration->get_runtime_status()['status'] );
		$this->assertNull( $integration->get_runtime_status()['bundle'] );
	}

	public function test__status_holds_no_config(): void {
		$integration = $this->configured_integration( 'network', $this->acme_sync_config() );
		$integration->activate();

		$this->assertStringNotContainsString( 'env-key', wp_json_encode( $integration->get_runtime_status() ) );
	}
}
