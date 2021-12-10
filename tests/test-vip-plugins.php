<?php

namespace Automattic\VIP\Tests;

use WP_UnitTestCase;

class VIP_Go_Plugins_Test extends WP_UnitTestCase {

	protected $option_active_plugins          = [];
	protected $option_active_sitewide_plugins = [];
	protected $code_activated_plugins         = [];

	public function setUp(): void {
		parent::setUp();

		// emulate the active plugins option
		$this->option_active_plugins = [
			'airplane-mode/airplane-mode.php',
			'gutenberg/gutenberg.php',
			'hello.php',
			'liveblog/liveblog.php',
			'msm-sitemap/msm-sitemap.php',
			'shortcake/shortcode-ui.php',
			'wp-instagram-widget/wp-instagram-widget.php',
		];

		sort( $this->option_active_plugins );

		// emulate the network active plugins option
		$this->option_active_sitewide_plugins = [
			'akismet/akismet.php'         => 1507904154,
			'hello.php'                   => 1507904134,
			'msm-sitemap/msm-sitemap.php' => 1507904154,
			'shortcake/shortcode-ui.php'  => 1507904134,
		];

		ksort( $this->option_active_sitewide_plugins );

		// emulate the code activated plugins array
		$this->code_activated_plugins = [
			'shared-plugins/two-factor/two-factor.php',
			'plugins/advent-calender/advent-calendar.php',
			'plugins/msm-sitemap/msm-sitemap.php',
		];

		/**
		 * Clear global of code activated plugins from previous test
		 */
		global $vip_loaded_plugins;
		$vip_loaded_plugins = array();

		/**
		 * Set active_plugins to empty to start the test
		 */
		update_option( 'active_plugins', [] );

		/**
		 * Set active_sitewide_plugins to empty to start the test
		 */
		update_site_option( 'active_sitewide_plugins', [] );
	}

	public function test__modify_active_plugins() {
		global $wpdb;

		/**
		 * Ensure the values are indeed empty
		 */
		$this->assertEmpty( get_option( 'active_plugins' ) );

		/**
		 * Update the option with our list of active plugins
		 */
		update_option( 'active_plugins', $this->option_active_plugins );

		/**
		 * Check that option matches what we started with
		 */
		$this->assertEquals( $this->option_active_plugins, get_option( 'active_plugins' ), 'The value of `$option_active_plugins` does not match the returned value of `get_option( \'active_plugins\')`.' );

		/**
		 * Setup the code activated plugins
		 */
		foreach ( $this->code_activated_plugins as $plugin ) {
			wpcom_vip_add_loaded_plugin( $plugin );
		}

		/**
		 * Check that list of code activated plugins matches the mocked data
		 */
		$this->assertEquals( $this->code_activated_plugins, wpcom_vip_get_loaded_plugins(), 'The value of `$code_activated_plugins` does not match the returned value of `wpcom_vip_get_loaded_plugins()`.' );

		/**
		 * Check that the returned option matches a merge of the filtered loaded plugins and active plugins
		 */
		$merged_plugins = array_unique( array_merge( $this->option_active_plugins, wpcom_vip_get_filtered_loaded_plugins() ) );
		sort( $merged_plugins );
		$this->assertEquals( $merged_plugins, get_option( 'active_plugins' ), 'The value of `$merged_plugins` does not match the returned value of `get_option( \'active_plugins\')`.' );

		/**
		 * Check that updating the option is OK, add an extra plugin
		 */
		$plugin_change = array_merge( get_option( 'active_plugins' ), array( 'amp-wp/amp.php' ) );
		$option_update = update_option( 'active_plugins', $plugin_change );
		$this->assertTrue( $option_update );

		/**
		 * Check the raw value in the DB matches what we sent above - skips any filters
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$option_db     = $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name = 'active_plugins' LIMIT 1" );
		$option_db     = maybe_unserialize( $option_db );
		$plugin_change = array_merge( $this->option_active_plugins, array( 'amp-wp/amp.php' ) );
		// because this was a dupe plugin we will be smart enough to remove it from the option
		$key = array_search( 'msm-sitemap/msm-sitemap.php', $plugin_change, true );
		if ( false !== $key ) {
			unset( $plugin_change[ $key ] );
		}
		sort( $plugin_change );
		$this->assertEquals( $plugin_change, $option_db, 'The database value `$option_db` does not match `$plugin_change`.' );

		/**
		 * Check that the option still makes sense again
		 */
		$saved_plugins = array_merge( $plugin_change, wpcom_vip_get_filtered_loaded_plugins() );
		sort( $saved_plugins );
		$this->assertEquals( $saved_plugins, get_option( 'active_plugins' ), 'The value of `$saved_plugins` does not match the returned value of `get_option( \'active_plugins\')`.' );
	}

