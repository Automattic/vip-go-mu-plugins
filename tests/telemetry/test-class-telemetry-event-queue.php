<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use WP_UnitTestCase;

class Telemetry_Event_Queue_Test extends WP_UnitTestCase {

	/**
	 * Deliver queued real events through production shutdown registration once.
	 */
	public function test_shutdown_delivers_queued_events_once(): void {
		wp_set_current_user( self::factory()->user->create() );
		$event  = new \Automattic\VIP\Telemetry\Tracks\Tracks_Event( 'test_', 'shutdown_event' );
		$client = $this->createMock( Telemetry_Client::class );
		$client->expects( $this->once() )->method( 'batch_record_events' )->with( array( $event ) )->willReturn( true );
		$original_shutdown = $GLOBALS['wp_filter']['shutdown'] ?? null;
		// Isolate the dispatch boundary from unrelated platform shutdown effects.
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Isolate the shutdown dispatch boundary.
		$GLOBALS['wp_filter']['shutdown'] = new \WP_Hook();
		$queue                            = new Telemetry_Event_Queue( $client );
		try {
			$this->assertTrue( $queue->record_event_asynchronously( $event ) );
			do_action( 'shutdown' );
			do_action( 'shutdown' );
			$property = new \ReflectionProperty( $queue, 'events' );
			$property->setAccessible( true );
			$this->assertSame( array(), $property->getValue( $queue ) );
		} finally {
			remove_action( 'shutdown', array( $queue, 'record_events' ) );
			if ( null === $original_shutdown ) {
				unset( $GLOBALS['wp_filter']['shutdown'] );
			} else {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore the dispatch registry.
				$GLOBALS['wp_filter']['shutdown'] = $original_shutdown;
			}
		}
	}

	public function test_should_create_queue_and_record_events() {
		$client = $this->getMockBuilder( Telemetry_Client::class )
			->disableOriginalConstructor()
			->getMock();

		$event = $this->getMockBuilder( Telemetry_Event::class )
			->disableOriginalConstructor()
			->getMock();

		$event->expects( $this->once() )->method( 'is_recordable' )->willReturn( true );

		$bad_event = $this->getMockBuilder( Telemetry_Event::class )
			->disableOriginalConstructor()
			->getMock();

		$bad_event->expects( $this->once() )->method( 'is_recordable' )->willReturn( false );

		$client->expects( $this->once() )
			->method( 'batch_record_events' )
			->with( [ $event ] )
			->willReturn( true );

		$queue = new Telemetry_Event_Queue( $client );
		$queue->record_event_asynchronously( $event );
		$queue->record_event_asynchronously( $bad_event );
		$queue->record_events();
		$queue->record_events();
	}
}
