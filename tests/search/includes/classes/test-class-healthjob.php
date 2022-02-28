<?php

namespace Automattic\VIP\Search;

use WP_UnitTestCase;
use Automattic\Test\Constant_Mocker;

// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class HealthJob_Test extends WP_UnitTestCase {
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		if ( ! defined( 'VIP_ELASTICSEARCH_ENDPOINTS' ) ) {
			define( 'VIP_ELASTICSEARCH_ENDPOINTS', array( 'https://elasticsearch:9200' ) );
		}

		Constant_Mocker::define( 'VIP_GO_ENV', 'test' );

		require_once __DIR__ . '/../../../../search/search.php';

		\Automattic\VIP\Search\Search::instance();

		// Required so that EP registers the Indexables
		do_action( 'plugins_loaded' );

		// Users indexable doesn't get registered by default, but we have tests that queue user objects
		\ElasticPress\Indexables::factory()->register( new \ElasticPress\Indexable\User\User() );
	}

	public function setUp(): void {
		parent::setUp();
		require_once __DIR__ . '/../../../../search/includes/classes/class-healthjob.php';
	}

	public function test_vip_search_healthjob_is_enabled_when_expected() {
		add_filter( 'ep_is_indexing', '__return_false' );
		add_filter( 'ep_last_sync', '__return_true' );

		$enabled_environments = function() {
			return [ 'test' ];
		};

		add_filter( 'vip_search_healthchecks_enabled_environments', $enabled_environments );

		$job = new \Automattic\VIP\Search\HealthJob( Search::instance() );

		$enabled = $job->is_enabled();

		$this->assertTrue( $enabled );

		remove_filter( 'ep_is_indexing', '__return_false' );
		remove_filter( 'ep_last_sync', '__return_true' );
		remove_filter( 'vip_search_healthchecks_enabled_environments', $enabled_environments );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_vip_search_healthjob_is_disabled_when_constant_is_set() {
		Constant_Mocker::define( 'DISABLE_VIP_SEARCH_HEALTHCHECKS', true );

		$job = new \Automattic\VIP\Search\HealthJob( Search::instance() );

		$enabled = $job->is_enabled();

		$this->assertFalse( $enabled );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_vip_search_healthjob_is_disabled_when_app_id_matches_disabled_list() {
		Constant_Mocker::define( 'VIP_GO_APP_ID', 2341 );

		$job                                = new \Automattic\VIP\Search\HealthJob( Search::instance() );
		$job->health_check_disabled_sites[] = Constant_Mocker::constant( 'VIP_GO_APP_ID' );

		$enabled = $job->is_enabled();

		$this->assertFalse( $enabled );
	}
}
