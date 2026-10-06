<?php

namespace Automattic\VIP\Tests;

use Automattic\Test\Constant_Mocker;
use PHPUnit\Framework\TestCase;

use function Automattic\VIP\Proxy\get_proxy_verification_key;
use function Automattic\VIP\Proxy\is_valid_proxy_verification_key;
use function Automattic\VIP\Proxy\fix_remote_address_with_verification_key;

// phpcs:disable Squiz.PHP.CommentedOutCode.Found

class Get_Proxy_Verification_Key_Test extends TestCase {
	public function setUp(): void {
		parent::setUp();
		Constant_Mocker::clear();
	}

	public function tearDown(): void {
		Constant_Mocker::clear();
		parent::tearDown();
	}

	public function test__not_defined() {
		// not defining the key

		$actual_key = get_proxy_verification_key();

		$this->assertNotEmpty( $actual_key, 'The Proxy Verification Key is empty' );
		$this->assertTrue( is_string( $actual_key ), 'The Proxy Verification Key is not a string' );
	}

	public function test__defined_but_empty() {
		Constant_Mocker::define( 'WPCOM_VIP_PROXY_VERIFICATION', '' );

		$actual_key = get_proxy_verification_key();

		$this->assertNotEmpty( $actual_key, 'The Proxy Verification Key is empty' );
		$this->assertTrue( is_string( $actual_key ), 'The Proxy Verification Key is not a string' );
	}

	public function test__defined_but_integer() {
		Constant_Mocker::define( 'WPCOM_VIP_PROXY_VERIFICATION', 1234 );

		$actual_key = get_proxy_verification_key();

		$this->assertSame( '1234', $actual_key );
	}

	public function test__defined() {
		$expected_key = 'secretkey';

		Constant_Mocker::define( 'WPCOM_VIP_PROXY_VERIFICATION', $expected_key );

		$actual_key = get_proxy_verification_key();

		$this->assertEquals( $expected_key, $actual_key );
	}
	/**
	 * Missing configuration must produce fresh keys that cannot authorize later requests.
	 *
	 * @dataProvider absent_configuration
	 */
	public function test__fallback_cannot_be_reused( ?string $configured_key ): void {
		if ( null !== $configured_key ) {
			Constant_Mocker::define( 'WPCOM_VIP_PROXY_VERIFICATION', $configured_key );
		}
		$captured = get_proxy_verification_key();
		$this->assertNotSame( $captured, get_proxy_verification_key() );
		$this->assertFalse( is_valid_proxy_verification_key( $captured ) );
		// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders -- Controlled request fixture and exact restoration.
		$original_server        = $_SERVER;
		$_SERVER['REMOTE_ADDR'] = '192.0.2.1';
		try {
			$this->assertFalse( fix_remote_address_with_verification_key( '203.0.113.5', $captured ) );
			$this->assertSame( '192.0.2.1', $_SERVER['REMOTE_ADDR'] );
		} finally {
			$_SERVER = $original_server;
			Constant_Mocker::clear();
		}
		// phpcs:enable WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
	}

	/**
	 * Exercise both absent and explicitly empty site configuration.
	 */
	public function absent_configuration(): array {
		return [
			'absent' => [ null ],
			'empty'  => [ '' ],
		];
	}
}
