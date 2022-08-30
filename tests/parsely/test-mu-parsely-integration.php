<?php

namespace Automattic\VIP\WP_Parsely_Integration;

use Automattic\VIP\WP_Parsely_Integration\Parsely_Loader_Info;
use Parsely\UI\Row_Actions;
use WP_UnitTestCase;

function test_mode() {
	$mode = getenv( 'WPVIP_PARSELY_INTEGRATION_TEST_MODE' );
	return $mode ?: 'disabled';
}

class MU_Parsely_Integration_Test extends WP_UnitTestCase {
	protected static $test_mode;

	public static function setUpBeforeClass(): void {
		self::$test_mode = test_mode();
		if ( 'disabled' !== self::$test_mode ) {
			echo 'Running Parsely Integration Tests in mode: ' . esc_html( self::$test_mode ) . "\n";
		}
	}

	public function test_parsely_loader_info_defaults() {
		$this->assertFalse( Parsely_Loader_Info::get_active() );
		$this->assertEquals( Parsely_Loader_Info::INTEGRATION_TYPE_NONE, Parsely_Loader_Info::get_integration_type() );
		$this->assertEquals( [], Parsely_Loader_Info::get_parsely_options() );
		$this->assertEquals( Parsely_Loader_Info::SERVICE_TYPE_UNKNOWN, Parsely_Loader_Info::get_service_type() );
		$this->assertEquals( Parsely_Loader_Info::VERSION_UNKNOWN, Parsely_Loader_Info::get_version() );
	}

	public function test_is_queued_for_activation_get() {
		$this->set_get_globals_for_parsely_activation();
		$this->assertTrue( \Automattic\VIP\WP_Parsely_Integration\is_queued_for_activation() );
	}

	public function test_is_queued_for_activation_post() {
		$this->set_post_globals_for_parsely_activation();
		$this->assertTrue( \Automattic\VIP\WP_Parsely_Integration\is_queued_for_activation() );
	}

	public function test_is_not_queued_for_activation() {
		$this->assertFalse( \Automattic\VIP\WP_Parsely_Integration\is_queued_for_activation() );
	}

	public function test_has_maybe_load_plugin_action() {
		$this->assertSame(
			10,
			has_action( 'muplugins_loaded', __NAMESPACE__ . '\maybe_load_plugin' )
		);
	}

	public function test_parsely_class_existance() {
		$class_should_exist = 'disabled' !== self::$test_mode;

		if ( $class_should_exist ) {
			$this->assertTrue( class_exists( 'Parsely\Parsely' ) );
		} else {
			$this->assertFalse( class_exists( 'Parsely' ) );
			$this->assertFalse( class_exists( 'Parsely\Parsely' ) );
		}
	}

	public function test_parsely_global() {
		global $parsely;

		$instance_should_exist = 'disabled' !== self::$test_mode;
		$this->assertSame( $instance_should_exist, isset( $parsely ) );
	}

	public function test_bootstrap_modes() {
		\Automattic\VIP\WP_Parsely_Integration\maybe_load_plugin();
		switch ( self::$test_mode ) {
			case 'disabled':
				$this->assertFalse( has_filter( 'wpvip_parsely_load_mu' ) );
				$this->assertFalse( get_option( '_wpvip_parsely_mu' ) );
				$this->assertFalse( Parsely_Loader_Info::get_active() );
				$this->assertEquals( Parsely_Loader_Info::INTEGRATION_TYPE_NONE, Parsely_Loader_Info::get_integration_type() );
				break;
			case 'filter_enabled':
				$this->assertTrue( has_filter( 'wpvip_parsely_load_mu' ) );
				$this->assertFalse( get_option( '_wpvip_parsely_mu' ) );
				$this->assertTrue( Parsely_Loader_Info::get_active() );
				$this->assertEquals( Parsely_Loader_Info::INTEGRATION_TYPE_MUPLUGINS, Parsely_Loader_Info::get_integration_type() );
				break;
			case 'option_enabled':
				$this->assertFalse( has_filter( 'wpvip_parsely_load_mu' ) );
				$this->assertSame( '1', get_option( '_wpvip_parsely_mu' ) );
				$this->assertTrue( Parsely_Loader_Info::get_active() );
				$this->assertEquals( Parsely_Loader_Info::INTEGRATION_TYPE_SELF_MANAGED, Parsely_Loader_Info::get_integration_type() );
				break;
			case 'filter_and_option_enabled':
				$this->assertTrue( has_filter( 'wpvip_parsely_load_mu' ) );
				$this->assertSame( '1', get_option( '_wpvip_parsely_mu' ) );
				$this->assertTrue( Parsely_Loader_Info::get_active() );
				$this->assertEquals( Parsely_Loader_Info::INTEGRATION_TYPE_SELF_MANAGED, Parsely_Loader_Info::get_integration_type() );
				break;
			default:
				$this->fail( 'Invalid test mode specified: ' . self::$test_mode );
		}
	}

