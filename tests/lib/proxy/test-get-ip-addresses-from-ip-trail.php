<?php

namespace Automattic\VIP\Tests;

use function Automattic\VIP\Proxy\get_ip_addresses_from_ip_trail;

require_once __DIR__ . '/class-ip-forward-test-base.php';

// phpcs:disable WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders

class Get_IP_Addresses_From_IP_Trail_Test extends IP_Forward_Test_Base {
	public function data_get_ip_addresses_from_ip_trail(): array {
		return [
			'no forwarded for'                   => [ null, '1.2.3.4, 5.6.7.8', false ],
			'IP trail has less than 2 IPs'       => [ '5.6.7.8', '1.2.3.4', false ],
			'IP trail has more than 2 IPs'       => [ '5.6.7.8', '1.2.3.4, 9.0.21.0, 5.6.7.8', false ],
			'proxy does not match forwarded for' => [ '5.5.5.5', '1.2.3.4, 5.6.7.8', false ],
			'invalid remote IP'                  => [ '5.6.7.8', '1.2.3.4, 123456789', false ],
			'invalid user IP'                    => [ '5.6.7.8', 'bad_ip, 5.6.7.8', false ],
			'valid IPv4 trail'                   => [ '5.6.7.8', '1.2.3.4, 5.6.7.8', [ '1.2.3.4', '5.6.7.8' ] ],
			'valid IPv6 trail'                   => [ '5.6.7.8', '2001:db8::1234:ace:6006:1e, 5.6.7.8', [ '2001:db8::1234:ace:6006:1e', '5.6.7.8' ] ],
		];
	}

	/**
	 * @dataProvider data_get_ip_addresses_from_ip_trail
	 */
	public function test__get_ip_addresses_from_ip_trail( ?string $forwarded_for, string $ip_trail, $expected ) {
		if ( null === $forwarded_for ) {
			unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		} else {
			$_SERVER['HTTP_X_FORWARDED_FOR'] = $forwarded_for;
		}

		self::assertSame( $expected, get_ip_addresses_from_ip_trail( $ip_trail ) );
	}
}
