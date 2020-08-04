<?php

namespace Automattic\VIP\Search;

class HealthJob_Test extends \WP_UnitTestCase {
	public static function setUpBeforeClass() {
		define( 'VIP_ELASTICSEARCH_ENDPOINTS', array( 'https://elasticsearch:9200' ) );

		require_once __DIR__ . '/../../../../search/search.php';

		\Automattic\VIP\Search\Search::instance();

		// Required so that EP registers the Indexables
		do_action( 'plugins_loaded' );

		// Users indexable doesn't get registered by default, but we have tests that queue user objects
		\ElasticPress\Indexables::factory()->register( new \ElasticPress\Indexable\User\User() );
	}

	public function setUp() {
		require_once __DIR__ . '/../../../../search/includes/classes/class-health-job.php';
	}

	public function test__vip_search_healthjob_check_health() {
		// We have to test under the assumption that the main class has been loaded and initialized,
		// as it does various setup tasks like including dependencies
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$job = new \Automattic\VIP\Search\HealthJob();

		$job->check_health();
	}

	public function test__vip_search_healthjob_check_health_with_inactive_features() {
		add_filter( 'enable_vip_search_healthchecks', '__return_true' );
		update_option( 'ep_last_sync', time() ); // So EP thinks we've done an index before

		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$users_mock = $this->getMockBuilder( \ElasticPress\Feature\Users\Users::class )
			->setMethods( array( 'is_active' ) )
			->getMock();

		$users_mock->method( 'is_active' )->will( $this->returnValue( false ) );

		// Mock the users feature
		\ElasticPress\Features::factory()->registered_features['users'] = $users_mock;

		// Mock the health job
		$job = $this->getMockBuilder( \Automattic\VIP\Search\HealthJob::class )
			->setMethods( array( 'process_results' ) )
			->getMock();

		// Only expect it to process 1 set of results (for regular posts)
		$job->expects( $this->exactly( 1 ) )
			->method( 'process_results' );

		$job->check_health();

		remove_filter( 'enable_vip_search_healthchecks', '__return_true' );
	}

	/**
	 * Test that we correctly handle the results of health checks when inconsistencies are found
	 */
	public function test__vip_search_healthjob_process_results_with_inconsistencies() {
		$results = array(
			array(
				'entity' => 'post',
				'type' => 'post',
				'db_total' => 1000,
				'es_total' => 900,
				'diff' => -100,
			),
			array(
				'entity' => 'post',
				'type' => 'custom_type',
				'db_total' => 100,
				'es_total' => 200,
				'diff' => 100,
			),
			array(
				'entity' => 'users',
				'type' => 'N/A',
				'db_total' => 100,
				'es_total' => 100,
				'diff' => 0,
			),
			array(
				'error' => 'Foo Error',
			),
		);

		// We have to test under the assumption that the main class has been loaded and initialized,
		// as it does various setup tasks like including dependencies
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$stub = $this->getMockBuilder( \Automattic\VIP\Search\HealthJob::class )
			->setMethods( [ 'send_alert' ] )
			->getMock();

		$stub->expects( $this->exactly( 3 ) )
			->method( 'send_alert' )
			->withConsecutive(
				array(
					'#vip-go-es-alerts',
					sprintf(
						'Index inconsistencies found for %s: (entity: %s, type: %s, DB count: %s, ES count: %s, Diff: %s)',
						home_url(),
						$results[0]['entity'],
						$results[0]['type'],
						$results[0]['db_total'],
						$results[0]['es_total'],
						$results[0]['diff']
					),
					2,
					"{$results[0]['entity']}:{$results[0]['type']}",
				),
				array(
					'#vip-go-es-alerts',
					sprintf(
						'Index inconsistencies found for %s: (entity: %s, type: %s, DB count: %s, ES count: %s, Diff: %s)',
						home_url(),
						$results[1]['entity'],
						$results[1]['type'],
						$results[1]['db_total'],
						$results[1]['es_total'],
						$results[1]['diff']
					),
					2,
					"{$results[1]['entity']}:{$results[1]['type']}",
				),
				// NOTE - we've skipped the 3rd result here b/c it has a diff of 0 and shouldn't alert
				array(
					'#vip-go-es-alerts',
					'Error while validating index for http://example.org: Foo Error',
					2
				)
			)
			->will( $this->returnValue( true ) );

		$stub->process_results( $results );
	}

	/**
	 * Test that we correctly handle the results of health checks when a check fails completely
	 */
	public function test__vip_search_healthjob_process_results_with_wp_error() {
		$results = new \WP_Error( 'foo', 'Bar' );

		// We have to test under the assumption that the main class has been loaded and initialized,
		// as it does various setup tasks like including dependencies
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$stub = $this->getMockBuilder( \Automattic\VIP\Search\HealthJob::class )
			->setMethods( [ 'send_alert' ] )
			->getMock();

		$stub->expects( $this->once() )
			->method( 'send_alert' )
			->with(
				'#vip-go-es-alerts',
				sprintf( 'Error while validating index for %s: %s', home_url(), 'Bar' ),
				2
			)
			->will( $this->returnValue( true ) );

		$stub->process_results( $results );
	}

	public function test_vip_search_healthjob_is_not_enabled_when_indexing_is_occuring() {
		add_filter( 'ep_is_indexing', '__return_true' );

		$job = new \Automattic\VIP\Search\HealthJob();

		$enabled = $job->is_enabled();

		$this->assertFalse( $enabled );

		remove_filter( 'ep_is_indexing', '__return_true' );
	}

	public function test_vip_search_healthjob_is_not_enabled_before_first_index() {
		add_filter( 'ep_last_sync', '__return_false' );

		$job = new \Automattic\VIP\Search\HealthJob();

		$enabled = $job->is_enabled();

		$this->assertFalse( $enabled );

		remove_filter( 'ep_last_sync', '__return_false' );
	}

	public function test_vip_search_healthjob_is_enabled_when_expected() {
		add_filter( 'ep_is_indexing', '__return_false' );
		add_filter( 'ep_last_sync', '__return_true' );

		// Have to filter the enabled envs to allow `false`, which is the VIP_GO_ENV in tests
		$enabled_environments = function() {
			return [ false ];
		};

		add_filter( 'vip_search_healthchecks_enabled_environments', $enabled_environments );

		$job = new \Automattic\VIP\Search\HealthJob();

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
		define( 'DISABLE_VIP_SEARCH_HEALTHCHECKS', true );

		$job = new \Automattic\VIP\Search\HealthJob();

		$enabled = $job->is_enabled();

		$this->assertFalse( $enabled );
	}

		/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_vip_search_healthjob_is_disabled_when_app_id_matches_disabled_list() {
		define( 'VIP_GO_APP_ID', 2341 );

		$job = new \Automattic\VIP\Search\HealthJob();
		$job->health_check_disabled_sites[] = VIP_GO_APP_ID;

		$enabled = $job->is_enabled();

		$this->assertFalse( $enabled );
	}
}
