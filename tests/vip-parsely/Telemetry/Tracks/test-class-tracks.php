<?php

declare(strict_types=1);

namespace Automattic\VIP\Parsely\Telemetry;

use WP_UnitTestCase;

require_once __DIR__ . '/../../../../vip-parsely/Telemetry/class-telemetry-system.php';
require_once __DIR__ . '/../../../../vip-parsely/Telemetry/Tracks/class-tracks.php';

class Tracks_Test extends WP_UnitTestCase {
	public function test__should_normalize_invalid_name() {
		$normalize_event_name = self::get_method( 'normalize_event_name' );

		$tracks = new Tracks();
		$normalized_name = $normalize_event_name->invokeArgs( $tracks, array( 'invalid' ) );
		$this->assertEquals( 'wpparsely_invalid', $normalized_name );
	}

	/**
	 * @dataProvider data_normalize_event_name
	 */
	public function test_normalize_event_name( string $input, string $expected ) {
		$normalize_event_name = self::get_method( 'normalize_event_name' );
		$tracks = new Tracks();
		$actual = $normalize_event_name->invokeArgs( $tracks, array( $input ) );
		self::assertEquals( $expected, $actual );
	}

	public function data_normalize_event_name() {
		return [
			[ 'invalid', 'wpparsely_invalid' ],
			[ 'wpparsely_valid', 'wpparsely_valid' ],
		];
	}
}
