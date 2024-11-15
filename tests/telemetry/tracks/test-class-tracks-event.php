<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry\Tracks;

use Automattic\Test\Constant_Mocker;
use WP_UnitTestCase;
use WP_Error;
use WP_User;

class Tracks_Event_Test extends WP_UnitTestCase {

	protected const VIP_TELEMETRY_SALT = 'test_salt';

	protected const VIP_GO_APP_ENVIRONMENT = 'test';

	protected const VIP_ORG_ID = 17;

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
		$event = new Tracks_Event( 'prefix_', 'test_event', [ 'property1' => 'value1' ] );

		$this->assertInstanceOf( Tracks_Event::class, $event );
	}

	public function test_should_return_event_data() {
		Constant_Mocker::define( 'VIP_TELEMETRY_SALT', self::VIP_TELEMETRY_SALT );
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', self::VIP_GO_APP_ENVIRONMENT );
		Constant_Mocker::define( 'VIP_ORG_ID', self::VIP_ORG_ID );

		$event = new Tracks_Event( 'prefix_', 'test_event', [
			'property1' => 'value1',
			'_via_ip'   => '1.2.3.4',
		] );

		if ( $event->get_data() instanceof WP_Error ) {
			$this->fail( sprintf( '%s: %s', $event->get_data()->get_error_code(), $event->get_data()->get_error_message() ) );
		}

		$this->assertInstanceOf( Tracks_Event_DTO::class, $event->get_data() );
		$this->assertIsString( $event->get_data()->_ts );
		$this->assertGreaterThan( ( time() - 10 ) * 1000, (int) $event->get_data()->_ts );
		$this->assertSame( 'prefix_test_event', $event->get_data()->_en );
		$this->assertSame( 'value1', $event->get_data()->property1 );
		$this->assertSame( '1.2.3.4', $event->get_data()->_via_ip );
		$this->assertSame( hash_hmac( 'sha256', $this->user->user_email, self::VIP_TELEMETRY_SALT ), $event->get_data()->_ui );
		$this->assertSame( 'vip:user_email', $event->get_data()->_ut );
		$this->assertSame( self::VIP_GO_APP_ENVIRONMENT, $event->get_data()->vip_env );
		$this->assertSame( self::VIP_ORG_ID, $event->get_data()->vip_org );
		$this->assertSame( 'other', $event->get_data()->hosting_provider );
		$this->assertSame( is_multisite(), $event->get_data()->is_multisite );
		$this->assertSame( get_bloginfo( 'version' ), $event->get_data()->wp_version );
		$this->assertFalse( $event->get_data()->is_vip_user );
		$this->assertTrue( $event->is_recordable() );
	}

	public function test_should_not_add_prefix_twice() {
		$event = new Tracks_Event( 'prefixed_', 'prefixed_event_name' );

		$this->assertNotInstanceOf( WP_Error::class, $event->get_data() );

		$this->assertSame( 'prefixed_event_name', $event->get_data()->_en );
	}

	public function test_should_not_override_timestamp() {
		$ts    = 1234567890;
		$event = new Tracks_Event( 'prefixed_', 'example', [
			'_ts' => $ts,
		] );

		$this->assertSame( (string) $ts, $event->get_data()->_ts );
	}

	public function test_should_encode_complex_properties() {
		$event = new Tracks_Event( 'prefix_', 'event_name', [ 'example' => [ 'a' => 'b' ] ] );

		$this->assertNotInstanceOf( WP_Error::class, $event->get_data() );

		$this->assertSame( '{"a":"b"}', $event->get_data()->example );
	}

	public function test_should_not_encode_errors_to_json() {
		$event = new Tracks_Event( 'prefix_', 'bogus name' );

		$this->assertInstanceOf( WP_Error::class, $event->get_data() );

		$this->assertSame( '{}', wp_json_encode( $event ) );
	}

	public function test_should_fallback_to_vip_go_app_wp_user() {
		Constant_Mocker::define( 'VIP_GO_APP_ID', 1234 );

		$event = new Tracks_Event( 'prefix_', 'test_event' );

		$this->assertNotInstanceOf( WP_Error::class, $event->get_data() );
		$this->assertSame( 'vip_go_app_wp_user', $event->get_data()->_ut );
		$this->assertSame( '1234_' . $this->user->ID, $event->get_data()->_ui );
	}

	public function test_should_fallback_to_anon_wp_hash() {
		$event = new Tracks_Event( 'prefix_', 'test_event' );

		$this->assertNotInstanceOf( WP_Error::class, $event->get_data() );
		$this->assertSame( 'anon', $event->get_data()->_ut );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]+$/', $event->get_data()->_ui );
	}

	public function test_should_not_record_events_for_logged_out_users() {
		wp_set_current_user( 0 );

		$event = new Tracks_Event( 'prefix_', 'test_event' );

		$this->assertInstanceOf( WP_Error::class, $event->get_data() );
		$this->assertSame( 'empty_user_information', $event->get_data()->get_error_code() );
	}

	public static function provide_non_routable_ips() {
		yield [ '192.168.10.1' ];
		yield [ '10.11.10.11' ];
	}

	/**
	 * @dataProvider provide_non_routable_ips
	 */
	public function test_should_remove_non_routable_ips( string $_via_ip ) {
		$event = new Tracks_Event( 'prefix_', 'example', [ '_via_ip' => $_via_ip ] );

		$this->assertNotInstanceOf( WP_Error::class, $event->get_data() );
		$this->assertFalse( isset( $event->get_data()->_via_ip ) );
		$this->assertStringNotContainsString( 'via_ip', wp_json_encode( $event ) );
	}

	public function test_should_return_error_on_missing_event_name() {
		$event = new Tracks_Event( 'prefix_', '', [ 'property1' => 'value1' ] );

		$this->assertInstanceOf( WP_Error::class, $event->get_data() );
		$this->assertInstanceOf( WP_Error::class, $event->is_recordable() );
		$this->assertSame( $event->is_recordable(), $event->get_data() );

		$this->assertSame( 'invalid_event', $event->get_data()->get_error_code() );
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
		$event = new Tracks_Event( 'prefix_', $event_name, [ 'property1' => 'value1' ] );

		$this->assertInstanceOf( WP_Error::class, $event->get_data() );
		$this->assertInstanceOf( WP_Error::class, $event->is_recordable() );
		$this->assertSame( $event->is_recordable(), $event->get_data() );

		$this->assertSame( 'invalid_event_name', $event->get_data()->get_error_code() );
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
		$event = new Tracks_Event( 'prefix_', 'test_event', [ $property_name => 'value1' ] );

		$this->assertInstanceOf( WP_Error::class, $event->get_data() );
		$this->assertInstanceOf( WP_Error::class, $event->is_recordable() );
		$this->assertSame( $event->is_recordable(), $event->get_data() );
		$this->assertSame( 'invalid_property_name', $event->get_data()->get_error_code() );
	}
}
