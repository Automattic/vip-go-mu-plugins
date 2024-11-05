<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use Automattic\VIP\Telemetry\Tracks\Tracks_Event;
use PHPUnit\Framework\MockObject\MockObject;
use WP_UnitTestCase;

class Tracks_Test extends WP_UnitTestCase {
	public function test_event_queued() {
		$user = $this->factory()->user->create_and_get();
		wp_set_current_user( $user->ID );

		/** @var MockObject|Telemetry_Event_Queue */
		$queue = $this->getMockBuilder( Telemetry_Event_Queue::class )
			->disableOriginalConstructor()
			->getMock();

		$queue->expects( $this->once() )
			->method( 'record_event_asynchronously' )
			->with($this->callback(function ( Tracks_Event $event ) {
				$this->assertSame( 'test_cool_event', $event->get_data()->_en );
				$this->assertSame( 'bar', $event->get_data()->foo );
				$this->assertFalse( isset( $event->get_data()->global_baz ) );

				return true;
			}))
			->willReturn( true );

		$tracks = new Tracks( 'test_', [], $queue );

		$this->assertTrue( $tracks->record_event( 'cool_event', [ 'foo' => 'bar' ] ) );
	}

	public function test_event_queued_with_global_properies() {
		$user = $this->factory()->user->create_and_get();
		wp_set_current_user( $user->ID );

		$queue = $this->getMockBuilder( Telemetry_Event_Queue::class )
			->disableOriginalConstructor()
			->getMock();

		$queue->expects( $this->once() )
			->method( 'record_event_asynchronously' )
			->with($this->callback(function ( Tracks_Event $event ) {
				$this->assertSame( 'nice_fuzzy_event', $event->get_data()->_en );
				$this->assertSame( 'bar', $event->get_data()->foo );
				$this->assertSame( 'qux', $event->get_data()->global_baz );

				return true;
			}))
			->willReturn( true );

		$tracks = new Tracks( 'nice_', [
			'global_baz' => 'qux',
			'foo'        => 'default_foo',
		], $queue );
		$this->assertTrue( $tracks->record_event( 'fuzzy_event', [ 'foo' => 'bar' ] ) );
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
		$class    = new \ReflectionClass( Tracks::class );
		$property = $class->getProperty( $name );
		$property->setAccessible( true );
		return $property;
	}
}
