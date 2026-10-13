<?php

namespace Automattic\VIP\Search;

use WP_UnitTestCase;
use ElasticPress\Indexable;
use ElasticPress\Indexables;
use PHPUnit\Framework\MockObject\MockObject;
use WP_Error;

require_once __DIR__ . '/../../../../search/includes/classes/class-settingshealthjob.php';
require_once __DIR__ . '/trait-es-http-mock.php';
require_once __DIR__ . '/trait-search-test-bootstrap.php';

class SettingsHealthJob_Test extends WP_UnitTestCase {
	use ES_HTTP_Mock;
	use Search_Test_Bootstrap;

	/** @var Search */
	public $search;
	/** @var Versioning */
	public $version_instance;

	public function setUp(): void {
		parent::setUp();

		$this->search = $this->boot_search( [
			'FILES_CLIENT_SITE_ID'        => 123,
			'VIP_ELASTICSEARCH_ENDPOINTS' => array(
				'https://es-endpoint1',
				'https://es-endpoint2',
			),
		] );

		$this->version_instance = $this->search->versioning;

		$this->add_es_http_mock();
	}

	public function tearDown(): void {
		$this->remove_es_http_mock();
		parent::tearDown();
	}

	public function process_indexables_settings_health_results_error_data() {
		$error = new WP_Error( 'foo', 'Bar' );

		return [
			'whole check failed' => [ $error, 1 ],
			'indexables failed'  => [
				[
					'post' => $error,
					'user' => $error,
				],
				2,
			],
		];
	}

	/**
	 * @dataProvider process_indexables_settings_health_results_error_data
	 */
	public function test__process_indexables_settings_health_results__reports_error( $results, $expected_alerts ) {
		$stub = $this->stub_job();

		$stub->expects( $this->exactly( $expected_alerts ) )
			->method( 'send_alert' );

		$stub->process_indexables_settings_health_results( $results );
	}

	public function test__heal_index_settings__reports_error_per_failed_indexable_retrieval() {
		$error                = new WP_Error( 'foo', 'Bar' );
		$unhealthy_indexables = [
			'post' => [],
			'user' => [],
		];

		$indexables_mock = $this->createMock( Indexables::class );
		$indexables_mock->method( 'get' )->willReturn( $error );

		$stub = $this->stub_job();

		$stub->indexables = $indexables_mock;

		$stub->expects( $this->exactly( count( $unhealthy_indexables ) ) )
			->method( 'send_alert' );

		$stub->heal_index_settings( $unhealthy_indexables );
	}

	public function test__heal_index_settings__heal_indexables_with_diff() {
		$indexable_versions_with_non_empty_diff = 1; // only post has diff
		$unhealthy_indexables                   = [
			'post' => [
				[
					'index_version' => 1,
					'diff'          => [ 'index.max_result_window' => [] ],
				],
			],
			'user' => [
				[
					'index_version' => 1,
					'diff'          => [], // user has no diff, so won't be healed
				],
			],
		];

		$indexables_mock = $this->createMock( Indexables::class );
		$indexables_mock->method( 'get' )->willReturn( $this->createMock( Indexable::class ) );

		$versioning_mock = $this->getMockBuilder( Versioning::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'get_active_version_number' ] )
			->getMock();

		// Both indexables have version 1 as active
		$versioning_mock->method( 'get_active_version_number' )
			->willReturn( 1 );

		$search_mock             = $this->createMock( Search::class );
		$search_mock->versioning = $versioning_mock;

