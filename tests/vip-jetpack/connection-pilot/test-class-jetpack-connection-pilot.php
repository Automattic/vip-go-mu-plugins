<?php

namespace Automattic\VIP\Jetpack;

use DateTime;
use WP_UnitTestCase;

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
	 * @dataProvider get_test_data__update_backoff_factor
	 */
	public function test__update_backoff_factor( ?int $backoff_factor, int $expected ) {
		$option = array(
			'site_url'        => get_site_url(),
			'hashed_site_url' => md5( get_site_url() ),
			'cache_site_id'   => (int) \Jetpack_Options::get_option( 'id', - 1 ),
			'timestamp'       => time(),
			'backoff_factor'  => $backoff_factor,
		);
		update_option( 'vip_jetpack_connection_pilot_heartbeat', $option, false );

		$cp                    = Connection_Pilot::instance();
		$update_backoff_factor = self::getMethod( 'update_backoff_factor' );
		$update_backoff_factor->invoke( $cp );

		$option = get_option( 'vip_jetpack_connection_pilot_heartbeat' );
		$this->assertEquals( $expected, $option['backoff_factor'] );
	}

	/**
	 * @group jetpack-required
	 * @preserveGlobalState disabled
	 * @dataProvider get_test_data__update_heartbeat
	 */
	public function test__update_heartbeat( ?int $backoff_factor, array $expected ) {
		$cp               = Connection_Pilot::instance();
		$update_heartbeat = self::getMethod( 'update_heartbeat' );

		if ( $backoff_factor ) {
			$update_heartbeat->invokeArgs( $cp, [ $backoff_factor ] );
		} else {
			$update_heartbeat->invoke( $cp );
		}

		$option = get_option( 'vip_jetpack_connection_pilot_heartbeat' );
		$this->assertEquals( $expected['site_url'], $option['site_url'] );
		$this->assertEquals( $expected['hashed_site_url'], $option['hashed_site_url'] );
		$this->assertEquals( $expected['cache_site_id'], $option['cache_site_id'] );
		$this->assertEquals( $expected['backoff_factor'], $option['backoff_factor'] );
	}

	/**
	 * @group jetpack-required
	 * @preserveGlobalState disabled
	 * @dataProvider get_test_data__should_back_off
	 */
	public function test__should_back_off( ?int $backoff_factor, DateTime $dt, bool $expected ) {
		if ( null !== $backoff_factor ) {
			$option = array(
				'site_url'        => get_site_url(),
				'hashed_site_url' => md5( get_site_url() ),
				'cache_site_id'   => (int) \Jetpack_Options::get_option( 'id', - 1 ),
				'timestamp'       => $dt->getTimestamp(),
				'backoff_factor'  => $backoff_factor,
			);
			update_option( 'vip_jetpack_connection_pilot_heartbeat', $option, false );
		}

		$cp              = Connection_Pilot::instance();
		$should_back_off = self::getMethod( 'should_back_off' );

		$result = $should_back_off->invoke( $cp );

		$this->assertEquals( $expected, $result );
	}

	public function get_test_data__update_backoff_factor() {
		return [
			'null' => [ null, 1 ],
			'zero' => [ 0, 1 ],
			'one'  => [ 1, 2 ],
			'two'  => [ 2, 4 ],
			'max'  => [ 2048, 2048 ],
		];
	}

	public function get_test_data__update_heartbeat() {
		return [
			'null' => [
				null,
				array(
					'site_url'        => get_site_url(),
					'hashed_site_url' => md5( get_site_url() ),
					'cache_site_id'   => (int) \Jetpack_Options::get_option( 'id', - 1 ),
					'timestamp'       => time(),
					'backoff_factor'  => 0,
				),
			],
			'zero' => [
				0,
				array(
					'site_url'        => get_site_url(),
					'hashed_site_url' => md5( get_site_url() ),
					'cache_site_id'   => (int) \Jetpack_Options::get_option( 'id', - 1 ),
					'timestamp'       => time(),
					'backoff_factor'  => 0,
				),
			],
			'one'  => [
				1,
				array(
					'site_url'        => get_site_url(),
					'hashed_site_url' => md5( get_site_url() ),
					'cache_site_id'   => (int) \Jetpack_Options::get_option( 'id', - 1 ),
					'timestamp'       => time(),
					'backoff_factor'  => 1,
				),
			],
		];
	}

	public function get_test_data__should_back_off() {
		return [
			'null'              => [ null, new DateTime(), false ],
			'zero'              => [ 0, new DateTime(), false ],
			'one-hour-true'     => [ 1, new DateTime(), true ],
			'one-hour-false'    => [ 1, ( new DateTime() )->modify( '-2 hours' ), false ],
			'eight-hours-true'  => [ 8, new DateTime(), true ],
			'eight-hours-false' => [ 8, ( new DateTime() )->modify( '-9 hours' ), false ],
		];
	}
}
