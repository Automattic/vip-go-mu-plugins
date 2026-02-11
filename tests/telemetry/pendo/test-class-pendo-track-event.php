<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry\Pendo;

use Automattic\Test\Constant_Mocker;
use WP_UnitTestCase;
use WP_Error;
use WP_User;

class Pendo_Track_Event_Test extends WP_UnitTestCase {

	protected const VIP_TELEMETRY_SALT = 'test_salt';

	protected const VIP_ORG_ID = 17;

	protected const VIP_SF_ACCOUNT_ID = 1234;

	protected const WP_ENVIRONMENT_TYPE = 'test';

	private WP_User $user;

	public function setUp(): void {
		$this->user = $this->factory()->user->create_and_get();
		wp_set_current_user( $this->user->ID );

		parent::setUp();
	}

	public function tearDown(): void {
		Constant_Mocker::clear();
		parent::tearDown();
	}

	public function test_should_create_event() {
		$event = new Pendo_Track_Event( 'prefix_', 'test_event' );

		$this->assertInstanceOf( Pendo_Track_Event::class, $event );
	}

	public function test_should_return_event_data() {
		Constant_Mocker::define( 'VIP_TELEMETRY_SALT', self::VIP_TELEMETRY_SALT );
		Constant_Mocker::define( 'VIP_ORG_ID', self::VIP_ORG_ID );
		Constant_Mocker::define( 'VIP_SF_ACCOUNT_ID', self::VIP_SF_ACCOUNT_ID );
		Constant_Mocker::define( 'WP_ENVIRONMENT_TYPE', self::WP_ENVIRONMENT_TYPE );

		$event_context    = [
			'url'       => 'http://test.cool/page',
			'userAgent' => 'Cool browser 2.0',
		];
		$event_properties = array_merge(
			get_base_properties_of_pendo_track_event(),
			[
				'property1' => 'value1',
				'property2' => 'value2',
			]
		);

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

		// Test default event properties.
		$this->assertSame( self::WP_ENVIRONMENT_TYPE, $event->get_data()->properties->environment_type );
		$this->assertSame( 'other', $event->get_data()->properties->hosting_provider );
		$this->assertSame( is_multisite(), $event->get_data()->properties->is_multisite );
		$this->assertSame( 'unknown', $event->get_data()->properties->mu_plugins_version );
		$this->assertSame( get_bloginfo( 'version' ), $event->get_data()->properties->wp_version );
		$this->assertFalse( $event->get_data()->properties->is_vip_user );

		// Test passed event properties.
		$this->assertSame( 'value1', $event->get_data()->properties->property1 );
		$this->assertSame( 'value2', $event->get_data()->properties->property2 );

		$this->assertTrue( $event->is_recordable() );
	}

	public function test_should_return_vip_prefixed_visitor_id() {
		$user = $this->factory()->user->create_and_get( [
			'user_email' => 'vip@example.com',
		] );
		$user->add_role( 'vip_support' );
		wp_set_current_user( $user->ID );

		Constant_Mocker::define( 'VIP_TELEMETRY_SALT', self::VIP_TELEMETRY_SALT );
		Constant_Mocker::define( 'VIP_ORG_ID', self::VIP_ORG_ID );
		Constant_Mocker::define( 'VIP_SF_ACCOUNT_ID', self::VIP_SF_ACCOUNT_ID );
		Constant_Mocker::define( 'WP_ENVIRONMENT_TYPE', self::WP_ENVIRONMENT_TYPE );

		$event = new Pendo_Track_Event( 'prefix_', 'test_event' );

		if ( $event->get_data() instanceof WP_Error ) {
			$this->fail( sprintf( '%s: %s', $event->get_data()->get_error_code(), $event->get_data()->get_error_message() ) );
		}

		$this->assertSame( 'vip-vip@example.com', $event->get_data()->visitorId );
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

	public function test_should_not_encode_errors_to_json() {
		$event = new Pendo_Track_Event( 'prefix_', 'bogus name' );

		$this->assertInstanceOf( WP_Error::class, $event->get_data() );

		$this->assertSame( '{}', wp_json_encode( $event ) );
	}

	public function test_should_not_record_events_for_logged_out_users() {
		wp_set_current_user( 0 );

		$event = new Pendo_Track_Event( 'prefix_', 'test_event' );

		$this->assertInstanceOf( WP_Error::class, $event->get_data() );
		$this->assertSame( 'empty_user_information', $event->get_data()->get_error_code() );
	}

	public function test_should_return_error_on_missing_event_name() {
		$event = new Pendo_Track_Event( 'prefix_', '', [ 'property1' => 'value1' ] );

		$this->assertInstanceOf( WP_Error::class, $event->get_data() );
		$this->assertInstanceOf( WP_Error::class, $event->is_recordable() );
		$this->assertSame( $event->is_recordable(), $event->get_data() );

		$this->assertSame( 'invalid_event_name', $event->get_data()->get_error_code() );
	}

	public static function provide_invalid_event_names() {
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
	 * @dataProvider provide_invalid_property_names
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