	public function test_parsely_ui_hooks() {
		$repeated_metas_expected = 'option_enabled' == self::$test_mode ? 10 : false;
		$this->assertSame( $repeated_metas_expected, has_action( 'option_parsely', 'Automattic\VIP\WP_Parsely_Integration\alter_option_use_repeated_metas' ) );

		// Class should only exist if Parse.ly is enabled
		if ( 'disabled' !== self::$test_mode ) {
			$row_actions = new Row_Actions( $GLOBALS['parsely'] );
			$row_actions->run();

			$row_actions_expected = in_array( self::$test_mode, [ 'filter_enabled', 'filter_and_option_enabled' ] ) ? 10 : false;
			$this->assertSame( $row_actions_expected, has_filter( 'page_row_actions', array( $row_actions, 'row_actions_add_parsely_link' ) ) );
			$this->assertSame( $row_actions_expected, has_filter( 'post_row_actions', array( $row_actions, 'row_actions_add_parsely_link' ) ) );
		}
	}

	public function test_alter_option_use_repeated_metas() {
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
		if ( 'disabled' === self::$test_mode ) {
			$this->markTestSkipped();
		}

		$post = [
			'post_title'   => 'Testing unprotected posts',
			'post_content' => 'stuff & things',
			'post_status'  => 'publish',
		];

		$post_id  = wp_insert_post( $post, true );
		$metadata = $this->get_post_metadata( $post_id );

		$this->assertIsArray( $metadata, 'post metadata should be an array' );
		$this->assertNotEmpty( $metadata, 'post metadata should not be empty' );
	}

	public function test_protected_post_do_not_show_meta() {
		global $parsely;

		if ( 'disabled' === self::$test_mode ) {
			$this->markTestSkipped();
		}

		$post = [
			'post_title'    => 'Testing protected posts',
			'post_content'  => 'http://bash.org/?244321',
			'post_status'   => 'publish',
			'post_password' => 'hunter2',
		];

		$post_id  = wp_insert_post( $post, true );
		$metadata = $this->get_post_metadata( $post_id );

		if ( version_compare( $parsely::VERSION, '3.3.0', '<' ) ) {
			$expected_metadata = array(
				'@context' => 'http://schema.org',
				'@type'    => 'WebPage',
			);
		} else {
			$expected_metadata = array(
				'@context' => 'https://schema.org',
				'@type'    => 'WebPage',
			);
		}

		$this->assertSame( $expected_metadata, $metadata, 'Metadata should only show basic fields' );
	}

	private function get_post_metadata( int $post_id ) {
		global $parsely;

		$options = array_merge( [ 'apikey' => 'testing123' ], $parsely->get_options() );
		return $parsely->construct_parsely_metadata( $options, get_post( $post_id ) );
	}

	private function set_get_globals_for_parsely_activation() {
		$_GET['plugin'] = \Automattic\VIP\WP_Parsely_Integration\PARSELY_PLUGIN_SIGNATURE;
		$_GET['action'] = 'activate';
	}

	private function set_post_globals_for_parsely_activation() {
		$_POST['checked'] = [ \Automattic\VIP\WP_Parsely_Integration\PARSELY_PLUGIN_SIGNATURE ];
		$_POST['action']  = 'activate-selected';
	}
}
