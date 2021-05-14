<?php
namespace Automattic\VIP\Search;

use \WP_Query;

class Cache_Test extends \WP_UnitTestCase {
	/**
	 * Make tests run in separate processes since we're testing state
	 * related to plugin init, including various constants.
	 */
	protected $preserveGlobalState = false; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
	protected $runTestInSeparateProcess = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

	public static function setUpBeforeClass() {
		define( 'VIP_ELASTICSEARCH_ENDPOINTS', array( 'https://elasticsearch:9200' ) );
	}

	public function setUp() {
		global $wpdb;
		require_once __DIR__ . '/../../../../search/search.php';
		include_once __DIR__ . '/../../../../advanced-post-cache/advanced-post-cache.php';

		$this->apc_filters = [
			'posts_request',
			'posts_results',
			'post_limits_request',
			'found_posts_query',
			'found_posts',
		];

		$this->es = new \Automattic\VIP\Search\Search();
		$this->es->init();
		\ElasticPress\register_indexable_posts();

		add_filter( 'ep_skip_query_integration', '__return_false', 100 );
	}

	public function test_apc_compat_pre_get_posts_wired() {
		$this->assertInternalType( 'int', has_action( 'pre_get_posts', array( $this->es->cache, 'disable_apc_for_ep_enabled_requests' ) ) );
	}

	public function test_disable_enable_apc() {
		if ( ! class_exists( 'Advanced_Post_Cache' ) ) {
			$this->markTestSkipped( 'Advanced Post Cache is not available' );
		}

		// All of APC's filters should be unhooked for EP queries
		$q = new WP_Query( [
			's' => 'test',
			'ep_integrate' => true,
		] );

		$filters = array_filter( $this->apc_filters, function( $filter ) {
			return false !== has_filter( $filter, [ $GLOBALS['advanced_post_cache_object'], $filter ] );
		} );

		$this->assertEmpty( $filters, 'Failed to remove APC filters' );

		// All of APC's filters should be re-enabled for any non-EP query
		$q = new WP_Query( [ 'posts_per_page' => 10 ] );

		$filters = array_filter( $this->apc_filters, function( $filter ) {
			return false !== has_filter( $filter, [ $GLOBALS['advanced_post_cache_object'], $filter ] );
		} );

		$this->assertEquals( count( $this->apc_filters ), count( $filters ), 'Failed to re-attach APC filters' );
	}
}
