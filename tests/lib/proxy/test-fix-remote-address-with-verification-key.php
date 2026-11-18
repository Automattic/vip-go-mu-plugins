<?php

namespace Automattic\VIP\Tests;

use function Automattic\VIP\Proxy\fix_remote_address_with_verification_key;

require_once __DIR__ . '/class-ip-forward-test-base.php';

// phpcs:disable WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__
// phpcs:disable WordPress.Security.ValidatedSanitizedInput

class Fix_Remote_Address_With_Verification_Key_Test extends IP_Forward_Test_Base {
	const PROXY_VERIFICATION_KEY = 'valid-key';

	public function data_fix_remote_address_with_verification_key(): array {
		return [
			'invalid IP'  => [ 'bad_ip', 'valid-key', false, self::DEFAULT_REMOTE_ADDR ],
			'invalid key' => [ '5.6.7.8', 'not-a-valid-key', false, self::DEFAULT_REMOTE_ADDR ],
			'all valid'   => [ '5.6.7.8', 'valid-key', true, '5.6.7.8' ],
		];
	}

	/**
	 * @dataProvider data_fix_remote_address_with_verification_key
	 */
	public function test__fix_remote_address_with_verification_key( string $user_ip, string $key, bool $expected, string $expected_remote_addr ) {
		$result = fix_remote_address_with_verification_key( $user_ip, $key );

		self::assertSame( $expected, $result );
		self::assertEquals( $expected_remote_addr, $_SERVER['REMOTE_ADDR'] );
	}
}
