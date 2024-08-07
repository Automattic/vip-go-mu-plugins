<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use WP_UnitTestCase;

require_once __DIR__ . '/../../../telemetry/class-telemetry-system.php';
require_once __DIR__ . '/../../../telemetry/tracks/class-tracks.php';

class Tracks_Test extends WP_UnitTestCase {

	public function test_should_register_events() {
		$tracks = new Tracks();
		$tracks->register_events(
			[ 'event1' => 'value1' ],
			[ 'event2' => 'value2' ],
			[ 'event3' => 'value3' ],
		);

		$events = self::get_property( 'events' )->getValue( $tracks );

		self::assertCount( 3, $events );
		self::assertEquals( [ 'event1' => 'value1' ], $events[0] );
		self::assertEquals( [ 'event2' => 'value2' ], $events[1] );
		self::assertEquals( [ 'event3' => 'value3' ], $events[2] );
	}

	public function test_should_run_and_activate_tracking() {
		$tracks = new Tracks();
		$tracks->register_events(
			[
				'action_hook'   => 'test_filter',
				'callable'      => function () {
					return true;
				},
				'accpeted_args' => 1,
			],
		);

		$tracks->run();

		$events = self::get_property( 'events' )->getValue( $tracks );

		self::assertCount( 1, $events );
		self::assertEquals( 'test_filter', $events[0]['action_hook'] );
		self::assertTrue( has_filter( 'test_filter' ) );
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
