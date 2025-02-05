<?php

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting

namespace Automattic\VIP\Files\Acl;

use Automattic\Test\Constant_Mocker;
use ErrorException;
use WP_UnitTest_Factory;
use WP_UnitTestCase;

require_once __DIR__ . '/mock-header.php';
require_once __DIR__ . '/../../../files/acl/acl.php';

/**
 * @property WP_UnitTest_Factory $factory
 */
class VIP_Files_Acl_Test extends WP_UnitTestCase {
	private $original_error_reporting;

	public function setUp(): void {
		parent::setUp();
		header_remove();

		Constant_Mocker::clear();

		$this->original_error_reporting = error_reporting();

		// As of PHPUnit 10.x, expectWarning() is removed. We'll use a custom error handler to test for warnings.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		set_error_handler( static function ( int $errno, string $errstr ) {
			if ( error_reporting() & $errno ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- CLI
				throw new ErrorException( $errstr, $errno );
			}

			return false;
		}, E_USER_WARNING );
	}

	public function tearDown(): void {
		restore_error_handler();
		error_reporting( $this->original_error_reporting );
		Constant_Mocker::clear();
		parent::tearDown();
	}

	public function test__maybe_load_restrictions__no_constant_and_no_options() {
		// no setup

		maybe_load_restrictions();

		$this->assertEquals( false, has_filter( 'vip_files_acl_file_visibility' ) );
	}

	public function test__maybe_load_restrictions__no_constant_and_with_one_option__warning() {
		update_option( 'vip_files_acl_restrict_all_enabled', 1 );

		$this->expectException( ErrorException::class );
		$this->expectExceptionMessage( 'File ACL restrictions are enabled without server configs' );
		$this->expectExceptionCode( E_USER_WARNING );

		maybe_load_restrictions();
	}

	public function test__maybe_load_restrictions__no_constant_and_with_one_option() {
		error_reporting( $this->original_error_reporting & ~E_USER_WARNING );
		update_option( 'vip_files_acl_restrict_all_enabled', 1 );

		maybe_load_restrictions();
		self::assertFalse( has_filter( 'vip_files_acl_file_visibility' ) );
	}

	public function test__maybe_load_restrictions__constant_and_restrict_all_option() {
		Constant_Mocker::define( 'VIP_FILES_ACL_ENABLED', true );
		update_option( 'vip_files_acl_restrict_all_enabled', 1 );

		maybe_load_restrictions();

		$this->assertTrue( function_exists( 'Automattic\VIP\Files\Acl\Restrict_All_Files\check_file_visibility' ), 'Restrict_All_Files\check_file_visibility() is not defined; restrict-all-files.php may not have been require-d correctly' );
		$this->assertEquals( 10, has_filter( 'vip_files_acl_file_visibility', 'Automattic\VIP\Files\Acl\Restrict_All_Files\check_file_visibility' ), 'vip_files_acl_file_visibility filter does not have the correct callback attached' );
	}

	public function test__maybe_load_restrictions__constant_and_restrict_unpublished_option() {
		Constant_Mocker::define( 'VIP_FILES_ACL_ENABLED', true );
		update_option( 'vip_files_acl_restrict_unpublished_enabled', 1 );

		maybe_load_restrictions();

		$this->assertTrue( function_exists( 'Automattic\VIP\Files\Acl\Restrict_Unpublished_Files\check_file_visibility' ), 'Restrict_Unpublished_Files\check_file_visibility() is not defined; restrict-unpublished-files.php may not have been require-d correctly' );
		$this->assertEquals( 10, has_filter( 'vip_files_acl_file_visibility', 'Automattic\VIP\Files\Acl\Restrict_Unpublished_Files\check_file_visibility' ), 'vip_files_acl_file_visibility filter does not have correct callback attached' );
		$this->assertEquals( 10, has_filter( 'wpcom_vip_cache_purge_urls', 'Automattic\VIP\Files\Acl\Restrict_Unpublished_Files\purge_attachments_for_post' ), 'wpcom_vip_cache_purge_urls filter does not have correct callback attached' );
	}

	public function test__maybe_load_restrictions__constant_and_restrict_all_option_false() {
		Constant_Mocker::define( 'VIP_FILES_ACL_ENABLED', true );
		update_option( 'vip_files_acl_restrict_all_enabled', false );

		maybe_load_restrictions();

		$this->assertEquals( false, has_filter( 'vip_files_acl_file_visibility' ) );
	}

	public function test__maybe_load_restrictions__constant_and_restrict_unpublished_option_false() {
		Constant_Mocker::define( 'VIP_FILES_ACL_ENABLED', true );
		update_option( 'vip_files_acl_restrict_unpublished_enabled', false );

		maybe_load_restrictions();

		$this->assertEquals( false, has_filter( 'vip_files_acl_file_visibility' ) );
	}

	public function test__get_option_as_bool__option_not_exists() {
		$actual_value = get_option_as_bool_if_exists( 'my_test_get_option_as_bool_option_not_exists' );

		$this->assertEquals( null, $actual_value );
	}

	public function data_provider__get_option_as_bool__option_exists() {
		return [
			// true
			'bool true'    => [
				true,
				true,
			],
			'string true'  => [
				'true',
				true,
			],
			'string yes'   => [
				'yes',
				true,
			],
			'int 1'        => [
				1,
				true,
			],
			'string 1'     => [
				'1',
				true,
			],

			// false
			'bool false'   => [
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

		$actual_value = get_option_as_bool_if_exists( 'my_test_get_option_as_bool_option' );

		$this->assertEquals( $expected_value, $actual_value );
	}

	public function data_provider__send_visibility_headers() {
		return [
			'public-file'              => [
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

			'private-and-denied-file'  => [
				'FILE_IS_PRIVATE_AND_DENIED',
				'/wp-content/uploads/denied.jpg',
				403,
				'true',
			],
		];
	}

	/**
	 * @dataProvider data_provider__send_visibility_headers
	 */
	public function test__send_visibility_headers( $file_visibility, $file_path, $expected_status_code, $private_header_value ) {
		send_visibility_headers( $file_visibility, $file_path );

		$this->assertEquals( $expected_status_code, http_response_code(), 'Status code does not match expected' );

		$headers = headers_list();
		$this->assertContains( sprintf( 'X-Private: %s', $private_header_value ), $headers, 'Sent headers do not include X-Private header or its value is unexpected', true );
	}

	public function test__send_visibility_headers__invalid_visibility__warning() {
		$this->expectException( ErrorException::class );
		$this->expectExceptionMessage( 'Invalid file visibility (NOT_A_VISIBILITY) ACL set for /wp-content/uploads/invalid.jpg' );

		send_visibility_headers( 'NOT_A_VISIBILITY', '/wp-content/uploads/invalid.jpg' );
	}

	public function test__send_visibility_headers__invalid_visibility() {
		error_reporting( $this->original_error_reporting & ~E_USER_WARNING );
		send_visibility_headers( 'NOT_A_VISIBILITY', '/wp-content/uploads/invalid.jpg' );

		self::assertEquals( 500, http_response_code(), 'Status code does not match expected' );

		$headers = headers_list();
		self::assertNotContains( 'X-Private: true', $headers, 'Sent headers include X-Private: true header but should not.', true );
		self::assertNotContains( 'X-Private: false', $headers, 'Sent headers include X-Private: false header but should not.', true );
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

		add_filter( 'upload_dir', function ( $params ) {
			$params['path']    = 'vip:/' . $params['path'];
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
		$subsite_id = $this->factory()->blog->create();
		$file_path  = sprintf( 'sites/%d/2021/01/dogs.gif', $subsite_id );

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
		$subsite_id = $this->factory()->blog->create();
		$file_path  = sprintf( 'sites/%d/2021/01/hamster.gif', $subsite_id );

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
		$subsite_id = $this->factory()->blog->create();
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
		$first_subsite_id  = $this->factory()->blog->create();
		$second_subsite_id = $this->factory()->blog->create();

		// Get file path from second
		$file_path = sprintf( 'sites/%d/2021/01/parakeets.gif', $second_subsite_id );

		// Restore first subsite
		switch_to_blog( $first_subsite_id );

		$actual_is_allowed = is_valid_path_for_site( $file_path );

		$this->assertEquals( $expected_is_allowed, $actual_is_allowed );
	}

	public function test__is_valid_path_for_site__multisite_subsite_can_access_other_subsite_with_filter() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		$expected_is_allowed = true;

		add_filter( 'vip_files_acl_is_valid_path_for_site', '__return_true' );

		// Create two subsites
		$first_subsite_id  = $this->factory()->blog->create();
		$second_subsite_id = $this->factory()->blog->create();

		// Get file path from second
		$file_path = sprintf( 'sites/%d/2021/01/parakeets.gif', $second_subsite_id );

		// Restore first subsite
		switch_to_blog( $first_subsite_id );

		$actual_is_allowed = is_valid_path_for_site( $file_path );

		$this->assertEquals( $expected_is_allowed, $actual_is_allowed );
	}

	public function test__is_valid_path_for_site__multisite_subsite_can_access_other_paths_with_filter() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		$expected_is_allowed = true;

		add_filter(
			'vip_files_acl_is_valid_path_for_site',
			function ( $is_valid, $file_path ) {
				// Allow access to any file path starting with 'custom-uploads/'.
				if ( str_starts_with( $file_path, 'custom-uploads/' ) ) {
					return true;
				}

				// Return the original validation result for other paths.
				return $is_valid;
			},
			10,
			2
		);

		$this->factory()->blog->create();

		$file_path = 'custom-uploads/parakeets.gif';

		$actual_is_allowed = is_valid_path_for_site( $file_path );

		$this->assertEquals( $expected_is_allowed, $actual_is_allowed );
	}
}
