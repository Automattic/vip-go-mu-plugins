<?php

namespace Automattic\VIP\Jetpack;

use DateTime;
use WP_UnitTestCase;

/**
 * @requires function Jetpack_Options::get_option
 */
class Connection_Pilot_Test extends WP_UnitTestCase {
	protected static function getMethod( $name ) {
		$class  = new \ReflectionClass( 'Automattic\VIP\Jetpack\Connection_Pilot' );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );

		return $method;
	}

	/**
	 * @group jetpack-required
	 * @preserveGlobalState disabled
	 * @dataProvider get_test_data__update_heartbeat_on_failure
	 */
	public function test__update_heartbeat_on_failure( ?int $backoff_factor, int $expected_backoff, int $retry_count ) {
		$last_failure = time() - 1000;

		$this->set_heartbeat( [
			'site_url'          => 'example.com',
			'backoff_factor'    => $backoff_factor,
			'retry_count'       => $retry_count,
			'failure_timestamp' => $last_failure,
		] );

		$connection_pilot      = Connection_Pilot::instance();
		$update_backoff_factor = self::getMethod( 'update_heartbeat_on_failure' );
		$update_backoff_factor->invoke( $connection_pilot );

		$option = $this->get_heartbeat();
		$this->assertEquals( $expected_backoff, $option['backoff_factor'] );
		$this->assertEquals( $retry_count + 1, $option['retry_count'] );
		$this->assertTrue( $option['failure_timestamp'] > $last_failure );

		// Doesn't change.
		$this->assertEquals( 'example.com', $option['site_url'] );
	}

	/**
	 * @group jetpack-required
	 * @preserveGlobalState disabled
	 */
	public function test__update_heartbeat_on_success() {
		$this->set_heartbeat( [
			'site_url'          => 'example.com',
			'cache_site_id'     => 22,
			'backoff_factor'    => 24,
			'retry_count'       => 2,
			'failure_timestamp' => time() - 1000,
		] );

		$connection_pilot = Connection_Pilot::instance();
		$update_heartbeat = self::getMethod( 'update_heartbeat_on_success' );
		$update_heartbeat->invoke( $connection_pilot );

		$option = $this->get_heartbeat();
		$this->assertEquals( get_site_url(), $option['site_url'] );
		$this->assertEquals( md5( get_site_url() ), $option['hashed_site_url'] );
		$this->assertEquals( \Jetpack_Options::get_option( 'id', - 1 ), $option['cache_site_id'] );

		// Resets.
		$this->assertEquals( 0, $option['backoff_factor'] );
		$this->assertEquals( 0, $option['retry_count'] );
		$this->assertEquals( 0, $option['failure_timestamp'] );
	}

	/**
	 * @group jetpack-required
	 * @preserveGlobalState disabled
	 * @dataProvider get_test_data__should_back_off
	 */
	public function test__should_back_off( ?int $backoff_factor, ?DateTime $failure_time, ?DateTime $legacy_time, int $retry_count, bool $expected ) {
		if ( null !== $backoff_factor ) {
			$this->set_heartbeat( [
				'backoff_factor'    => $backoff_factor,
				'retry_count'       => $retry_count,
				'failure_timestamp' => null === $failure_time ? $legacy_time->getTimestamp() : $failure_time->getTimestamp(),
			] );
		}

		$connection_pilot = Connection_Pilot::instance();
		$should_back_off  = self::getMethod( 'should_back_off' );

		$this->assertEquals( $expected, $should_back_off->invoke( $connection_pilot ) );
	}

	public function get_test_data__update_heartbeat_on_failure() {
		$connection_pilot = Connection_Pilot::instance();
		$increments       = $connection_pilot::BACKOFF_INCREMENTS;
		$max_increment    = $connection_pilot::MAX_BACKOFF_FACTOR;

		$test_data = [];
		foreach ( $increments as $key => $increment ) {
			if ( isset( $increments[ $key + 1 ] ) ) {
				$test_data[ 'increment_' . $key ] = [ $increment, $increments[ $key + 1 ], $key ];
			}
		}

		$test_data['null']     = [ null, $increments[0], 1 ];
		$test_data['zero']     = [ 0, $increments[0], 2 ];
		$test_data['max']      = [ $max_increment, $max_increment, 3 ];
		$test_data['over_max'] = [ $max_increment + 1000, $max_increment, 4 ];

		return $test_data;
	}

	public function get_test_data__should_back_off() {
		$connection_pilot = Connection_Pilot::instance();
		$max_retries      = $connection_pilot::MAX_RETRIES;

		// [ current backoff factor, last failure's timestamp, legacy timestamp, retry count, expected result ]
		return [
			'null'                     => [ null, new DateTime(), null, 0, false ],
			'zero'                     => [ 0, new DateTime(), null, 0, false ],
			'one-hour-true'            => [ 1, new DateTime(), null, 0, true ],
			'one-hour-false'           => [ 1, ( new DateTime() )->modify( '-2 hours' ), null, 0, false ],
			'eight-hours-true'         => [ 8, new DateTime(), null, 0, true ],
			'eight-hours-false'        => [ 8, ( new DateTime() )->modify( '-9 hours' ), null, 0, false ],
			'eight-hours-legacy-true'  => [ 8, null, new DateTime(), 0, true ],
			'eight-hours-legacy-false' => [ 8, null, ( new DateTime() )->modify( '-9 hours' ), 0, false ],
			'exceeds-retry-limit-true' => [ 8, ( new DateTime() )->modify( '-9 hours' ), null, $max_retries, true ],
		];
	}

	private function get_heartbeat() {
		return get_option( 'vip_jetpack_connection_pilot_heartbeat', [] );
	}

	private function set_heartbeat( $values = [] ) {
		$saved = update_option( 'vip_jetpack_connection_pilot_heartbeat', [
			'site_url'          => $values['site_url'] ?? get_site_url(),
			'hashed_site_url'   => isset( $values['site_url'] ) ? md5( $values['site_url'] ) : md5( get_site_url() ),
			'cache_site_id'     => $values['cache_site_id'] ?? (int) \Jetpack_Options::get_option( 'id', - 1 ),
			'success_timestamp' => $values['success_timestamp'] ?? time(),
			'backoff_factor'    => $values['backoff_factor'] ?? 0,
			'retry_count'       => $values['retry_count'] ?? 0,
			'failure_timestamp' => $values['failure_timestamp'] ?? 0,
		], false );

		if ( ! $saved ) {
			throw new Error( 'Failed to set heartbeat' );
		}
	}
}
