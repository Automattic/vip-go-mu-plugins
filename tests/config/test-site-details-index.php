<?php

namespace Automattic\VIP\Config;

use WP_UnitTestCase;

require_once __DIR__ . '/../../config/class-site-details-index.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class Site_Details_Index_Test extends WP_UnitTestCase {
	public function test__cron_event_should_not_be_hooked_if_no_init() {
		$sdi = new Site_Details_Index();

		$this->assertFalse( has_filter( 'vip_site_details_index_data', [ $sdi, 'set_env_and_core' ] ) );
	}

	public function test__cron_event_should_be_hooked_if_init() {
		// Getting the instance should call init which should add set_env_and_core to the hook
		$sdi = Site_Details_Index::instance();
		
		$this->assertTrue( is_integer( has_filter( 'vip_site_details_index_data', [ $sdi, 'set_env_and_core' ] ) ) );
	}

	public function test__set_env_and_core() {
		global $wp_version;

		$plugins = array(
			'hello.php' => array(
				'Name'    => 'Hello Tests',
				'Version' => '4.5',
			),
			'world.php' => array(
				'Name'    => 'Testing World',
				'Version' => '8.6',
			),
		);

		// Set the cache for plugins and the option for enabling a plugin	
		wp_cache_set( 'plugins', array( '' => $plugins ), 'plugins' );
		update_option( 'active_plugins', array( 'world.php' ) );

		$site_details = Site_Details_Index::instance( 100 )->set_env_and_core( array() );

		$this->assertTrue( array_key_exists( 'timestamp', $site_details ), 'timestamp should exist' );
		$this->assertTrue( is_integer( $site_details['timestamp'] ), 'timestamp should be int' );
		$this->assertEquals( 100, $site_details['timestamp'], 'timestamp should be 100 since it was mocked to be 100' );

		$this->assertTrue( array_key_exists( 'client_site_id', $site_details ), 'client_site_id should exist' );
		$this->assertTrue( is_integer( $site_details['client_site_id'] ), 'client_site_id should be int' );
		$this->assertEquals( 123, $site_details['client_site_id'], 'client_site_id should be 123' );

		$this->assertTrue( array_key_exists( 'environment_name', $site_details ), 'environment_name should exist' );
		$this->assertTrue( is_string( $site_details['environment_name'] ), 'environment_name should be string' );
		$this->assertEquals( '', $site_details['environment_name'], 'environment_name should be blank since it\'s not current set' ); // Not set in test environment currently

		$this->assertTrue( array_key_exists( 'plugins', $site_details ), 'plugins should exist' );
		$this->assertTrue( is_array( $site_details['plugins'] ), 'plugins should be array' );
		$this->assertEquals(
			array(
				array(
					'path'         => 'hello.php',
					'name'         => 'Hello Tests',
					'version'      => '4.5',
					'active'       => false,
					'activated_by' => null,
				),
				array(
					'path'         => 'world.php',
					'name'         => 'Testing World',
					'version'      => '8.6',
					'active'       => true,
					'activated_by' => 'option',
				),
			),
			$site_details['plugins'] 
		);

		$this->assertTrue( array_key_exists( 'core', $site_details ), 'core should exist' );
		$this->assertTrue( is_array( $site_details['core'] ), 'core should be array' );

		$this->assertTrue( array_key_exists( 'wp_version', $site_details['core'] ), 'wp_version should exist in core' );
		$this->assertTrue( is_string( $site_details['core']['wp_version'] ), 'wp_version should be string' );
		$this->assertEquals( $wp_version, $site_details['core']['wp_version'], 'wp_version should be equal to global wp_version' );

		$this->assertTrue( array_key_exists( 'blog_id', $site_details['core'] ), 'blog_id should exist in core' );
		$this->assertTrue( is_integer( $site_details['core']['blog_id'] ), 'blog_id should be int' );
		$this->assertEquals( get_current_blog_id(), $site_details['core']['blog_id'], 'blog_id should be equal to get_current_blog_id()' );

		$this->assertTrue( array_key_exists( 'site_url', $site_details['core'] ), 'site_url should exist in core' );
		$this->assertTrue( is_string( $site_details['core']['site_url'] ), 'site_url should be string' );
		$this->assertEquals( get_site_url(), $site_details['core']['site_url'], 'site_url should be equal to get_site_url()' );

		$this->assertTrue( array_key_exists( 'is_multisite', $site_details['core'] ), 'is_multisite should exist in core' );
		$this->assertTrue( is_bool( $site_details['core']['is_multisite'] ), 'is_multisite should be bool' );
		$this->assertEquals( is_multisite(), $site_details['core']['is_multisite'], 'is_multisite should be equal to is_multisite()' );
	}

	public function test__vip_site_details_index_data() {
		global $wp_version;

		$plugins = array(
			'hello.php' => array(
				'Name'    => 'Hello Tests',
				'Version' => '4.5',
			),
			'world.php' => array(
				'Name'    => 'Testing World',
				'Version' => '8.6',
			),
		);

		// Set the cache for plugins and the option for enabling a plugin	
		wp_cache_set( 'plugins', array( '' => $plugins ), 'plugins' );
		update_option( 'active_plugins', array( 'hello.php' ) );

		Site_Details_Index::instance( 100 );

		$site_details = apply_filters( 'vip_site_details_index_data', array() );

		$this->assertTrue( array_key_exists( 'timestamp', $site_details ), 'timestamp should exist' );
		$this->assertTrue( is_integer( $site_details['timestamp'] ), 'timestamp should be int' );
		$this->assertEquals( 100, $site_details['timestamp'], 'timestamp should be 100 since it was mocked to be 100' );

		$this->assertTrue( array_key_exists( 'client_site_id', $site_details ), 'client_site_id should exist' );
		$this->assertTrue( is_integer( $site_details['client_site_id'] ), 'client_site_id should be int' );
		$this->assertEquals( 123, $site_details['client_site_id'], 'client_site_id should be 123' );

		$this->assertTrue( array_key_exists( 'environment_name', $site_details ), 'environment_name should exist' );
		$this->assertTrue( is_string( $site_details['environment_name'] ), 'environment_name should be string' );
		$this->assertEquals( '', $site_details['environment_name'], 'environment_name should be blank since it\'s not current set' ); // Not set in test environment currently

		$this->assertTrue( array_key_exists( 'plugins', $site_details ), 'plugins should exist' );
		$this->assertTrue( is_array( $site_details['plugins'] ), 'plugins should be array' );
		$this->assertEquals(
			array(
				array(
					'path'         => 'hello.php',
					'name'         => 'Hello Tests',
					'version'      => '4.5',
					'active'       => true,
					'activated_by' => 'option',
				),
				array(
					'path'         => 'world.php',
					'name'         => 'Testing World',
					'version'      => '8.6',
					'active'       => false,
					'activated_by' => null,
				),
			),
			$site_details['plugins'] 
		);

		$this->assertTrue( array_key_exists( 'core', $site_details ), 'core should exist' );
		$this->assertTrue( is_array( $site_details['core'] ), 'core should be array' );

		$this->assertTrue( array_key_exists( 'wp_version', $site_details['core'] ), 'wp_version should exist in core' );
		$this->assertTrue( is_string( $site_details['core']['wp_version'] ), 'wp_version should be string' );
		$this->assertEquals( $wp_version, $site_details['core']['wp_version'], 'wp_version should be equal to global wp_version' );

		$this->assertTrue( array_key_exists( 'blog_id', $site_details['core'] ), 'blog_id should exist in core' );
		$this->assertTrue( is_integer( $site_details['core']['blog_id'] ), 'blog_id should be int' );
		$this->assertEquals( get_current_blog_id(), $site_details['core']['blog_id'], 'blog_id should be equal to get_current_blog_id()' );

		$this->assertTrue( array_key_exists( 'site_url', $site_details['core'] ), 'site_url should exist in core' );
		$this->assertTrue( is_string( $site_details['core']['site_url'] ), 'site_url should be string' );
		$this->assertEquals( get_site_url(), $site_details['core']['site_url'], 'site_url should be equal to get_site_url()' );

		$this->assertTrue( array_key_exists( 'is_multisite', $site_details['core'] ), 'is_multisite should exist in core' );
		$this->assertTrue( is_bool( $site_details['core']['is_multisite'] ), 'is_multisite should be bool' );
		$this->assertEquals( is_multisite(), $site_details['core']['is_multisite'], 'is_multisite should be equal to is_multisite()' );
	}
}
