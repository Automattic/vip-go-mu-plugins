<?php

namespace Automattic\VIP\Files\Acl;

use WP_Error;

class VIP_Files_Acl_Test extends \WP_UnitTestCase {

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once( __DIR__ . '/../../files/acl.php' );
	}

	public function get_data__validate_path__invalid() {
		return [
			'null-uri' => [
				null,
				'VIP Files ACL failed due to empty path',
			],
	
			'empty-uri' => [
				'',
				'VIP Files ACL failed due to empty path',
			],
	
			'path-no-wp-content' => [
				'/a/path/to/a/file.jpg',
				'VIP Files ACL failed due to invalid path (for /a/path/to/a/file.jpg)'
			],
	
			'relative-url-with-wp-content' => [
				'en/wp-content/uploads/file.png',
				'VIP Files ACL failed due to relative path (for en/wp-content/uploads/file.png)'
			],
		];
	}
	
	public function get_data__validate_path__valid() {
		return [
			'valid-path' => [
				'/wp-content/uploads/kittens.gif',
			],
	
			'valid-path-nested' => [
				'/wp-content/uploads/subfolder/2099/12/cats.jpg',
			],
	
			'valid-path-subsite' => [
				'/subsite/wp-content/uploads/puppies.png',
			],
	
			/* TODO: not supported yet
			'resized-image' => [
				'/wp-content/uploads/2021/01/dinos-100x100.jpg',
			],
			*/
		];
	}

	public function get_data__sanitize_path() {
		return [
			'valid-path' => [
				'/wp-content/uploads/kittens.gif',
				'kittens.gif',
			],

			'valid-path-nested' => [
				'/wp-content/uploads/subfolder/2099/12/cats.jpg',
				'subfolder/2099/12/cats.jpg',
			],

			'valid-path-subsite' => [
				'/subsite/wp-content/uploads/puppies.png',
				'puppies.png',
			],

			/* TODO: not supported yet
			'resized-image' => [
				'/wp-content/uploads/2021/01/dinos-100x100.jpg',
				'dinos.jpg',
			],
			*/
		];
	}

	/**
	 * @dataProvider get_data__validate_path__invalid
	 */
	public function test__pre_wp_validate_path__invalid( $file_path, $expected_warning ) {
		$this->expectException( \PHPUnit\Framework\Error\Warning::class );
		$this->expectExceptionMessage( $expected_warning );

		$actual_is_valid = pre_wp_validate_path( $file_path );
	
		$this->assertFalse( $actual_is_valid );
	}

	/**
	 * @dataProvider get_data__validate_path__valid
	 */
	public function test__pre_wp_validate_path__valid( $file_path ) {
		$actual_is_valid = pre_wp_validate_path( $file_path );
	
		$this->assertTrue( $actual_is_valid );
	}

	/**
	 * @dataProvider get_data__sanitize_path
	 */
	public function test__pre_wp_sanitize_path( $file_path, $expected_path ) {
		$actual_path = pre_wp_sanitize_path( $file_path );

		$this->assertEquals( $actual_path, $expected_path );
	}

	public function get_data__send_visibility_headers() {
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

			'file-not-found' => [
				'FILE_NOT_FOUND',
				'/wp-content/uploads/404.jpg',
				202,
				false,
			],
		];
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @dataProvider get_data__send_visibility_headers
	 */
	public function test__send_visibility_headers( $file_visibility, $file_path, $expected_status_code, $should_have_private_header ) {
		send_visibility_headers( $file_visibility, $file_path );

		$this->assertEquals( $expected_status_code, http_response_code(), 'Status code does not match expected' );

		// Not ideal to have a branch in , but good enough.
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
	public function test__send_visibility_headers__invaid_visibility() {
		define( 'NOT_A_VISIBILITY', 'NOT_A_VISIBILITY' );

		$this->expectException( \PHPUnit\Framework\Error\Warning::class );
		$this->expectExceptionMessage( 'Invalid file visibility (NOT_A_VISIBILITY) ACL set for /wp-content/uploads/invalid.jpg' );

		send_visibility_headers( NOT_A_VISIBILITY, '/wp-content/uploads/invalid.jpg' );

		$this->assertEquals( 500, http_response_code(), 'Status code does not match expected' );

		$this->assertNotContains( 'X-Private: true', xdebug_get_headers(), 'Sent headers include X-Private header but should not.' );
	}
}
