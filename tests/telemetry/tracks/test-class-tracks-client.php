<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry\Tracks;

use Automattic\VIP\Telemetry\Telemetry_Client;
use Automattic\VIP\Telemetry\Telemetry_Client_Test_Cases;
use PHPUnit\Framework\Constraint\Constraint;
use WP_Http;
use WP_UnitTestCase;

use function Automattic\Test\Utils\http_response;

require_once __DIR__ . '/../trait-telemetry-client-test-cases.php';

class Tracks_Client_Test extends WP_UnitTestCase {
	use Telemetry_Client_Test_Cases;

	protected function make_client( WP_Http $http ): Telemetry_Client {
		return new Tracks_Client( $http );
	}

	protected function event_class(): string {
		return Tracks_Event::class;
	}

	protected function rejected_code(): string {
		return 'tracks_http_rejected';
	}

	protected function endpoint(): Constraint {
		return $this->stringContains( 'tracks/record' );
	}

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
			->willReturn( http_response() );

		$client = new Tracks_Client( $http );
		$this->assertTrue( $client->batch_record_events( [ $event, $bad_event ], [ 'foo' => 'bar' ] ) );
	}
}
