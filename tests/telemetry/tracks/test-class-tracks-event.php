<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use Automattic\Test\Constant_Mocker;
use WP_UnitTestCase;
use WP_Error;

class Tracks_Event_Test extends WP_UnitTestCase {

	protected const VIP_TELEMETRY_SALT = 'test_salt';

	protected const VIP_GO_APP_ENVIRONMENT = 'test';

	protected const VIP_ORG_ID = '17';

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		Constant_Mocker::define( 'VIP_TELEMETRY_SALT', self::VIP_TELEMETRY_SALT );
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', self::VIP_GO_APP_ENVIRONMENT );
		Constant_Mocker::define( 'VIP_ORG_ID', self::VIP_ORG_ID );
	}

	public static function tearDownAfterClass(): void {
		Constant_Mocker::clear();
		parent::tearDownAfterClass();
	}

	public function test_should_create_event() {
		$event = new Tracks_Event( 'prefix_', 'test_event', [ 'property1' => 'value1' ] );

		$this->assertInstanceOf( Tracks_Event::class, $event );
	}

	public function test_should_return_event_data() {
		$user = $this->factory()->user->create_and_get();
		wp_set_current_user( $user->ID );

		$event = new Tracks_Event( 'prefix_', 'test_event', [ 'compressed_size' => 'value1' ] );

		if ( $event->get_data() instanceof WP_Error ) {
			$this->fail( sprintf( '%s: %s', $event->get_data()->get_error_code(), $event->get_data()->get_error_message() ) );
		}

		$this->assertInstanceOf( Tracks_Event_DTO::class, $event->get_data() );
		$this->assertSame( 'prefix_test_event', $event->get_data()->_en );
		$this->assertSame( 'value1', $event->get_data()->compressed_size );
		$this->assertSame( hash_hmac( 'sha256', $user->user_email, self::VIP_TELEMETRY_SALT ), $event->get_data()->_ui );
		$this->assertSame( 'vip:user_email', $event->get_data()->_ut );
		$this->assertSame( self::VIP_GO_APP_ENVIRONMENT, $event->get_data()->vipgo_env );
		$this->assertSame( self::VIP_ORG_ID, $event->get_data()->vipgo_org );
	}

	public static function provide_invalid_event_names() {
		yield 'spaces' => [ 'cool page viewed' ];
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
