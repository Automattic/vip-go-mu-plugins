<?php

require_once __DIR__ . '/../../lib/class-vip-request-block.php';

// phpcs:disable WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders

class VIP_Request_Block_Test extends WP_UnitTestCase {
	/*
	 * The $_SERVER headers that are used in this class to test
	 * are defined in the tests/bootstrap.php file.
	 */

	public function tearDown(): void {
		unset( $_SERVER['HTTP_TRUE_CLIENT_IP'], $_SERVER['HTTP_X_FORWARDED_FOR'] );
		parent::tearDown();
	}

	public function test__no_error_raised_when_ip_is_not_present() {
		$_SERVER['HTTP_TRUE_CLIENT_IP']  = '4.4.4.4';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1, 8.8.8.8';

		$actual = VIP_Request_Block::ip( '2.2.2.2' );
		self::assertFalse( $actual );
	}

	public function test__invalid_ip_should_not_raise_error() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1, 8.8.8.8';

		$actual = VIP_Request_Block::ip( '1' );
		self::assertFalse( $actual );
	}

	public function test__error_raised_when_true_client_ip() {
		$_SERVER['HTTP_TRUE_CLIENT_IP'] = '4.4.4.4';

		$actual = VIP_Request_Block::ip( '4.4.4.4' );
		self::assertTrue( $actual );
	}

	public function test__error_raised_first_ip_forwarded() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1, 8.8.8.8';

		$actual = VIP_Request_Block::ip( '1.1.1.1' );
		self::assertTrue( $actual );
	}

	public function test__error_raised_second_ip_forwarded() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1, 8.8.8.8';

		$actual = VIP_Request_Block::ip( '8.8.8.8' );
		self::assertTrue( $actual );
	}

	public function test_partial_match_xff(): void {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '11.1.1.11, 8.8.8.8';

		$actual = VIP_Request_Block::ip( '1.1.1.1' );
		self::assertFalse( $actual );
	}

	/**
	 * @dataProvider data_ipv6_corner_cases
	 */
	public function test_ipv6_corner_cases( string $index, string $value, string $block ): void {
		$_SERVER[ $index ] = $value;

		$actual = VIP_Request_Block::ip( $block );
		self::assertTrue( $actual );
	}

	public function data_ipv6_corner_cases(): iterable {
		return [
			[ 'HTTP_TRUE_CLIENT_IP', '::ffff:127.0.0.1', '::FFFF:127.0.0.1' ],
			[ 'HTTP_X_FORWARDED_FOR', '::ffff:127.0.0.1', '::FFFF:127.0.0.1' ],
			[ 'HTTP_TRUE_CLIENT_IP', '2001:4860:4860::8844', '2001:4860:4860:0000:0000:0000:0000:8844' ],
			[ 'HTTP_TRUE_CLIENT_IP', '2001:4860:4860:0:0:0:0:8844', '2001:4860:4860:0000:0000:0000:0000:8844' ],
			[ 'HTTP_X_FORWARDED_FOR', '2001:4860:4860::8844', '2001:4860:4860:0000:0000:0000:0000:8844' ],
			[ 'HTTP_X_FORWARDED_FOR', '2001:4860:4860:0:0:0:0:8844', '2001:4860:4860:0000:0000:0000:0000:8844' ],
		];
	}
}
