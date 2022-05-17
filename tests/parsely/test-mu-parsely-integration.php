<?php

namespace Automattic\VIP\WP_Parsely_Integration;

use Parsely\UI\Row_Actions;
use WP_UnitTestCase;

// Copied from https://github.com/Automattic/vip-go-mu-plugins/blob/ffa8669aa4c9fabfb67815c53110f229dcd049b7/wp-parsely-2.6/src/class-parsely.php#L28-L51
// This can be removed and replaced by `$parsely->get_options()` once these are running against v3.0+
const OPTION_DEFAULTS = array(
	'apikey'                      => '',
	'content_id_prefix'           => '',
	'api_secret'                  => '',
	'use_top_level_cats'          => false,
	'custom_taxonomy_section'     => 'category',
	'cats_as_tags'                => false,
	'track_authenticated_users'   => true,
	'lowercase_tags'              => true,
	'force_https_canonicals'      => false,
	'track_post_types'            => array( 'post' ),
	'track_page_types'            => array( 'page' ),
	'disable_javascript'          => false,
	'disable_amp'                 => false,
	'meta_type'                   => 'json_ld',
	'logo'                        => '',
	'metadata_secret'             => '',
	'parsely_wipe_metadata_cache' => false,
);

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

	public function test_has_maybe_load_plugin_action() {
		$this->assertSame(
			10,
			has_action( 'muplugins_loaded', __NAMESPACE__ . '\maybe_load_plugin' )
		);
	}

	public function test_parsely_class_existance() {
		$class_should_exist = 'disabled' !== self::$test_mode;

		if ( $class_should_exist ) {
			global $parsely;

			$class_name = 'Parsely\Parsely';

			$this->assertTrue( class_exists( $class_name ) );
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

		$options = array_merge( [ 'apikey' => 'testing123' ], OPTION_DEFAULTS );
		return $parsely->construct_parsely_metadata( $options, get_post( $post_id ) );
	}
}
