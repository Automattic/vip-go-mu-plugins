<?php

namespace Automattic\VIP\WP_Parsely_Integration;

use Parsely\UI\Row_Actions;
use WP_UnitTestCase;

const CURRENT_DEFAULT_VERSION = '3.5';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class MU_Parsely_Integration_Test extends WP_UnitTestCase {
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

	public function test_parsely_no_instance_by_default() {
		$this->assertFalse( isset( $GLOBALS['parsely'] ) );
		maybe_load_plugin();
		$this->assertFalse( isset( $GLOBALS['parsely'] ) );
	}

	public function test_parsely_instance_option_enabled() {
		self::set_load_via_option();
		maybe_load_plugin();
		\Parsely\parsely_initialize_plugin();

		$classname = get_class( $GLOBALS['parsely'] );

		$this->assertEquals( 'Parsely\Parsely', $classname );

		$this->assertEquals( 1, preg_match( '/^(\d+\.\d+)(\.|$)/', $GLOBALS['parsely']::VERSION, $matches ) );
		$this->assertEquals( CURRENT_DEFAULT_VERSION, $matches[1] );
	}

	public function test_parsely_instance_filter_enabled() {
		self::set_load_via_filter();
		maybe_load_plugin();
		\Parsely\parsely_initialize_plugin();

		$classname = get_class( $GLOBALS['parsely'] );

		$this->assertEquals( 'Parsely\Parsely', $classname );

		$this->assertEquals( 1, preg_match( '/^(\d+\.\d+)(\.|$)/', $GLOBALS['parsely']::VERSION, $matches ) );
		$this->assertEquals( CURRENT_DEFAULT_VERSION, $matches[1] );
	}

	public function test_parsely_instance_option_enabled_3_1() {
		self::override_wp_parsely_version( '3.1' );
		self::set_load_via_option();
		maybe_load_plugin();
		\Parsely\parsely_initialize_plugin();

		$classname = get_class( $GLOBALS['parsely'] );

		$this->assertEquals( 'Parsely\Parsely', $classname );

		$this->assertEquals( 1, preg_match( '/^(\d+\.\d+)(\.|$)/', $GLOBALS['parsely']::VERSION, $matches ) );
		$this->assertEquals( '3.1', $matches[1] );
	}

	public function test_parsely_instance_filter_enabled_3_1() {
		self::override_wp_parsely_version( '3.1' );
		self::set_load_via_filter();
		maybe_load_plugin();
		\Parsely\parsely_initialize_plugin();

		$classname = get_class( $GLOBALS['parsely'] );

		$this->assertEquals( 'Parsely\Parsely', $classname );

		$this->assertEquals( 1, preg_match( '/^(\d+\.\d+)(\.|$)/', $GLOBALS['parsely']::VERSION, $matches ) );
		$this->assertEquals( '3.1', $matches[1] );
	}

	public function test_is_not_filter_or_option_enabled_by_default() {
		$this->assertFalse( has_filter( 'wpvip_parsely_load_mu' ) );
		$this->assertFalse( get_option( '_wpvip_parsely_mu' ) );
	}

	public function test_default_bootstrap_mode_is_disabled() {
		maybe_load_plugin();

		$this->assertFalse( has_filter( 'wpvip_parsely_load_mu' ) );
		$this->assertFalse( get_option( '_wpvip_parsely_mu' ) );
		$this->assertFalse( is_callable( '\Parsely\parsely_initialize_plugin' ) );
		$this->assertFalse(
			Parsely_Loader_Info::is_active(),
			'Expecting the plugin to be inactive'
		);
		$this->assertEquals( Parsely_Loader_Info::INTEGRATION_TYPE_NONE, Parsely_Loader_Info::get_integration_type() );
		$this->assertEquals( [], Parsely_Loader_Info::get_parsely_options() );
		$this->assertEquals( Parsely_Loader_Info::VERSION_UNKNOWN, Parsely_Loader_Info::get_version() );
		$this->assertFalse( class_exists( 'Parsely' ) );
		$this->assertFalse( class_exists( 'Parsely\Parsely' ) );
	}

	public function test_filter_enabled_bootstrap_mode() {
		self::set_load_via_filter();

		maybe_load_plugin();

		$this->assertFalse( get_option( '_wpvip_parsely_mu' ) );
		$this->assertTrue( is_callable( '\Parsely\parsely_initialize_plugin' ) );

		$this->assertTrue(
			Parsely_Loader_Info::is_active(),
			'Expecting wp-parsely plugin to be enabled by the option.'
		);
		$this->assertEquals( Parsely_Loader_Info::INTEGRATION_TYPE_MUPLUGINS, Parsely_Loader_Info::get_integration_type() );
		$this->assertEquals( SUPPORTED_VERSIONS[0], CURRENT_DEFAULT_VERSION );
		$this->assertEquals( CURRENT_DEFAULT_VERSION, Parsely_Loader_Info::get_version() );
		$this->assertFalse( class_exists( 'Parsely' ) );
		$this->assertTrue( class_exists( 'Parsely\Parsely' ) );
	}

	public function test_option_enabled_bootstrap_mode() {
		self::set_load_via_option();

		maybe_load_plugin();

		$this->assertTrue( is_callable( '\Parsely\parsely_initialize_plugin' ) );
		$this->assertFalse( has_filter( 'wpvip_parsely_load_mu' ) );
		$this->assertTrue(
			Parsely_Loader_Info::is_active(),
			'Expecting wp-parsely plugin to be enabled by the option.'
		);
		$this->assertEquals(
			Parsely_Loader_Info::INTEGRATION_TYPE_MUPLUGINS_SILENT,
			Parsely_Loader_Info::get_integration_type(),
			'Expecting wp-parsely integration type to be "silent."'
		);
		$this->assertEquals( SUPPORTED_VERSIONS[0], CURRENT_DEFAULT_VERSION );
		$this->assertEquals( CURRENT_DEFAULT_VERSION, Parsely_Loader_Info::get_version() );
		$this->assertFalse( class_exists( 'Parsely' ) );
		$this->assertTrue( class_exists( 'Parsely\Parsely' ) );
	}

	public function test_filter_and_option_enabled_bootstrap_mode() {
		self::set_load_via_filter();
		self::set_load_via_option();

		maybe_load_plugin();

		$this->assertTrue( is_callable( '\Parsely\parsely_initialize_plugin' ) );
		$this->assertTrue(
			Parsely_Loader_Info::is_active(),
			'Expecting wp-parsely plugin to be enabled by the filter.'
		);
		$this->assertEquals( Parsely_Loader_Info::INTEGRATION_TYPE_MUPLUGINS, Parsely_Loader_Info::get_integration_type() );
		$this->assertEquals( SUPPORTED_VERSIONS[0], CURRENT_DEFAULT_VERSION );
		$this->assertEquals( CURRENT_DEFAULT_VERSION, Parsely_Loader_Info::get_version() );
		$this->assertFalse( class_exists( 'Parsely' ) );
		$this->assertTrue( class_exists( 'Parsely\Parsely' ) );
	}

	public function test_parsely_ui_hooks_disabled() {
		maybe_load_plugin();
		$this->assertFalse( has_action( 'option_parsely', __NAMESPACE__ . '\alter_option_use_repeated_metas' ) );
	}

	public function test_parsely_ui_hooks_option_enabled() {
		self::set_load_via_option();
		maybe_load_plugin();
		\Parsely\parsely_initialize_plugin();
		maybe_disable_some_features();
		$this->assertSame( 10, has_action( 'option_parsely', __NAMESPACE__ . '\alter_option_use_repeated_metas' ) );

		$row_actions = new Row_Actions( $GLOBALS['parsely'] );
		$row_actions->run();

		$this->assertFalse( has_filter( 'page_row_actions', array( $row_actions, 'row_actions_add_parsely_link' ) ) );
		$this->assertFalse( has_filter( 'post_row_actions', array( $row_actions, 'row_actions_add_parsely_link' ) ) );
	}

	public function test_parsely_ui_hooks_filter_enabled() {
		self::set_load_via_filter();
		maybe_load_plugin();
		\Parsely\parsely_initialize_plugin();
		maybe_disable_some_features();
		$this->assertFalse( has_action( 'option_parsely', __NAMESPACE__ . '\alter_option_use_repeated_metas' ) );

		$row_actions = new Row_Actions( $GLOBALS['parsely'] );
		$row_actions->run();

		$this->assertSame( 10, has_filter( 'page_row_actions', array( $row_actions, 'row_actions_add_parsely_link' ) ) );
		$this->assertSame( 10, has_filter( 'post_row_actions', array( $row_actions, 'row_actions_add_parsely_link' ) ) );
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

	public function test_unprotected_published_posts_show_meta_filter_enabled() {
		self::set_load_via_filter();
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

		$metadata = $this->get_post_metadata( $post_id );

		$this->assertIsArray( $metadata, 'post metadata should be an array' );
		$this->assertNotEmpty( $metadata, 'post metadata should not be empty' );
	}

	public function test_unprotected_published_posts_show_meta_option_enabled() {
		self::set_load_via_option();
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

		$metadata = $this->get_post_metadata( $post_id );

		$this->assertIsArray( $metadata, 'post metadata should be an array' );
		$this->assertNotEmpty( $metadata, 'post metadata should not be empty' );
	}

	public function test_protected_post_do_not_show_meta_filter_enabled() {
		global $parsely;

		self::set_load_via_filter();
		maybe_load_plugin();
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
		$this->assertSame( [], $metadata, 'Metadata should be empty' );
	}

	public function test_protected_post_do_not_show_meta_option_enabled() {
		global $parsely;

		self::set_load_via_option();
		maybe_load_plugin();
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
		$this->assertSame( [], $metadata, 'Metadata should be empty' );
	}

	private static function override_wp_parsely_version( string $version ) {
		add_filter( 'wpvip_parsely_version', function () use ( $version ) {
			return $version;
		} );
	}

	private static function set_load_via_option() {
		update_option( '_wpvip_parsely_mu', '1' );
	}

	private static function set_load_via_filter() {
		add_filter( 'wpvip_parsely_load_mu', '__return_true' );
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
