<?php

namespace Automattic\VIP\Search;

use WP_UnitTestCase;
use Automattic\Test\Constant_Mocker;
use ElasticPress\Indexable\User\User;
use ElasticPress\Indexables;

// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export

class HealthJob_Test extends WP_UnitTestCase {
	/** @var Search */
	private $search;

	public function setUp(): void {
		parent::setUp();

		Constant_Mocker::clear();
		Constant_Mocker::define( 'VIP_ELASTICSEARCH_ENDPOINTS', array( 'https://elasticsearch:9200' ) );
		Constant_Mocker::define( 'VIP_GO_ENV', 'test' );

		require_once __DIR__ . '/../../../../search/search.php';
		require_once __DIR__ . '/../../../../search/includes/classes/class-healthjob.php';

		$this->search = new Search();
		$this->search->init();
		// Required so that EP registers the Indexables
		do_action( 'plugins_loaded' );

		// Users indexable doesn't get registered by default, but we have tests that queue user objects
		Indexables::factory()->register( new User() );
	}

	public function tearDown(): void {
		Constant_Mocker::clear();
		parent::tearDown();
	}

	public function test_vip_search_healthjob_is_enabled_when_expected() {
		add_filter( 'ep_is_indexing', '__return_false' );
		add_filter( 'ep_last_sync', '__return_true' );

		add_filter( 'vip_search_healthchecks_enabled_environments', fn() => [ 'test' ] );

		$job = new HealthJob( $this->search );

		$enabled = $job->is_enabled();

		$this->assertTrue( $enabled );
	}

	public function test_vip_search_healthjob_is_disabled_when_constant_is_set() {
		Constant_Mocker::define( 'DISABLE_VIP_SEARCH_HEALTHCHECKS', true );

		$job = new HealthJob( $this->search );

		$enabled = $job->is_enabled();

		$this->assertFalse( $enabled );
	}

	public function test_vip_search_healthjob_is_disabled_when_app_id_matches_disabled_list() {
		Constant_Mocker::define( 'VIP_GO_APP_ID', 2341 );

		$job                                = new HealthJob( $this->search );
		$job->health_check_disabled_sites[] = Constant_Mocker::constant( 'VIP_GO_APP_ID' );

		$enabled = $job->is_enabled();

		$this->assertFalse( $enabled );
	}
}
