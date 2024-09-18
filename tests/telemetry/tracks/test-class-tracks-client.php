<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry\Tracks;

use WP_Http;
use WP_UnitTestCase;

class Tracks_Client_Test extends WP_UnitTestCase {

	public function test_should_create_queue_and_record_events() {
		$http = $this->getMockBuilder( WP_Http::class )
			->disableOriginalConstructor()
			->getMock();

		$event = $this->getMockBuilder( Tracks_Event::class )
			->disableOriginalConstructor()
			->getMock();

		$event->expects( $this->once() )->method( 'is_recordable' )->willReturn( true );
		$event->expects( $this->once() )->method( 'jsonSerialize' )->willReturn( [ 'test_event' => true ] );

		$bad_event = $this->getMockBuilder( Tracks_Event::class )
			->disableOriginalConstructor()
			->getMock();

		$bad_event->expects( $this->once() )->method( 'is_recordable' )->willReturn( false );

		$http->expects( $this->once() )
			->method( 'post' )
			->with( $this->stringContains( 'tracks/record' ), [
				'body'       => wp_json_encode([
					'events'      => [ [ 'test_event' => true ] ],
					'commonProps' => [ 'foo' => 'bar' ],
				]),
				'user-agent' => 'viptelemetry',
				'headers'    => array(
					'Content-Type' => 'application/json',
				),

			] )
			->willReturn( true );

		$client = new Tracks_Client( $http );
		$client->batch_record_events( [ $event, $bad_event ], [ 'foo' => 'bar' ] );
	}
}
