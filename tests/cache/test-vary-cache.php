<?php

namespace Automattic\VIP\Cache;

use Automattic\Test\Constant_Mocker;
use Automattic\Test\Utils\Captures_Errors;
use ErrorException;
use WP_UnitTestCase;

use function Automattic\Test\Utils\get_class_method_as_public;

require_once __DIR__ . '/mock-header.php';

// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE

class Vary_Cache_Test extends WP_UnitTestCase {
	use Captures_Errors;

	private $original_cookie;

	/** @var array[] Arguments of each `vip_vary_cache_did_send_headers` action. */
	private $did_send_headers_calls = [];

	public static function wpSetUpBeforeClass() {
		require_once __DIR__ . '/../../cache/class-vary-cache.php';
		Vary_Cache::unload();
	}

	public function setUp(): void {
		parent::setUp();

		$this->original_cookie = $_COOKIE;

		header_remove();
		Cookie_Recorder::$calls = [];

		Vary_Cache::load();

		$this->did_send_headers_calls = [];
	}

	public function tearDown(): void {
		Cookie_Recorder::$calls = [];

		Vary_Cache::unload();

		$_COOKIE = $this->original_cookie;

		parent::tearDown();
	}

	/**
	 * Record the arguments of each `vip_vary_cache_did_send_headers` action in $did_send_headers_calls.
	 */
	private function record_did_send_headers(): void {
		add_action( 'vip_vary_cache_did_send_headers', function ( $sent_vary, $sent_cookie ) {
			$this->did_send_headers_calls[] = [ $sent_vary, $sent_cookie ];
		}, 10, 2 );
	}

	public function get_test_data__is_user_in_group_segment() {
		return [
			'group-not-defined'                            => [
				[],
				[],
				'dev-group',
				'yes',
				false,
			],

			'user-not-in-group'                            => [
				[
					'vip-go-seg' => 'design-group_--_yes',
				],
				[
					'design-group',
				],
				'dev-group',
				'yes',
				false,
			],

			'user-in-group-with-empty-segment'             => [
				[
					'vip-go-seg' => 'dev-group_--_',
				],
				[
					'dev-group',
				],
				'dev-group',
				'',
				false,
			],

			'user-in-group-segment-but-searching-for-null' => [
				[
					'vip-go-seg' => 'dev-group_--_maybe',
				],
				[
					'dev-group',
				],
				'dev-group',
				null,
				false,
			],

			'user-in-group-but-different-segment'          => [
				[
					'vip-go-seg' => 'dev-group_--_maybe',
				],
				[
					'dev-group',
				],
				'dev-group',
				'yes',
				false,
			],

			'user-in-group-and-same-segment'               => [
				[
					'vip-go-seg' => 'dev-group_--_yes',
				],
				[
					'dev-group',
				],
				'dev-group',
				'yes',
				true,
			],

			'user-in-group-and-segment-with-zero-value'    => [
				[
					'vip-go-seg' => 'dev-group_--_0',
				],
				[
					'dev-group',
				],
				'dev-group',
				'0',
				true,
			],
		];
	}

	public function get_test_data__is_user_in_group() {
		return [
			'group-not-defined'               => [
				[],
				[],
				'dev-group',
				false,
			],
			'user-not-in-group'               => [
				[
					'vip-go-seg' => 'design-group_--_yes',
				],
				[
					'design-group',
				],
				'dev-group',
				false,
			],
			'user-in-group'                   => [
				[
					'vip-go-seg' => 'dev-group_--_yes',
				],
				[
					'dev-group',
				],
				'dev-group',
				true,
			],
			'user-in-group-and-empty-segment' => [
				[
					'vip-go-seg' => 'dev-group_--_',
				],
				[
					'dev-group',
				],
				'dev-group',
				false,
			],
			'user-not-yet-assigned'           => [
				[],
				[
					'dev-group',
				],
				'dev-group',
				false,
			],
		];
	}

