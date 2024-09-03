<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use WP_UnitTestCase;
use Automattic\VIP\Support_User\Role;

class Tracks_Event_Test extends WP_UnitTestCase {

	protected const VIP_TELEMETRY_SALT = 'test_salt';

	protected const VIP_GO_APP_ENVIRONMENT = 'test';

	protected const VIP_ORG_ID = '17';

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		define( 'VIP_TELEMETRY_SALT', self::VIP_TELEMETRY_SALT );
		define( 'VIP_GO_APP_ENVIRONMENT', self::VIP_GO_APP_ENVIRONMENT );
		define( 'VIP_ORG_ID', self::VIP_ORG_ID );
	}

	public function test_should_create_event() {
		$event = new Tracks_Event( 'prefix_', 'test_event', [ 'property1' => 'value1' ] );

		$this->assertInstanceOf( Tracks_Event::class, $event );
	}

	public function test_should_return_event_data() {
		$user = $this->factory()->user->create_and_get();
		wp_set_current_user( $user->ID );

		$event = new Tracks_Event( 'prefix_', 'test_event', [ 'property1' => 'value1' ] );

		$this->assertInstanceOf( \stdClass::class, $event->get_data() );
		$this->assertEquals( 'prefix_test_event', $event->get_data()->_en );
		$this->assertEquals( 'value1', $event->get_data()->property1 );
		$this->assertEquals( hash_hmac( 'sha256', $user->user_email, self::VIP_TELEMETRY_SALT ), $event->get_data()->_ui );
		$this->assertEquals( 'vip:user_email', $event->get_data()->_ut );
		$this->assertEquals( self::VIP_GO_APP_ENVIRONMENT, $event->get_data()->vipgo_env );
		$this->assertEquals( self::VIP_ORG_ID, $event->get_data()->vipgo_org );
	}

	public function test_should_return_error_on_invalid_event_name() {
		$event = new Tracks_Event( 'prefix_', 'invalid_event', [ 'property1' => 'value1' ] );

		$this->assertInstanceOf( \WP_Error::class, $event->get_data() );
	}

	public function test_should_return_error_on_invalid_property_name() {
		$event = new Tracks_Event( 'prefix_', 'test_event', [ 'invalid_property' => 'value1' ] );

		$this->assertInstanceOf( \WP_Error::class, $event->get_data() );
	}

	public function test_should_return_error_on_invalid_property_value() {
		$event = new Tracks_Event( 'prefix_', 'test_event', [ 'property1' => [ 'value1' ] ] );

		$this->assertInstanceOf( \WP_Error::class, $event->get_data() );
	}

	public function test_is_recordable_should_return_wp_error_on_invalid_event() {
		$event = new Tracks_Event( 'prefix_', 'invalid_event', [ 'property1' => 'value1' ] );

		$this->assertInstanceOf( \WP_Error::class, $event->is_recordable() );
	}
}
