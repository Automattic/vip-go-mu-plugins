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
		unset( $_SERVER['HTTP_TRUE_CLIENT_IP'], $_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_USER_AGENT'] );
		parent::tearDown();
	}


	/**
	 * Exercise the native HTTP denial path in a separate PHP server process.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_native_block_sends_forbidden_no_cache_and_exits() {
		$socket = stream_socket_server( 'tcp://127.0.0.1:0', $error_code, $error_message );
		$this->assertNotFalse( $socket, $error_message );
		$address = stream_socket_get_name( $socket, false );
		fclose( $socket );
		$port = (int) substr( strrchr( $address, ':' ), 1 );

		// phpcs:disable WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_tempnam, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink -- Temporary files isolate the real response path.
		$script_path = tempnam( get_temp_dir(), 'vip-request-block-' );
		$server_log  = tempnam( get_temp_dir(), 'vip-request-block-log-' );
		$source      = sprintf(
			"<?php\nrequire %s;\nVIP_Request_Block::toggle_logging( false );\n\$_SERVER['HTTP_TRUE_CLIENT_IP'] = '203.0.113.9';\nVIP_Request_Block::ip( '203.0.113.9' );\necho 'BLOCK_DID_NOT_EXIT';\n",
			wp_json_encode( dirname( __DIR__, 2 ) . '/lib/class-vip-request-block.php', JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR )
		);
		file_put_contents( $script_path, $source );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Spawn the isolated native HTTP request.
		$server = proc_open(
			[ PHP_BINARY, '-S', '127.0.0.1:' . $port, $script_path ],
			// phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound -- Descriptor tuples are short and fixed.
			[
				0 => [ 'pipe', 'r' ],
				1 => [ 'file', $server_log, 'a' ],
				2 => [ 'file', $server_log, 'a' ],
			],
			$pipes
		);
		if ( is_resource( $server ) ) {
			fclose( $pipes[0] );
		}

		try {
			$this->assertIsResource( $server );
			$response = false;
			for ( $attempt = 0; $attempt < 40 && false === $response; $attempt++ ) {
					// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsRemoteFile -- Connection attempts wait for the temporary local test server.
				$response = @file_get_contents( 'http://127.0.0.1:' . $port . '/', false, stream_context_create( [ 'http' => [ 'ignore_errors' => true ] ] ) );
				if ( false === $response ) {
					usleep( 25000 );
				}
			}

			$response_headers = function_exists( 'http_get_last_response_headers' ) ? http_get_last_response_headers() : $http_response_header;
			$this->assertSame( '', $response );
			$this->assertSame( 403, isset( $response_headers[0] ) ? (int) substr( $response_headers[0], 9, 3 ) : 0 );
			$this->assertContains( 'Cache-Control: no-cache, must-revalidate, max-age=0', $response_headers );
			$this->assertContains( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT', $response_headers );
			$this->assertStringNotContainsString( 'BLOCK_DID_NOT_EXIT', $response );
		} finally {
			if ( is_resource( $server ) ) {
				proc_terminate( $server );
				proc_close( $server );
			}
			unlink( $script_path );
			unlink( $server_log );
		}
		// phpcs:enable WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_tempnam, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
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

	public function test__true_client_ip_takes_precedence_over_cf_connecting_ip(): void {
		$_SERVER['HTTP_TRUE_CLIENT_IP']   = '4.4.4.4';
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '5.5.5.5';

		$actual = VIP_Request_Block::ip( '4.4.4.4' );
		self::assertTrue( $actual, 'Expected request to be blocked when blocking IP from HTTP_TRUE_CLIENT_IP' );

		$actual = VIP_Request_Block::ip( '5.5.5.5' );
		self::assertFalse( $actual, 'Expected request not to be blocked when IP only in HTTP_CF_CONNECTING_IP, since HTTP_TRUE_CLIENT_IP takes precedence' );
	}

	public function test__cf_connecting_ip_takes_precedence_over_x_forwarded_for(): void {
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '5.5.5.5';
		$_SERVER['HTTP_X_FORWARDED_FOR']  = '1.1.1.1, 8.8.8.8';

		$actual = VIP_Request_Block::ip( '5.5.5.5' );
		self::assertTrue( $actual, 'Expected request to be blocked when blocking IP from HTTP_CF_CONNECTING_IP (takes precedence over HTTP_X_FORWARDED_FOR)' );

		$actual = VIP_Request_Block::ip( '1.1.1.1' );
		self::assertTrue( $actual, 'Expected request to be blocked when blocking IP from HTTP_X_FORWARDED_FOR' );
	}

	public function test__error_raised_when_cf_connecting_ip(): void {
		$_SERVER['HTTP_CF_CONNECTING_IP'] = '5.5.5.5';

		$actual = VIP_Request_Block::ip( '5.5.5.5' );
		self::assertTrue( $actual );
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
