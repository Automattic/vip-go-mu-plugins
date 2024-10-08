<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use WP_UnitTestCase;

class Telemetry_Event_Queue_Test extends WP_UnitTestCase {

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
