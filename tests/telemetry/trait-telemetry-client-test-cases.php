<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use Automattic\VIP\Logstash\Testable_Logger;
use PHPUnit\Framework\Constraint\Constraint;
use WP_Error;
use WP_Http;

use function Automattic\Test\Utils\http_response;

require_once __DIR__ . '/../logstash/class-testable-logger.php';
require_once __DIR__ . '/trait-telemetry-test-helpers.php';

/**
 * Test cases shared by the Telemetry_Client implementations.
 */
trait Telemetry_Client_Test_Cases {
	use Telemetry_Test_Helpers;

	private array $original_log_entries;

	/**
	 * Create the client under test, sending its requests through $http.
	 */
	abstract protected function make_client( WP_Http $http ): Telemetry_Client;

	/**
	 * @return class-string<Telemetry_Event> The event class the client sends.
	 */
	abstract protected function event_class(): string;

	/**
	 * The error code logged when the endpoint rejects a batch.
	 */
	abstract protected function rejected_code(): string;

	/**
	 * @return string|Constraint The client's endpoint URL, or a constraint matching it.
	 */
	abstract protected function endpoint(): string|Constraint;

	public function set_up(): void {
		parent::set_up();
		$this->original_log_entries = Testable_Logger::get_entries();
		Testable_Logger::set_entries( [] );
	}

	public function tear_down(): void {
		Testable_Logger::set_entries( $this->original_log_entries );
		parent::tear_down();
	}

	/**
	 * Verify successful responses and rejected HTTP statuses through a real queue.
	 */
	public function test_http_status_controls_queue_acknowledgement(): void {
		$this->login_as();
		$event_class = $this->event_class();
		foreach ( array( 199, 200, 204, 299, 300, 401, 429, 500 ) as $status ) {
			Testable_Logger::set_entries( [] );
			$http = $this->createMock( WP_Http::class );
			$http->expects( $this->once() )->method( 'post' )->willReturn( http_response( $status ) );
			$event = new $event_class( 'test_', 'queue_event' );
			$queue = new Telemetry_Event_Queue( $this->make_client( $http ) );
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
						'error_codes' => array( $this->rejected_code() ),
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

		$event = $this->getMockBuilder( $this->event_class() )
			->disableOriginalConstructor()
			->getMock();

		$event->expects( $this->once() )->method( 'is_recordable' )->willReturn( true );
		$event->expects( $this->once() )->method( 'jsonSerialize' )->willReturn( [ 'test_event' => true ] );

		$error = new WP_Error( 'http_request_failed', 'This is a failure', array( 'private_response' => 'must-not-be-logged' ) );

		$http->expects( $this->once() )
			->method( 'post' )
			->with( $this->endpoint() )
			->willReturn( $error );

		$client = $this->make_client( $http );
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

		$bad_event = $this->getMockBuilder( $this->event_class() )
			->disableOriginalConstructor()
			->getMock();

		$bad_event->expects( $this->once() )->method( 'is_recordable' )->willReturn( false );

		$http->expects( $this->never() )
			->method( 'post' );

		$client = $this->make_client( $http );
		$this->assertTrue( $client->batch_record_events( [ $bad_event ], [ 'foo' => 'bar' ] ) );
	}
}