	/**
	 * @dataProvider get_test_data__is_user_in_group_segment
	 */
	public function test__is_user_in_group_segment( $initial_cookie, $initial_groups, $test_group, $test_value, $expected_result ) {
		$_COOKIE = $initial_cookie;
		Vary_Cache::register_groups( $initial_groups );
		Vary_Cache::parse_cookies();

		$actual_result = Vary_Cache::is_user_in_group_segment( $test_group, $test_value );

		$this->assertEquals( $expected_result, $actual_result );
	}

	/**
	 * @dataProvider get_test_data__is_user_in_group
	 */
	public function test__is_user_in_group( $initial_cookie, $initial_groups, $test_group, $expected_result ) {
		$_COOKIE = $initial_cookie;
		Vary_Cache::register_groups( $initial_groups );
		Vary_Cache::parse_cookies();

		$actual_result = Vary_Cache::is_user_in_group( $test_group );

		$this->assertEquals( $expected_result, $actual_result );
	}

	public function test__register_group_and_groups() {
		$this->assertTrue( Vary_Cache::register_group( 'dev-group' ), 'register_group returned false' );
		$this->assertEquals( [ 'dev-group' => '' ], Vary_Cache::get_groups() );

		// Later calls add to the registered groups.
		$this->assertTrue( Vary_Cache::register_groups( [ 'design-group', 'qa-group' ] ), 'Valid register_groups call did not return true' );
		$this->assertEquals( [
			'dev-group'    => '',
			'design-group' => '',
			'qa-group'     => '',
		], Vary_Cache::get_groups(), 'Registered groups do not match expected.' );
	}

	public function test__register_groups__did_send_headers() {
		do_action( 'send_headers' );

		[ $result, $warnings ] = $this->capture_errors( fn() => Vary_Cache::register_groups( [
			'dev-group',
			'design-group',
		] ) );

		self::assertFalse( $result );
		self::assertSame( [ 'Failed to register_groups (dev-group, design-group); cannot be called after the `send_headers` hook has fired.' ], $warnings );
		self::assertEmpty( Vary_Cache::get_groups(), 'Registered groups are not empty.' );
	}

	public function get_test_data__register_groups_invalid() {
		return [
			'invalid-group-array' => [
				[ 'dev-group', 'dev-group---__' ],
				[ 'dev-group' => '' ],
			],
			'invalid-group-name'  => [
				[ 'dev-group---__' ],
				[],
			],
		];
	}

	/**
	 * @dataProvider get_test_data__register_groups_invalid
	 */
	public function test__register_groups__invalid( $groups, $expected_groups ) {
		[ $result, $warnings ] = $this->capture_errors( fn() => Vary_Cache::register_groups( $groups ) );

		self::assertTrue( $result );
		self::assertCount( 1, $warnings );
		self::assertStringStartsWith( 'Failed to register group (dev-group---__);', $warnings[0] );
		self::assertEquals( $expected_groups, Vary_Cache::get_groups() );
	}

	public function get_test_data__set_group_for_user_invalid() {
		return [
			'invalid-group-name-group-separator'    => [
				'dev-group---__',
				'yes',
				'invalid_vary_group_name',
			],
			'invalid-group-segment-group-separator' => [
				'dev-group',
				'yes---__',
				'invalid_vary_group_segment',
			],
			'invalid-group-name-value-separator'    => [
				'dev-group_--_',
				'yes',
				'invalid_vary_group_name',
			],
			'invalid-group-segment-value-separator' => [
				'dev-group',
				'yes_--_',
				'invalid_vary_group_segment',
			],
			'invalid-group-name-value-character'    => [
				'dev-group%',
				'yes',
				'invalid_vary_group_name',
			],
			'invalid-group-segment-value-character' => [
				'dev-group',
				'yes%',
				'invalid_vary_group_segment',
			],
			'group-not-registered'                  => [
				'dev-group',
				'yes',
				'invalid_vary_group_notregistered',
			],
		];
	}

