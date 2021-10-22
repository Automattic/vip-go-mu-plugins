<?php

declare(strict_types=1);

namespace Automattic\VIP\Parsely\Telemetry;

use WP_UnitTestCase;

require_once __DIR__ . '/../../../../vip-parsely/Telemetry/class-telemetry-system.php';
require_once __DIR__ . '/../../../../vip-parsely/Telemetry/Tracks/class-tracks.php';

class Tracks_Test extends WP_UnitTestCase {
	public function test__should_normalize_invalid_name() {
		$normalize_event_name = self::get_method('normalize_event_name');

		$tracks = new Tracks();
		$normalized_name = $normalize_event_name->invokeArgs( $tracks, array( 'invalid' ) );
		$this->assertEquals( 'wpparsely_invalid', $normalized_name);
	}

	public function test__should_not_normalize_valid_name() {
		$normalize_event_name = self::get_method('normalize_event_name');

		$tracks = new Tracks();
		$normalized_name = $normalize_event_name->invokeArgs( $tracks, array( 'wpparsely_valid' ) );
		$this->assertEquals( 'wpparsely_valid', $normalized_name);
	}

	/**
	 * Helper function for accessing protected methods.
	 */
	protected static function get_method( $name ) {
		$class = new \ReflectionClass( __NAMESPACE__ . '\Tracks' );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );
		return $method;
	}
}
