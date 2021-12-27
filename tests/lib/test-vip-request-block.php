<?php

use Yoast\PHPUnitPolyfills\Polyfills\ExpectPHPException;

require_once __DIR__ . '/../../lib/class-vip-request-block.php';

// phpcs:disable WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders

class VIP_Request_Block_Test extends WP_UnitTestCase {
	use ExpectPHPException;

	/*
	 * The $_SERVER headers that are used in this class to test
	 * are defined in the tests/bootstrap.php file.
	 */

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
}
