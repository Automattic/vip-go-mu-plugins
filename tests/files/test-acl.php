<?php

namespace Automattic\VIP\Files\Acl;

use WP_Error;

class VIP_Files_Acl_Test extends \WP_UnitTestCase {
	const TEST_IMAGE_PATH = VIP_GO_MUPLUGINS_TESTS__DIR__ . '/fixtures/image.jpg';

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once( __DIR__ . '/../../files/acl.php' );
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
	public function test__send_visibility_headers__invaid_visibility() {
		define( 'NOT_A_VISIBILITY', 'NOT_A_VISIBILITY' );

		$this->expectException( \PHPUnit\Framework\Error\Warning::class );
		$this->expectExceptionMessage( 'Invalid file visibility (NOT_A_VISIBILITY) ACL set for /wp-content/uploads/invalid.jpg' );

		send_visibility_headers( NOT_A_VISIBILITY, '/wp-content/uploads/invalid.jpg' );

		$this->assertEquals( 500, http_response_code(), 'Status code does not match expected' );

		$this->assertNotContains( 'X-Private: true', xdebug_get_headers(), 'Sent headers include X-Private header but should not.' );
	}

	public function test__get_file_path_from_attachment_id__attachment_not_found() {
		$attachment_path = '/2020/12/not-an-attachment.pdf';
		$expected_attachment_id = 0;

		// Run the test.
		$actual_attachment_id = get_file_path_from_attachment_id( $attachment_path );

		$this->assertEquals( $expected_attachment_id, $actual_attachment_id );
	}

	public function test__get_file_path_from_attachment_id__attachment_only_one_result() {
		// Set up a test attachment.
		$expected_attachment_id = $this->factory->attachment->create_upload_object( self::TEST_IMAGE_PATH );
		list( $attachment_src ) = wp_get_attachment_image_src( $expected_attachment_id, 'full' );
		$attachment_path = parse_url( $attachment_src, PHP_URL_PATH );
		$attachment_path = $this->strip_wpcontent_uploads( $attachment_path );

		// Run the test.
		$actual_attachment_id = get_file_path_from_attachment_id( $attachment_path );

		$this->assertEquals( $expected_attachment_id, $actual_attachment_id );
	}

	public function test__get_file_path_from_attachment_id__attachment_multiple_results_first_in_list() {
		// Set up the first attachment.
		$attachment_id = $this->factory->attachment->create_upload_object( self::TEST_IMAGE_PATH );

		// Create a second attachment with the same file path.
		$duplicate_attachment_id = $this->factory->attachment->create_upload_object( self::TEST_IMAGE_PATH );
		$duplicate_attachment_file = get_post_meta( $duplicate_attachment_id, '_wp_attached_file', true );
		update_post_meta( $duplicate_attachment_id, '_wp_attached_file', $duplicate_attachment_file );

		// Look up the first one in the list.
		$expected_attachment_id = $attachment_id;
		list( $attachment_src ) = wp_get_attachment_image_src( $expected_attachment_id, 'full' );
		$attachment_path = parse_url( $attachment_src, PHP_URL_PATH );
		$attachment_path = $this->strip_wpcontent_uploads( $attachment_path );

		// Run the test.
		$actual_attachment_id = get_file_path_from_attachment_id( $attachment_path );

		$this->assertEquals( $expected_attachment_id, $actual_attachment_id );
	}

	public function test__get_file_path_from_attachment_id__attachment_multiple_results_exact_match_first() {
		// Set up the first attachment.
		$attachment_id = $this->factory->attachment->create_upload_object( self::TEST_IMAGE_PATH );

		// Create a second attachment with the same file path.
		$duplicate_attachment_id = $this->factory->attachment->create_upload_object( self::TEST_IMAGE_PATH );
		$duplicate_attachment_file = get_post_meta( $duplicate_attachment_id, '_wp_attached_file', true );
		update_post_meta( $duplicate_attachment_id, '_wp_attached_file', strtoupper( $duplicate_attachment_file ) );

		// Look up the second one in the list.
		$expected_attachment_id = $duplicate_attachment_id;
		list( $attachment_src ) = wp_get_attachment_image_src( $expected_attachment_id, 'full' );
		$attachment_path = parse_url( $attachment_src, PHP_URL_PATH );
		$attachment_path = $this->strip_wpcontent_uploads( $attachment_path );

		// Run the test.
		$actual_attachment_id = get_file_path_from_attachment_id( $attachment_path );

		$this->assertEquals( $expected_attachment_id, $actual_attachment_id );
	}

	private function strip_wpcontent_uploads( $path ) {
		return substr( $path, strlen( '/wp-content/uploads/' ) );
	}
}