	public function test__modify_network_active_plugins() {
		global $wpdb;

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Not relevant on single-site' );
			return;
		}

		/**
		 * Ensure the values are indeed empty
		 */
		$this->assertEmpty( get_site_option( 'active_sitewide_plugins' ) );

		/**
		 * Update the option with our list of active network plugins
		 */
		update_site_option( 'active_sitewide_plugins', $this->option_active_sitewide_plugins );

		/**
		 * Check that option is not empty
		 */
		$this->assertEquals( $this->option_active_sitewide_plugins, get_site_option( 'active_sitewide_plugins' ), 'The value of `$option_active_sidewide_plugins` does not match the returned value of `get_site_option( \'active_sitewide_plugins\')`.' );

		/**
		 * Setup the code activated plugins
		 */
		foreach ( $this->code_activated_plugins as $plugin ) {
			wpcom_vip_add_loaded_plugin( $plugin );
		}

		/**
		 * Check that list of code activated plugins matches the mocked data
		 */
		$this->assertEquals( $this->code_activated_plugins, wpcom_vip_get_loaded_plugins(), 'The value of `$code_activated_plugins` does not match the returned value of `wpcom_vip_get_loaded_plugins()`.' );

		/**
		 * Check that the returned option matches a merge of the filtered loaded plugins and active plugins
		 */
		$merged_plugins = array_merge( $this->option_active_sitewide_plugins, wpcom_vip_get_network_filtered_loaded_plugins() );
		ksort( $merged_plugins );
		$this->assertEquals( $merged_plugins, get_site_option( 'active_sitewide_plugins' ), 'The value of `$merged_plugins` does not match the returned value of `get_site_option( \'active_sitewide_plugins\')`.' );

		/**
		 * Check that updating the option is OK, add an extra plugin
		 */
		$plugin_change = array_merge( $this->option_active_sitewide_plugins, array( 'amp-wp/amp.php' => 1507904134 ) );
		$option_update = update_site_option( 'active_sitewide_plugins', $plugin_change );
		$this->assertTrue( $option_update );

		/**
		 * Check the raw value in the DB matches what we sent above - skips any filters
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$option_db = $wpdb->get_var( "SELECT meta_value FROM $wpdb->sitemeta WHERE meta_key = 'active_sitewide_plugins' LIMIT 1" );
		$option_db = maybe_unserialize( $option_db );
		ksort( $plugin_change );
		$this->assertEquals( $plugin_change, $option_db, 'The database value `$option_db` does not match `$plugin_change`.' );

		/**
		 * Check that the option still makes sense again
		 */
		$saved_plugins = array_merge( $plugin_change, wpcom_vip_get_network_filtered_loaded_plugins() );
		ksort( $saved_plugins );
		$this->assertEquals( $saved_plugins, get_site_option( 'active_sitewide_plugins' ), 'The value of `$merged_plugins` does not match the returned value of `get_site_option( \'active_sitewide_plugins\')`.' );
	}
}
