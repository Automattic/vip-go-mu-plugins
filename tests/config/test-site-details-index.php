<?php
/**
 * Tests SDI data syncing.
 *
 * @phpcs:disable WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
 * @phpcs:disable WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
 */

namespace Automattic\VIP\Config;

use WP_UnitTestCase;

require_once __DIR__ . '/../../config/class-site-details-index.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class Site_Details_Index_Test extends WP_UnitTestCase {
	public function test__data_filter_should_not_be_hooked_if_no_init() {
		$sdi = new Site_Details_Index();

		$this->assertFalse( has_filter( 'vip_site_details_index_data', [ $sdi, 'set_env_and_core' ] ) );
	}

	public function test__data_filter_should_be_hooked_if_init() {
		// Getting the instance should call init which should add set_env_and_core to the hook.
		$sdi = Site_Details_Index::instance();

		$this->assertTrue( is_integer( has_filter( 'vip_site_details_index_data', [ $sdi, 'set_env_and_core' ] ) ) );
	}

	public function test__vip_site_details_index_data_filter() {
		global $wp_version;

		$test_plugins = [
			'hello.php' => [ 'Name' => 'Hello Tests', 'Version' => '4.5', 'UpdateURI' => '' ],
			'world.php' => [ 'Name' => 'Testing World', 'Version' => '8.6', 'UpdateURI' => '' ],
			'woocommerce/woocommerce.php' => [ 'Name' => 'WooCommerce', 'Version' => '1.2.3', 'UpdateURI' => '' ],
		];

		// Mock the list of plugins, which are active, and which have available updates from WPorg.
		wp_cache_set( 'plugins', array( '' => $test_plugins ), 'plugins' );
		update_option( 'active_plugins', array( 'hello.php' ) );
		add_filter( 'pre_site_transient_update_plugins', [ $this, 'mock_update_plugins_transient' ], 999 );

		Site_Details_Index::instance( 100 );

		$site_details = apply_filters( 'vip_site_details_index_data', array() );

		$this->assertSame( 100, $site_details['timestamp'], 'timestamp should be 100 since it was mocked to be 100' );
		$this->assertSame( 123, $site_details['client_site_id'], 'client_site_id should be 123' );
		$this->assertSame( '', $site_details['environment_name'], 'environment_name should be blank since it\'s not currently set' ); // Not set in test environment currently
		$this->assertSame( $wp_version, $site_details['core']['wp_version'], 'wp_version should be equal to global wp_version' );
		$this->assertSame( get_current_blog_id(), $site_details['core']['blog_id'], 'blog_id should be equal to get_current_blog_id()' );
		$this->assertSame( get_site_url(), $site_details['core']['site_url'], 'site_url should be equal to get_site_url()' );
		$this->assertSame( get_home_url(), $site_details['core']['home_url'], 'home_url should be equal to get_home_url()' );
		$this->assertSame( is_multisite(), $site_details['core']['is_multisite'], 'is_multisite should be equal to is_multisite()' );
		$this->assertEquals(
			[
				[
					'path'         => 'hello.php',
					'name'         => 'Hello Tests',
					'wporg_slug'   => 'hello-wporg-slug',
					'version'      => '4.5',
					'active'       => true,
					'activated_by' => 'option',
				],
				[
					'path'         => 'world.php',
					'name'         => 'Testing World',
					'wporg_slug'   => null, // wasn't present in the update_plugins transient
					'version'      => '8.6',
					'active'       => false,
					'activated_by' => null,
				],
				[
					'path'         => 'woocommerce/woocommerce.php',
					'name'         => 'WooCommerce',
					'wporg_slug'   => 'woocommerce',
					'version'      => '1.2.3',
					'active'       => false,
					'activated_by' => null,
				],
			],
			$site_details['plugins']
		);

		remove_filter( 'pre_site_transient_update_plugins', [ $this, 'mock_update_plugins_transient' ], 999 );
	}

	public function mock_update_plugins_transient() {
		$updates_available = [
			'hello.php' => (object) [ 'slug' => 'hello-wporg-slug' ],
		];

		$no_updates = [
			'woocommerce/woocommerce.php' => (object) [ 'slug' => 'woocommerce' ],
		];

		// Helps prevent the wp_update_plugins() call from needing to do an actual remote lookup.
		$checked_plugins = [ 'hello.php' => '4.5', 'world.php' => '8.6', 'woocommerce' => '1.2.3' ];

		return (object) [
			'last_checked' => time(),
			'response'     => $updates_available,
			'no_update'    => $no_updates,
			'checked'      => $checked_plugins,
		];
	}

	public function test__get_current_timestamp() {
		$timestamp = Site_Details_Index::instance()->get_current_timestamp();

		$this->assertEquals( gmdate( 'd-m-Y', $timestamp / 1000 ), gmdate( 'd-m-Y', round( microtime( true ) ) ) );
	}
}
