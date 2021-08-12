<?php

namespace Automattic\VIP\WP_Parsely_Integration;

function test_mode() {
	$mode = getenv( 'WPVIP_PARSELY_INTEGRATION_TEST_MODE' );
	return $mode ? $mode : 'disabled';
}

class MU_Parsely_Integration_Test extends \WP_UnitTestCase {
	protected static $test_mode;

	public static function setUpBeforeClass() {
		self::$test_mode = test_mode();
		if ( 'disabled' !== self::$test_mode ) {
			echo 'Running Parsely Integration Tests in mode: ' . esc_html( self::$test_mode ) . "\n";
		}
	}

	public function test_has_maybe_load_plugin_action() {
		$this->assertSame(
			10,
			has_action( 'after_setup_theme', __NAMESPACE__ . '\maybe_load_plugin' )
		);
	}

	public function test_parsely_class_existance() {
		$class_should_exist = 'disabled' !== self::$test_mode;
		$this->assertSame( $class_should_exist, class_exists( 'Parsely' ) );
	}

	public function test_parsely_global() {
		global $parsely;

		$instance_should_exist = 'disabled' !== self::$test_mode;
		$this->assertSame( $instance_should_exist, isset( $parsely ) );
	}

	public function test_bootstrap_modes() {
		switch ( self::$test_mode ) {
			case 'disabled':
				$this->assertFalse( has_filter( 'wpvip_parsely_load_mu' ) );
				$this->assertFalse( get_option( '_wpvip_parsely_mu' ) );
				break;
			case 'filter_enabled':
				$this->assertTrue( has_filter( 'wpvip_parsely_load_mu' ) );
				$this->assertFalse( get_option( '_wpvip_parsely_mu' ) );
				break;
			case 'option_enabled':
				$this->assertFalse( has_filter( 'wpvip_parsely_load_mu' ) );
				$this->assertSame( '1', get_option( '_wpvip_parsely_mu' ) );
				break;
			case 'filter_and_option_enabled':
				$this->assertTrue( has_filter( 'wpvip_parsely_load_mu' ) );
				$this->assertSame( '1', get_option( '_wpvip_parsely_mu' ) );
				break;
			default:
				$this->fail( 'Invalid test mode specified: ' . self::$test_mode );
		}
	}

	public function test_parsely_ui_hooks() {
		global $parsely;

		$expected = in_array( self::$test_mode, [ 'filter_enabled', 'filter_and_option_enabled' ] ) ? 10 : false;
		$this->assertSame( $expected, has_action( 'admin_menu', array( $parsely, 'add_settings_sub_menu' ) ) );
		$this->assertSame( $expected, has_action( 'admin_footer', array( $parsely, 'display_admin_warning' ) ) );
		$this->assertSame( $expected, has_action( 'widgets_init', 'parsely_recommended_widget_register' ) );

		/*
			TODO: put these in when landing the quick links
			$this->assertSame( $expected, has_filter( 'page_row_actions', array( $parsely, 'row_actions_add_parsely_link' ) ) );
			$this->assertSame( $expected, has_filter( 'post_row_actions', array( $parsely, 'row_actions_add_parsely_link' ) ) );
		*/
	}
}
