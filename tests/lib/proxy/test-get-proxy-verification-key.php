<?php

namespace Automattic\VIP\Tests;

use Automattic\Test\Constant_Mocker;
use PHPUnit\Framework\TestCase;

use function Automattic\VIP\Proxy\get_proxy_verification_key;

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

		$this->assertTrue( is_string( $actual_key ) );
	}

	public function test__defined() {
		$expected_key = 'secretkey';

		Constant_Mocker::define( 'WPCOM_VIP_PROXY_VERIFICATION', $expected_key );

		$actual_key = get_proxy_verification_key();

		$this->assertEquals( $expected_key, $actual_key );
	}
}
