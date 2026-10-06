<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry\Pendo;

use WP_Error;
use WP_Http;
use WP_UnitTestCase;
use Automattic\VIP\Logstash\Testable_Logger;

require_once __DIR__ . '/../../logstash/class-testable-logger.php';

class Pendo_Track_Client_Test extends WP_UnitTestCase {

	private array $original_log_entries;

	public function setUp(): void {
		parent::setUp();
		$this->original_log_entries = Testable_Logger::get_entries();
		Testable_Logger::set_entries( [] );
	}

	public function tearDown(): void {
		Testable_Logger::set_entries( $this->original_log_entries );
		parent::tearDown();
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
			->willReturn( array(
				'headers'  => array(),
				'body'     => '{}',
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			) );

		$client = new Pendo_Track_Client( 'test_api_key', $http );
		$this->assertTrue( $client->batch_record_events( [ $event, $bad_event ], [ 'foo' => 'bar' ] ) );
	}

	public function test_should_return_error_with_no_integration_key() {
		$client = new Pendo_Track_Client();

		$error = $client->batch_record_events( [ 'foo' => 'bar' ] );

		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertSame( 'pendo_track_integration_key_not_defined', $error->get_error_code() );
	}

	/**
	 * Verify successful responses and rejected HTTP statuses through a real queue.
	 */
	public function test_http_status_controls_queue_acknowledgement(): void {
		wp_set_current_user( self::factory()->user->create() );
		foreach ( array( 199, 200, 204, 299, 300, 401, 429, 500 ) as $status ) {
			Testable_Logger::set_entries( [] );
			$http = $this->createMock( WP_Http::class );
			$http->expects( $this->once() )->method( 'post' )->willReturn( array(
				'headers'  => array(),
				'body'     => '{}',
				'response' => array(
					'code'    => $status,
					'message' => 'Test response',
				),
				'cookies'  => array(),
				'filename' => null,
			) );
			$event = new Pendo_Track_Event( 'test_', 'queue_event' );
			$queue = new \Automattic\VIP\Telemetry\Telemetry_Event_Queue( new Pendo_Track_Client( 'test_api_key', $http ) );
			try {
				$this->assertTrue( $queue->record_event_asynchronously( $event ) );
				$result   = $queue->record_events();
				$property = new \ReflectionProperty( $queue, 'events' );
				if ( $status >= 200 && $status < 300 ) {
					$this->assertSame( [], Testable_Logger::get_entries() );
					$this->assertTrue( $result );
					$this->assertSame( array(), $property->getValue( $queue ) );
				} else {
					$this->assertInstanceOf( WP_Error::class, $result );
					$this->assertSame( array( 'status' => $status ), $result->get_error_data() );
					$this->assertSame( array( $event ), $property->getValue( $queue ) );
					$entries = Testable_Logger::get_entries();
					$this->assertCount( 1, $entries );
					$this->assertSame( array(
						'error'       => array( 'Telemetry endpoint rejected the event.' ),
						'error_codes' => array( 'pendo_http_rejected' ),
						'http_status' => $status,
					), json_decode( $entries[0]['extra'], true ) );
				}
			} finally {
				remove_action( 'shutdown', array( $queue, 'record_events' ) );
			}
		}
	}

	public function test_should_handle_failed_requests() {
		/** @var MockObject|WP_Http */
		$http = $this->getMockBuilder( WP_Http::class )
			->disableOriginalConstructor()
			->getMock();

		$event = $this->getMockBuilder( Pendo_Track_Event::class )
			->disableOriginalConstructor()
			->getMock();

		$event->expects( $this->once() )->method( 'is_recordable' )->willReturn( true );
		$event->expects( $this->once() )->method( 'jsonSerialize' )->willReturn( [ 'test_event' => true ] );

		$error = new WP_Error( 'http_request_failed', 'This is a failure', array( 'private_response' => 'must-not-be-logged' ) );

		$http->expects( $this->once() )
			->method( 'post' )
			->with( 'https://app.pendo.io/data/track' )
			->willReturn( $error );

		$client = new Pendo_Track_Client( 'test_api_key', $http );
		$this->assertSame( $error, $client->batch_record_events( [ $event ], [ 'foo' => 'bar' ] ) );
		$entries = Testable_Logger::get_entries();
		$this->assertCount( 1, $entries );
		$this->assertSame( array(
			'error'       => array( 'This is a failure' ),
			'error_codes' => array( 'http_request_failed' ),
			'http_status' => null,
		), json_decode( $entries[0]['extra'], true ) );
	}

	public function test_should_not_make_requests_for_no_events() {
		/** @var MockObject|WP_Http */
		$http = $this->getMockBuilder( WP_Http::class )
			->disableOriginalConstructor()
			->getMock();

		$bad_event = $this->getMockBuilder( Pendo_Track_Event::class )
			->disableOriginalConstructor()
			->getMock();

		$bad_event->expects( $this->once() )->method( 'is_recordable' )->willReturn( false );

		$http->expects( $this->never() )
			->method( 'post' );

		$client = new Pendo_Track_Client( 'test_api_key', $http );
		$this->assertTrue( $client->batch_record_events( [ $bad_event ], [ 'foo' => 'bar' ] ) );
	}
}