	/**
	 * Verify real group emission, raw encryption and custom cookie scope.
	 *
	 * @dataProvider get_cookie_encoding_cases
	 */
	public function test_group_cookie_parameters( bool $encrypted ): void {
		$path_filter   = static function () {
			return '/segmentation/';
		};
		$domain_filter = static function () {
			return 'cookies.example.org';
		};
		add_filter( 'vip_vary_cache_cookie_path', $path_filter );
		add_filter( 'vip_vary_cache_cookie_domain', $domain_filter );
		if ( $encrypted ) {
			Constant_Mocker::define( 'VIP_GO_AUTH_COOKIE_KEY', '0123456789abcdef' );
			Constant_Mocker::define( 'VIP_GO_AUTH_COOKIE_IV', 'fedcba9876543210' );
			Vary_Cache::enable_encryption();
		}
		Vary_Cache::set_cookie_expiry( HOUR_IN_SECONDS );
		Vary_Cache::register_group( 'dev-group' );
		$this->assertTrue( Vary_Cache::set_group_for_user( 'dev-group', 'yep' ), 'Return value was not true' );
		$this->assertEquals( [ 'dev-group' => 'yep' ], Vary_Cache::get_groups(), 'Groups did not match expected value' );
		$this->record_did_send_headers();
		$before = time();
		try {
			do_action( 'send_headers' );
		} finally {
			remove_filter( 'vip_vary_cache_cookie_path', $path_filter );
			remove_filter( 'vip_vary_cache_cookie_domain', $domain_filter );
		}
		$this->assertSame( [ [ true, true ] ], $this->did_send_headers_calls, 'Vary and cookie were not sent' );
		$this->assertCount( 1, Cookie_Recorder::$calls );
		[ $raw, $name, $value, $expiry, $path, $domain ] = Cookie_Recorder::$calls[0];
		$this->assertTrue( $raw );
		$this->assertSame( $encrypted ? Vary_Cache::COOKIE_AUTH : Vary_Cache::COOKIE_SEGMENT, $name );
		$this->assertGreaterThanOrEqual( $before + HOUR_IN_SECONDS, $expiry );
		$this->assertLessThanOrEqual( time() + HOUR_IN_SECONDS, $expiry );
		$this->assertSame( '/segmentation/', $path );
		$this->assertSame( 'cookies.example.org', $domain );
		if ( $encrypted ) {
			$this->assertStringStartsWith( '123.', $value );
			$payload = substr( $value, 4 );
			$this->assertSame( $payload, base64_encode( base64_decode( $payload, true ) ) );
			$value = get_class_method_as_public( Vary_Cache::class, 'decrypt_cookie_value' )->invoke( null, $payload );
		}
		$this->assertSame( 'vc-v1__dev-group_--_yep', $value );
	}

	/**
	 * Cover both plaintext and encrypted group cookies.
	 */
	public function get_cookie_encoding_cases(): array {
		return [
			'plain'     => [ false ],
			'encrypted' => [ true ],
		];
	}

	/**
	 * Verify no-cache creation and deletion reach their respective writers.
	 */
	public function test_nocache_cookie_parameters(): void {
		$this->assertTrue( Vary_Cache::set_nocache_for_user(), 'Result was not true' );
		$this->assertTrue( Vary_Cache::is_user_in_nocache(), 'Did not switch on nocache mode' );
		$this->record_did_send_headers();
		$before = time();
		do_action( 'send_headers' );
		$this->assertSame( [ [ false, true ] ], $this->did_send_headers_calls, 'Only the cookie should be sent' );
		$this->assertCount( 1, Cookie_Recorder::$calls );
		[ $raw, $name, $value, $expiry, $path, $domain ] = Cookie_Recorder::$calls[0];
		$this->assertTrue( $raw );
		$this->assertSame( Vary_Cache::COOKIE_NOCACHE, $name );
		$this->assertSame( 1, $value );
		$this->assertGreaterThanOrEqual( $before + MONTH_IN_SECONDS, $expiry );
		$this->assertLessThanOrEqual( time() + MONTH_IN_SECONDS, $expiry );
		$this->assertSame( COOKIEPATH, $path );
		$this->assertSame( (string) COOKIE_DOMAIN, $domain );

		Vary_Cache::unload();
		Vary_Cache::load();
		Cookie_Recorder::$calls       = [];
		$this->did_send_headers_calls = [];
		$this->assertTrue( Vary_Cache::remove_nocache_for_user(), 'Result was not true' );
		$this->assertFalse( Vary_Cache::is_user_in_nocache(), 'Did not switch off nocache mode' );
		$before = time();
		do_action( 'send_headers' );
		$this->assertSame( [ [ false, true ] ], $this->did_send_headers_calls, 'Only the cookie should be sent' );
		$this->assertCount( 1, Cookie_Recorder::$calls );
		[ $raw, $name, $value, $expiry, $path, $domain ] = Cookie_Recorder::$calls[0];
		$this->assertFalse( $raw );
		$this->assertSame( Vary_Cache::COOKIE_NOCACHE, $name );
		$this->assertSame( '', $value );
		$this->assertGreaterThanOrEqual( $before - HOUR_IN_SECONDS, $expiry );
		$this->assertLessThanOrEqual( time() - HOUR_IN_SECONDS, $expiry );
		$this->assertSame( '', $path );
		$this->assertSame( '', $domain );
	}

