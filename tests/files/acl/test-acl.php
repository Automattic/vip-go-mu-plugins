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
				false,
			],

			'private-and-allowed-file' => [
				'FILE_IS_PRIVATE_AND_ALLOWED',
				'/wp-content/uploads/allowed.jpg',
				202,
				true,
			],

			'private-and-denied-file' => [
				'FILE_IS_PRIVATE_AND_DENIED',
				'/wp-content/uploads/denied.jpg',
				403,
				true,
			],
		];
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @dataProvider data_provider__send_visibility_headers
	 */
	public function test__send_visibility_headers( $file_visibility, $file_path, $expected_status_code, $should_have_private_header ) {
		send_visibility_headers( $file_visibility, $file_path );

		$this->assertEquals( $expected_status_code, http_response_code(), 'Status code does not match expected' );

		// Not ideal to have a branch in tests, but good enough.
		if ( $should_have_private_header ) {
			$this->assertContains( 'X-Private: true', xdebug_get_headers(), 'Sent headers do not include X-Private header' );
		} else {
			$this->assertNotContains( 'X-Private: true', xdebug_get_headers(), 'Sent headers include the X-Private header' );
		}
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

		$this->assertNotContains( 'X-Private: true', xdebug_get_headers(), 'Sent headers include X-Private header but should not.' );
	}

	public function test__is_valid_path_for_site__always_true_for_not_multisite() {
		if ( is_multisite() ) {
			$this->markTestSkipped();
		}

		$expected_is_allowed = true;

		$file_path = '2021/01/kittens.jpg';

		$actual_is_allowed = is_valid_path_for_site( $file_path );

		$this->assertEquals( $expected_is_allowed, $actual_is_allowed );
	}

	public function test__is_valid_path_for_site__multisite_main_site_can_access_self_path_with_vip_protocol() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		$expected_is_allowed = true;

		add_filter( 'upload_dir', function( $params ) {
			$params['path'] = 'vip:/' . $params['path'];
			$params['basedir'] = 'vip:/' . $params['basedir'];
			return $params;
		} );

		$file_path = '2021/01/kittens.jpg';

		$actual_is_allowed = is_valid_path_for_site( $file_path );

		$this->assertEquals( $expected_is_allowed, $actual_is_allowed );
	}

	public function test__is_valid_path_for_site__multisite_main_site_can_access_self_path() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		$expected_is_allowed = true;

		$file_path = '2021/01/kittens.jpg';

		$actual_is_allowed = is_valid_path_for_site( $file_path );

		$this->assertEquals( $expected_is_allowed, $actual_is_allowed );
	}

	public function test__is_valid_path_for_site__multisite_main_site_can_access_basedir_path() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		// Can access other paths from basedir, as long as they don't contain `/sites/ 
		$expected_is_allowed = true;

		$file_path = 'cache/css/cats.css';

		$actual_is_allowed = is_valid_path_for_site( $file_path );

		$this->assertEquals( $expected_is_allowed, $actual_is_allowed );
	}

	public function test__is_valid_path_for_site__multisite_main_site_cannot_access_subsite_path() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		$expected_is_allowed = false;

		// Get file path for a subsite
		$subsite_id = $this->factory->blog->create();
		$file_path = sprintf( 'sites/%d/2021/01/dogs.gif', $subsite_id );

		// Stay in main site context

		$actual_is_allowed = is_valid_path_for_site( $file_path );

		$this->assertEquals( $expected_is_allowed, $actual_is_allowed );
	}

	public function test__is_valid_path_for_site__multisite_subsite_can_access_self_path() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		$expected_is_allowed = true;

		// Get file path for a subsite
		$subsite_id = $this->factory->blog->create();
		$file_path = sprintf( 'sites/%d/2021/01/hamster.gif', $subsite_id );

		// Run test in subsite context
		switch_to_blog( $subsite_id );

		$actual_is_allowed = is_valid_path_for_site( $file_path );

		$this->assertEquals( $expected_is_allowed, $actual_is_allowed );
	}

	public function test__is_valid_path_for_site__multisite_subsite_cannot_access_main_site_path() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		$expected_is_allowed = false;

		// Get file path for main site
		$file_path = '2021/01/parakeets.gif';

		// Run test in a subsite context
		$subsite_id = $this->factory->blog->create();
		switch_to_blog( $subsite_id );

		$actual_is_allowed = is_valid_path_for_site( $file_path );

		$this->assertEquals( $expected_is_allowed, $actual_is_allowed );
	}

	public function test__is_valid_path_for_site__multisite_subsite_cannot_access_another_subsite_path() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		$expected_is_allowed = false;

		// Create two subsites
		$first_subsite_id = $this->factory->blog->create();
		$second_subsite_id = $this->factory->blog->create();

		// Get file path from second
		$file_path = sprintf( 'sites/%d/2021/01/parakeets.gif', $second_subsite_id );

		// Restore first subsite
		switch_to_blog( $first_subsite_id );

		$actual_is_allowed = is_valid_path_for_site( $file_path );

		$this->assertEquals( $expected_is_allowed, $actual_is_allowed );
	}
}
