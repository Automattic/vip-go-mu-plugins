<?php

namespace Automattic\VIP\Tests;

use Automattic\VIP\Proxy\Proxy_Verification_Helper;

use function Automattic\VIP\Proxy\fix_remote_address_from_ip_trail_with_verification_key;

require_once __DIR__ . '/class-ip-forward-test-base.php';

// phpcs:disable WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__
// phpcs:disable WordPress.Security.ValidatedSanitizedInput

class Fix_Remote_Address_From_Ip_Trail_With_Verification_Key_Test extends IP_Forward_Test_Base {
	public function setUp(): void {
		parent::setUp();

		// Mocks `define( 'WPCOM_VIP_PROXY_VERIFICATION', 'valid-key' )`
		Proxy_Verification_Helper::set_proxy_verification_key( 'valid-key' );
	}

	public function test__all_valid() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail                        = '1.2.3.4, 5.6.7.8';

		$key = 'valid-key';

		$result = fix_remote_address_from_ip_trail_with_verification_key( $ip_trail, $key );

		self::assertTrue( $result );
		self::assertEquals( '1.2.3.4', $_SERVER['REMOTE_ADDR'] );
	}

	public function test__invalid_key() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail                        = '1.2.3.4, 5.6.7.8';
		
		$key = 'invalid-key';

		$result = fix_remote_address_from_ip_trail_with_verification_key( $ip_trail, $key );

		self::assertFalse( $result );
		self::assertEquals( self::DEFAULT_REMOTE_ADDR, $_SERVER['REMOTE_ADDR'] );
	}

	public function test__invalid_ip_trail() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail                        = '1.2.3.4, 5.6.7.eight';

		$key = 'valid-key';

		$result = fix_remote_address_from_ip_trail_with_verification_key( $ip_trail, $key );

		self::assertFalse( $result );
		self::assertEquals( self::DEFAULT_REMOTE_ADDR, $_SERVER['REMOTE_ADDR'] );
	}
}
