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
		VIP_Request_Block::toggle_logging( true );
		parent::tearDown();
	}


	/**
	 * Exercise the native HTTP denial path in a separate PHP server process (`php -S`), so the
	 * headers and exit never touch this process.
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

		$response_stream  = false;
		$response_context = stream_context_create( [ 'http' => [ 'ignore_errors' => true ] ] );
		try {
			$this->assertIsResource( $server );
			for ( $attempt = 0; $attempt < 40 && false === $response_stream; $attempt++ ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Connection attempts wait for the temporary local test server.
				$response_stream = @fopen( 'http://127.0.0.1:' . $port . '/', 'rb', false, $response_context );
				if ( false === $response_stream ) {
					usleep( 25000 );
				}
			}

			$this->assertIsResource( $response_stream );
			$stream_metadata  = stream_get_meta_data( $response_stream );
			$response_headers = $stream_metadata['wrapper_data'] ?? [];
			$response         = stream_get_contents( $response_stream );
			fclose( $response_stream );
			$response_stream = false;
			$this->assertSame( '', $response );
			$this->assertSame( 403, isset( $response_headers[0] ) ? (int) substr( $response_headers[0], 9, 3 ) : 0 );
			$this->assertContains( 'Cache-Control: no-cache, must-revalidate, max-age=0', $response_headers );
			$this->assertContains( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT', $response_headers );
			$this->assertStringNotContainsString( 'BLOCK_DID_NOT_EXIT', $response );
		} finally {
			if ( is_resource( $response_stream ) ) {
				fclose( $response_stream );
			}
			if ( is_resource( $server ) ) {
				proc_terminate( $server );
				proc_close( $server );
			}
			unlink( $script_path );
			unlink( $server_log );
		}
		// phpcs:enable WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_tempnam, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents, WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
	}

	/**
	 * @dataProvider data_logging
	 */
	public function test__error_log_unless_suppressed( bool $should_log ): void {
		$_SERVER['HTTP_TRUE_CLIENT_IP'] = '1.1.1.1';

		LogTrackingRequestBlock::toggle_logging( $should_log );
		LogTrackingRequestBlock::ip( '1.1.1.1' );

		self::assertSame( $should_log, LogTrackingRequestBlock::$log_called );
	}

	public function data_logging(): array {
		return [
			'logging enabled'    => [ true ],
			'logging suppressed' => [ false ],
		];
	}

	/**
	 * @dataProvider data_ip
	 */
	public function test_ip( array $headers, string $block, bool $expected ): void {
		foreach ( $headers as $header => $value ) {
			$_SERVER[ $header ] = $value;
		}

		self::assertSame( $expected, VIP_Request_Block::ip( $block ) );
	}

	public function data_ip(): iterable {
		$xff = [ 'HTTP_X_FORWARDED_FOR' => '1.1.1.1, 8.8.8.8' ];

		return [
			'IP not present'                              => [ [ 'HTTP_TRUE_CLIENT_IP' => '4.4.4.4' ] + $xff, '2.2.2.2', false ],
			'invalid IP'                                  => [ $xff, '1', false ],
			'true-client-ip'                              => [ [ 'HTTP_TRUE_CLIENT_IP' => '4.4.4.4' ], '4.4.4.4', true ],
			'first x-forwarded-for IP'                    => [ $xff, '1.1.1.1', true ],
			'second x-forwarded-for IP'                   => [ $xff, '8.8.8.8', true ],
			'partial x-forwarded-for match'               => [ [ 'HTTP_X_FORWARDED_FOR' => '11.1.1.11, 8.8.8.8' ], '1.1.1.1', false ],
			'cf-connecting-ip'                            => [ [ 'HTTP_CF_CONNECTING_IP' => '5.5.5.5' ], '5.5.5.5', true ],
			'true-client-ip over cf-connecting-ip'        => [
				[
					'HTTP_TRUE_CLIENT_IP'   => '4.4.4.4',
					'HTTP_CF_CONNECTING_IP' => '5.5.5.5',
				],
				'4.4.4.4',
				true,
			],
			// HTTP_TRUE_CLIENT_IP takes precedence, so cf-connecting-ip is never checked.
			'cf-connecting-ip shadowed by true-client-ip' => [
				[
					'HTTP_TRUE_CLIENT_IP'   => '4.4.4.4',
					'HTTP_CF_CONNECTING_IP' => '5.5.5.5',
				],
				'5.5.5.5',
				false,
			],
			'cf-connecting-ip over x-forwarded-for'       => [ [ 'HTTP_CF_CONNECTING_IP' => '5.5.5.5' ] + $xff, '5.5.5.5', true ],
			'x-forwarded-for still checked with cf-connecting-ip' => [ [ 'HTTP_CF_CONNECTING_IP' => '5.5.5.5' ] + $xff, '1.1.1.1', true ],
			'IPv4-mapped IPv6 true-client-ip'             => [ [ 'HTTP_TRUE_CLIENT_IP' => '::ffff:127.0.0.1' ], '::FFFF:127.0.0.1', true ],
			'IPv4-mapped IPv6 x-forwarded-for'            => [ [ 'HTTP_X_FORWARDED_FOR' => '::ffff:127.0.0.1' ], '::FFFF:127.0.0.1', true ],
			'compressed IPv6 true-client-ip'              => [ [ 'HTTP_TRUE_CLIENT_IP' => '2001:4860:4860::8844' ], '2001:4860:4860:0000:0000:0000:0000:8844', true ],
			'unpadded IPv6 true-client-ip'                => [ [ 'HTTP_TRUE_CLIENT_IP' => '2001:4860:4860:0:0:0:0:8844' ], '2001:4860:4860:0000:0000:0000:0000:8844', true ],
			'compressed IPv6 x-forwarded-for'             => [ [ 'HTTP_X_FORWARDED_FOR' => '2001:4860:4860::8844' ], '2001:4860:4860:0000:0000:0000:0000:8844', true ],
			'unpadded IPv6 x-forwarded-for'               => [ [ 'HTTP_X_FORWARDED_FOR' => '2001:4860:4860:0:0:0:0:8844' ], '2001:4860:4860:0000:0000:0000:0000:8844', true ],
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
