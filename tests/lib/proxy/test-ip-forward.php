<?php

namespace Automattic\VIP\Tests;

use function Automattic\VIP\Proxy\fix_remote_address;
use function Automattic\VIP\Proxy\fix_remote_address_from_ip_trail;
use function Automattic\VIP\Proxy\is_valid_ip;
use function Automattic\VIP\Proxy\set_remote_address;

require_once __DIR__ . '/class-ip-forward-test-base.php';

// phpcs:disable WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__
// phpcs:disable WordPress.Security.ValidatedSanitizedInput

class IP_Forward_Tests extends IP_Forward_Test_Base {

	// is_valid_ip
	public function test__is_valid_ip__invalid() {
		$ip = 'bad_ip';

		$result = is_valid_ip( $ip );

		self::assertFalse( $result );
	}

	public function test__is_valid_ip__valid_ip4() {
		$ip = '1.2.3.4';

		$result = is_valid_ip( $ip );

		self::assertTrue( $result );
	}

	public function test__is_valid_ip__valid_ip6() {
		$ip = '2001:db8::1234:ace:6006:1e';

		$result = is_valid_ip( $ip );

		self::assertTrue( $result );
	}

	// set_remote_address
	public function test__set_remote_address() {
		$user_ip = '5.6.7.8';

		set_remote_address( $user_ip );

		self::assertEquals( $user_ip, $_SERVER['REMOTE_ADDR'] );
	}

	// fix_remote_address
	public function test__fix_remote_address__invalid_user_ip() {
		$user_ip   = 'bad_ip';
		$proxy_ip  = '5.6.7.8';
		$whitelist = [ '5.6.7.8' ];

		$result = fix_remote_address( $user_ip, $proxy_ip, $whitelist );

		self::assertFalse( $result );
		self::assertEquals( self::DEFAULT_REMOTE_ADDR, $_SERVER['REMOTE_ADDR'] );
	}

	public function test__fix_remote_address__ip_not_in_whitelist() {
		$user_ip   = '1.2.3.4';
		$proxy_ip  = '5.6.7.8';
		$whitelist = [ '0.0.0.0' ];

		$result = fix_remote_address( $user_ip, $proxy_ip, $whitelist );

		self::assertFalse( $result );
		self::assertEquals( self::DEFAULT_REMOTE_ADDR, $_SERVER['REMOTE_ADDR'] );
	}

	public function test__fix_remote_address__ip_in_whitelist_ipv4() {
		$user_ip   = '1.2.3.4';
		$proxy_ip  = '5.6.7.8';
		$whitelist = [ '5.6.7.8' ];

		$result = fix_remote_address( $user_ip, $proxy_ip, $whitelist );

		self::assertTrue( $result );
		self::assertEquals( '1.2.3.4', $_SERVER['REMOTE_ADDR'] );
	}

	public function test__fix_remote_address__ip_in_whitelist_ipv6() {
		$user_ip   = '2001:db8::1234:ace:6006:1e';
		$proxy_ip  = '5.6.7.8';
		$whitelist = [ '5.6.7.8' ];

		$result = fix_remote_address( $user_ip, $proxy_ip, $whitelist );

		self::assertTrue( $result );
		self::assertEquals( '2001:db8::1234:ace:6006:1e', $_SERVER['REMOTE_ADDR'] );
	}

	public function test__fix_remote_address_from_ip_trail__ip_not_in_whitelist() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail                        = '1.2.3.4, 5.6.7.8';
		$whitelist                       = [ '0.0.0.0' ];

		$result = fix_remote_address_from_ip_trail( $ip_trail, $whitelist );

		self::assertFalse( $result );
		self::assertEquals( self::DEFAULT_REMOTE_ADDR, $_SERVER['REMOTE_ADDR'] );
	}

	public function test__fix_remote_address_from_ip_trail__ip_in_whitelist() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail                        = '1.2.3.4, 5.6.7.8';
		$whitelist                       = [ '5.6.7.8' ];

		$result = fix_remote_address_from_ip_trail( $ip_trail, $whitelist );

		self::assertTrue( $result );
		self::assertEquals( '1.2.3.4', $_SERVER['REMOTE_ADDR'] );
	}
}
