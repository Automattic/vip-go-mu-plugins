<?php

namespace Automattic\VIP\Files\Acl\Restrict_Unpublished_Files;

use WP_UnitTestCase;

require_once __DIR__ . '/../../../files/acl/acl.php';
require_once __DIR__ . '/../../../files/acl/restrict-unpublished-files.php';

class VIP_Files_Acl_Restrict_Unpublished_Files_Test extends WP_UnitTestCase {
	const TEST_IMAGE_PATH = VIP_GO_MUPLUGINS_TESTS__DIR__ . '/fixtures/image.jpg';

	public function setUp(): void {
		parent::setUp();

		$this->original_current_user_id = get_current_user_id();
	}

	public function tearDown(): void {
		wp_set_current_user( $this->original_current_user_id );

		parent::tearDown();
	}

	public function test__check_file_visibility__attachment_not_found() {
		$expected_file_visibility = \Automattic\VIP\Files\Acl\FILE_IS_PUBLIC;

		$file_visibility = false;
		$file_path       = '2021/01/kittens.jpg';

		$actual_file_visibility = check_file_visibility( $file_visibility, $file_path );

		$this->assertEquals( $expected_file_visibility, $actual_file_visibility );
	}

	public function test__check_file_visibility__attachment_without_post() {
		$expected_file_visibility = \Automattic\VIP\Files\Acl\FILE_IS_PUBLIC;

		// post meta entry exists but points to non-existent post
		update_post_meta( PHP_INT_MAX, '_wp_attached_file', '2021/01/kittens.jpg' );

		$file_visibility = false;
		$file_path       = '2021/01/kittens.jpg';

		$actual_file_visibility = check_file_visibility( $file_visibility, $file_path );

		$this->assertEquals( $expected_file_visibility, $actual_file_visibility );
	}

	public function test__check_file_visibility__attachment_not_inherit() {
		$expected_file_visibility = \Automattic\VIP\Files\Acl\FILE_IS_PUBLIC;

		$post_id       = $this->factory->post->create();
		$attachment_id = $this->factory->attachment->create_upload_object( self::TEST_IMAGE_PATH, $post_id );

		wp_update_post( [
			'ID'          => $attachment_id,
			'post_status' => 'publish',
		] );

		$file_visibility = false;
		$file_path       = get_post_meta( $attachment_id, '_wp_attached_file', true );

		$actual_file_visibility = check_file_visibility( $file_visibility, $file_path );

		$this->assertEquals( $expected_file_visibility, $actual_file_visibility );
	}