	/**
	 * @dataProvider get_test_data__set_group_for_user_invalid
	 */
	public function test__set_group_for_user_invalid( $group, $value, $expected_error_code ) {
		$actual_result = Vary_Cache::set_group_for_user( $group, $value );

		$this->assertWPError( $actual_result, 'Not WP_Error object' );

		$actual_error_code = $actual_result->get_error_code();
		$this->assertEquals( $expected_error_code, $actual_error_code, 'Incorrect error code' );
	}

	public function get_test_data__did_send_headers() {
		return [
			'set_group_for_user'      => [ fn() => Vary_Cache::set_group_for_user( 'group', 'segment' ) ],
			'set_nocache_for_user'    => [ fn() => Vary_Cache::set_nocache_for_user() ],
			'remove_nocache_for_user' => [ fn() => Vary_Cache::remove_nocache_for_user() ],
		];
	}

	/**
	 * @dataProvider get_test_data__did_send_headers
	 */
	public function test__did_send_headers( callable $set_after_headers ) {
		do_action( 'send_headers' );

		$actual_result = $set_after_headers();

		$this->assertWPError( $actual_result, 'Not WP_Error object' );
		$this->assertEquals( 'did_send_headers', $actual_result->get_error_code(), 'Incorrect error code' );
	}

	public function test__enable_encryption_invalid() {
		$this->assert_enable_encryption_triggers_error();
	}

	public function test__enable_encryption_invalid_empty_constants() {
		Constant_Mocker::define( 'VIP_GO_AUTH_COOKIE_KEY', '' );
		Constant_Mocker::define( 'VIP_GO_AUTH_COOKIE_IV', '' );

		$this->assert_enable_encryption_triggers_error();
	}

	private function assert_enable_encryption_triggers_error(): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		set_error_handler( static function ( int $errno, string $errstr ) {
			if ( E_USER_ERROR === $errno ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI
				throw new ErrorException( $errstr, 0, $errno );
			}

			// PHP 8.4+ also emits a deprecation for passing E_USER_ERROR to trigger_error().
			return true;
		}, E_USER_ERROR | E_DEPRECATED );

		try {
			Vary_Cache::enable_encryption();
			$this->fail( 'Expected enable_encryption() to trigger an E_USER_ERROR' );
		} catch ( ErrorException $e ) {
			$this->assertSame( E_USER_ERROR, $e->getSeverity() );
			$this->assertStringContainsString( 'Cannot enable encryption', $e->getMessage() );
		} finally {
			restore_error_handler();
		}

