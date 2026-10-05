<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use WP_UnitTestCase;

class Telemetry_Event_Queue_Test extends WP_UnitTestCase {

	/**
	 * Return the real invalid event error without storing or sending the event.
	 */
	public function test_invalid_event_returns_validation_error(): void {
		wp_set_current_user( self::factory()->user->create() );
		$event = new \Automattic\VIP\Telemetry\Tracks\Tracks_Event( 'test_', 'Invalid Event' );
		$error = $event->is_recordable();
		$this->assertInstanceOf( \WP_Error::class, $error );
		$client = $this->createMock( Telemetry_Client::class );
		$client->expects( $this->never() )->method( 'batch_record_events' );
		$queue = new Telemetry_Event_Queue( $client );
		try {
			$this->assertSame( $error, $queue->record_event_asynchronously( $event ) );
			$property = new \ReflectionProperty( $queue, 'events' );
			$property->setAccessible( true );
			$this->assertSame( array(), $property->getValue( $queue ) );
			$this->assertTrue( $queue->record_events() );
		} finally {
			remove_action( 'shutdown', array( $queue, 'record_events' ) );
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
