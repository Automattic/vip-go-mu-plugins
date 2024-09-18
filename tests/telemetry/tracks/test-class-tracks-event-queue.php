<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use WP_UnitTestCase;

class Tracks_Event_Queue_Test extends WP_UnitTestCase {

	public function test_should_create_queue() {
		$client = $this->getMockBuilder( Tracks_Client::class )
			->disableOriginalConstructor()
			->getMock();

		$event = $this->getMockBuilder( Tracks_Event::class )
			->disableOriginalConstructor()
			->getMock();
		$event->expects( $this->once() )->method( 'is_recordable' )->willReturn( true );

		$client->expects( $this->once() )
			->method( 'batch_record_events' )
			->with( [ $event ] )
			->willReturn( true );

		$queue = new Tracks_Event_Queue( $client );
		$queue->record_event_asynchronously( $event );
		$queue->record_events();
		$queue->record_events();
	}
}
