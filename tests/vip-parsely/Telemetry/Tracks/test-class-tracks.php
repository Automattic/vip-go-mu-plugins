<?php

declare(strict_types=1);

namespace Automattic\VIP\Parsely\Telemetry;

use WP_UnitTestCase;

require_once __DIR__ . '/../../../../vip-parsely/Telemetry/class-telemetry-system.php';
require_once __DIR__ . '/../../../../vip-parsely/Telemetry/Tracks/class-tracks.php';

class Tracks_Test extends WP_UnitTestCase {
	/**
	 * @dataProvider data_normalize_event_name
	 */
	public function test_normalize_event_name( string $input, string $expected ) {
		$normalize_event_name = self::get_method( 'normalize_event_name' );
		$tracks               = new Tracks();
		$actual               = $normalize_event_name->invokeArgs( $tracks, array( $input ) );
		self::assertEquals( $expected, $actual );
	}

	public function data_normalize_event_name(): array {
		return [
			[ 'invalid', 'wpparsely_invalid' ],
			[ 'wpparsely_valid', 'wpparsely_valid' ],
		];
	}

	/**
	 * Helper function for accessing protected methods.
	 */
	protected static function get_method( $name ) {
		$class  = new \ReflectionClass( __NAMESPACE__ . '\Tracks' );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );
		return $method;
	}
}
