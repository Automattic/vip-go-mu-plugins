<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry\Pendo;

use Automattic\VIP\Telemetry\Telemetry_Client;
use Automattic\VIP\Telemetry\Telemetry_Client_Test_Cases;
use WP_Error;
use WP_Http;
use WP_UnitTestCase;

use function Automattic\Test\Utils\http_response;

require_once __DIR__ . '/../trait-telemetry-client-test-cases.php';

class Pendo_Track_Client_Test extends WP_UnitTestCase {
	use Telemetry_Client_Test_Cases;

	protected function make_client( WP_Http $http ): Telemetry_Client {
		return new Pendo_Track_Client( 'test_api_key', $http );
	}

	protected function event_class(): string {
		return Pendo_Track_Event::class;
	}

	protected function rejected_code(): string {
		return 'pendo_http_rejected';
	}

	protected function endpoint(): string {
		return 'https://app.pendo.io/data/track';
	}

	public function test_should_create_queue_and_record_events() {
		/** @var MockObject|WP_Http */
		$http = $this->getMockBuilder( WP_Http::class )
			->disableOriginalConstructor()
			->getMock();

		$event = $this->getMockBuilder( Pendo_Track_Event::class )
			->disableOriginalConstructor()
			->getMock();

		$event->expects( $this->once() )->method( 'is_recordable' )->willReturn( true );
		$event->expects( $this->once() )->method( 'jsonSerialize' )->willReturn( [ 'test_event' => true ] );

		$bad_event = $this->getMockBuilder( Pendo_Track_Event::class )
			->disableOriginalConstructor()
			->getMock();

		$bad_event->expects( $this->once() )->method( 'is_recordable' )->willReturn( false );

		$http->expects( $this->once() )
			->method( 'post' )
			->with( 'https://app.pendo.io/data/track', [
				'body'       => wp_json_encode( [
					'test_event' => true,
				] ),
				'user-agent' => 'viptelemetry',
				'headers'    => array(
					'Content-Type'            => 'application/json',
					'x-pendo-integration-key' => 'test_api_key',
				),
			] )
			->willReturn( http_response() );

		$client = new Pendo_Track_Client( 'test_api_key', $http );
		$this->assertTrue( $client->batch_record_events( [ $event, $bad_event ], [ 'foo' => 'bar' ] ) );
	}

	public function test_should_return_error_with_no_integration_key() {
		$client = new Pendo_Track_Client();

		$error = $client->batch_record_events( [ 'foo' => 'bar' ] );

		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertSame( 'pendo_track_integration_key_not_defined', $error->get_error_code() );
	}
}
