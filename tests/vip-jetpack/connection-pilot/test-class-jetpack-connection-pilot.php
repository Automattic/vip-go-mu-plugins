<?php

namespace Automattic\VIP\Jetpack;

class Connection_Pilot_Test extends \WP_UnitTestCase {
	/**
	 * @preserveGlobalState disabled
	 * @dataProvider get_test_data__update_backoff_factor
	 */
	public function test__update_backoff_factor( $backoff_factor, $expected ) {
		$option = array(
			'site_url'        => get_site_url(),
			'hashed_site_url' => md5( get_site_url() ),
			'cache_site_id'   => (int) \Jetpack_Options::get_option( 'id', - 1 ),
			'timestamp'       => time(),
			'backoff_factor'  => $backoff_factor,
		);
		update_option( 'vip_jetpack_connection_pilot_heartbeat', $option, false );

		$cp = Connection_Pilot::instance();
		$cp->update_backoff_factor();

		$option = get_option( 'vip_jetpack_connection_pilot_heartbeat' );
		$this->assertEquals( $expected, $option['backoff_factor'] );
	}

	/**
	 * @preserveGlobalState disabled
	 * @dataProvider get_test_data__update_heartbeat
	 */
	public function test__update_heartbeat( $backoff_factor, $expected ) {
		$cp = Connection_Pilot::instance();

		if ( $backoff_factor ) {
			$cp->update_heartbeat( $backoff_factor );
		} else {
			$cp->update_heartbeat();
		}

		$option = get_option( 'vip_jetpack_connection_pilot_heartbeat' );
		$this->assertEquals( $expected['site_url'], $option['site_url'] );
		$this->assertEquals( $expected['hashed_site_url'], $option['hashed_site_url'] );
		$this->assertEquals( $expected['cache_site_id'], $option['cache_site_id'] );
		$this->assertEquals( $expected['backoff_factor'], $option['backoff_factor'] );
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
				)
			],
			'zero' => [
				0,
				array(
					'site_url'        => get_site_url(),
					'hashed_site_url' => md5( get_site_url() ),
					'cache_site_id'   => (int) \Jetpack_Options::get_option( 'id', - 1 ),
					'timestamp'       => time(),
					'backoff_factor'  => 0,
				)
			],
			'one'  => [
				1,
				array(
					'site_url'        => get_site_url(),
					'hashed_site_url' => md5( get_site_url() ),
					'cache_site_id'   => (int) \Jetpack_Options::get_option( 'id', - 1 ),
					'timestamp'       => time(),
					'backoff_factor'  => 1,
				)
			],
		];
	}
}
