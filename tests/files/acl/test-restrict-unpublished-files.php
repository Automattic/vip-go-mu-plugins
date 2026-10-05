<?php

namespace Automattic\VIP\Files\Acl\Restrict_Unpublished_Files;

use WP_UnitTestCase;

require_once __DIR__ . '/../../../files/acl/acl.php';
require_once __DIR__ . '/../../../files/acl/restrict-unpublished-files.php';

class VIP_Files_Acl_Restrict_Unpublished_Files_Test extends WP_UnitTestCase {
	const TEST_IMAGE_PATH = VIP_GO_MUPLUGINS_TESTS__DIR__ . '/fixtures/image.jpg';

	/** @var int */
	private $original_current_user_id;

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

		$post_id       = $this->factory()->post->create();
		$attachment_id = $this->factory()->attachment->create_upload_object( self::TEST_IMAGE_PATH, $post_id );

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
		$subsite_id = $this->factory()->blog->create();
		switch_to_blog( $subsite_id );

		// Create attachment
		$attachment_id = $this->factory()->attachment->create_upload_object( self::TEST_IMAGE_PATH );

		$file_visibility = false;
		$file_path       = sprintf( 'sites/%d/%s', $subsite_id, get_post_meta( $attachment_id, '_wp_attached_file', true ) );

		$actual_file_visibility = check_file_visibility( $file_visibility, $file_path );

		$this->assertEquals( $expected_file_visibility, $actual_file_visibility );
	}

	public function test__check_file_visibility__attachment_with_publish_parent() {
		$expected_file_visibility = \Automattic\VIP\Files\Acl\FILE_IS_PUBLIC;

		$post_id       = $this->factory()->post->create( [ 'post_status' => 'publish' ] );
		$attachment_id = $this->factory()->attachment->create_upload_object( self::TEST_IMAGE_PATH, $post_id );

		$file_visibility = false;
		$file_path       = get_post_meta( $attachment_id, '_wp_attached_file', true );

		$actual_file_visibility = check_file_visibility( $file_visibility, $file_path );

		$this->assertEquals( $expected_file_visibility, $actual_file_visibility );
	}

	public function test__check_file_visibility__attachment_with_draft_parent_and_without_user() {
		$expected_file_visibility = \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_DENIED;

		$post_id       = $this->factory()->post->create( [ 'post_status' => 'draft' ] );
		$attachment_id = $this->factory()->attachment->create_upload_object( self::TEST_IMAGE_PATH, $post_id );

		$file_visibility = false;
		$file_path       = get_post_meta( $attachment_id, '_wp_attached_file', true );

		$actual_file_visibility = check_file_visibility( $file_visibility, $file_path );

		$this->assertEquals( $expected_file_visibility, $actual_file_visibility );
	}

	public function test__check_file_visibility__attachment_with_draft_parent_and_without_user_permissions() {
		$expected_file_visibility = \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_DENIED;

		$post_id       = $this->factory()->post->create( [ 'post_status' => 'draft' ] );
		$attachment_id = $this->factory()->attachment->create_upload_object( self::TEST_IMAGE_PATH, $post_id );

		$test_user_id = $this->factory()->user->create( array( 'role' => 'contributor' ) ); // will not have access to post
		wp_set_current_user( $test_user_id );

		$file_visibility = false;
		$file_path       = get_post_meta( $attachment_id, '_wp_attached_file', true );

		$actual_file_visibility = check_file_visibility( $file_visibility, $file_path );

		$this->assertEquals( $expected_file_visibility, $actual_file_visibility );
	}

	public function test__check_file_visibility__attachment_with_draft_parent_and_with_user_permissions() {
		$expected_file_visibility = \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_ALLOWED;

		$post_id       = $this->factory()->post->create( [ 'post_status' => 'draft' ] );
		$attachment_id = $this->factory()->attachment->create_upload_object( self::TEST_IMAGE_PATH, $post_id );

		$test_user_id = $this->factory()->user->create( array( 'role' => 'editor' ) );
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
		$expected_attachment_id = $this->factory()->attachment->create_upload_object( self::TEST_IMAGE_PATH );
		list( $attachment_src ) = wp_get_attachment_image_src( $expected_attachment_id, 'full' );
		$attachment_path        = wp_parse_url( $attachment_src, PHP_URL_PATH );
		$attachment_path        = $this->strip_wpcontent_uploads( $attachment_path );

		// Run the test.
		$actual_attachment_id = get_attachment_id_from_file_path( $attachment_path );

		$this->assertEquals( $expected_attachment_id, $actual_attachment_id );
	}

	/**
	 * Cover exact case matches and the first-candidate fallback.
	 */
	public function duplicate_path_cases(): array {
		return [
			'exact first'  => [ '2026/10/shared-file.jpg', false ],
			'exact second' => [ '2026/10/SHARED-FILE.JPG', true ],
			'no exact'     => [ '2026/10/Shared-File.jpg', false ],
		];
	}

	/**
	 * Verify the real case-insensitive SQL returns both attachment candidates.
	 *
	 * @dataProvider duplicate_path_cases
	 */
	public function test_duplicate_attachment_path_selection( string $query_path, bool $select_second ): void {
		global $wpdb;
		$first_id  = self::factory()->post->create( [ 'post_type' => 'attachment' ] );
		$second_id = self::factory()->post->create( [ 'post_type' => 'attachment' ] );
		update_post_meta( $first_id, '_wp_attached_file', '2026/10/shared-file.jpg' );
		update_post_meta( $second_id, '_wp_attached_file', '2026/10/SHARED-FILE.JPG' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Confirm the production SQL fixture shape.
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value = %s",
			$query_path
		) );
		$this->assertCount( 2, $rows, 'The lookup must exercise multiple case-insensitive matches.' );
		$this->assertSame( [ $first_id, $second_id ], array_map( static function ( $row ) {
			return (int) $row->post_id;
		}, $rows ) );
		$this->assertSame( $select_second ? $second_id : $first_id, (int) get_attachment_id_from_file_path( $query_path ) );
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
		$test_post_id = $this->factory()->post->create();

		$actual_urls = purge_attachments_for_post( $input_urls, $test_post_id );

		$this->assertEquals( $expected_urls, $actual_urls );
	}

	public function test__purge_attachments_for_post__post_with_some_attachments() {
		$input_urls = [
			'https://example.com',
		];

		$test_post_id    = $this->factory()->post->create();
		$attachment_id_1 = $this->factory()->attachment->create_upload_object( self::TEST_IMAGE_PATH, $test_post_id );
		$attachment_id_2 = $this->factory()->attachment->create_upload_object( self::TEST_IMAGE_PATH, $test_post_id );
		$attachment_id_3 = $this->factory()->attachment->create_upload_object( self::TEST_IMAGE_PATH, $test_post_id );

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
