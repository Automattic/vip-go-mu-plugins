<?php

namespace Automattic\VIP\WP_Parsely_Integration;

use Automattic\VIP\Integrations\Integration;
use Automattic\VIP\Integrations\ParselyIntegration;
use Parsely\UI\Row_Actions;
use WP_UnitTestCase;

use function Automattic\Test\Utils\get_class_property_as_public;
use function Automattic\Test\Utils\get_parsely_test_mode;
use function Automattic\Test\Utils\is_parsely_disabled;

function test_version() {
	$major_version = getenv( 'WPVIP_PARSELY_INTEGRATION_PLUGIN_VERSION' );
	return $major_version ?: SUPPORTED_VERSIONS[0];
}

/**
 * Checks if the version of the Parse.ly is latest or not.
 *
 * @return boolean
 */
function is_latest_version() {
	return test_version() === SUPPORTED_VERSIONS[0];
}

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class MU_Parsely_Integration_Test extends WP_UnitTestCase {
	protected static $test_mode;
	protected static $major_version;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		self::$test_mode     = get_parsely_test_mode();
		self::$major_version = test_version();
	}

	public function test_parsely_loader_info_defaults() {
		maybe_load_plugin();

		// Can only reliably test the defaults in "disabled" mode.
		if ( 'disabled' === self::$test_mode ) {
			$this->assertFalse( Parsely_Loader_Info::is_active() );
			$this->assertEquals( Parsely_Integration_Type::NONE, Parsely_Loader_Info::get_integration_type() );
			$this->assertEquals( [], Parsely_Loader_Info::get_parsely_options() );
			$this->assertEquals( Parsely_Loader_Info::VERSION_UNKNOWN, Parsely_Loader_Info::get_version() );
		} elseif ( is_parsely_disabled() ) {
			$this->assertFalse( Parsely_Loader_Info::is_active() );
		} else {
			$this->assertTrue( Parsely_Loader_Info::is_active() );
		}
	}

	public function test_is_queued_for_activation_get() {
		set_current_screen( 'plugins' );

		$this->set_get_globals_for_parsely_activation();
		$this->assertTrue( is_queued_for_activation() );
	}

	public function test_is_queued_for_activation_post() {
		set_current_screen( 'plugins' );

		$this->set_post_globals_for_parsely_activation();
		$this->assertTrue( is_queued_for_activation() );
	}

	public function test_is_not_queued_for_activation() {
		set_current_screen( 'plugins' );

		$this->assertFalse( is_queued_for_activation() );
	}

	public function test_parsely_class_existance() {
		$this->assertFalse( class_exists( 'Parsely' ) );
		$this->assertFalse( class_exists( 'Parsely\Parsely' ) );

		maybe_load_plugin();

		if ( ! is_parsely_disabled() ) {
			$this->assertTrue( class_exists( 'Parsely\Parsely' ) );
			return;
		}

		$this->assertFalse( class_exists( 'Parsely' ) );
		$this->assertFalse( class_exists( 'Parsely\Parsely' ) );
	}

	public function test_parsely_instance() {
		maybe_load_plugin();
		$this->assertFalse( isset( $GLOBALS['parsely'] ) );

		if ( is_parsely_disabled() ) {
			return;
		}

		\Parsely\parsely_initialize_plugin();

		$classname = get_class( $GLOBALS['parsely'] );

		$this->assertEquals( 'Parsely\Parsely', $classname );

		$this->assertEquals( 1, preg_match( '/^(\d+\.\d+)(\.|$)/', $GLOBALS['parsely']::VERSION, $matches ) );
		$this->assertEquals( self::$major_version, $matches[1] );
	}

	public function test_bootstrap_modes_enabled_without_constant() {
		maybe_load_plugin();
		switch ( self::$test_mode ) {
			case 'disabled':
				$this->assertFalse( has_filter( 'wpvip_parsely_load_mu' ) );
				$this->assertFalse( get_option( '_wpvip_parsely_mu' ) );
				$this->assertFalse(
					Parsely_Loader_Info::is_active(),
					'Expecting the plugin to be inactive'
				);
				$this->assertEquals( Parsely_Integration_Type::NONE, Parsely_Loader_Info::get_integration_type() );
				break;
			case 'filter_enabled':
				$this->assertTrue( has_filter( 'wpvip_parsely_load_mu' ) );
				$this->assertFalse( get_option( '_wpvip_parsely_mu' ) );
				$this->assertTrue(
					Parsely_Loader_Info::is_active(),
					'Expecting wp-parsely plugin to be enabled by the filter.'
				);
				$this->assertEquals( Parsely_Integration_Type::ENABLED_MUPLUGINS_FILTER, Parsely_Loader_Info::get_integration_type() );
				break;
			case 'filter_disabled':
				$this->assertTrue( has_filter( 'wpvip_parsely_load_mu' ) );
				$this->assertFalse( get_option( '_wpvip_parsely_mu' ) );
				$this->assertFalse(
					Parsely_Loader_Info::is_active(),
					'Expecting wp-parsely plugin to be disabled by the filter.'
				);
				$this->assertEquals( Parsely_Integration_Type::DISABLED_MUPLUGINS_FILTER, Parsely_Loader_Info::get_integration_type() );
				break;
			case 'filter_and_option_enabled':
				$this->assertTrue( has_filter( 'wpvip_parsely_load_mu' ) );
				$this->assertSame( '1', get_option( '_wpvip_parsely_mu' ) );
				$this->assertTrue(
					Parsely_Loader_Info::is_active(),
					'Expecting wp-parsely plugin to be enabled by the filter overriding the option.'
				);
				$this->assertEquals( Parsely_Integration_Type::ENABLED_MUPLUGINS_FILTER, Parsely_Loader_Info::get_integration_type() );
				break;
			case 'filter_and_option_disabled':
				$this->assertTrue( has_filter( 'wpvip_parsely_load_mu' ) );
				$this->assertSame( '0', get_option( '_wpvip_parsely_mu' ) );
				$this->assertFalse(
					Parsely_Loader_Info::is_active(),
					'Expecting wp-parsely plugin to be disabled by the filter overriding the option.'
				);
				$this->assertEquals( Parsely_Integration_Type::DISABLED_MUPLUGINS_FILTER, Parsely_Loader_Info::get_integration_type() );
				break;
			default:
				$this->fail( 'Invalid test mode specified: ' . self::$test_mode );
		}
	}

	public function test_bootstrap_modes_enabled_via_constant() {
		define( 'VIP_PARSELY_ENABLED', true );
		maybe_load_plugin();

		switch ( self::$test_mode ) {
			case 'disabled':
				$this->assertFalse( has_filter( 'wpvip_parsely_load_mu' ) );
				$this->assertFalse( get_option( '_wpvip_parsely_mu' ) );
				break;
			case 'filter_enabled':
				$this->assertTrue( has_filter( 'wpvip_parsely_load_mu' ) );
				$this->assertFalse( get_option( '_wpvip_parsely_mu' ) );
				break;
			case 'filter_disabled':
				$this->assertTrue( has_filter( 'wpvip_parsely_load_mu' ) );
				$this->assertFalse( get_option( '_wpvip_parsely_mu' ) );
				break;
			case 'option_enabled':
				$this->assertFalse( has_filter( 'wpvip_parsely_load_mu' ) );
				$this->assertSame( '1', get_option( '_wpvip_parsely_mu' ) );
				break;
			case 'option_disabled':
				$this->assertFalse( has_filter( 'wpvip_parsely_load_mu' ) );
				$this->assertSame( '0', get_option( '_wpvip_parsely_mu' ) );
				break;
			case 'filter_and_option_enabled':
				$this->assertTrue( has_filter( 'wpvip_parsely_load_mu' ) );
				$this->assertSame( '1', get_option( '_wpvip_parsely_mu' ) );
				break;
			case 'filter_and_option_disabled':
				$this->assertTrue( has_filter( 'wpvip_parsely_load_mu' ) );
				$this->assertSame( '0', get_option( '_wpvip_parsely_mu' ) );
				break;
			default:
				$this->fail( 'Invalid test mode specified: ' . self::$test_mode );
		}

		$this->assertTrue( Parsely_Loader_Info::is_active() );
		$this->assertEquals( Parsely_Integration_Type::ENABLED_CONSTANT, Parsely_Loader_Info::get_integration_type() );
	}

	public function test_bootstrap_modes_disabled_via_constant() {
		define( 'VIP_PARSELY_ENABLED', false );
		maybe_load_plugin();

		switch ( self::$test_mode ) {
			case 'disabled':
				$this->assertFalse( has_filter( 'wpvip_parsely_load_mu' ) );
				$this->assertFalse( get_option( '_wpvip_parsely_mu' ) );
				break;
			case 'filter_enabled':
				$this->assertTrue( has_filter( 'wpvip_parsely_load_mu' ) );
				$this->assertFalse( get_option( '_wpvip_parsely_mu' ) );
				break;
			case 'filter_disabled':
				$this->assertTrue( has_filter( 'wpvip_parsely_load_mu' ) );
				$this->assertFalse( get_option( '_wpvip_parsely_mu' ) );
				break;
			default:
				$this->fail( 'Invalid test mode specified: ' . self::$test_mode );
		}

		$this->assertFalse( Parsely_Loader_Info::is_active() );
		$this->assertEquals( Parsely_Integration_Type::DISABLED_CONSTANT, Parsely_Loader_Info::get_integration_type() );
	}

	public function test_default_parsely_configs() {
		maybe_load_plugin();

		if ( is_parsely_disabled() ) {
			$this->assertNull( Parsely_Loader_Info::get_configs() );
			return;
		}

		\Parsely\parsely_initialize_plugin();

		$this->assertEquals( Parsely_Loader_Info::get_configs(), array(
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
		) );
	}

	public function test_custom_parsely_configs() {
		maybe_load_plugin();

		if ( is_parsely_disabled() ) {
			$this->assertNull( Parsely_Loader_Info::get_configs() );
			return;
		}

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

		$this->assertEquals( Parsely_Loader_Info::get_configs(), array(
			'is_pinned_version'            => has_filter( 'wpvip_parsely_version' ),
			'site_id'                      => 'example.com',
			'have_api_secret'              => true,
			'is_javascript_disabled'       => true,
			'is_autotracking_disabled'     => true,
			'should_track_logged_in_users' => true,
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
		) );

		// Reset settings.
		update_option( 'parsely', $current_settings );
	}

	/**
	 * Verify Parse.ly data is correctly setting when the plugin is in Managed Mode.
	 *
	 * @return void
	 */
	public function test_parsely_configs_for_managed_mode() {
		maybe_load_plugin();

		if ( is_parsely_disabled() ) {
			$this->assertNull( Parsely_Loader_Info::get_configs() );
			return;
		}

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
		$this->assertEquals( array(
			'is_pinned_version'            => has_filter( 'wpvip_parsely_version' ),
			'site_id'                      => 'site_id_value',
			'have_api_secret'              => true,
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
		), $configs );
	}

	public function test_alter_option_use_repeated_metas() {
		maybe_load_plugin();
		$options = alter_option_use_repeated_metas();
		$this->assertSame( array( 'meta_type' => 'repeated_metas' ), $options );

		$options = alter_option_use_repeated_metas( array( 'some_option' => 'value' ) );
		$this->assertSame( array(
			'some_option' => 'value',
			'meta_type'   => 'repeated_metas',
		), $options );

		$options = alter_option_use_repeated_metas( array( 'meta_type' => 'json_ld' ) );
		$this->assertSame( array( 'meta_type' => 'repeated_metas' ), $options );
	}

	public function test_unprotected_published_posts_show_meta() {
		maybe_load_plugin();

		if ( is_parsely_disabled() ) {
			$this->assertFalse( is_callable( '\Parsely\parsely_initialize_plugin' ) );
			return;
		}

		\Parsely\parsely_initialize_plugin();

		$post = [
			'post_title'   => 'Testing unprotected posts',
			'post_content' => 'stuff & things',
			'post_status'  => 'publish',
		];

		$post_id = wp_insert_post( $post, true );
		$option  = get_option( 'parsely' ) ?: [];
		update_option( 'parsely', array_merge( $option, [ 'apikey' => 'testing123' ] ) );

		$metadata = $this->get_post_metadata( $post_id );

		$this->assertIsArray( $metadata, 'post metadata should be an array' );
		$this->assertNotEmpty( $metadata, 'post metadata should not be empty' );
	}

	public function test_protected_post_do_not_show_meta() {
		global $parsely;

		maybe_load_plugin();

		if ( is_parsely_disabled() ) {
			$this->assertFalse( is_callable( '\Parsely\parsely_initialize_plugin' ) );
			return;
		}

		\Parsely\parsely_initialize_plugin();

		$post = [
			'post_title'    => 'Testing protected posts',
			'post_content'  => 'http://bash.org/?244321',
			'post_status'   => 'publish',
			'post_password' => 'hunter2',
		];

		$post_id = wp_insert_post( $post, true );
		$option  = get_option( 'parsely' ) ?: [];
		update_option( 'parsely', array_merge( $option, [ 'apikey' => 'testing123' ] ) );

		$metadata = $this->get_post_metadata( $post_id );

		if ( version_compare( $parsely::VERSION, '3.3.0', '<' ) ) {
			$this->assertSame(
				[
					'@context' => 'http://schema.org',
					'@type'    => 'WebPage',
				],
				$metadata,
				'Metadata should only show basic fields'
			);
			return;
		}

		if ( version_compare( $parsely::VERSION, '3.5.0', '<' ) ) {
			$this->assertSame(
				[
					'@context' => 'https://schema.org',
					'@type'    => 'WebPage',
				],
				$metadata,
				'Metadata should only show basic fields'
			);
			return;
		}

		$this->assertSame( [], $metadata, 'Metadata should be empty' );
	}

	private function get_post_metadata( int $post_id ) {
		global $parsely;

		$_post = get_post( $post_id );

		if ( version_compare( $parsely::VERSION, '3.3', '<' ) ) {
			return $parsely->construct_parsely_metadata( $parsely->get_options(), $_post );
		}

		$metadata = new \Parsely\Metadata( $parsely );
		return $metadata->construct_metadata( $_post );
	}

	private function set_get_globals_for_parsely_activation() {
		$_GET['plugin'] = 'wp-parsely/wp-parsely.php';
		$_GET['action'] = 'activate';
	}

	private function set_post_globals_for_parsely_activation() {
		$_POST['checked'] = [ 'wp-parsely/wp-parsely.php' ];
		$_POST['action']  = 'activate-selected';
	}
}
