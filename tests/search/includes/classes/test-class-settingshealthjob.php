<?php

namespace Automattic\VIP\Search;

use WP_UnitTestCase;
use Automattic\Test\Constant_Mocker;

class SettingsHealthJob_Test extends WP_UnitTestCase {
	/** @var \Automattic\VIP\Search\Search */
	public static $search;
	public static $version_instance;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once __DIR__ . '/../../../../search/search.php';
		require_once __DIR__ . '/../../../../search/includes/classes/class-settingshealthjob.php';
		require_once __DIR__ . '/../../../../prometheus.php';

		self::$search = \Automattic\VIP\Search\Search::instance();
		self::$search->init();

		if ( ! Constant_Mocker::defined( 'VIP_ELASTICSEARCH_ENDPOINTS' ) ) { // Need to define endpoints for versioning usage
			Constant_Mocker::define( 'VIP_ELASTICSEARCH_ENDPOINTS', array(
				'https://es-endpoint1',
				'https://es-endpoint2',
			) );
		}

		self::$version_instance = self::$search->versioning;

		// Required so that EP registers the Indexables
		do_action( 'plugins_loaded' );
		do_action( 'init' );
	}

	public function setUp(): void {
		parent::setUp();

		\Automattic\VIP\Prometheus\Plugin::get_instance()->init_registry();
		self::$search->load_collector();
		\Automattic\VIP\Prometheus\Plugin::get_instance()->load_collectors();
	}

	public function test__process_indexables_settings_health_results__reports_error() {
		$error = new \WP_Error( 'foo', 'Bar' );

		$stub = $this->getMockBuilder( \Automattic\VIP\Search\SettingsHealthJob::class )
			->disableOriginalConstructor()
			->setMethods( [ 'send_alert' ] )
			->getMock();

		$stub->expects( $this->once() )
			->method( 'send_alert' );

		$stub->process_indexables_settings_health_results( $error );
	}

	public function test__process_indexables_settings_health_results__reports_error_per_indexable() {
		$error                = new \WP_Error( 'foo', 'Bar' );
		$unhealthy_indexables = [
			'post' => $error,
			'user' => $error,
		];

		$stub = $this->getMockBuilder( \Automattic\VIP\Search\SettingsHealthJob::class )
			->disableOriginalConstructor()
			->setMethods( [ 'send_alert' ] )
			->getMock();

		$stub->expects( $this->exactly( count( $unhealthy_indexables ) ) )
			->method( 'send_alert' );

		$stub->process_indexables_settings_health_results( $unhealthy_indexables );
	}

	public function test__heal_index_settings__reports_error_per_failed_indexable_retrieval() {
		$error                = new \WP_Error( 'foo', 'Bar' );
		$unhealthy_indexables = [
			'post' => [],
			'user' => [],
		];

		$indexables_mock = $this->createMock( \ElasticPress\Indexables::class );
		$indexables_mock->method( 'get' )->willReturn( $error );

		$stub = $this->getMockBuilder( \Automattic\VIP\Search\SettingsHealthJob::class )
			->disableOriginalConstructor()
			->setMethods( [ 'send_alert' ] )
			->getMock();

		$stub->indexables = $indexables_mock;

		$stub->expects( $this->exactly( count( $unhealthy_indexables ) ) )
			->method( 'send_alert' );

		$stub->heal_index_settings( $unhealthy_indexables );
	}

	public function test__heal_index_settings__heal_indexables_with_diff() {
		$indexable_versions_with_non_empty_diff = 1;
		$unhealthy_indexables                   = [
			'post' => [
				[
					'index_version' => 1,
					'diff'          => [ 'index.max_result_window' => [] ],
				],
				[
					'index_version' => 2,
					'diff'          => [],
				],
			],
			'user' => [
				[
					'index_version' => 1,
					'diff'          => [ 'index.max_shingle_diff' => [] ],
				],
				[
					'index_version' => 2,
					'diff'          => [],
				],
			],
		];

		$indexables_mock = $this->createMock( \ElasticPress\Indexables::class );
		$indexables_mock->method( 'get' )->willReturn( $this->createMock( \ElasticPress\Indexable::class ) );


		$health_mock = $this->getMockBuilder( \Automattic\VIP\Search\Health::class )
			->disableOriginalConstructor()
			->setMethods( [ 'heal_index_settings_for_indexable' ] )
			->getMock();

		$health_mock->method( 'heal_index_settings_for_indexable' )->willReturn( array(
			'result'        => true,
			'index_version' => 1,
			'index_name'    => 'foo-index',
		) );

		$stub = $this->getMockBuilder( \Automattic\VIP\Search\SettingsHealthJob::class )
			->disableOriginalConstructor()
			->setMethods( [ 'send_alert' ] )
			->getMock();

		$stub->indexables = $indexables_mock;
		$stub->health     = $health_mock;

		$health_mock->expects( $this->exactly( $indexable_versions_with_non_empty_diff ) )
			->method( 'heal_index_settings_for_indexable' );

		$stub->heal_index_settings( $unhealthy_indexables );
	}

	public function test__maybe_process_build__one_version_existence() {
		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		$stub = $this->getMockBuilder( \Automattic\VIP\Search\SettingsHealthJob::class )
			->disableOriginalConstructor()
			->setMethods( [ 'wp_schedule_single_event', 'send_alert' ] )
			->getMock();

		$stub->search = self::$search;

		$stub->expects( $this->never() )
			->method( 'send_alert' );

		$stub->maybe_process_build( $indexable );

		$event = \wp_next_scheduled( \Automattic\VIP\Search\SettingsHealthJob::CRON_EVENT_BUILD_NAME, [ $indexable->slug ] );
		$this->assertIsInt( $event );
	}

	public function test__maybe_process_build__two_version_existence() {
		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		self::$version_instance->add_version( $indexable );

		$stub = $this->getMockBuilder( \Automattic\VIP\Search\SettingsHealthJob::class )
			->disableOriginalConstructor()
			->setMethods( [ 'wp_schedule_single_event', 'send_alert' ] )
			->getMock();

		$stub->search = self::$search;

		$stub->expects( $this->once() )
			->method( 'send_alert' );

		$stub->maybe_process_build( $indexable );

		$event = \wp_next_scheduled( \Automattic\VIP\Search\SettingsHealthJob::CRON_EVENT_BUILD_NAME, [ $indexable->slug ] );
		$this->assertFalse( $event );
	}

	public function test__maybe_process_build__locks() {
		update_option( \Automattic\VIP\Search\SettingsHealthJob::BUILD_LOCK_NAME, time() );

		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		$stub = $this->getMockBuilder( \Automattic\VIP\Search\SettingsHealthJob::class )
			->disableOriginalConstructor()
			->setMethods( [ 'send_alert' ] )
			->getMock();

		$stub->search = self::$search;

		$stub->expects( $this->never() )
			->method( 'send_alert' );

		$stub->maybe_process_build( $indexable );

		$event = \wp_next_scheduled( \Automattic\VIP\Search\SettingsHealthJob::CRON_EVENT_BUILD_NAME, [ $indexable->slug ] );
		$this->assertFalse( $event );

		delete_option( \Automattic\VIP\Search\SettingsHealthJob::BUILD_LOCK_NAME );

		$stub->maybe_process_build( $indexable );

		$event = \wp_next_scheduled( \Automattic\VIP\Search\SettingsHealthJob::CRON_EVENT_BUILD_NAME, [ $indexable->slug ] );
		$this->assertIsInt( $event );
	}

	public function test__maybe_process_build() {
		update_option( \Automattic\VIP\Search\SettingsHealthJob::BUILD_LOCK_NAME, time() );

		$stub = $this->getMockBuilder( \Automattic\VIP\Search\SettingsHealthJob::class )
			->disableOriginalConstructor()
			->setMethods( [ 'check_process_build' ] )
			->getMock();

		$stub->search = self::$search;

		$stub->expects( $this->once() )
			->method( 'check_process_build' );

		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );
		$status    = $stub->maybe_process_build( $indexable );
	}

	public function test__maybe_process_build__in_progress() {
		update_option( \Automattic\VIP\Search\SettingsHealthJob::BUILD_LOCK_NAME, time() );

		$stub = $this->getMockBuilder( \Automattic\VIP\Search\SettingsHealthJob::class )
			->setMethods( [ 'check_process_build' ] )
			->disableOriginalConstructor()
			->getMock();

		$stub->search = self::$search;

		$stub->method( 'check_process_build' )
			->willReturn( 'in-progress' );

		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );
		$status    = $stub->maybe_process_build( $indexable );

		$event = \wp_next_scheduled( \Automattic\VIP\Search\SettingsHealthJob::CRON_EVENT_BUILD_NAME, [ $indexable->slug ] );
		$this->assertFalse( $event );
	}

	public function test__maybe_process_build__resume() {
		update_option( \Automattic\VIP\Search\SettingsHealthJob::BUILD_LOCK_NAME, time() );
		$last_processed_id = '1234';
		update_option( \Automattic\VIP\Search\SettingsHealthJob::LAST_PROCESSED_ID_OPTION, $last_processed_id );

		$stub = $this->getMockBuilder( \Automattic\VIP\Search\SettingsHealthJob::class )
			->setMethods( [ 'check_process_build' ] )
			->disableOriginalConstructor()
			->getMock();

		$stub->search = self::$search;

		$stub->method( 'check_process_build' )
			->willReturn( 'resume' );

		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );
		$status    = $stub->maybe_process_build( $indexable );

		$event = \wp_next_scheduled( \Automattic\VIP\Search\SettingsHealthJob::CRON_EVENT_BUILD_NAME, [ $indexable->slug, $last_processed_id ] );
		$this->assertIsInt( $event );
	}

	public function test__maybe_process_build__swap() {
		update_option( \Automattic\VIP\Search\SettingsHealthJob::BUILD_LOCK_NAME, time() );
		$completed_status = 'Indexing completed';
		update_option( \Automattic\VIP\Search\SettingsHealthJob::LAST_PROCESSED_ID_OPTION, $completed_status );

		$stub = $this->getMockBuilder( \Automattic\VIP\Search\SettingsHealthJob::class )
			->disableOriginalConstructor()
			->setMethods( [ 'check_process_build', 'swap_index_versions' ] )
			->getMock();

		$stub->search = self::$search;

		$stub->method( 'check_process_build' )
		->willReturn( 'swap' );

		$stub->expects( $this->once() )
			->method( 'swap_index_versions' );

		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );
		$status    = $stub->maybe_process_build( $indexable );

		$event = \wp_next_scheduled( \Automattic\VIP\Search\SettingsHealthJob::CRON_EVENT_BUILD_NAME, [ $indexable->slug, $completed_status ] );
		$this->assertFalse( $event );
	}
}
