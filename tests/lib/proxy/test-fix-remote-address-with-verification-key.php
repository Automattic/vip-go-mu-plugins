<?php

namespace Automattic\VIP\Tests;

use Automattic\VIP\Proxy\Proxy_Verification_Helper;

use function Automattic\VIP\Proxy\fix_remote_address_with_verification_key;

require_once __DIR__ . '/class-ip-forward-test-base.php';

// phpcs:disable WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__
// phpcs:disable WordPress.Security.ValidatedSanitizedInput

class Fix_Remote_Address_With_Verification_Key_Test extends IP_Forward_Test_Base {
	public function setUp(): void {
		parent::setUp();

		// Mocks `define( 'WPCOM_VIP_PROXY_VERIFICATION', 'valid-key' )`
		Proxy_Verification_Helper::set_proxy_verification_key( 'valid-key' );
	}

	public function test__invalid_ip() {
		$key     = 'valid-key';
		$user_ip = 'bad_ip';
		$result  = fix_remote_address_with_verification_key( $user_ip, $key );

		self::assertFalse( $result );
		self::assertEquals( self::DEFAULT_REMOTE_ADDR, $_SERVER['REMOTE_ADDR'] );
	}

	public function test__invalid_key() {
		$key     = 'not-a-valid-key';
		$user_ip = '5.6.7.8';
		$result  = fix_remote_address_with_verification_key( $user_ip, $key );

		self::assertFalse( $result );
		self::assertEquals( self::DEFAULT_REMOTE_ADDR, $_SERVER['REMOTE_ADDR'] );
	}

	public function test__all_valid() {
		$key     = 'valid-key';
		$user_ip = '5.6.7.8';
		$result  = fix_remote_address_with_verification_key( $user_ip, $key );

		self::assertTrue( $result );
		self::assertEquals( '5.6.7.8', $_SERVER['REMOTE_ADDR'] );
	}
}