		/** @var MockObject&Health */
		$health_mock = $this->getMockBuilder( Health::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'heal_index_settings_for_indexable' ] )
			->getMock();

		$health_mock->method( 'heal_index_settings_for_indexable' )->willReturn( array(
			'result'        => true,
			'index_version' => 1,
			'index_name'    => 'foo-index',
		) );

		$stub = $this->stub_job();

		$stub->indexables = $indexables_mock;
		$stub->health     = $health_mock;
		$stub->search     = $search_mock;

		$health_mock->expects( $this->exactly( $indexable_versions_with_non_empty_diff ) )
			->method( 'heal_index_settings_for_indexable' );

		$stub->heal_index_settings( $unhealthy_indexables );
	}

	public function test__maybe_process_build__one_version_existence() {
		$indexable = Indexables::factory()->get( 'post' );

		$stub = $this->stub_job();

		$stub->expects( $this->never() )
			->method( 'send_alert' );

		$stub->maybe_process_build( $indexable );

		$event = wp_next_scheduled( SettingsHealthJob::CRON_EVENT_BUILD_NAME, [ $indexable->slug ] );
		$this->assertIsInt( $event );
	}

	public function test__maybe_process_build__two_version_existence() {
		$indexable = Indexables::factory()->get( 'post' );

		$this->version_instance->add_version( $indexable );

		$stub = $this->stub_job();

		$stub->expects( $this->once() )
			->method( 'send_alert' );

		$stub->maybe_process_build( $indexable );

		$event = wp_next_scheduled( SettingsHealthJob::CRON_EVENT_BUILD_NAME, [ $indexable->slug ] );
		$this->assertFalse( $event );
	}

	public function test__maybe_process_build__locks() {
		update_option( SettingsHealthJob::BUILD_LOCK_NAME, time() );

		$indexable = Indexables::factory()->get( 'post' );

		$stub = $this->stub_job();

		$stub->expects( $this->never() )
			->method( 'send_alert' );

		$stub->maybe_process_build( $indexable );

		$event = wp_next_scheduled( SettingsHealthJob::CRON_EVENT_BUILD_NAME, [ $indexable->slug ] );
		$this->assertFalse( $event );
	}

	public function test__maybe_process_build__in_progress() {
		update_option( SettingsHealthJob::BUILD_LOCK_NAME, time() );
		$last_processed_id = '1234';
		update_option( SettingsHealthJob::LAST_PROCESSED_ID_OPTION, $last_processed_id );

		$stub = $this->stub_job( [ 'check_process_build', 'alert_to_swap_index_versions', 'send_alert' ] );

		$stub->method( 'check_process_build' )
			->willReturn( 'in-progress' );

		$stub->expects( $this->never() )
			->method( 'alert_to_swap_index_versions' );
		$stub->expects( $this->never() )
			->method( 'send_alert' );

		$indexable = Indexables::factory()->get( 'post' );
		$stub->maybe_process_build( $indexable );

		// Unlike 'resume', a build that is still in progress must not be rescheduled from the last processed ID
		$this->assertFalse( wp_next_scheduled( SettingsHealthJob::CRON_EVENT_BUILD_NAME, [ $indexable->slug, $last_processed_id ] ) );
		$this->assertFalse( wp_next_scheduled( SettingsHealthJob::CRON_EVENT_BUILD_NAME, [ $indexable->slug ] ) );
	}

	public function test__maybe_process_build__resume() {
		update_option( SettingsHealthJob::BUILD_LOCK_NAME, time() );
		$last_processed_id = '1234';
		update_option( SettingsHealthJob::LAST_PROCESSED_ID_OPTION, $last_processed_id );

		$stub = $this->stub_job( [ 'check_process_build' ] );

		$stub->method( 'check_process_build' )
			->willReturn( 'resume' );

		$indexable = Indexables::factory()->get( 'post' );
		$stub->maybe_process_build( $indexable );

		$event = wp_next_scheduled( SettingsHealthJob::CRON_EVENT_BUILD_NAME, [ $indexable->slug, $last_processed_id ] );
		$this->assertIsInt( $event );
	}

	public function test__maybe_process_build__swap() {
		update_option( SettingsHealthJob::BUILD_LOCK_NAME, time() );
		$completed_status = 'Indexing completed';
		update_option( SettingsHealthJob::LAST_PROCESSED_ID_OPTION, $completed_status );

		$stub = $this->stub_job( [ 'check_process_build', 'alert_to_swap_index_versions' ] );

		$stub->method( 'check_process_build' )
		->willReturn( 'swap' );

		$stub->expects( $this->once() )
			->method( 'alert_to_swap_index_versions' );

		$indexable = Indexables::factory()->get( 'post' );
		$stub->maybe_process_build( $indexable );

		$event = wp_next_scheduled( SettingsHealthJob::CRON_EVENT_BUILD_NAME, [ $indexable->slug, $completed_status ] );
		$this->assertFalse( $event );
	}

	/**
	 * @return MockObject&SettingsHealthJob
	 */
	private function stub_job( array $methods = [ 'send_alert' ] ): SettingsHealthJob {
		/** @var MockObject&SettingsHealthJob */
		$stub = $this->getMockBuilder( SettingsHealthJob::class )
			->disableOriginalConstructor()
			->onlyMethods( $methods )
			->getMock();

		$stub->search = $this->search;

		return $stub;
	}
}
