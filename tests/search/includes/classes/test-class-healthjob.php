<?php

namespace Automattic\VIP\Search;

class HealthJob_Test extends \WP_UnitTestCase {
	/**
	 * Make tests run in separate processes since we're testing state
	 * related to plugin init, including various constants.
	 */
	protected $preserveGlobalState      = false; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
	protected $runTestInSeparateProcess = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

	public static function setUpBeforeClass() {
		if ( ! defined( 'VIP_ELASTICSEARCH_ENDPOINTS' ) ) {
			define( 'VIP_ELASTICSEARCH_ENDPOINTS', array( 'https://elasticsearch:9200' ) );
		}

		require_once __DIR__ . '/../../../../search/search.php';

		\Automattic\VIP\Search\Search::instance();

		// Required so that EP registers the Indexables
		do_action( 'plugins_loaded' );

		// Users indexable doesn't get registered by default, but we have tests that queue user objects
		\ElasticPress\Indexables::factory()->register( new \ElasticPress\Indexable\User\User() );
	}

	public function setUp() {
		require_once __DIR__ . '/../../../../search/includes/classes/class-healthjob.php';
	}

	public function test__vip_search_healthjob_check_health() {
		// We have to test under the assumption that the main class has been loaded and initialized,
		// as it does various setup tasks like including dependencies
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$job = new \Automattic\VIP\Search\HealthJob( $es );

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
			->setConstructorArgs( [ $es ] )
			->setMethods( array( 'process_document_count_health_results', 'send_alert' ) )
			->getMock();

		// Only expect it to process 1 set of results (for regular posts)
		$job->expects( $this->exactly( 1 ) )
			->method( 'process_document_count_health_results' );

		$job->check_health();

		remove_filter( 'enable_vip_search_healthchecks', '__return_true' );
	}

	/**
	 * Test that we correctly handle the results of health checks when inconsistencies are found
	 */
	public function test__vip_search_healthjob_process_results_with_inconsistencies() {
		$results = array(
			array(
				'entity'        => 'post',
				'type'          => 'post',
				'db_total'      => 1000,
				'es_total'      => 900,
				'index_version' => 111,
				'diff'          => -100,
			),
			array(
				'entity'        => 'post',
				'type'          => 'custom_type',
				'db_total'      => 100,
				'es_total'      => 200,
				'index_name'    => 'posts-123',
				'index_version' => 222,
				'diff'          => 100,
			),
			array(
				'entity'        => 'users',
				'type'          => 'N/A',
				'db_total'      => 100,
				'es_total'      => 100,
				'index_name'    => 'posts-123',
				'index_version' => 333,
				'diff'          => 0,
			),
			array(
				'index_name'    => 'posts-123',
				'index_version' => 333,
				'error' => 'Foo Error',
			),
		);

		// We have to test under the assumption that the main class has been loaded and initialized,
		// as it does various setup tasks like including dependencies
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$stub = $this->getMockBuilder( \Automattic\VIP\Search\HealthJob::class )
			->setConstructorArgs( [ $es ] )
			->setMethods( [ 'send_alert' ] )
			->getMock();

		$stub->expects( $this->exactly( 3 ) )
			->method( 'send_alert' )
			->withConsecutive(
				array(
					'#vip-go-es-inconsistencies',
					$this->getExpectedDiffMessage( $results[0] ),
					2,
					"{$results[0]['entity']}:{$results[0]['type']}",
				),
				array(
					'#vip-go-es-inconsistencies',
					$this->getExpectedDiffMessage( $results[1] ),
					2,
					"{$results[1]['entity']}:{$results[1]['type']}",
				),
				// NOTE - we've skipped the 3rd result here b/c it has a diff of 0 and shouldn't alert
				array(
					'#vip-go-es-alerts',
					'Error while validating index for http://example.org: Foo Error (index_name: posts-123, index_version: 333)',
					2,
				)
			)
			->will( $this->returnValue( true ) );

		$stub->process_document_count_health_results( $results );
	}

	private function getExpectedDiffMessage( $result ) {
		return sprintf(
			'Index inconsistencies found for %s: (entity: %s, type: %s, index_name: %s, index_version: %d, DB count: %s, ES count: %s, Diff: %s)',
			home_url(),
			$result['entity'],
			$result['type'],
			$result['index_name'] ?? '<unknown>',
			$result['index_version'],
			$result['db_total'],
			$result['es_total'],
			$result['diff']
		);
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
			->setConstructorArgs( [ $es ] )
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

		$stub->process_document_count_health_results( $results );
	}

