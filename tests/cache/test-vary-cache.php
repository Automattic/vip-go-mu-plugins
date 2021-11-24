<?php

namespace Automattic\VIP\Cache;

use Automattic\Test\Constant_Mocker;
use WP_UnitTestCase;
use Yoast\PHPUnitPolyfills\Polyfills\ExpectPHPException;

require_once __DIR__ . '/mock-header.php';
require_once __DIR__ . '/../../cache/class-vary-cache.php';

// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE

class Vary_Cache_Test extends WP_UnitTestCase {
	use ExpectPHPException;

	private $original_cookie;
	private $original_server;

	public function setUp(): void {
		parent::setUp();

		$this->original_cookie = $_COOKIE;
		$this->original_server = $_SERVER;

		header_remove();

		Vary_Cache::load();
		Constant_Mocker::clear();
	}

	public function tearDown(): void {
		Vary_Cache::unload();

		$_COOKIE = $this->original_cookie;
		$_SERVER = $this->original_server;

		parent::tearDown();
	}

	/**
	 * Helper function for accessing protected methods.
	 */
	protected static function get_vary_cache_method( $name ) {
		$class  = new \ReflectionClass( __NAMESPACE__ . '\Vary_Cache' );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );
		return $method;
	}

	/**
	 * Helper function for accessing protected properties.
	 */
	protected static function get_vary_cache_property( $name ) {
		$class    = new \ReflectionClass( __NAMESPACE__ . '\Vary_Cache' );
		$property = $class->getProperty( $name );
		$property->setAccessible( true );
		return $property->getValue();
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

	public function test__register_group() {
		$expected_groups = [
			'dev-group' => '',
		];

		$actual_result = Vary_Cache::register_group( 'dev-group' );

		$this->assertTrue( $actual_result, 'register_group returned false' );
		$this->assertEquals( $expected_groups, Vary_Cache::get_groups() );
	}

	public function test__register_groups__valid() {
		$expected_groups = [
			'dev-group'    => '',
			'design-group' => '',
		];

		$actual_result = Vary_Cache::register_groups( [
			'dev-group',
			'design-group',
		] );

		$this->assertTrue( $actual_result, 'Valid register_groups call did not return true' );
		$this->assertEquals( $expected_groups, Vary_Cache::get_groups(), 'Registered groups do not match expected.' );
	}

	public function test__register_groups__multiple_calls() {
		$expected_groups = [
			'dev-group'    => '',
			'design-group' => '',
		];

		Vary_Cache::register_groups( [ 'dev-group' ] );
		Vary_Cache::register_groups( [ 'design-group' ] );

		$this->assertEquals( $expected_groups, Vary_Cache::get_groups(), 'Multiple register_groups did not result in expected groups' );
	}

	public function test__register_groups__did_send_headers() {
		do_action( 'send_headers' );

		$this->expectWarning();

		$actual_result = Vary_Cache::register_groups( [
			'dev-group',
			'design-group',
		] );

		$this->assertFalse( $actual_result, 'register_groups after send_headers did not return false' );
		$this->assertEquals( [], Vary_Cache::get_groups(), 'Registered groups are not empty.' );
	}

	public function get_test_data__register_groups_invalid() {
		return [
			'invalid-group-array' => [
				[ 'dev-group', 'dev-group---__' ],
				'invalid_vary_group_name',
			],
			'invalid-group-name'  => [
				[ 'dev-group---__' ],
				'invalid_vary_group_name',
			],
		];
	}

	/**
	 * @dataProvider get_test_data__register_groups_invalid
	 */
	public function test__register_groups__invalid( $invalid_groups ) {
		$this->expectWarning();
		$actual_result = Vary_Cache::register_groups( $invalid_groups );

		$this->assertFalse( $actual_result, 'Invalid register_groups call did not return false' );
		$this->assertEquals( [], Vary_Cache::get_groups(), 'Registered groups was not empty.' );
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

		];
	}

	public function test__set_group_for_user__valid() {

		Vary_Cache::register_group( 'dev-group' );

		$actual_result = Vary_Cache::set_group_for_user( 'dev-group', 'yep' );

		$this->assertTrue( $actual_result, 'Return value was not true' );

		$this->assertEquals( [ 'dev-group' => 'yep' ], Vary_Cache::get_groups(), 'Groups did not match expected value' );

		$this->assertTrue( self::get_vary_cache_property( 'should_update_group_cookie' ), 'Did not update group cookie' );

		// Verify cookie actions were taken
		add_action( 'vip_vary_cache_did_send_headers', function( $sent_vary, $sent_cookie ) {
			$this->assertTrue( $sent_vary, 'Vary was not sent' );
			$this->assertTrue( $sent_cookie, 'Cookie was not sent' );
		}, 10, 2 );

		// Trigger headers to verify assertions
		do_action( 'send_headers' );

		$this->assertEquals( 1, did_action( 'vip_vary_cache_did_send_headers' ) );
	}

	public function test__set_group_for_user_group_not_registered() {

		$expected_error_code = 'invalid_vary_group_notregistered';

		$actual_result = Vary_Cache::set_group_for_user( 'dev-group', 'yes' );

		$this->assertWPError( $actual_result, 'Not WP_Error object' );

		$actual_error_code = $actual_result->get_error_code();
		$this->assertEquals( $expected_error_code, $actual_error_code, 'Incorrect error code' );
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

	public function test__set_group_for_user__did_send_headers() {
		do_action( 'send_headers' );

		$expected_error_code = 'did_send_headers';

		$actual_result = Vary_Cache::set_group_for_user( 'group', 'segment' );

		$this->assertWPError( $actual_result, 'Not WP_Error object' );

		$actual_error_code = $actual_result->get_error_code();
		$this->assertEquals( $expected_error_code, $actual_error_code, 'Incorrect error code' );
	}

	public function test__enable_encryption_invalid() {
		$this->markTestSkipped( 'Skip for now until PHPUnit is updated in Travis' );
		$this->expectError();
		$actual_result = Vary_Cache::enable_encryption();
		$this->assertNull( $actual_result );
	}

	public function test__enable_encryption_invalid_empty_constants() {
		$this->markTestSkipped( 'Skip for now until PHPUnit is updated in Travis' );
		$this->expectError();

		Constant_Mocker::define( 'VIP_GO_AUTH_COOKIE_KEY', '' );
		Constant_Mocker::define( 'VIP_GO_AUTH_COOKIE_IV', '' );

		$actual_result = Vary_Cache::enable_encryption();
		$this->assertNull( $actual_result );
	}

	public function test__enable_encryption_true_valid() {
		Constant_Mocker::define( 'VIP_GO_AUTH_COOKIE_KEY', 'abc' );
		Constant_Mocker::define( 'VIP_GO_AUTH_COOKIE_IV', '123' );

		$actual_result = Vary_Cache::enable_encryption();
		$this->assertNull( $actual_result );
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
		$get_validate_cookie_value_method = self::get_vary_cache_method( 'validate_cookie_value' );

		$actual_result = $get_validate_cookie_value_method->invokeArgs(null, [
			$value,
		] );

		$this->assertWPError( $actual_result, 'Not WP_Error object' );

		$actual_error_code = $actual_result->get_error_code();
		$this->assertEquals( $expected_error_code, $actual_error_code, 'Incorrect error code' );
	}

	public function test__validate_cookie_value_valid() {
		$get_validate_cookie_value_method = self::get_vary_cache_method( 'validate_cookie_value' );

		$actual_result = $get_validate_cookie_value_method->invokeArgs(null, [
			'dev-group',
		] );

		$this->assertTrue( $actual_result );
	}

	public function test__send_vary_headers__sent_for_group() {
		Vary_Cache::register_group( 'dev-group' );

		do_action( 'send_headers' );

		$headers = headers_list();
		self::assertIsArray( $headers );
		self::assertContains( 'Vary: X-VIP-Go-Segmentation', $headers, '', true );
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

	public function test__set_nocache_for_user__did_send_headers() {
		do_action( 'send_headers' );

		$actual_result = Vary_Cache::set_nocache_for_user();

		$this->assertWPError( $actual_result, 'Not WP_Error object' );
		$this->assertEquals( 'did_send_headers', $actual_result->get_error_code(), 'Incorrect error code' );
	}

	public function test__set_nocache_for_user() {
		$actual_result = Vary_Cache::set_nocache_for_user();

		$this->assertTrue( $actual_result, 'Result was not true' );

		$this->assertTrue( self::get_vary_cache_property( 'is_user_in_nocache' ), 'Did not switch on nocache mode' );
		$this->assertTrue( self::get_vary_cache_property( 'should_update_nocache_cookie' ), 'Did not update nocache cookie' );

		// Verify cookie actions were taken
		add_action( 'vip_vary_cache_did_send_headers', function( $sent_vary, $sent_cookie ) {
			$this->assertFalse( $sent_vary, 'Vary should not be sent' );
			$this->assertTrue( $sent_cookie, 'Cookie was not sent' );
		}, 10, 2 );

		// Trigger headers to verify assertions
		do_action( 'send_headers' );

		$this->assertEquals( 1, did_action( 'vip_vary_cache_did_send_headers' ) );
	}

	public function test__remove_nocache_for_user__did_send_headers() {
		do_action( 'send_headers' );

		$actual_result = Vary_Cache::remove_nocache_for_user();

		$this->assertWPError( $actual_result, 'Not WP_Error object' );
		$this->assertEquals( 'did_send_headers', $actual_result->get_error_code(), 'Incorrect error code' );
	}

	public function test__remove_nocache_for_user() {
		$actual_result = Vary_Cache::remove_nocache_for_user();

		$this->assertTrue( $actual_result, 'Result was not true' );

		$this->assertFalse( self::get_vary_cache_property( 'is_user_in_nocache' ), 'Did not switch off nocache mode' );
		$this->assertTrue( self::get_vary_cache_property( 'should_update_nocache_cookie' ), 'Did not update nocache cookie' );

		// Verify cookie actions were taken
		add_action( 'vip_vary_cache_did_send_headers', function( $sent_vary, $sent_cookie ) {
			$this->assertFalse( $sent_vary, 'Vary should not be sent' );
			$this->assertTrue( $sent_cookie, 'Cookie was not sent' );
		}, 10, 2 );

		// Trigger headers to verify assertions
		do_action( 'send_headers' );

		$this->assertEquals( 1, did_action( 'vip_vary_cache_did_send_headers' ) );
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
		$get_stringify_groups_method = self::get_vary_cache_method( 'stringify_groups' );
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
		$get_parse_group_cookie_method = self::get_vary_cache_method( 'parse_group_cookie' );
		if ( ! empty( $secrets ) ) {
			Constant_Mocker::define( 'VIP_GO_AUTH_COOKIE_KEY', $secrets['key'] );
			Constant_Mocker::define( 'VIP_GO_AUTH_COOKIE_IV', $secrets['iv'] );
			Constant_Mocker::define( 'VIP_GO_APP_ID', $secrets['siteid'] );
			Vary_Cache::enable_encryption();
		}
		$get_parse_group_cookie_method->invokeArgs( null, [] );
		$this->assertEquals( $expected_result, Vary_Cache::get_groups() );
	}
}
