<?php

namespace Automattic\VIP\Tests;

use Automattic\VIP\Proxy\Proxy_Verification_Helper;
use PHPUnit\Framework\TestCase;

use function Automattic\VIP\Proxy\get_proxy_verification_key;

// phpcs:disable Squiz.PHP.CommentedOutCode.Found

class Get_Proxy_Verification_Key_Test extends TestCase {
	public function setUp(): void {
		parent::setUp();
		Proxy_Verification_Helper::set_proxy_verification_key( null );
	}

	public function test__not_defined() {
		// not defining the key

		$actual_key = get_proxy_verification_key();

		$this->assertNotEmpty( $actual_key, 'The Proxy Verification Key is empty' );
		$this->assertTrue( is_string( $actual_key ), 'The Proxy Verification Key is not a string' );
	}

	public function test__defined_but_empty() {
		// Mocks `define( 'WPCOM_VIP_PROXY_VERIFICATION', '' )`
		Proxy_Verification_Helper::set_proxy_verification_key( '' );

		$actual_key = get_proxy_verification_key();

		$this->assertNotEmpty( $actual_key, 'The Proxy Verification Key is empty' );
		$this->assertTrue( is_string( $actual_key ), 'The Proxy Verification Key is not a string' );
	}

	public function test__defined_but_integer() {
		// Mocks `define( 'WPCOM_VIP_PROXY_VERIFICATION', 1234 )`
		Proxy_Verification_Helper::set_proxy_verification_key( 1234 );

		$actual_key = get_proxy_verification_key();

		$this->assertTrue( is_string( $actual_key ) );
	}

	public function test__defined() {
		$expected_key = 'secretkey';

		// Mocks `define( 'WPCOM_VIP_PROXY_VERIFICATION', $expected_key )`
		Proxy_Verification_Helper::set_proxy_verification_key( $expected_key );

		$actual_key = get_proxy_verification_key();

		$this->assertEquals( $expected_key, $actual_key );
	}
}
