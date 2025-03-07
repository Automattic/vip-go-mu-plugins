<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use Automattic\Test\Constant_Mocker;
use Automattic\VIP\Telemetry\Pendo\Pendo_Track_Client;
use Automattic\VIP\Telemetry\Pendo\Pendo_Track_Event;
use PHPUnit\Framework\MockObject\MockObject;
use WP_Error;
use WP_UnitTestCase;

class Pendo_Test extends WP_UnitTestCase {
	public function tear_down() {
		parent::tear_down();
		Constant_Mocker::clear();
	}

	public function test_disabled_by_default() {
		$user = $this->factory()->user->create_and_get();
		wp_set_current_user( $user->ID );

		/** @var MockObject|Telemetry_Event_Queue */
		$queue = $this->getMockBuilder( Telemetry_Event_Queue::class )
			->disableOriginalConstructor()
			->getMock();

		$queue->expects( $this->never() )
			->method( 'record_event_asynchronously' );

		$pendo = new Pendo( 'test_', [], $queue );

		$this->assertFalse( $pendo->record_event( 'cool_event', [ 'foo' => 'bar' ] ) );
		$this->assertFalse( self::get_property( 'is_enabled' )->getValue( $pendo ) );
	}

	public function test_disabled_by_constant() {
		$user = $this->factory()->user->create_and_get();
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_DISABLE_PENDO_TELEMETRY', true );
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );

		/** @var MockObject|Telemetry_Event_Queue */
		$queue = $this->getMockBuilder( Telemetry_Event_Queue::class )
			->disableOriginalConstructor()
			->getMock();

		$queue->expects( $this->never() )
			->method( 'record_event_asynchronously' );

		$pendo = new Pendo( 'test_', [], $queue );

		$this->assertFalse( $pendo->record_event( 'cool_event', [ 'foo' => 'bar' ] ) );
		$this->assertFalse( self::get_property( 'is_enabled' )->getValue( $pendo ) );
	}

	public function test_disabled_for_fedramp() {
		$user = $this->factory()->user->create_and_get();
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		Constant_Mocker::define( 'VIP_IS_FEDRAMP', true );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );

		/** @var MockObject|Telemetry_Event_Queue */
		$queue = $this->getMockBuilder( Telemetry_Event_Queue::class )
			->disableOriginalConstructor()
			->getMock();

		$queue->expects( $this->never() )
			->method( 'record_event_asynchronously' );

		$pendo = new Pendo( 'test_', [], $queue );

		$this->assertFalse( $pendo->record_event( 'cool_event', [ 'foo' => 'bar' ] ) );
		$this->assertFalse( self::get_property( 'is_enabled' )->getValue( $pendo ) );
	}

	public function test_disabled_for_non_production() {
		$user = $this->factory()->user->create_and_get();
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'preprod' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );

		/** @var MockObject|Telemetry_Event_Queue */
		$queue = $this->getMockBuilder( Telemetry_Event_Queue::class )
			->disableOriginalConstructor()
			->getMock();

		$queue->expects( $this->never() )
			->method( 'record_event_asynchronously' );

		$pendo = new Pendo( 'test_', [], $queue );

		$this->assertFalse( $pendo->record_event( 'cool_event', [ 'foo' => 'bar' ] ) );
		$this->assertFalse( self::get_property( 'is_enabled' )->getValue( $pendo ) );
	}

	public function test_disabled_for_sandbox() {
		$user = $this->factory()->user->create_and_get();
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );
		Constant_Mocker::define( 'WPCOM_SANDBOXED', true );

		/** @var MockObject|Telemetry_Event_Queue */
		$queue = $this->getMockBuilder( Telemetry_Event_Queue::class )
			->disableOriginalConstructor()
			->getMock();

		$queue->expects( $this->never() )
			->method( 'record_event_asynchronously' );

		$pendo = new Pendo( 'test_', [], $queue );

		$this->assertFalse( $pendo->record_event( 'cool_event', [ 'foo' => 'bar' ] ) );
		$this->assertFalse( self::get_property( 'is_enabled' )->getValue( $pendo ) );
	}

	public function test_event_queued() {
		$user = $this->factory()->user->create_and_get();
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );

		/** @var MockObject|Telemetry_Event_Queue */
		$queue = $this->getMockBuilder( Telemetry_Event_Queue::class )
			->disableOriginalConstructor()
			->getMock();

		$queue->expects( $this->once() )
			->method( 'record_event_asynchronously' )
			->with($this->callback( function ( Pendo_Track_Event $event ) {
				$this->assertSame( 'test_cool_event', $event->get_data()->event );
				$this->assertSame( 'bar', $event->get_data()->properties->foo );
				$this->assertFalse( isset( $event->get_data()->properties->global_baz ) );

				return true;
			} ) )
			->willReturn( true );

		$pendo = new Pendo( 'test_', [], $queue );

		$this->assertTrue( $pendo->record_event( 'cool_event', [ 'foo' => 'bar' ] ) );
	}

	public function test_event_queued_with_global_properies() {
		$user = $this->factory()->user->create_and_get();
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );

		/** @var MockObject|Telemetry_Event_Queue */
		$queue = $this->getMockBuilder( Telemetry_Event_Queue::class )
			->disableOriginalConstructor()
			->getMock();

		$queue->expects( $this->once() )
			->method( 'record_event_asynchronously' )
			->with($this->callback(function ( Pendo_Track_Event $event ) {
				$this->assertSame( 'nice_fuzzy_event', $event->get_data()->event );
				$this->assertSame( 'bar', $event->get_data()->properties->foo );
				$this->assertSame( 'qux', $event->get_data()->properties->global_baz );

				return true;
			}))
			->willReturn( true );

		$pendo = new Pendo( 'nice_', [
			'global_baz' => 'qux',
			'foo'        => 'default_foo',
		], $queue );
		$this->assertTrue( $pendo->record_event( 'fuzzy_event', [ 'foo' => 'bar' ] ) );
	}

	public function test_recording_error_with_no_integration_key() {
		$user = $this->factory()->user->create_and_get();
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );

		$queue = new Telemetry_Event_Queue( new Pendo_Track_Client() );
		$pendo = new Pendo( 'test_', [], $queue );

		// Valid events are always accepted for asyncronous recording.
		$this->assertTrue( $pendo->record_event( 'cool_event', [ 'foo' => 'bar' ] ) );

		// Directly call record_events. Normally this is called on shutdown hook.
		$error = $queue->record_events();

		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertSame( 'pendo_track_integration_key_not_defined', $error->get_error_code() );
	}

	public function test_event_prefix() {
		$pendo        = new Pendo();
		$event_prefix = self::get_property( 'event_prefix' )->getValue( $pendo );
		$this->assertEquals( 'vip_wordpress_', $event_prefix );
	}

	public function test_custom_event_prefix() {
		$pendo        = new Pendo( 'test_' );
		$event_prefix = self::get_property( 'event_prefix' )->getValue( $pendo );
		$this->assertEquals( 'test_', $event_prefix );
	}

	/**
	 * Helper function for accessing protected properties.
	 */
	protected static function get_property( $name ) {
		$class    = new \ReflectionClass( Pendo::class );
		$property = $class->getProperty( $name );
		$property->setAccessible( true );
		return $property;
	}
}
