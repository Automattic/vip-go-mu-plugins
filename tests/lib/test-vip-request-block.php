<?php

class VIP_Request_Block_Test extends \WP_UnitTestCase {
	/*
	 * The $_SERVER headers that are used in this class to test
	 * are defined in the tests/bootstrap.php file.
	 */

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once __DIR__ . '/../../lib/class-vip-request-block.php';
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test__error_raised_true_client_ip() {
		VIP_Request_Block::ip( '2.2.2.2' );
		// Expecting that no exception has been raised up to this point
	}

	/**
	 * @doesNotPerformAssertions
	 */
	public function test__invalid_ip_should_not_raise_error() {
		VIP_Request_Block::ip( '1' );
		// Expecting that no exception has been raised up to this point
	}

	public function test__error_raised_first_ip_forwarded() {
		$this->expectException( PHPUnit\Framework\Error\Warning::class );
		VIP_Request_Block::ip( '1.1.1.1' );
	}

	public function test__error_raised_second_ip_forwarded() {
		$this->expectException( PHPUnit\Framework\Error\Warning::class );
		VIP_Request_Block::ip( '8.8.8.8' );
	}
}
