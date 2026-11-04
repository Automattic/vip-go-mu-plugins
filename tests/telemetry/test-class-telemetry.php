<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use Automattic\Test\Constant_Mocker;
use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;

require_once __DIR__ . '/trait-telemetry-test-helpers.php';

class Telemetry_Test extends WP_UnitTestCase {
	use Telemetry_Test_Helpers;

	/**
	 * Exercise default and explicit-null options through real systems and queues.
	 *
	 * Runs in a VIP production environment that still defines the legacy Pendo
	 * key, so it also guards against events reaching anything but Tracks.
	 *
	 * @dataProvider nullable_constructor_options
	 */
	public function test_default_constructor_records_real_events( $explicit_null ) {
		$this->login_as();
		$this->enable_vip_production_environment();
		Constant_Mocker::define( 'VIP_PENDO_TRACK_INTEGRATION_KEY', 'fake-integration-key' );

		global $wp_filter;
		$original_shutdown = $wp_filter['shutdown'];
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Isolate the dispatch boundary.
		$wp_filter['shutdown'] = new \WP_Hook();
		$requests              = array();
		$capture               = static function ( $response, $args, $url ) use ( &$requests ) {
			$requests[] = array( $url, json_decode( $args['body'], true ) );
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => '',
				'headers'  => array(),
			);
		};
		add_filter( 'pre_http_request', $capture, PHP_INT_MAX, 3 );
		try {
			$telemetry = $explicit_null ? new Telemetry( null, null, null ) : new Telemetry();
			$this->assertTrue( $telemetry->record_event( 'default_event', array( 'foo' => 'bar' ) ) );
			do_action( 'shutdown' );
			$this->assertCount( 1, $requests );
			list( $url, $body ) = $requests[0];
			$this->assertSame( 'https://public-api.wordpress.com/rest/v1.1/tracks/record', $url );
			$this->assertSame( 'vip_default_event', $body['events'][0]['_en'] );
			$this->assertSame( 'bar', $body['events'][0]['foo'] );
		} finally {
			remove_filter( 'pre_http_request', $capture, PHP_INT_MAX );
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the dispatch boundary.
			$wp_filter['shutdown'] = $original_shutdown;
		}
	}

	/**
	 * Constructor variants sharing the documented fallback contract.
	 */
	public function nullable_constructor_options() {
		return array(
			'defaults'       => array( false ),
			'explicit nulls' => array( true ),
		);
	}

	public function test_event_queued() {
		$this->login_as();

		/** @var MockObject|Telemetry_Event_Queue */
		$queue = $this->getMockBuilder( Telemetry_Event_Queue::class )
			->disableOriginalConstructor()
			->getMock();

		$queue->expects( $this->once() )
			->method( 'record_event_asynchronously' )
			->with($this->callback( function ( Telemetry_Event $event ) {
				$event_data = $event->get_data();

				$this->assertFalse( is_wp_error( $event_data ) );
				$this->assertSame( 'test_cool_event', $event_data->_en );
				$this->assertSame( 'bar', $event_data->foo );
				$this->assertFalse( isset( $event_data->global_baz ) );

				return true;
			} ) )
			->willReturn( true );

		$telemetry = new Telemetry( 'test_', [], $queue );

		$this->assertTrue( $telemetry->record_event( 'cool_event', [ 'foo' => 'bar' ] ) );
	}

	public function test_event_queued_with_global_properties() {
		$this->login_as();

		/** @var MockObject|Telemetry_Event_Queue */
		$queue = $this->getMockBuilder( Telemetry_Event_Queue::class )
			->disableOriginalConstructor()
			->getMock();

		$queue->expects( $this->once() )
			->method( 'record_event_asynchronously' )
			->with($this->callback( function ( Telemetry_Event $event ) {
				$event_data = $event->get_data();

				$this->assertFalse( is_wp_error( $event_data ) );
				$this->assertSame( 'nice_fuzzy_event', $event_data->_en );
				$this->assertSame( 'bar', $event_data->foo );
				$this->assertSame( 'qux', $event_data->global_baz );

				return true;
			} ) )
			->willReturn( true );

		$telemetry = new Telemetry( 'nice_', [
			'global_baz' => 'qux',
			'foo'        => 'default_foo',
		], $queue );
		$this->assertTrue( $telemetry->record_event( 'fuzzy_event', [ 'foo' => 'bar' ] ) );
	}
}
