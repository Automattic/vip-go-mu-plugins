<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry\Pendo;

use Automattic\Test\Constant_Mocker;
use Automattic\VIP\Telemetry\Telemetry_Test_Helpers;
use WP_UnitTestCase;
use WP_Error;
use WP_User;

require_once __DIR__ . '/../trait-telemetry-test-helpers.php';

class Pendo_Track_Event_Test extends WP_UnitTestCase {
	use Telemetry_Test_Helpers;

	protected const VIP_SF_ACCOUNT_ID = 1234;

	private WP_User $user;

	public function setUp(): void {
		parent::setUp();

		$this->user = get_userdata( $this->login_as() );
	}

	public function test_should_return_event_data() {
		Constant_Mocker::define( 'VIP_SF_ACCOUNT_ID', self::VIP_SF_ACCOUNT_ID );

		$event_context    = [
			'url'       => 'http://test.cool/page',
			'userAgent' => 'Cool browser 2.0',
		];
		$event_properties = [
			'property1' => 'value1',
			'property2' => 'value2',
			// Booleans and integers are sent as-is, like the base is_multisite and is_vip_user properties.
			'flag'      => false,
			'count'     => 3,
		];

		$event = new Pendo_Track_Event( 'prefix_', 'test_event', $event_context, $event_properties );

		if ( $event->get_data() instanceof WP_Error ) {
			$this->fail( sprintf( '%s: %s', $event->get_data()->get_error_code(), $event->get_data()->get_error_message() ) );
		}

		$this->assertInstanceOf( Pendo_Track_Event_DTO::class, $event->get_data() );

		// Test core event properties.
		$this->assertSame( (string) self::VIP_SF_ACCOUNT_ID, $event->get_data()->accountId );
		$this->assertSame( 'prefix_test_event', $event->get_data()->event );
		$this->assertIsFloat( $event->get_data()->timestamp );
		$this->assertGreaterThan( ( time() - 10 ) * 1000, $event->get_data()->timestamp );
		$this->assertSame( 'track', $event->get_data()->type );
		$this->assertSame( strtolower( $this->user->user_email ), $event->get_data()->visitorId );

		// Test event context.
		$this->assertSame( 'http://test.cool/page', $event->get_data()->context->url );
		$this->assertSame( 'Cool browser 2.0', $event->get_data()->context->userAgent );

		// Test passed event properties.
		$this->assertSame( 'value1', $event->get_data()->properties->property1 );
		$this->assertSame( 'value2', $event->get_data()->properties->property2 );
		$this->assertFalse( $event->get_data()->properties->flag );
		$this->assertSame( 3, $event->get_data()->properties->count );

		$this->assertTrue( $event->is_recordable() );
	}

	public function test_should_not_add_prefix_twice() {
		$event = new Pendo_Track_Event( 'prefixed_', 'prefixed_event_name' );

		$this->assertNotInstanceOf( WP_Error::class, $event->get_data() );

		$this->assertSame( 'prefixed_event_name', $event->get_data()->event );
	}

	public function test_should_encode_complex_properties() {
		$event = new Pendo_Track_Event( 'prefix_', 'event_name', [], [ 'example' => [ 'a' => 'b' ] ] );

		$this->assertNotInstanceOf( WP_Error::class, $event->get_data() );
		$this->assertSame( '{"a":"b"}', $event->get_data()->properties->example );
	}

	public function test_should_not_record_events_for_logged_out_users() {
		wp_set_current_user( 0 );

		$event = new Pendo_Track_Event( 'prefix_', 'test_event' );

		$this->assertInstanceOf( WP_Error::class, $event->get_data() );
		$this->assertSame( 'empty_user_information', $event->get_data()->get_error_code() );
	}

	public static function provide_invalid_event_names() {
		yield 'empty' => [ '' ];
		yield 'spaces' => [ 'cool page viewed' ];
		yield 'dashes' => [ 'cool-page-viewed' ];
		yield 'mixed-case' => [ 'cool_page_Viewed' ];
	}

	/**
	 * @dataProvider provide_invalid_event_names
	 */
	public function test_should_return_error_on_invalid_event_name( string $event_name ) {
		$event = new Pendo_Track_Event( 'prefix_', $event_name, [ 'property1' => 'value1' ] );

		$this->assertInstanceOf( WP_Error::class, $event->get_data() );
		$this->assertInstanceOf( WP_Error::class, $event->is_recordable() );
		$this->assertSame( $event->is_recordable(), $event->get_data() );

		$this->assertSame( 'invalid_event_name', $event->get_data()->get_error_code() );
	}

	public static function provide_invalid_context_names() {
		yield 'empty' => [ '' ];
		yield 'not allowed' => [ 'cool property' ];
	}

	/**
	 * @dataProvider provide_invalid_context_names
	 */
	public function test_should_return_error_on_invalid_context_name( string $context_name ) {
		$event = new Pendo_Track_Event( 'prefix_', 'test_event', [ $context_name => 'value1' ] );

		$this->assertInstanceOf( WP_Error::class, $event->get_data() );
		$this->assertInstanceOf( WP_Error::class, $event->is_recordable() );
		$this->assertSame( $event->is_recordable(), $event->get_data() );
		$this->assertSame( 'invalid_context_name', $event->get_data()->get_error_code() );
	}

	public static function provide_invalid_property_names() {
		yield 'empty' => [ '' ];
		yield 'spaces' => [ 'cool property' ];
		yield 'mixed-case' => [ 'cool_Property' ];
		yield 'camelCase' => [ 'compressedSize' ];
		yield 'dashes' => [ 'cool-property' ];
	}

	/**
	 * @dataProvider provide_invalid_property_names
	 */
	public function test_should_return_error_on_invalid_property_name( string $property_name ) {
		$event = new Pendo_Track_Event( 'prefix_', 'test_event', [], [ $property_name => 'value1' ] );

		$this->assertInstanceOf( WP_Error::class, $event->get_data() );
		$this->assertInstanceOf( WP_Error::class, $event->is_recordable() );
		$this->assertSame( $event->is_recordable(), $event->get_data() );
		$this->assertSame( 'invalid_property_name', $event->get_data()->get_error_code() );
	}
}
