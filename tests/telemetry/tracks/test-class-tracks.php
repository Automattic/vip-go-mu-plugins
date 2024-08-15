<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use WP_UnitTestCase;

require_once __DIR__ . '/../../../telemetry/class-telemetry-system.php';
require_once __DIR__ . '/../../../telemetry/tracks/class-tracks.php';
require_once __DIR__ . '/../../../telemetry/tracks/class-tracks-client.php';

class Tracks_Test extends WP_UnitTestCase {
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
