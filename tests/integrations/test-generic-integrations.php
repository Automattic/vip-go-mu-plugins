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
use WP_UnitTestCase;

use function Automattic\Test\Utils\get_class_method_as_public;

require_once __DIR__ . '/fake-integration.php';

class Generic_Integrations_Test extends WP_UnitTestCase {
	private const SITE_ID = 1234;

	private array $original_feature_ids;
	private array $original_feature_percentages;
	private array $original_feature_envs;

	/** @var string[] */
	private array $warnings = [];

	/** @var string[] */
	private array $temp_dirs = [];

	public function setUp(): void {
		parent::setUp();

		$this->original_feature_ids         = Feature::$feature_ids;
		$this->original_feature_percentages = Feature::$feature_percentages;
		$this->original_feature_envs        = Feature::$feature_envs;

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
		$this->assertArrayHasKey( GENERIC_INTEGRATIONS_FEATURE, $this->original_feature_ids );
		$this->assertSame( [], $this->original_feature_ids[ GENERIC_INTEGRATIONS_FEATURE ] );
		$this->assertArrayNotHasKey( GENERIC_INTEGRATIONS_FEATURE, $this->original_feature_percentages );
		$this->assertArrayNotHasKey( GENERIC_INTEGRATIONS_FEATURE, $this->original_feature_envs );

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
				'version'      => 1,
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
			'version'      => 1,
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
			'version'      => 1,
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
			'version'      => 1,
			'integrations' => [ 'acme-sync' => $this->entry( 'acme-sync', [ 'extra' => 'ignored' ] ) ],
		] );

		$this->assertSame( [ 'acme-sync' => $this->entry() ], get_active_integrations_mapping() );
	}

	public function data_unusable_mappings(): array {
		return [
			'no file'                         => [ null ],
			'a file with no return'           => [ 1 ],
			'not an array'                    => [ 'mapping' ],
			'no version'                      => [ [ 'integrations' => [] ] ],
			'version 0'                       => [
				[
					'version'      => 0,
					'integrations' => [ 'acme-sync' => $this->entry() ],
				],
			],
			'the version as a string'         => [
				[
					'version'      => '1',
					'integrations' => [],
				],
			],
			'integrations that are not a map' => [
				[
					'version'      => 1,
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

	public function test__a_newer_mapping_is_read_with_a_warning(): void {
		$this->use_mapping( [
			'version'      => ACTIVE_INTEGRATIONS_FORMAT_VERSION + 1,
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
			'version'      => 1,
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
			'version'      => 1,
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
				'version'      => 1,
				'integrations' => [ 'acme-sync' => $this->entry() ],
			], true ) . ';' // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		);

		$this->assertSame( [ 'acme-sync' => $this->entry() ], get_active_integrations_mapping() );
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
}
