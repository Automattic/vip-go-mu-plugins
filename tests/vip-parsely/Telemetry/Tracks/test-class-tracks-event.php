<?php

declare(strict_types=1);

namespace Automattic\VIP\Parsely\Telemetry;

use WP_UnitTestCase;
use WP_Error;

require_once __DIR__ . '/../../../../vip-parsely/Telemetry/Tracks/class-tracks-event.php';

class Tracks_Event_Test extends WP_UnitTestCase {
	public static function provide_ip_policy(): array {
		return [
			'private 192.168'      => [ '192.168.10.1', true ],
			'private 10'           => [ '10.11.10.11', true ],
			'private 172 low'      => [ '172.16.0.0', true ],
			'private 172 high'     => [ '172.31.255.255', true ],
			'public low neighbor'  => [ '172.15.255.255', false ],
			'public high neighbor' => [ '172.32.0.0', false ],
			'public original'      => [ '1.2.3.4', false ],
		];
	}

	/**
	 * @dataProvider provide_ip_policy
	 */
	public function test_rfc1918_private_ipv4_policy( string $ip, bool $private ) {
		wp_set_current_user( self::factory()->user->create() );
		$event = new Tracks_Event( [
			'_en'     => 'wpparsely_example',
			'_via_ip' => $ip,
		] );

		$this->assertNotInstanceOf( WP_Error::class, $event->data );
		if ( $private ) {
			$this->assertFalse( isset( $event->data->_via_ip ) );
		} else {
			$this->assertSame( $ip, $event->data->_via_ip );
		}
	}
}
