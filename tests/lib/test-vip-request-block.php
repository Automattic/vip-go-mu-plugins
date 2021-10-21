<?php

use Yoast\PHPUnitPolyfills\Polyfills\ExpectPHPException;

require_once __DIR__ . '/../../lib/class-vip-request-block.php';

class VIP_Request_Block_Test extends WP_UnitTestCase {
	use ExpectPHPException;

	/*
	 * The $_SERVER headers that are used in this class to test
	 * are defined in the tests/bootstrap.php file.
	 */

	public function test__no_error_raised_when_ip_is_not_present() {
		$this->expectNotToPerformAssertions();

		// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
		$_SERVER['HTTP_TRUE_CLIENT_IP'] = '4.4.4.4';
		// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1, 8.8.8.8';
		VIP_Request_Block::ip( '2.2.2.2' );
		// Expecting that no exception has been raised up to this point
	}

	public function test__invalid_ip_should_not_raise_error() {
		$this->expectNotToPerformAssertions();

		// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1, 8.8.8.8';
		VIP_Request_Block::ip( '1' );
		// Expecting that no exception has been raised up to this point
	}

	public function test__error_raised_when_true_client_ip() {
		// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
		$_SERVER['HTTP_TRUE_CLIENT_IP'] = '4.4.4.4';
		// We're detecting that the block is successful by expecting
		// "Cannot modify header information" headers already sent by warning
		$this->expectWarning();
		VIP_Request_Block::ip( '4.4.4.4' );
	}

	public function test__error_raised_first_ip_forwarded() {
		// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1, 8.8.8.8';
		// We're detecting that the block is successful by expecting
		// "Cannot modify header information" headers already sent by warning
		$this->expectWarning();
		VIP_Request_Block::ip( '1.1.1.1' );
	}

	public function test__error_raised_second_ip_forwarded() {
		// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1, 8.8.8.8';
		// We're detecting that the block is successful by expecting
		// "Cannot modify header information" headers already sent by warning
		$this->expectWarning();
		VIP_Request_Block::ip( '8.8.8.8' );
	}
}