	public function test__check_file_visibility__multisite_subsite_attachment_with_sites_path() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped();
		}

		$expected_file_visibility = \Automattic\VIP\Files\Acl\FILE_IS_PUBLIC;

		// Switch to a subsite
		$subsite_id = $this->factory->blog->create();
		switch_to_blog( $subsite_id );

		// Create attachment
		$attachment_id = $this->factory->attachment->create_upload_object( self::TEST_IMAGE_PATH );

		$file_visibility = false;
		$file_path       = sprintf( 'sites/%d/%s', $subsite_id, get_post_meta( $attachment_id, '_wp_attached_file', true ) );

		$actual_file_visibility = check_file_visibility( $file_visibility, $file_path );

		$this->assertEquals( $expected_file_visibility, $actual_file_visibility );
	}

	public function test__check_file_visibility__attachment_with_publish_parent() {
		$expected_file_visibility = \Automattic\VIP\Files\Acl\FILE_IS_PUBLIC;

		$post_id       = $this->factory->post->create( [ 'post_status' => 'publish' ] );
		$attachment_id = $this->factory->attachment->create_upload_object( self::TEST_IMAGE_PATH, $post_id );

		$file_visibility = false;
		$file_path       = get_post_meta( $attachment_id, '_wp_attached_file', true );

		$actual_file_visibility = check_file_visibility( $file_visibility, $file_path );

		$this->assertEquals( $expected_file_visibility, $actual_file_visibility );
	}

	public function test__check_file_visibility__attachment_with_draft_parent_and_without_user() {
		$expected_file_visibility = \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_DENIED;

		$post_id       = $this->factory->post->create( [ 'post_status' => 'draft' ] );
		$attachment_id = $this->factory->attachment->create_upload_object( self::TEST_IMAGE_PATH, $post_id );

		$file_visibility = false;
		$file_path       = get_post_meta( $attachment_id, '_wp_attached_file', true );

		$actual_file_visibility = check_file_visibility( $file_visibility, $file_path );

		$this->assertEquals( $expected_file_visibility, $actual_file_visibility );
	}

	public function test__check_file_visibility__attachment_with_draft_parent_and_without_user_permissions() {
		$expected_file_visibility = \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_DENIED;

		$post_id       = $this->factory->post->create( [ 'post_status' => 'draft' ] );
		$attachment_id = $this->factory->attachment->create_upload_object( self::TEST_IMAGE_PATH, $post_id );

		$test_user_id = $this->factory->user->create( array( 'role' => 'contributor' ) ); // will not have access to post
		wp_set_current_user( $test_user_id );

		$file_visibility = false;
		$file_path       = get_post_meta( $attachment_id, '_wp_attached_file', true );

		$actual_file_visibility = check_file_visibility( $file_visibility, $file_path );

		$this->assertEquals( $expected_file_visibility, $actual_file_visibility );
	}

	public function test__check_file_visibility__attachment_with_draft_parent_and_with_user_permissions() {
		$expected_file_visibility = \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_ALLOWED;

		$post_id       = $this->factory->post->create( [ 'post_status' => 'draft' ] );
		$attachment_id = $this->factory->attachment->create_upload_object( self::TEST_IMAGE_PATH, $post_id );

		$test_user_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $test_user_id );

		$file_visibility = false;
		$file_path       = get_post_meta( $attachment_id, '_wp_attached_file', true );

		$actual_file_visibility = check_file_visibility( $file_visibility, $file_path );

		$this->assertEquals( $expected_file_visibility, $actual_file_visibility );
	}

	public function test__get_attachment_id_from_file_path__attachment_not_found() {
		$attachment_path        = '/2020/12/not-an-attachment.pdf';
		$expected_attachment_id = 0;

		// Run the test.
		$actual_attachment_id = get_attachment_id_from_file_path( $attachment_path );

		$this->assertEquals( $expected_attachment_id, $actual_attachment_id );
	}

	public function test__get_attachment_id_from_file_path__attachment_only_one_result() {
		// Set up a test attachment.
		$expected_attachment_id = $this->factory->attachment->create_upload_object( self::TEST_IMAGE_PATH );
		list( $attachment_src ) = wp_get_attachment_image_src( $expected_attachment_id, 'full' );
		$attachment_path        = wp_parse_url( $attachment_src, PHP_URL_PATH );
		$attachment_path        = $this->strip_wpcontent_uploads( $attachment_path );

		// Run the test.
		$actual_attachment_id = get_attachment_id_from_file_path( $attachment_path );

		$this->assertEquals( $expected_attachment_id, $actual_attachment_id );
	}

	public function test__get_attachment_id_from_file_path__attachment_multiple_results_first_in_list() {
		// Set up the first attachment.
		$attachment_id = $this->factory->attachment->create_upload_object( self::TEST_IMAGE_PATH );

		// Create a second attachment with the same file path.
		$duplicate_attachment_id   = $this->factory->attachment->create_upload_object( self::TEST_IMAGE_PATH );
		$duplicate_attachment_file = get_post_meta( $duplicate_attachment_id, '_wp_attached_file', true );
		update_post_meta( $duplicate_attachment_id, '_wp_attached_file', $duplicate_attachment_file );

		// Look up the first one in the list.
		$expected_attachment_id = $attachment_id;
		list( $attachment_src ) = wp_get_attachment_image_src( $expected_attachment_id, 'full' );
		$attachment_path        = wp_parse_url( $attachment_src, PHP_URL_PATH );
		$attachment_path        = $this->strip_wpcontent_uploads( $attachment_path );

		// Run the test.
		$actual_attachment_id = get_attachment_id_from_file_path( $attachment_path );

		$this->assertEquals( $expected_attachment_id, $actual_attachment_id );
	}

	public function test__get_attachment_id_from_file_path__attachment_multiple_results_exact_match_first() {
		// Set up the first attachment.
		$this->factory->attachment->create_upload_object( self::TEST_IMAGE_PATH );

		// Create a second attachment with the same file path.
		$duplicate_attachment_id   = $this->factory->attachment->create_upload_object( self::TEST_IMAGE_PATH );
		$duplicate_attachment_file = get_post_meta( $duplicate_attachment_id, '_wp_attached_file', true );
		update_post_meta( $duplicate_attachment_id, '_wp_attached_file', strtoupper( $duplicate_attachment_file ) );

		// Look up the second one in the list.
		$expected_attachment_id = $duplicate_attachment_id;
		list( $attachment_src ) = wp_get_attachment_image_src( $expected_attachment_id, 'full' );
		$attachment_path        = wp_parse_url( $attachment_src, PHP_URL_PATH );
		$attachment_path        = $this->strip_wpcontent_uploads( $attachment_path );

		// Run the test.
		$actual_attachment_id = get_attachment_id_from_file_path( $attachment_path );

		$this->assertEquals( $expected_attachment_id, $actual_attachment_id );
	}

	private function strip_wpcontent_uploads( $path ) {
		return substr( $path, strlen( '/wp-content/uploads/' ) );
	}

	public function test__purge_attachments_for_post__invalid_post() {
		// Input and output are the same; no change
		$input_urls    = [
			'https://example.com',
		];
		$expected_urls = [
			'https://example.com',
		];

		// Set a highly unlikely post ID
		$test_post_id = PHP_INT_MAX;

		$actual_urls = purge_attachments_for_post( $input_urls, $test_post_id );

		$this->assertEquals( $expected_urls, $actual_urls );
	}

	public function test__purge_attachments_for_post__post_with_no_attachments() {
		// Input and output are the same; no change
		$input_urls    = [
			'https://example.com',
		];
		$expected_urls = [
			'https://example.com',
		];

		// No attachments for post
		$test_post_id = $this->factory->post->create();

		$actual_urls = purge_attachments_for_post( $input_urls, $test_post_id );

		$this->assertEquals( $expected_urls, $actual_urls );
	}

	public function test__purge_attachments_for_post__post_with_some_attachments() {
		$input_urls = [
			'https://example.com',
		];

		$test_post_id    = $this->factory->post->create();
		$attachment_id_1 = $this->factory->attachment->create_upload_object( self::TEST_IMAGE_PATH, $test_post_id );
		$attachment_id_2 = $this->factory->attachment->create_upload_object( self::TEST_IMAGE_PATH, $test_post_id );
		$attachment_id_3 = $this->factory->attachment->create_upload_object( self::TEST_IMAGE_PATH, $test_post_id );

		// Output should include new attachment URLs
		$expected_urls = [
			'https://example.com',
			wp_get_attachment_url( $attachment_id_1 ),
			wp_get_attachment_url( $attachment_id_2 ),
			wp_get_attachment_url( $attachment_id_3 ),
		];

		$actual_urls = purge_attachments_for_post( $input_urls, $test_post_id );

		$this->assertEquals( $expected_urls, $actual_urls );
	}
}

