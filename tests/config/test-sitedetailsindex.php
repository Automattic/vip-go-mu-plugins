<?php

namespace Automattic\VIP\Config;

class SiteDetailsIndex_Test extends \WP_UnitTestCase {
	/**
	 * Make tests run in separate processes since we're testing state
	 * related to plugin init, including various constants.
	 */
	protected $preserveGlobalState = false; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
	protected $runTestInSeparateProcess = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

	public function setUp() {
		require_once __DIR__ . '/../../config/class-sitedetailsindex.php';
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__cron_event_should_not_be_hooked_if_no_init() {
		$sdi = new SiteDetailsIndex();

		$this->assertFalse( has_filter( 'vip_site_details_index_data', [ $sdi, 'set_env_and_core' ] ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__cron_event_should_be_hooked_if_init() {
		// Getting the instance should call init which should add set_env_and_core to the hook
		$sdi = SiteDetailsIndex::instance();
		
		$this->assertTrue( is_integer( has_filter( 'vip_site_details_index_data', [ $sdi, 'set_env_and_core' ] ) ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__set_env_and_core() {
		$site_details = SiteDetailsIndex::instance()->set_env_and_core( array() );

		$this->assertTrue( array_key_exists( 'timestamp', $site_details ), 'timestamp should exist' );
		$this->assertTrue( is_integer( $site_details['timestamp'] ), 'timestamp should be int' );
		
		$this->assertTrue( array_key_exists( 'client_site_id', $site_details ), 'client_site_id should exist' );
		$this->assertTrue( is_integer( $site_details['client_site_id'] ), 'client_site_id should be int' );

		$this->assertTrue( array_key_exists( 'environment_id', $site_details ), 'environment_id should exist' );
		$this->assertTrue( is_integer( $site_details['environment_id'] ), 'environment_id should be int' );

		$this->assertTrue( array_key_exists( 'environment_name', $site_details ), 'environment_name should exist' );
		$this->assertTrue( is_string( $site_details['environment_name'] ), 'environment_name should be string' );

		$this->assertTrue( array_key_exists( 'plugins', $site_details ), 'plugins should exist' );
		$this->assertTrue( is_array( $site_details['plugins'] ), 'plugins should be array' );

		$this->assertTrue( array_key_exists( 'core', $site_details ), 'core should exist' );
		$this->assertTrue( is_array( $site_details['core'] ), 'core should be array' );

		$this->assertTrue( array_key_exists( 'wp_version', $site_details['core'] ), 'wp_version should exist in core' );
		$this->assertTrue( is_string( $site_details['core']['wp_version'] ), 'wp_version should be string' );

		$this->assertTrue( array_key_exists( 'blog_id', $site_details['core'] ), 'blog_id should exist in core' );
		$this->assertTrue( is_integer( $site_details['core']['blog_id'] ), 'blog_id should be int' );

		$this->assertTrue( array_key_exists( 'is_multisite', $site_details['core'] ), 'is_multisite should exist in core' );
		$this->assertTrue( is_bool( $site_details['core']['is_multisite'] ), 'is_multisite should be bool' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__vip_site_details_index_data() {
		SiteDetailsIndex::instance();

		$site_details = apply_filters( 'vip_site_details_index_data', array() );

		$this->assertTrue( array_key_exists( 'timestamp', $site_details ), 'timestamp should exist' );
		$this->assertTrue( is_integer( $site_details['timestamp'] ), 'timestamp should be int' );
		
		$this->assertTrue( array_key_exists( 'client_site_id', $site_details ), 'client_site_id should exist' );
		$this->assertTrue( is_integer( $site_details['client_site_id'] ), 'client_site_id should be int' );

		$this->assertTrue( array_key_exists( 'environment_id', $site_details ), 'environment_id should exist' );
		$this->assertTrue( is_integer( $site_details['environment_id'] ), 'environment_id should be int' );

		$this->assertTrue( array_key_exists( 'environment_name', $site_details ), 'environment_name should exist' );
		$this->assertTrue( is_string( $site_details['environment_name'] ), 'environment_name should be string' );

		$this->assertTrue( array_key_exists( 'plugins', $site_details ), 'plugins should exist' );
		$this->assertTrue( is_array( $site_details['plugins'] ), 'plugins should be array' );

		$this->assertTrue( array_key_exists( 'core', $site_details ), 'core should exist' );
		$this->assertTrue( is_array( $site_details['core'] ), 'core should be array' );

		$this->assertTrue( array_key_exists( 'wp_version', $site_details['core'] ), 'wp_version should exist in core' );
		$this->assertTrue( is_string( $site_details['core']['wp_version'] ), 'wp_version should be string' );

		$this->assertTrue( array_key_exists( 'blog_id', $site_details['core'] ), 'blog_id should exist in core' );
		$this->assertTrue( is_integer( $site_details['core']['blog_id'] ), 'blog_id should be int' );

		$this->assertTrue( array_key_exists( 'is_multisite', $site_details['core'] ), 'is_multisite should exist in core' );
		$this->assertTrue( is_bool( $site_details['core']['is_multisite'] ), 'is_multisite should be bool' );
	}
}
