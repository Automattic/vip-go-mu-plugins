<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use WP_UnitTestCase;

class Tracks_Test extends WP_UnitTestCase {

	/** @var (Tracks_Client&\PHPUnit\Framework\MockObject\MockObject)|\PHPUnit\Framework\MockObject\MockObject */
	private $client;

	public function setUp(): void {
		$this->client = $this->getMockBuilder( Tracks_Client::class )
			->disableOriginalConstructor()
			->getMock();

		parent::setUp();
	}

	public function test_event_queued() {
		$queue = $this->getMockBuilder( Tracks_Event_Queue::class )
			->disableOriginalConstructor()
			->getMock();

		$queue->expects( $this->once() )
			->method( 'record_event_asynchronously' )
			->with($this->callback(function ( Tracks_Event $event ) {
				$this->assertSame( 'test_cool_event', $event->get_data()->_en );
				$this->assertSame( 'bar', $event->get_data()->foo );

				return true;
			}))
			->willReturn( true );


		$tracks = new Tracks( 'test_', $queue, $this->client );
		$this->assertTrue( $tracks->record_event( 'cool_event', [ 'foo' => 'bar' ] ) );
	}

	public function test_event_prefix() {
		$tracks       = new Tracks();
		$event_prefix = self::get_property( 'event_prefix' )->getValue( $tracks );
		$this->assertEquals( 'vip_', $event_prefix );
	}

	public function test_custom_event_prefix() {
		$tracks       = new Tracks( 'test_' );
		$event_prefix = self::get_property( 'event_prefix' )->getValue( $tracks );
		$this->assertEquals( 'test_', $event_prefix );
	}

	/**
	 * Helper function for accessing protected properties.
	 */
	protected static function get_property( $name ) {
		$class    = new \ReflectionClass( __NAMESPACE__ . '\Tracks' );
		$property = $class->getProperty( $name );
		$property->setAccessible( true );
		return $property;
	}
}
