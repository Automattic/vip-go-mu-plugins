<?php

namespace Automattic\VIP\Parsely\Telemetry;

class Tracks_test extends \WP_UnitTestCase {
	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once __DIR__ . '/../../../../vip-parsely/Telemetry/class-telemetry-system.php';
		require_once __DIR__ . '/../../../../vip-parsely/Telemetry/Tracks/class-tracks.php';
	}

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
