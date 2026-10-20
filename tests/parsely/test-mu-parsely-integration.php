<?php

namespace Automattic\VIP\WP_Parsely_Integration;

use Automattic\VIP\Integrations\Integration;
use Automattic\VIP\Integrations\ParselyIntegration;
use WP_UnitTestCase;

use function Automattic\Test\Utils\get_class_property_as_public;
use function Automattic\Test\Utils\get_parsely_test_mode;
use function Automattic\Test\Utils\is_parsely_disabled;

function test_version() {
	$major_version = getenv( 'WPVIP_PARSELY_INTEGRATION_PLUGIN_VERSION' );
	return $major_version ?: SUPPORTED_VERSIONS[0];
}

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class MU_Parsely_Integration_Test extends WP_UnitTestCase {
	/**
	 * Tests that only have something to check once wp-parsely is loaded (the parsely workflow's enabled modes).
	 */
	private const REQUIRES_LOADED_PLUGIN = [
		'test_parsely_instance',
		'test_default_parsely_configs',
		'test_custom_parsely_configs',
		'test_parsely_configs_for_managed_mode',
		'test_unprotected_published_posts_show_meta',
	];

	/**
	 * Whether each test mode registers the `wpvip_parsely_load_mu` filter, and the `_wpvip_parsely_mu` option it sets.
	 */
	private const MODE_FILTER_AND_OPTION = [
		'disabled'                   => [ false, false ],
		'filter_enabled'             => [ true, false ],
		'filter_disabled'            => [ true, false ],
		'option_enabled'             => [ false, '1' ],
		'option_disabled'            => [ false, '0' ],
		'filter_and_option_enabled'  => [ true, '1' ],
		'filter_and_option_disabled' => [ true, '0' ],
	];

	protected static $test_mode;
	protected static $major_version;

	public function setName( string $name ): void {
		parent::setName( $name );

		// When wp-parsely is disabled these tests are skipped in setUp(). PHPUnit names the test right before it
		// applies `@runTestsInSeparateProcesses`, so opt out here to avoid booting WordPress again only to skip.
		if ( is_parsely_disabled() && in_array( $name, self::REQUIRES_LOADED_PLUGIN, true ) ) {
			$this->setRunTestInSeparateProcess( false );
		}
	}

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		self::$test_mode     = get_parsely_test_mode();
		self::$major_version = test_version();
	}

	public function setUp(): void {
		parent::setUp();

		if ( is_parsely_disabled() && in_array( $this->getName( false ), self::REQUIRES_LOADED_PLUGIN, true ) ) {
			$this->markTestSkipped( 'Requires wp-parsely to be loaded (see the parsely workflow).' );
		}
	}

	/**
	 * Assert the filter and option the bootstrap configured for the current test mode.
	 */
	private function assert_mode_filter_and_option(): void {
		if ( ! isset( self::MODE_FILTER_AND_OPTION[ self::$test_mode ] ) ) {
			$this->fail( 'Invalid test mode specified: ' . self::$test_mode );
		}

		[ $has_filter, $option ] = self::MODE_FILTER_AND_OPTION[ self::$test_mode ];
		$this->assertSame( $has_filter, has_filter( 'wpvip_parsely_load_mu' ) );
		$this->assertSame( $option, get_option( '_wpvip_parsely_mu' ) );
	}

	/**
	 * Loader configs for a freshly initialized plugin, with the given overrides.
	 */
	private function expected_configs( array $overrides = [] ): array {
		return array_merge( array(
			'is_pinned_version'            => has_filter( 'wpvip_parsely_version' ),
			'site_id'                      => '',
			'have_api_secret'              => false,
			'is_javascript_disabled'       => false,
			'is_autotracking_disabled'     => false,
			'should_track_logged_in_users' => false,
			'tracked_post_types'           => array(
				array(
					'name'       => 'post',
					'track_type' => 'post',
				),
				array(
					'name'       => 'page',
					'track_type' => 'non-post',
				),
				array(
					'name'       => 'attachment',
					'track_type' => 'do-not-track',
				),
			),
		), $overrides );
	}

	public function test_bootstrap_modes_without_constant() {
		$this->assertFalse( class_exists( 'Parsely' ) );
		$this->assertFalse( class_exists( 'Parsely\Parsely' ) );

		maybe_load_plugin();

		$this->assert_mode_filter_and_option();
		$expected_integration_types = [
			'disabled'                   => Parsely_Integration_Type::NONE,
			'filter_enabled'             => Parsely_Integration_Type::ENABLED_MUPLUGINS_FILTER,
			'filter_disabled'            => Parsely_Integration_Type::DISABLED_MUPLUGINS_FILTER,
			'filter_and_option_enabled'  => Parsely_Integration_Type::ENABLED_MUPLUGINS_FILTER,
			'filter_and_option_disabled' => Parsely_Integration_Type::DISABLED_MUPLUGINS_FILTER,
		];
		if ( ! isset( $expected_integration_types[ self::$test_mode ] ) ) {
			$this->fail( 'Invalid test mode specified: ' . self::$test_mode );
		}
		$this->assertSame( ! is_parsely_disabled(), Parsely_Loader_Info::is_active(), 'Expecting the plugin to be ' . ( is_parsely_disabled() ? 'inactive' : 'active' ) );
		$this->assertEquals( $expected_integration_types[ self::$test_mode ], Parsely_Loader_Info::get_integration_type() );
		$this->assertSame( ! is_parsely_disabled(), ( new ParselyIntegration( 'parsely' ) )->is_loaded() );

		if ( ! is_parsely_disabled() ) {
			$this->assertTrue( class_exists( 'Parsely\Parsely' ) );
			return;
		}

		$this->assertNull( Parsely_Loader_Info::get_configs() );
		$this->assertFalse( class_exists( 'Parsely' ) );
		$this->assertFalse( class_exists( 'Parsely\Parsely' ) );
		$this->assertFalse( is_callable( '\Parsely\parsely_initialize_plugin' ) );
		$this->assertFalse( isset( $GLOBALS['parsely'] ) );

		// Can only reliably test the defaults in "disabled" mode.
		if ( 'disabled' === self::$test_mode ) {
			$this->assertEquals( [], Parsely_Loader_Info::get_parsely_options() );
			$this->assertEquals( Parsely_Loader_Info::VERSION_UNKNOWN, Parsely_Loader_Info::get_version() );
		}
	}

	public function test_parsely_instance() {
		maybe_load_plugin();
		$this->assertFalse( isset( $GLOBALS['parsely'] ) );

		\Parsely\parsely_initialize_plugin();

		$classname = get_class( $GLOBALS['parsely'] );

		$this->assertEquals( 'Parsely\Parsely', $classname );

		$this->assertEquals( 1, preg_match( '/^(\d+\.\d+)(\.|$)/', $GLOBALS['parsely']::VERSION, $matches ) );
		$this->assertEquals( self::$major_version, $matches[1] );
	}

	public function test_bootstrap_modes_enabled_via_constant() {
		define( 'VIP_PARSELY_ENABLED', true );
		maybe_load_plugin();

		$this->assert_mode_filter_and_option();
		$this->assertTrue( Parsely_Loader_Info::is_active() );
		$this->assertEquals( Parsely_Integration_Type::ENABLED_CONSTANT, Parsely_Loader_Info::get_integration_type() );
	}

	public function test_bootstrap_modes_disabled_via_constant() {
		define( 'VIP_PARSELY_ENABLED', false );
		maybe_load_plugin();

		$this->assert_mode_filter_and_option();
		$this->assertFalse( Parsely_Loader_Info::is_active() );
		$this->assertEquals( Parsely_Integration_Type::DISABLED_CONSTANT, Parsely_Loader_Info::get_integration_type() );
	}

	public function test_default_parsely_configs() {
		maybe_load_plugin();

		\Parsely\parsely_initialize_plugin();

		$this->assertEquals( Parsely_Loader_Info::get_configs(), $this->expected_configs() );
	}

	public function test_custom_parsely_configs() {
		maybe_load_plugin();

		\Parsely\parsely_initialize_plugin();
		$current_settings = get_option( 'parsely' ) ?: [];
		update_option( 'parsely', array_merge( $current_settings, array(
			'apikey'                    => 'example.com',
			'api_secret'                => 'secret',
			'track_authenticated_users' => true,
			'disable_javascript'        => true,
			'disable_autotrack'         => true,
			'track_post_types'          => array( 'post' ),
			'track_page_types'          => array( 'page' ),
		) ) );

		$this->assertEquals( Parsely_Loader_Info::get_configs(), $this->expected_configs( array(
			'site_id'                      => 'example.com',
			'have_api_secret'              => true,
			'is_javascript_disabled'       => true,
			'is_autotracking_disabled'     => true,
			'should_track_logged_in_users' => true,
		) ) );
	}

	/**
	 * Verify Parse.ly data is correctly setting when the plugin is in Managed Mode.
	 *
	 * @return void
	 */
	public function test_parsely_configs_for_managed_mode() {
		maybe_load_plugin();

		// Arrange.
		$parsely_integration = new ParselyIntegration( 'parsely' );
		get_class_property_as_public( Integration::class, 'options' )->setValue( $parsely_integration, [
			'config' => [
				'site_id'    => 'site_id_value',
				'api_secret' => 'api_secret_value',
			],
		] );
		$parsely_integration->configure();

		// Act.
		\Parsely\parsely_initialize_plugin();
		$configs = Parsely_Loader_Info::get_configs();

		// Assert.
		$this->assertEquals( $this->expected_configs( array(
			'site_id'         => 'site_id_value',
			'have_api_secret' => true,
		) ), $configs );
	}

	/**
	 * Smoke test that the loaded plugin renders metadata for a published post.
	 */
	public function test_unprotected_published_posts_show_meta() {
		global $parsely;

		maybe_load_plugin();

		\Parsely\parsely_initialize_plugin();

		$post = [
			'post_title'   => 'Testing unprotected posts',
			'post_content' => 'stuff & things',
			'post_status'  => 'publish',
		];

		$post_id = wp_insert_post( $post, true );
		$option  = get_option( 'parsely' ) ?: [];
		update_option( 'parsely', array_merge( $option, [ 'apikey' => 'testing123' ] ) );

		$metadata = ( new \Parsely\Metadata( $parsely ) )->construct_metadata( get_post( $post_id ) );

		$this->assertIsArray( $metadata, 'post metadata should be an array' );
		$this->assertNotEmpty( $metadata, 'post metadata should not be empty' );
	}
}
