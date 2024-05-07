<?php

require_once __DIR__ . '/../../lib/class-vip-request-block.php';

// phpcs:disable WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
class LogTrackingRequestBlock extends VIP_Request_Block {
	public static $log_called = false;

	public static function log( string $criteria, string $value ): void {
		self::$log_called = true;
	}

	public static function block_and_log( string $value, string $criteria ) {
		if ( static::$should_log ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			static::log( $criteria, $value );
		}
	}
}

class VIP_Request_Block_Test extends WP_UnitTestCase {
	/*
	 * The $_SERVER headers that are used in this class to test
	 * are defined in the tests/bootstrap.php file.
	 */

	public function setUp(): void {
		parent::setUp();
		LogTrackingRequestBlock::$log_called = false;
	}

	public function tearDown(): void {
		// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__
		unset( $_SERVER['HTTP_TRUE_CLIENT_IP'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_USER_AGENT'] );
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



	public function test__no_error_log_when_suppressed(): void {
		$_SERVER['HTTP_TRUE_CLIENT_IP'] = '1.1.1.1';

		LogTrackingRequestBlock::toggle_logging( false );
		LogTrackingRequestBlock::ip( '1.1.1.1' );

		self::assertFalse( LogTrackingRequestBlock::$log_called );
	}

	public function test__error_log_when_not_suppressed(): void {
		$_SERVER['HTTP_TRUE_CLIENT_IP'] = '1.1.1.1';

		LogTrackingRequestBlock::toggle_logging( true );
		LogTrackingRequestBlock::ip( '1.1.1.1' );

		self::assertTrue( LogTrackingRequestBlock::$log_called );
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

	public function test_ua_partial_match() {
		// Test that a partial match of the user agent string blocks bad site.
		$_SERVER['HTTP_USER_AGENT'] = 'WordPress/6.1.1; https://www.BadSite.com'; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__
		$actual                     = VIP_Request_Block::ua_partial_match( 'https://www.BadSite.com' );
		self::assertTrue( $actual, 'Expected request to be blocked based on partial User Agent string match.' );

		// Test that allowed user agent string is not blocked.
		$_SERVER['HTTP_USER_AGENT'] = 'WordPress/6.1.1; https://www.example.com'; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__
		$actual                     = VIP_Request_Block::ua_partial_match( 'https://www.BadSite.com' );
		self::assertFalse( $actual, 'Expected request to be allowed.' );
	}
}
