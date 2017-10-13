<?php

namespace Automattic\VIP\Tests;

class VIP_Go_Plugins_Test extends \WP_UnitTestCase {

	protected $option_active_plugins = [];
	protected $option_active_sitewide_plugins = [];
	protected $code_activated_plugins = [];

	public function setUp() {
		parent::setUp();

		// emulate the active plugins option
		$this->option_active_plugins = [
			'hello.php',
			'airplane-mode/airplane-mode.php',
			'msm-sitemap/msm-sitemap.php',
			'gutenberg/gutenberg.php',
			'liveblog/liveblog.php',
			'wp-instagram-widget/wp-instagram-widget.php',
			'shortcake/shortcode-ui.php',
		];

		// emulate the network active plugins option
		$this->option_active_sitewide_plugins = [
			'hello.php' => 1507904134,
			'akismet/akismet.php' => 1507904154,
			'msm-sitemap/msm-sitemap.php' => 1507904154,
			'shortcake/shortcode-ui.php' => 1507904134,
		];

		// emulate the code activated plugins array
		$this->code_activated_plugins = [
			'shared-plugins/two-factor/two-factor.php',
			'plugins/advent-calender/advent-calendar.php',
			'plugins/msm-sitemap/msm-sitemap.php',
			'shared-plugins/vip-go-elasticsearch/vip-go-elasticsearch.php',
		];
	}

	public function test__modify_active_plugins() {
		/**
		 * Set active_plugins to empty to start the test
		 */
		update_option( 'active_plugins', [] );

		/**
		 * Ensure the values are indeed empty
		 */
		$this->assertEmpty( get_option( 'active_plugins' ) );

		/**
		 * Setup the code activated plugins
		 */
		foreach ( $this->code_activated_plugins as $plugin ) {
			wpcom_vip_add_loaded_plugin( $plugin );
		}

		/**
		 * Check that list of code activated plugins matches the mocked data
		 */
		$this->assertEquals( $this->code_activated_plugins, wpcom_vip_get_loaded_plugins() );

		/**
		 * Check that option is not empty
		 */
		update_option( 'active_plugins' , $this->option_active_plugins );

		/**
		 * Check that option is not empty
		 */
		$this->assertNotEmpty( get_option( 'active_plugins' ) );

		/**
		 * Check that the returned option matches a merge of the filtered loaded plugins and active plugins
		 */
		$merged_plugins = array_merge( wpcom_vip_get_filtered_loaded_plugins(), $this->option_active_plugins );
		$merged_plugins_diff = array_diff( $merged_plugins, $this->code_activated_plugins );
		print_r( $merged_plugins );
		print_r( $merged_plugins_diff );
		print_r( get_option( 'active_plugins' ) );
		$this->assertEquals( $merged_plugins, get_option( 'active_plugins' ) );
	}

	public function test__modify_network_active_plugins() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Not relevant on single-site' );
			return;
		}
	}
}
