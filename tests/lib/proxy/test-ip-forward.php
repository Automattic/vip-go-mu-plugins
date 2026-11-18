<?php

namespace Automattic\VIP\Tests;

use function Automattic\VIP\Proxy\fix_remote_address;
use function Automattic\VIP\Proxy\fix_remote_address_from_ip_trail;
use function Automattic\VIP\Proxy\is_valid_ip;

require_once __DIR__ . '/class-ip-forward-test-base.php';

// phpcs:disable WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__
// phpcs:disable WordPress.Security.ValidatedSanitizedInput

class IP_Forward_Tests extends IP_Forward_Test_Base {
	public function data_is_valid_ip(): array {
		return [
			'invalid' => [ 'bad_ip', false ],
			'IPv4'    => [ '1.2.3.4', true ],
			'IPv6'    => [ '2001:db8::1234:ace:6006:1e', true ],
		];
	}

	/**
	 * @dataProvider data_is_valid_ip
	 */
	public function test__is_valid_ip( string $ip, bool $expected ) {
		self::assertSame( $expected, is_valid_ip( $ip ) );
	}

	public function data_fix_remote_address(): array {
		return [
			'invalid user IP'          => [ 'bad_ip', [ '5.6.7.8' ], false, self::DEFAULT_REMOTE_ADDR ],
			'proxy not in allow list'  => [ '1.2.3.4', [ '0.0.0.0' ], false, self::DEFAULT_REMOTE_ADDR ],
			'IPv4 user, allowed proxy' => [ '1.2.3.4', [ '5.6.7.8' ], true, '1.2.3.4' ],
			'IPv6 user, allowed proxy' => [ '2001:db8::1234:ace:6006:1e', [ '5.6.7.8' ], true, '2001:db8::1234:ace:6006:1e' ],
		];
	}

	/**
	 * @dataProvider data_fix_remote_address
	 */
	public function test__fix_remote_address( string $user_ip, array $whitelist, bool $expected, string $expected_remote_addr ) {
		$result = fix_remote_address( $user_ip, '5.6.7.8', $whitelist );

		self::assertSame( $expected, $result );
		self::assertEquals( $expected_remote_addr, $_SERVER['REMOTE_ADDR'] );
	}

	public function data_fix_remote_address_from_ip_trail(): array {
		return [
			'proxy not in allow list' => [ [ '0.0.0.0' ], false, self::DEFAULT_REMOTE_ADDR ],
			'allowed proxy'           => [ [ '5.6.7.8' ], true, '1.2.3.4' ],
		];
	}

	/**
	 * @dataProvider data_fix_remote_address_from_ip_trail
	 */
	public function test__fix_remote_address_from_ip_trail( array $whitelist, bool $expected, string $expected_remote_addr ) {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';

		$result = fix_remote_address_from_ip_trail( '1.2.3.4, 5.6.7.8', $whitelist );

		self::assertSame( $expected, $result );
		self::assertEquals( $expected_remote_addr, $_SERVER['REMOTE_ADDR'] );
	}
}
