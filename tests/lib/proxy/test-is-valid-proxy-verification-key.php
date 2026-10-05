<?php

namespace Automattic\VIP\Tests;

use Automattic\Test\Constant_Mocker;
use PHPUnit\Framework\TestCase;

use function Automattic\VIP\Proxy\is_valid_proxy_verification_key;

class Is_Valid_Proxy_Verification_Key_Test extends TestCase {
	public function setUp(): void {
		parent::setUp();
		Constant_Mocker::clear();
	}

	public function tearDown(): void {
		Constant_Mocker::clear();
		parent::tearDown();
	}

	public function test__invalid_key() {
		Constant_Mocker::define( 'WPCOM_VIP_PROXY_VERIFICATION', 'valid-key' );
		$key = 'not-a-valid-key';

		$result = is_valid_proxy_verification_key( $key );

		self::assertFalse( $result );
	}

	public function test__valid_key() {
		Constant_Mocker::define( 'WPCOM_VIP_PROXY_VERIFICATION', 'valid-key' );
		$key = 'valid-key';

		$result = is_valid_proxy_verification_key( $key );

		self::assertTrue( $result );
	}
}
