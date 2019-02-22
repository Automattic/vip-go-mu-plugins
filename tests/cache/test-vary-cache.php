<?php

namespace Automattic\VIP\Cache;

use WP_Error;

class Vary_Cache_Test extends \WP_UnitTestCase {
	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once( __DIR__ . '/../../cache/class-vary-cache.php' );
	}

	public function setUp() {
		parent::setUp();

		$this->original_COOKIE = $_COOKIE;

		Vary_Cache::load();
	}

	public function tearDown() {
		Vary_Cache::unload();

		$_COOKIE = $this->original_COOKIE;

		parent::tearDown();
	}

	/**
	 * Helper function for accessing protected methods.
	 */
	protected static function get_method( $name ) {
		$class = new \ReflectionClass( __NAMESPACE__ . '\Vary_Cache' );
		$method = $class->getMethod( $name );
		$method->setAccessible( true );
		return $method;
	}

	public function get_test_data__is_user_in_group_segment() {
		return [
			'group-not-defined' => [
				[],
				[],
				'dev-group',
				'yes',
				false,
			],

			'user-not-in-group' => [
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

			'user-in-group-with-empty-segment' => [
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

			'user-in-group-but-different-segment' => [
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

			'user-in-group-and-same-segment' => [
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

			'user-in-group-and-segment-with-zero-value' => [
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
			'group-not-defined' => [
				[],
				[],
				'dev-group',
				false,
			],
			'user-not-in-group' => [
				[
					'vip-go-seg' => 'design-group_--_yes',
				],
				[
					'design-group',
				],
				'dev-group',
				false,
			],
			'user-in-group' => [
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
			'user-not-yet-assigned' => [
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
		Vary_Cache::parse_group_cookie();

		$actual_result = Vary_Cache::is_user_in_group_segment( $test_group, $test_value );

		$this->assertEquals( $expected_result, $actual_result );
	}

	/**
	 * @dataProvider get_test_data__is_user_in_group
	 */
	public function test__is_user_in_group( $initial_cookie, $initial_groups, $test_group, $expected_result ) {
		$_COOKIE = $initial_cookie;
		Vary_Cache::register_groups( $initial_groups );
		Vary_Cache::parse_group_cookie();

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
			'dev-group' => '',
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
			'dev-group' => '',
			'design-group' => '',
		];

		Vary_Cache::register_groups( [ 'dev-group' ] );
		Vary_Cache::register_groups( [ 'design-group' ] );

		$this->assertEquals( $expected_groups, Vary_Cache::get_groups(), 'Multiple register_groups did not result in expected groups' );
	}

	public function get_test_data__register_groups_invalid() {
		return [
			'invalid-group-array' => [
				[ 'dev-group', 'dev-group---__' ],
				'invalid_vary_group_name',
			],
			'invalid-group-name' => [
				[ 'dev-group---__' ],
				'invalid_vary_group_name',
			],
		];
	}

	/**
	 * @dataProvider get_test_data__register_groups_invalid
	 */
	public function test__register_groups__invalid( $invalid_groups ) {
		$this->expectException( \PHPUnit_Framework_Error_Warning::class );
		$actual_result = Vary_Cache::register_groups( $invalid_groups );

		$this->assertFalse( $actual_result, 'Invalid register_groups call did not return false' );
		$this->assertEquals( [], Vary_Cache::get_groups(), 'Registered groups was not empty.' );
	}

	public function get_test_data__set_group_for_user_valid() {
		return [
			'valid-group-' => [
				'dev-group',
				'yes',
				true,
			],
		];
	}

	public function get_test_data__set_group_for_user_invalid() {
		return [
			'invalid-group-name-group-separator' => [
				'dev-group---__',
				'yes',
				'invalid_vary_group_name',
			],
			'invalid-group-segment-group-separator' => [
				'dev-group',
				'yes---__',
				'invalid_vary_group_segment',
			],
			'invalid-group-name-value-separator' => [
				'dev-group_--_',
				'yes',
				'invalid_vary_group_name',
			],
			'invalid-group-segment-value-separator' => [
				'dev-group',
				'yes_--_',
				'invalid_vary_group_segment',
			],
			'invalid-group-name-value-character' => [
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

	/**
	 * @dataProvider get_test_data__set_group_for_user_valid
	 */
	public function test__set_group_for_user_valid( $group, $value, $expected_result ) {
		$this->markTestSkipped('Skip for now until cookie is set in hook');
		$actual_result  = Vary_Cache::set_group_for_user( $group, $value );

		$this->assertEquals( $expected_result, $actual_result );
	}

	/**
	 * @dataProvider get_test_data__set_group_for_user_invalid
	 */
	public function test__set_group_for_user_invalid( $group, $value, $expected_error_code ) {
		$actual_result  = Vary_Cache::set_group_for_user( $group, $value );

		$this->assertWPError( $actual_result, 'Not WP_Error object' );

		$actual_error_code = $actual_result->get_error_code();
		$this->assertEquals( $expected_error_code, $actual_error_code, 'Incorrect error code' );
	}

	/**
	 */
	public function test__enable_encryption_invalid() {
		$this->markTestSkipped('Skip for now until PHPUnit is updated in Travis');
		$this->expectException( \PHPUnit_Framework_Error_Error::class );
		$actual_result = Vary_Cache::enable_encryption( );
		$this->assertNull( $actual_result );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__enable_encryption_invalid_empty_constants() {
		$this->markTestSkipped('Skip for now until PHPUnit is updated in Travis');
		$this->expectException( \PHPUnit_Framework_Error_Error::class );

		define( 'VIP_GO_AUTH_COOKIE_KEY', '' );
		define( 'VIP_GO_AUTH_COOKIE_IV', '');

		$actual_result = Vary_Cache::enable_encryption( );
		$this->assertNull( $actual_result );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__enable_encryption_true_valid() {

		define( 'VIP_GO_AUTH_COOKIE_KEY', 'abc' );
		define( 'VIP_GO_AUTH_COOKIE_IV', '123');

		$actual_result = Vary_Cache::enable_encryption( );
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
		$get_validate_cookie_value_method = self::get_method( 'validate_cookie_value' );

		$actual_result = $get_validate_cookie_value_method->invokeArgs(null, [
			$value
		] );
		$this->assertWPError( $actual_result, 'Not WP_Error object' );

		$actual_error_code = $actual_result->get_error_code();
		$this->assertEquals( $expected_error_code, $actual_error_code, 'Incorrect error code' );
	}

	public function test__validate_cookie_value_valid( ) {

		$get_validate_cookie_value_method = self::get_method( 'validate_cookie_value' );

		$actual_result = $get_validate_cookie_value_method->invokeArgs(null, [
			'dev-group'
		] );
		$this->assertTrue( $actual_result );

	}


}
