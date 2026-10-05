<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use Automattic\Test\Constant_Mocker;
use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;

class Telemetry_Test extends WP_UnitTestCase {
	public function tear_down() {
		parent::tear_down();
		Constant_Mocker::clear();
	}

	/**
	 * Exercise default and explicit-null options through real systems and queues.
	 *
	 * @dataProvider nullable_constructor_options
	 */
	public function test_default_constructor_records_real_events( $explicit_null ) {
		wp_set_current_user( $this->factory()->user->create() );
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );
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
			$this->assertCount( 2, $requests );
			$names = array();
			foreach ( $requests as list( $url, $body ) ) {
				$event   = $body['events'][0] ?? $body[0] ?? $body;
				$names[] = $event['_en'] ?? $event['event'];
				$this->assertSame( 'bar', $event['foo'] ?? $event['properties']['foo'] );
			}
			sort( $names );
			$this->assertSame( array( 'vip_default_event', 'vip_wordpress_default_event' ), $names );
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
		$user = $this->factory()->user->create_and_get();
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );

		/** @var MockObject|Telemetry_Event_Queue */
		$queue = $this->getMockBuilder( Telemetry_Event_Queue::class )
			->disableOriginalConstructor()
			->getMock();

		$queue->expects( $this->exactly( 2 ) )
			->method( 'record_event_asynchronously' )
			->with($this->callback( function ( Telemetry_Event $event ) {
				$event_data = $event->get_data();

				$this->assertFalse( is_wp_error( $event_data ) );
				$this->assertSame( 'test_cool_event', $event_data->_en ?? $event_data->event );
				$this->assertSame( 'bar', $event_data->foo ?? $event_data->properties->foo );

				return true;
			} ) )
			->willReturn( true );

		$telemetry = new Telemetry( 'test_', [], $queue );

		$this->assertTrue( $telemetry->record_event( 'cool_event', [ 'foo' => 'bar' ] ) );
	}

	public function test_event_queued_with_global_properties() {
		$user = $this->factory()->user->create_and_get();
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );

		/** @var MockObject|Telemetry_Event_Queue */
		$queue = $this->getMockBuilder( Telemetry_Event_Queue::class )
			->disableOriginalConstructor()
			->getMock();

		$queue->expects( $this->exactly( 2 ) )
			->method( 'record_event_asynchronously' )
			->with($this->callback( function ( Telemetry_Event $event ) {
				$event_data = $event->get_data();

				$this->assertFalse( is_wp_error( $event_data ) );
				$this->assertSame( 'nice_fuzzy_event', $event_data->_en ?? $event_data->event );
				$this->assertSame( 'bar', $event_data->foo ?? $event_data->properties->foo );
				$this->assertSame( 'qux', $event_data->global_baz ?? $event_data->properties->global_baz );

				return true;
			} ) )
			->willReturn( true );

		$telemetry = new Telemetry( 'nice_', [
			'global_baz' => 'qux',
			'foo'        => 'default_foo',
		], $queue );
		$this->assertTrue( $telemetry->record_event( 'fuzzy_event', [ 'foo' => 'bar' ] ) );
	}

	public function test_event_queued_with_pendo_disabled() {
		$user = $this->factory()->user->create_and_get();
		wp_set_current_user( $user->ID );

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

				return true;
			} ) )
			->willReturn( true );

		$telemetry = new Telemetry( 'test_', [], $queue );

		// Returns false because at least one system is disabled.
		$this->assertFalse( $telemetry->record_event( 'cool_event', [ 'foo' => 'bar' ] ) );
	}
}
