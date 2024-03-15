<?php

namespace Automattic\WP\Cron_Control\Tests;

use Automattic\WP\Cron_Control\REST_API;
use WP_REST_Request;
use WP_REST_Server;
use WP_CRON_CONTROL_SECRET;

class REST_API_Tests extends \WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server;
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		Utils::clear_cron_table();
	}

	function tearDown(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		Utils::clear_cron_table();
		parent::tearDown();
	}

	/**
	 * Verify that GET requests to the endpoint fail
	 */
	public function test_invalid_request() {
		$request  = new WP_REST_Request( 'GET', '/' . REST_API::API_NAMESPACE . '/' . REST_API::ENDPOINT_LIST );
		$response = $this->server->dispatch( $request );
		$this->assertResponseStatus( 404, $response );
	}

	/**
	 * Test that list endpoint returns expected format
	 */
	public function test_get_items() {
		$event = Utils::create_test_event();

		// Don't test internal events with this test.
		$internal_events = array(
			'a8c_cron_control_force_publish_missed_schedules',
			'a8c_cron_control_confirm_scheduled_posts',
			'a8c_cron_control_clean_legacy_data',
			'a8c_cron_control_purge_completed_events',
		);
		foreach ( $internal_events as $internal_event ) {
			wp_clear_scheduled_hook( $internal_event );
		}

		$request = new WP_REST_Request( 'POST', '/' . REST_API::API_NAMESPACE . '/' . REST_API::ENDPOINT_LIST );
		$request->set_body(
			wp_json_encode(
				array(
					'secret' => WP_CRON_CONTROL_SECRET,
				)
			)
		);
		$request->set_header( 'content-type', 'application/json' );

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertResponseStatus( 200, $response );
		$this->assertArrayHasKey( 'events', $data );
		$this->assertArrayHasKey( 'endpoint', $data );
		$this->assertArrayHasKey( 'total_events_pending', $data );

		$this->assertResponseData(
			array(
				'events' => array(
					array(
						'timestamp' => $event->get_timestamp(),
						'action'    => md5( $event->get_action() ),
						'instance'  => $event->get_instance(),
					),
				),
				'endpoint' => get_rest_url( null, REST_API::API_NAMESPACE . '/' . REST_API::ENDPOINT_RUN ),
				'total_events_pending' => 1,
			),
			$response
		);
	}

	/**
	 * Test that list endpoint returns expected format
	 */
	public function test_run_event() {
		$event = Utils::create_test_event();

		$expected_data = [
			'action'    => md5( $event->get_action() ),
			'instance'  => $event->get_instance(),
			'timestamp' => $event->get_timestamp(),
			'secret'    => WP_CRON_CONTROL_SECRET,
		];

		$request = new WP_REST_Request( 'PUT', '/' . REST_API::API_NAMESPACE . '/' . REST_API::ENDPOINT_RUN );
		$request->set_body( wp_json_encode( $expected_data ) );
		$request->set_header( 'content-type', 'application/json' );

		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertResponseStatus( 200, $response );
		$this->assertArrayHasKey( 'success', $data );
		$this->assertArrayHasKey( 'message', $data );
	}

	/**
	 * Check response code
	 *
	 * @param string $status Status code.
	 * @param object $response REST API response object.
	 */
	protected function assertResponseStatus( $status, $response ) {
		$this->assertEquals( $status, $response->get_status() );
	}

	/**
	 * Ensure response includes the expected data
	 *
	 * @param array  $data Expected data.
	 * @param object $response REST API response object.
	 */
	protected function assertResponseData( $data, $response ) {
		$this->assert_array_equals( $data, $response->get_data() );
	}

	private function assert_array_equals( $expected, $test ) {
		$tested_data = array();

		foreach ( $expected as $key => $value ) {
			if ( isset( $test[ $key ] ) ) {
				$tested_data[ $key ] = $test[ $key ];
			} else {
				$tested_data[ $key ] = null;
			}
		}

		$this->assertEquals( $expected, $tested_data );
	}
}
