<?php

namespace Automattic\VIP\Files\Acl\Pre_WP_Utils;

use WP_UnitTestCase;

require_once __DIR__ . '/../../../files/acl/pre-wp-utils.php';

class VIP_Files_Acl_Pre_Wp_Utils_Test extends WP_UnitTestCase {
	public function setUp(): void {
		// As of PHPUnit 10.x, expectWarning() is removed. We'll use a custom error handler to test for warnings.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		set_error_handler( static function ( int $errno, string $errstr ): never {
			throw new \Exception( $errstr, $errno );
		}, E_USER_WARNING );
	}

	public function tearDown(): void {
		restore_error_handler();

		parent::tearDown();
	}

	public function test__prepare_request__empty_request_uri() {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'VIP Files ACL failed due to empty URI' );

		$request_uri = '';
		prepare_request( $request_uri );
	}

	public function test__prepare_request__invalid_request_uri() {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'VIP Files ACL failed due to relative path (for invalid/path.jpg)' );

		$request_uri = 'invalid/path.jpg';
		prepare_request( $request_uri );
	}

	public function test__prepare_request__valid() {
		$request_uri     = '/sub/wp-content/uploads/file.jpg';
		$expected_result = [
			'/sub',
			'file.jpg',
		];

		$actual_result = prepare_request( $request_uri );

		$this->assertEquals( $actual_result, $expected_result );
	}

	public function get_data__validate_path__invalid() {
		return [
			'null-uri'                     => [
				null,
				'VIP Files ACL failed due to empty path',
			],

			'empty-uri'                    => [
				'',
				'VIP Files ACL failed due to empty path',
			],

			'path-no-wp-content'           => [
				'/a/path/to/a/file.jpg',
				'VIP Files ACL failed due to invalid path (for /a/path/to/a/file.jpg)',
			],

			'relative-url-with-wp-content' => [
				'en/wp-content/uploads/file.png',
				'VIP Files ACL failed due to relative path (for en/wp-content/uploads/file.png)',
			],
		];
	}

	public function get_data__validate_path__valid() {
		return [
			'valid-path'                     => [
				'/wp-content/uploads/kittens.gif',
			],

			'valid-path-nested'              => [
				'/wp-content/uploads/subfolder/2099/12/cats.jpg',
			],

			'valid-single-directory-subsite' => [
				'/subsite/wp-content/uploads/puppies.png',
			],

			'valid-multi-directory-subsite'  => [
				'/sub/site/wp-content/uploads/fishies.png',
			],

			'multi-wp-content-directories'   => [
				'/wp-content/uploads/path/to/wp-content/uploads/otters.png',
			],

			/* TODO: not supported yet
			'resized-image' => [
				'/wp-content/uploads/2021/01/dinos-100x100.jpg',
			],
			*/
		];
	}

	/**
	 * @dataProvider get_data__validate_path__invalid
	 */
	public function test__validate_path__invalid( $file_path, $expected_warning ) {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( $expected_warning );

		validate_path( $file_path );
	}

	/**
	 * @dataProvider get_data__validate_path__valid
	 */
	public function test__validate_path__valid( $file_path ) {
		$actual_is_valid = validate_path( $file_path );

		$this->assertTrue( $actual_is_valid );
	}

	public function get_data__sanitize_and_split_path() {
		return [
			'valid-path'                     => [
				'/wp-content/uploads/kittens.gif',
				[
					'',
					'kittens.gif',
				],
			],

			'valid-path-nested'              => [
				'/wp-content/uploads/subfolder/2099/12/cats.jpg',
				[
					'',
					'subfolder/2099/12/cats.jpg',
				],
			],

			'valid-single-directory-subsite' => [
				'/subsite/wp-content/uploads/puppies.png',
				[
					'/subsite',
					'puppies.png',
				],
			],

			'valid-multi-directory-subsite'  => [
				'/sub/site/wp-content/uploads/fishies.png',
				[
					'/sub/site',
					'fishies.png',
				],
			],

			'multi-wp-content-directories'   => [
				'/wp-content/uploads/path/to/wp-content/uploads/otters.png',
				[
					'',
					'path/to/wp-content/uploads/otters.png',
				],
			],

			// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			/* TODO: not supported yet
			'resized-image' => [
				'/wp-content/uploads/2021/01/dinos-100x100.jpg',
				'dinos.jpg',
			],
			*/
		];
	}

	/**
	 * @dataProvider get_data__sanitize_and_split_path
	 */
	public function test__sanitize_and_split_path( $file_path, $expected_path ) {
		$actual_path = sanitize_and_split_path( $file_path );

		$this->assertEquals( $actual_path, $expected_path );
	}
}
