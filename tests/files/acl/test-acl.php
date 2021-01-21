<?php

namespace Automattic\VIP\Files\Acl;

use WP_Error;

class VIP_Files_Acl_Test extends \WP_UnitTestCase {
	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once( __DIR__ . '/../../../files/acl/acl.php' );
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
}