		$this->assertFalse( Vary_Cache::is_encryption_enabled() );
	}

	public function test__enable_encryption_true_valid() {
		Constant_Mocker::define( 'VIP_GO_AUTH_COOKIE_KEY', 'abc' );
		Constant_Mocker::define( 'VIP_GO_AUTH_COOKIE_IV', '123' );
		$this->assertFalse( Vary_Cache::is_encryption_enabled() );

		Vary_Cache::enable_encryption();

		$this->assertTrue( Vary_Cache::is_encryption_enabled() );
	}

	public function get_test_data__validate_cookie_value_invalid() {
		return [
			'invalid-group-name-group-separator' => [
				'dev-group---__',
				'vary_cache_group_cannot_use_delimiter',
			],
			'invalid-group-name-value-separator' => [
				'dev-group_--_',
				'vary_cache_group_cannot_use_delimiter',
			],
			'invalid-group-name-value-character' => [
				'dev-group%',
				'vary_cache_group_invalid_chars',
			],
		];
	}

	/**
	 * @dataProvider get_test_data__validate_cookie_value_invalid
	 */
	public function test__validate_cookie_values_invalid( $value, $expected_error_code ) {
		$get_validate_cookie_value_method = get_class_method_as_public( Vary_Cache::class, 'validate_cookie_value' );

		$actual_result = $get_validate_cookie_value_method->invokeArgs(null, [
			$value,
		] );

		$this->assertWPError( $actual_result, 'Not WP_Error object' );

		$actual_error_code = $actual_result->get_error_code();
		$this->assertEquals( $expected_error_code, $actual_error_code, 'Incorrect error code' );
	}

	public function test__validate_cookie_value_valid() {
		$get_validate_cookie_value_method = get_class_method_as_public( Vary_Cache::class, 'validate_cookie_value' );

		$actual_result = $get_validate_cookie_value_method->invokeArgs(null, [
			'dev-group',
		] );

		$this->assertTrue( $actual_result );
	}

	public function test__send_vary_headers__dont_override_headers() {
		header( 'Vary: yay' );

		Vary_Cache::register_group( 'dev-group' );

		do_action( 'send_headers' );

		$headers = headers_list();
		self::assertIsArray( $headers );
		self::assertContains( 'Vary: X-VIP-Go-Segmentation', $headers, '', true );
		self::assertContains( 'Vary: yay', $headers, true );
	}

	public function test__send_vary_headers__sent_for_group_with_encryption() {
		Constant_Mocker::define( 'VIP_GO_AUTH_COOKIE_KEY', 'abc' );
		Constant_Mocker::define( 'VIP_GO_AUTH_COOKIE_IV', '123' );
		Vary_Cache::register_group( 'dev-group' );
		Vary_Cache::enable_encryption();

		do_action( 'send_headers' );

		$headers = headers_list();
		self::assertIsArray( $headers );
		self::assertContains( 'Vary: X-VIP-Go-Auth', $headers, '', true );
	}

	public function test__send_vary_headers__not_sent_with_no_groups() {
		do_action( 'send_headers' );

		$headers = headers_list();
		self::assertIsArray( $headers );

		self::assertNotContains( 'Vary: X-VIP-Go-Segmentation', $headers, 'Response should not include Vary: X-VIP-Go-Segmentation header', true );
		self::assertNotContains( 'Vary: X-VIP-Go-Auth', $headers, 'Response should not include Vary: X-VIP-Go-Auth header', true );
	}

	public function get_test_data__stringify_groups() {
		return [
			'values_for_all_groups'           => [
				[
					'dev-group',
					'design-group',
				],
				[
					'dev-group'    => 'yes',
					'design-group' => 'no',
				],
				'vc-v1__design-group_--_no---__dev-group_--_yes',
			],
			'values_for_only_nonempty_groups' => [
				[
					'dev-group',
					'design-group',
				],
				[
					'dev-group' => 'yes',
				],
				'vc-v1__dev-group_--_yes',
			],
			'values_for_all_empty_groups'     => [
				[],
				[],
				'',
			],

		];
	}

	/**
	 * @dataProvider get_test_data__stringify_groups
	 */
	public function test__stringify_groups_valid( $groups, $group_values, $expected_result ) {
		$get_stringify_groups_method = get_class_method_as_public( Vary_Cache::class, 'stringify_groups' );
		Vary_Cache::register_groups( $groups );
		foreach ( $group_values as $key => $value ) {
			Vary_Cache::set_group_for_user( $key, $value );
		}

		$actual_result = $get_stringify_groups_method->invokeArgs( null, [] );

		$this->assertSame( $expected_result, $actual_result );
	}

	public function get_test_data__parse_group_cookies() {
		return [
			'values_regular_group'             => [
				[],
				[
					'vip-go-seg' => 'vc-v1__design-group_--_no---__dev-group_--_yes',
				],
				[],
				[
					'design-group' => 'no',
					'dev-group'    => 'yes',
				],
			],
			'values_encrypted_group_header'    => [
				[
					'key'    => 'abc',
					'iv'     => '1231231231231234',
					'siteid' => 123,
				],
				[],
				[
					'HTTP_X_VIP_GO_AUTH' => 'vc-v1__design-group_--_no---__dev-group_--_yes',
				],
				[
					'design-group' => 'no',
					'dev-group'    => 'yes',
				],
			],
			'values_encrypted_group_no_header' => [
				[
					'key'    => 'abc',
					'iv'     => '1231231231231234',
					'siteid' => 123,
				],
				[
					'vip-go-auth' => '123.VyLXNl8VFvGE4+ZyW1jpbS677cXNgN4owowO0jIOq48LS3ImPe4l2RPUSd3YuD8bLS4UtV4Z6fxFW/E22qvKXaQwPI3fEnZghINwbwaqKhV0jqdovLCVfEIu9SAA4v6I',
				],
				[
					'HTTP_COOKIE' => 'vip-go-auth=123.VyLXNl8VFvGE4+ZyW1jpbS677cXNgN4owowO0jIOq48LS3ImPe4l2RPUSd3YuD8bLS4UtV4Z6fxFW/E22qvKXaQwPI3fEnZghINwbwaqKhV0jqdovLCVfEIu9SAA4v6I;',
				],
				[
					'design-group' => 'no',
					'dev-group'    => 'yes',
				],
			],
			'values_regular_nogroup'           => [
				[],
				[
					'vip-go-seg' => 'vc-v1__',
				],
				[],
				[],
			],
			'values_encrypted_nogroup'         => [
				[
					'key'    => 'abc',
					'iv'     => '1231231231231234',
					'siteid' => 123,
				],
				[],
				[
					'HTTP_X_VIP_GO_AUTH' => 'vc-v1__design-group_--_yes---__dev-group_--_no',
				],
				[
					'design-group' => 'yes',
					'dev-group'    => 'no',
				],
			],
		];
	}

	/**
	 * @dataProvider get_test_data__parse_group_cookies
	 */
	public function test__parse_group_cookie_valid( $secrets, $initial_cookie, $headers, $expected_result ) {
		$_SERVER                       = array_merge( $_SERVER, $headers );
		$_COOKIE                       = $initial_cookie;
		$get_parse_group_cookie_method = get_class_method_as_public( Vary_Cache::class, 'parse_group_cookie' );
		if ( ! empty( $secrets ) ) {
			Constant_Mocker::define( 'VIP_GO_AUTH_COOKIE_KEY', $secrets['key'] );
			Constant_Mocker::define( 'VIP_GO_AUTH_COOKIE_IV', $secrets['iv'] );
			Constant_Mocker::define( 'VIP_GO_APP_ID', $secrets['siteid'] );
			Vary_Cache::enable_encryption();
		}
		$get_parse_group_cookie_method->invokeArgs( null, [] );
		$this->assertEquals( $expected_result, Vary_Cache::get_groups() );
	}

	/**
	 * @ticket 157433-z
	 */
	public function test_parse_group_cookie_malformed(): void {
		$this->expectNotToPerformAssertions();

		try {
			$_COOKIE[ Vary_Cache::COOKIE_SEGMENT ] = sprintf( '%s%s', Vary_Cache::VERSION_PREFIX, 'name' );
			Vary_Cache::parse_cookies();
		} finally {
			$_COOKIE = [];
		}
	}
}
