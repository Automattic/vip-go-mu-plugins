<?php

namespace Automattic\VIP\Tests;

use function Automattic\VIP\Proxy\fix_remote_address_from_ip_trail_with_verification_key;

require_once __DIR__ . '/class-ip-forward-test-base.php';

// phpcs:disable WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__
// phpcs:disable WordPress.Security.ValidatedSanitizedInput

class Fix_Remote_Address_From_Ip_Trail_With_Verification_Key_Test extends IP_Forward_Test_Base {
	const PROXY_VERIFICATION_KEY = 'valid-key';

	public function data_fix_remote_address_from_ip_trail_with_verification_key(): array {
		return [
			'all valid'        => [ '1.2.3.4, 5.6.7.8', 'valid-key', true, '1.2.3.4' ],
			'invalid key'      => [ '1.2.3.4, 5.6.7.8', 'invalid-key', false, self::DEFAULT_REMOTE_ADDR ],
			'invalid IP trail' => [ '1.2.3.4, 5.6.7.eight', 'valid-key', false, self::DEFAULT_REMOTE_ADDR ],
		];
	}

	/**
	 * @dataProvider data_fix_remote_address_from_ip_trail_with_verification_key
	 */
	public function test__fix_remote_address_from_ip_trail_with_verification_key( string $ip_trail, string $key, bool $expected, string $expected_remote_addr ) {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';

		$result = fix_remote_address_from_ip_trail_with_verification_key( $ip_trail, $key );

		self::assertSame( $expected, $result );
		self::assertEquals( $expected_remote_addr, $_SERVER['REMOTE_ADDR'] );
	}
}
