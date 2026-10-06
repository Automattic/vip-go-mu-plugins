<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry\Tracks;

use WP_Error;
use WP_Http;
use WP_UnitTestCase;
use Automattic\VIP\Logstash\Testable_Logger;

require_once __DIR__ . '/../../logstash/class-testable-logger.php';

class Tracks_Client_Test extends WP_UnitTestCase {

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

		$client = new Tracks_Client( $http );
		$this->assertTrue( $client->batch_record_events( [ $event, $bad_event ], [ 'foo' => 'bar' ] ) );
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
			$event = new Tracks_Event( 'test_', 'queue_event' );
			$queue = new \Automattic\VIP\Telemetry\Telemetry_Event_Queue( new Tracks_Client( $http ) );
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
						'error_codes' => array( 'tracks_http_rejected' ),
						'http_status' => $status,
					), json_decode( $entries[0]['extra'], true ) );
				}
			} finally {
				remove_action( 'shutdown', array( $queue, 'record_events' ) );
			}
		}
	}

	public function test_should_handle_failed_requests() {
		$http = $this->getMockBuilder( WP_Http::class )
			->disableOriginalConstructor()
			->getMock();

		$event = $this->getMockBuilder( Tracks_Event::class )
			->disableOriginalConstructor()
			->getMock();

		$event->expects( $this->once() )->method( 'is_recordable' )->willReturn( true );
		$event->expects( $this->once() )->method( 'jsonSerialize' )->willReturn( [ 'test_event' => true ] );

		$error = new WP_Error( 'http_request_failed', 'This is a failure', array( 'private_response' => 'must-not-be-logged' ) );

		$http->expects( $this->once() )
			->method( 'post' )
			->with( $this->stringContains( 'tracks/record' ) )
			->willReturn( $error );

		$client = new Tracks_Client( $http );
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
		$http = $this->getMockBuilder( WP_Http::class )
			->disableOriginalConstructor()
			->getMock();

		$bad_event = $this->getMockBuilder( Tracks_Event::class )
			->disableOriginalConstructor()
			->getMock();

		$bad_event->expects( $this->once() )->method( 'is_recordable' )->willReturn( false );

		$http->expects( $this->never() )
			->method( 'post' );

		$client = new Tracks_Client( $http );
		$this->assertTrue( $client->batch_record_events( [ $bad_event ], [ 'foo' => 'bar' ] ) );
	}
}
