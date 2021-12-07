<?php

namespace Automattic\VIP\Tests;

use Automattic\VIP\Proxy\Proxy_Verification_Helper;
use PHPUnit\Framework\TestCase;

use function Automattic\VIP\Proxy\is_valid_proxy_verification_key;

class Is_Valid_Proxy_Verification_Key_Test extends TestCase {
	public function setUp(): void {
		parent::setUp();
		Proxy_Verification_Helper::set_proxy_verification_key( null );
	}

	public function test__invalid_key() {
		// Mocks `define( 'WPCOM_VIP_PROXY_VERIFICATION', 'valid-key' )`
		Proxy_Verification_Helper::set_proxy_verification_key( 'valid-key' );
		$key = 'not-a-valid-key';

		$result = is_valid_proxy_verification_key( $key );

		self::assertFalse( $result );
	}

	public function test__valid_key() {
		// Mocks `define( 'WPCOM_VIP_PROXY_VERIFICATION', 'valid-key' )`
		Proxy_Verification_Helper::set_proxy_verification_key( 'valid-key' );
		$key = 'valid-key';

		$result = is_valid_proxy_verification_key( $key );

		self::assertTrue( $result );
	}
}
