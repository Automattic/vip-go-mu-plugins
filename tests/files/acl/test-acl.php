<?php

namespace Automattic\VIP\Files\Acl;

use WP_Error;

class VIP_Files_Acl_Test extends \WP_UnitTestCase {
	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once( __DIR__ . '/../../../files/acl/acl.php' );
	}

	public function test__maybe_load_restrictions__no_constant_and_no_options() {
		// no setup

		maybe_load_restrictions();

		$this->assertEquals( false, has_filter( 'vip_files_acl_file_visibility' ) );
	}

	public function test__maybe_load_restrictions__no_constant_and_with_one_option() {
		update_option( 'vip_files_acl_restrict_all_enabled', 1 );

		$this->expectException( \PHPUnit\Framework\Error\Warning::class );
		$this->expectExceptionMessage( 'File ACL restrictions are enabled without server configs' );

		maybe_load_restrictions();

		$this->assertEquals( false, has_filter( 'vip_files_acl_file_visibility' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__maybe_load_restrictions__constant_and_restrict_all_option() {
		define( 'VIP_FILES_ACL_ENABLED', true );
		update_option( 'vip_files_acl_restrict_all_enabled', 1 );

		maybe_load_restrictions();

		$this->assertEquals( 10, has_filter( 'vip_files_acl_file_visibility', 'Automattic\VIP\Files\Acl\Restrict_All_Files\check_file_visibility' ), 'File visibility filter has no callbacks attached' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__maybe_load_restrictions__constant_and_restrict_unpublished_option() {
		define( 'VIP_FILES_ACL_ENABLED', true );
		update_option( 'vip_files_acl_restrict_unpublished_enabled', 1 );

		maybe_load_restrictions();

		$this->assertEquals( 10, has_filter( 'vip_files_acl_file_visibility', 'Automattic\VIP\Files\Acl\Restrict_Unpublished_Files\check_file_visibility' ), 'File visibility filter has no callbacks attached' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__maybe_load_restrictions__constant_and_restrict_all_option_false() {
		define( 'VIP_FILES_ACL_ENABLED', true );
		update_option( 'vip_files_acl_restrict_all_enabled', false );

		maybe_load_restrictions();

		$this->assertEquals( false, has_filter( 'vip_files_acl_file_visibility' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__maybe_load_restrictions__constant_and_restrict_unpublished_option_false() {
		define( 'VIP_FILES_ACL_ENABLED', true );
		update_option( 'vip_files_acl_restrict_unpublished_enabled', false );

		maybe_load_restrictions();

		$this->assertEquals( false, has_filter( 'vip_files_acl_file_visibility' ) );
	}

	public function test__get_option_as_bool__option_not_exists() {
		$actual_value = get_option_as_bool( 'my_test_get_option_as_bool_option_not_exists' );

		$this->assertEquals( false, $actual_value );
	}

	public function data_provider__get_option_as_bool__option_exists() {
		return [
			// true
			'bool true' => [
				true,
				true,
			],
			'string true' => [
				'true',
				true,
			],
			'string yes' => [
				'yes',
				true,
			],
			'int 1' => [
				1,
				true,
			],
			'string 1' => [
				'1',
				true,
			],

			// false
			'bool false' => [
				false,
				false,
			],
			'string false' => [
				'false',
				false,
			],
			'other number' => [
				'1231412312',
				false,
			],
			'other string' => [
				'awdasnasd',
				false,
			],
		];
	}

	/**
	 * @dataProvider data_provider__get_option_as_bool__option_exists
	 */
	public function test__get_option_as_bool__option_exists( $option_value, $expected_value ) {
		update_option( 'my_test_get_option_as_bool_option', $option_value );

		$actual_value = get_option_as_bool( 'my_test_get_option_as_bool_option' );

		$this->assertEquals( $expected_value, $actual_value );
	}

	public function data_provider__send_visibility_headers() {
		return [
			'public-file' => [
				'FILE_IS_PUBLIC',
				'/wp-content/uploads/public.jpg',
				202,
				'false',
			],

			'private-and-allowed-file' => [
				'FILE_IS_PRIVATE_AND_ALLOWED',
				'/wp-content/uploads/allowed.jpg',
				202,
				'true',
			],

			'private-and-denied-file' => [
				'FILE_IS_PRIVATE_AND_DENIED',
				'/wp-content/uploads/denied.jpg',
				403,
				'true',
			],
		];
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @dataProvider data_provider__send_visibility_headers
	 */
	public function test__send_visibility_headers( $file_visibility, $file_path, $expected_status_code, $private_header_value ) {
		send_visibility_headers( $file_visibility, $file_path );

		$this->assertEquals( $expected_status_code, http_response_code(), 'Status code does not match expected' );

		$this->assertContains( sprintf( 'X-Private: %s', $private_header_value ), xdebug_get_headers(), 'Sent headers do not include X-Private header or its value is unexpected' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__send_visibility_headers__invalid_visibility() {
		define( 'NOT_A_VISIBILITY', 'NOT_A_VISIBILITY' );

		$this->expectException( \PHPUnit\Framework\Error\Warning::class );
		$this->expectExceptionMessage( 'Invalid file visibility (NOT_A_VISIBILITY) ACL set for /wp-content/uploads/invalid.jpg' );

		send_visibility_headers( NOT_A_VISIBILITY, '/wp-content/uploads/invalid.jpg' );

		$this->assertEquals( 500, http_response_code(), 'Status code does not match expected' );

		$this->assertNotContains( 'X-Private: true', xdebug_get_headers(), 'Sent headers include X-Private: true header but should not.' );
		$this->assertNotContains( 'X-Private: false', xdebug_get_headers(), 'Sent headers include X-Private:false header but should not.' );
	}
}