	public function test_vip_search_healthjob_is_not_enabled_when_indexing_is_occuring() {
		add_filter( 'ep_is_indexing', '__return_true' );

		$job = new \Automattic\VIP\Search\HealthJob( Search::instance() );

		$enabled = $job->is_enabled();

		$this->assertFalse( $enabled );

		remove_filter( 'ep_is_indexing', '__return_true' );
	}

	public function test_vip_search_healthjob_is_not_enabled_before_first_index() {
		add_filter( 'ep_last_sync', '__return_false' );

		$job = new \Automattic\VIP\Search\HealthJob( Search::instance() );

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
		define( 'DISABLE_VIP_SEARCH_HEALTHCHECKS', true );

		$job = new \Automattic\VIP\Search\HealthJob( Search::instance() );

		$enabled = $job->is_enabled();

		$this->assertFalse( $enabled );
	}

		/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_vip_search_healthjob_is_disabled_when_app_id_matches_disabled_list() {
		define( 'VIP_GO_APP_ID', 2341 );

		$job                                = new \Automattic\VIP\Search\HealthJob( Search::instance() );
		$job->health_check_disabled_sites[] = VIP_GO_APP_ID;

		$enabled = $job->is_enabled();

		$this->assertFalse( $enabled );
	}

	/**
	 * Test that we correctly handle the results of index settings health checks when inconsistencies are found
	 */
	public function test__vip_search_healthjob_process_indexables_settings_health_results() {
		$this->markTestSkipped( 'currently disabled while we have the alert sending disabled' );

		$results = array(
			'post' => array(
				array(
					'index_version' => 1,
					'index_name'    => 'foo',
					'diff'          => array(
						'bar' => array(
							'expected' => 1,
							'actual'   => '2',
						),
					),
				),
				array(
					'index_version' => 1,
					'index_name'    => 'foo',
					'diff'          => array(
						'bar' => array(
							'expected' => 3,
							'actual'   => '4',
						),
					),
				),
			),
			'user' => array(
				array(
					'index_version' => 1,
					'index_name'    => 'foo',
					'diff'          => array(
						'bar' => array(
							'expected' => 5,
							'actual'   => '6',
						),
					),
				),
			),
			'foo'  => new \WP_Error( 'foo-error', 'foo message' ),
		);

		// We have to test under the assumption that the main class has been loaded and initialized,
		// as it does various setup tasks like including dependencies
		$es = new \Automattic\VIP\Search\Search();
		$es->init();

		$stub = $this->getMockBuilder( \Automattic\VIP\Search\HealthJob::class )
			->setConstructorArgs( [ $es ] )
			->setMethods( [ 'send_alert' ] )
			->getMock();

		$stub->expects( $this->exactly( 4 ) )
			->method( 'send_alert' )
			->withConsecutive(
				array(
					'#vip-go-es-alerts',

					sprintf(
						'Index settings inconsistencies found for %s: (indexable: %s, index_version: %d, index_name: %s, diff: %s)',
						home_url(),
						'post',
						$results['post'][0]['index_version'],
						$results['post'][0]['index_name'],
						var_export( $results['post'][0]['diff'], true )
					),
					2,
					'post',
				),
				array(
					'#vip-go-es-alerts',
					sprintf(
						'Index settings inconsistencies found for %s: (indexable: %s, index_version: %d, index_name: %s, diff: %s)',
						home_url(),
						'post',
						$results['post'][1]['index_version'],
						$results['post'][1]['index_name'],
						var_export( $results['post'][1]['diff'], true )
					),
					2,
					'post',
				),
				array(
					'#vip-go-es-alerts',

					sprintf(
						'Index settings inconsistencies found for %s: (indexable: %s, index_version: %d, index_name: %s, diff: %s)',
						home_url(),
						'user',
						$results['user'][0]['index_version'],
						$results['user'][0]['index_name'],
						var_export( $results['user'][0]['diff'], true )
					),
					2,
					'user',
				),
				array(
					'#vip-go-es-alerts',
					sprintf( 'Error while validating index settings for indexable %s on %s: %s', 'foo', home_url(), 'foo message' ),
					2,
				)
			)
			->will( $this->returnValue( true ) );

		$stub->process_indexables_settings_health_results( $results );
	}
}
