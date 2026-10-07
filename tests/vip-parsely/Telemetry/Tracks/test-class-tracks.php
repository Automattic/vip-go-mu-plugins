<?php

declare(strict_types=1);

namespace Automattic\VIP\Parsely\Telemetry;

use WP_UnitTestCase;

use function Automattic\Test\Utils\get_class_method_as_public;

require_once __DIR__ . '/../../../../vip-parsely/Telemetry/class-telemetry-system.php';
require_once __DIR__ . '/../../../../vip-parsely/Telemetry/Tracks/class-tracks.php';

class Tracks_Test extends WP_UnitTestCase {
	/**
	 * @dataProvider data_normalize_event_name
	 */
	public function test_normalize_event_name( string $input, string $expected ) {
		$normalize_event_name = get_class_method_as_public( Tracks::class, 'normalize_event_name' );
		$tracks               = new Tracks();
		$actual               = $normalize_event_name->invokeArgs( $tracks, array( $input ) );
		self::assertEquals( $expected, $actual );
	}

	public function data_normalize_event_name(): array {
		return [
			'unprefixed name' => [ 'invalid', 'wpparsely_invalid' ],
			'prefixed name'   => [ 'wpparsely_valid', 'wpparsely_valid' ],
		];
	}
}
