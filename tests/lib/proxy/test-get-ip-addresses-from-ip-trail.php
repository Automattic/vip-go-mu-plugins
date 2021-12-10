<?php

namespace Automattic\VIP\Tests;

use function Automattic\VIP\Proxy\get_ip_addresses_from_ip_trail;

require_once __DIR__ . '/class-ip-forward-test-base.php';

// phpcs:disable WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders

class Get_IP_Addresses_From_IP_Trail_Test extends IP_Forward_Test_Base {

	// fix_remote_address_from_ip_trail
	public function test__get_ip_addresses_from_ip_trail__no_forwarded_for() {
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		$ip_trail = '1.2.3.4, 5.6.7.8';

		$result = get_ip_addresses_from_ip_trail( $ip_trail );

		self::assertFalse( $result );
	}

	public function test__get_ip_addresses_from_ip_trail__ip_trail_has_lt_2_ips() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail                        = '1.2.3.4';

		$result = get_ip_addresses_from_ip_trail( $ip_trail );

		self::assertFalse( $result );
	}

	public function test__get_ip_addresses_from_ip_trail__ip_trail_has_gt_2_ips() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail                        = '1.2.3.4, 9.0.21.0, 5.6.7.8';

		$result = get_ip_addresses_from_ip_trail( $ip_trail );

		self::assertFalse( $result );
	}

	public function test__get_ip_addresses_from_ip_trail__proxy_doesnt_match_forwarded_for() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.5.5.5';
		$ip_trail                        = '1.2.3.4, 5.6.7.8';

		$result = get_ip_addresses_from_ip_trail( $ip_trail );

		self::assertFalse( $result );
	}

	public function test__fix_remote_address_from_ip_trail__invalid_remote_ip() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail                        = '1.2.3.4, 123456789';

		$result = get_ip_addresses_from_ip_trail( $ip_trail );

		self::assertFalse( $result );
	}

	public function test__fix_remote_address_from_ip_trail__invalid_user_ip() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail                        = 'bad_ip, 5.6.7.8';

		$result = get_ip_addresses_from_ip_trail( $ip_trail );

		self::assertFalse( $result );
	}

	public function test__fix_remote_address_from_ip_trail__valid_ip_trail_ipv4() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail                        = '1.2.3.4, 5.6.7.8';
		$expected_ip_addresses           = [ '1.2.3.4', '5.6.7.8' ];

		$result = get_ip_addresses_from_ip_trail( $ip_trail );

		self::assertEquals( $expected_ip_addresses, $result );
	}

	public function test__fix_remote_address_from_ip_trail__valid_ip_trail_ipv6() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail                        = '2001:db8::1234:ace:6006:1e, 5.6.7.8';
		$expected_ip_addresses           = [ '2001:db8::1234:ace:6006:1e', '5.6.7.8' ];

		$result = get_ip_addresses_from_ip_trail( $ip_trail );

		self::assertEquals( $expected_ip_addresses, $result );
	}
}
