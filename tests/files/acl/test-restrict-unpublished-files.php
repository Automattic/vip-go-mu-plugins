<?php

namespace Automattic\VIP\Files\Acl\Restrict_Unpublished_Files;

use WP_UnitTestCase;

require_once __DIR__ . '/../../../files/acl/acl.php';
require_once __DIR__ . '/../../../files/acl/restrict-unpublished-files.php';

class VIP_Files_Acl_Restrict_Unpublished_Files_Test extends WP_UnitTestCase {
	/** @var int */
	private $original_current_user_id;

	public function setUp(): void {
		parent::setUp();

		$this->original_current_user_id = get_current_user_id();

		foreach ( \Automattic\VIP\Files\Acl\PATH_CACHE_HOOKS as $hook ) {
			add_action( $hook, 'Automattic\VIP\Files\Acl\clear_path_cache', 10, 3 );
		}
	}

	public function tearDown(): void {
		wp_set_current_user( $this->original_current_user_id );

		parent::tearDown();
	}

	/**
	 * Insert an attachment for an uploads-relative path.
	 *
	 * The code under test only reads the attachment post and its `_wp_attached_file` meta,
	 * so no file is uploaded.
	 */
	private function create_attachment( string $file, int $parent_id = 0 ): int {
		return self::factory()->attachment->create_object( $file, $parent_id, [ 'post_mime_type' => 'image/jpeg' ] );
	}

	/**
	 * An image rotated on upload, then edited, with a generated size and a VIP dynamic size.
	 */
	private function create_edited_attachment( int $parent_id, string $file = '2021/01/photo-rotated-e1700000000123.jpg' ): int {
		$attachment_id = $this->create_attachment( $file, $parent_id );

		wp_update_attachment_metadata( $attachment_id, [
			'file'           => '2021/01/photo-rotated-e1700000000123.jpg',
			'original_image' => 'photo.jpg',
			'sizes'          => [
				'medium' => [ 'file' => 'photo-rotated-e1700000000123-300x200.jpg' ],
				'large'  => [ 'file' => 'photo-rotated-e1700000000123.jpg?resize=1024,683' ],
			],
		] );
		update_post_meta( $attachment_id, '_wp_attachment_backup_sizes', [ 'full-orig' => [ 'file' => 'photo-rotated.jpg' ] ] );

		return $attachment_id;
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

	/**
	 * Only attachments inheriting their parent's status follow it, so a draft parent doesn't restrict them.
	 */
	public function test__check_file_visibility__attachment_not_inherit() {
		$expected_file_visibility = \Automattic\VIP\Files\Acl\FILE_IS_PUBLIC;

		$post_id       = $this->factory()->post->create( [ 'post_status' => 'draft' ] );
		$attachment_id = $this->create_attachment( '2021/01/not-inherit.jpg', $post_id );

		// WordPress coerces any other attachment status except `private`, `trash` and `auto-draft` to `inherit`.
		wp_update_post( [
			'ID'          => $attachment_id,
			'post_status' => 'private',
		] );
		$this->assertSame( 'private', get_post_status( $attachment_id ) );

		$file_visibility = false;
		$file_path       = get_post_meta( $attachment_id, '_wp_attached_file', true );

		$actual_file_visibility = check_file_visibility( $file_visibility, $file_path );

		$this->assertEquals( $expected_file_visibility, $actual_file_visibility );
	}

	public function get_data__check_file_visibility__attachment_with_deleted_parent() {
		return [
			'without user'          => [ null, \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_DENIED ],
			'with user permissions' => [ 'editor', \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_ALLOWED ],
		];
	}

	/**
	 * An attachment with a dangling parent reference stays private, with access checked against the attachment itself.
	 *
	 * @dataProvider get_data__check_file_visibility__attachment_with_deleted_parent
	 */
	public function test__check_file_visibility__attachment_with_deleted_parent( $user_role, $expected_file_visibility ) {
		$post_id       = $this->factory()->post->create( [ 'post_status' => 'draft' ] );
		$attachment_id = $this->create_attachment( '2021/01/orphaned.jpg', $post_id );

		wp_delete_post( $post_id, true );
		$this->assertNull( get_post( $post_id ) );
		// Deletion reparents attachments, so explicitly recreate the dangling reference.
		wp_update_post( [
			'ID'          => $attachment_id,
			'post_parent' => $post_id,
		] );
		$this->assertSame( $post_id, get_post( $attachment_id )->post_parent );

		if ( $user_role ) {
			wp_set_current_user( $this->factory()->user->create( [ 'role' => $user_role ] ) );
		}

		$file_path = get_post_meta( $attachment_id, '_wp_attached_file', true );

		$actual_file_visibility = check_file_visibility( false, $file_path );

		$this->assertEquals( $expected_file_visibility, $actual_file_visibility );
	}

	/**
	 * Multisite file paths have a `sites/<id>/` prefix that `_wp_attached_file` doesn't store.
	 *
	 * The prefix is stripped by pattern, so this needs neither multisite nor a real subsite.
	 */
	public function test__check_file_visibility__strips_sites_path_prefix() {
		$expected_file_visibility = \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_DENIED;

		$post_id       = $this->factory()->post->create( [ 'post_status' => 'draft' ] );
		$attachment_id = $this->create_attachment( '2021/01/subsite.jpg', $post_id );

		$file_visibility = false;
		$file_path       = 'sites/2/' . get_post_meta( $attachment_id, '_wp_attached_file', true );

		// Only finding the attachment by the stripped path reaches the draft parent and denies access.
		$actual_file_visibility = check_file_visibility( $file_visibility, $file_path );

		$this->assertEquals( $expected_file_visibility, $actual_file_visibility );
	}

	public function get_data__check_file_visibility__attachment_with_parent() {
		return [
			'publish parent'                        => [ 'publish', null, \Automattic\VIP\Files\Acl\FILE_IS_PUBLIC ],
			'draft parent and without user'         => [ 'draft', null, \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_DENIED ],
			// Contributors cannot edit other users' posts.
			'draft parent without user permissions' => [ 'draft', 'contributor', \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_DENIED ],
			'draft parent with user permissions'    => [ 'draft', 'editor', \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_ALLOWED ],
		];
	}

	/**
	 * @dataProvider get_data__check_file_visibility__attachment_with_parent
	 */
	public function test__check_file_visibility__attachment_with_parent( $parent_status, $user_role, $expected_file_visibility ) {
		$post_id       = $this->factory()->post->create( [ 'post_status' => $parent_status ] );
		$attachment_id = $this->create_attachment( '2021/01/attached.jpg', $post_id );

		if ( $user_role ) {
			wp_set_current_user( $this->factory()->user->create( [ 'role' => $user_role ] ) );
		}

		$file_visibility = false;
		$file_path       = get_post_meta( $attachment_id, '_wp_attached_file', true );

		$actual_file_visibility = check_file_visibility( $file_visibility, $file_path );

		$this->assertEquals( $expected_file_visibility, $actual_file_visibility );
	}

	public function test__check_file_visibility__path_cached_as_not_found_gets_attached() {
		$file_path  = '2021/01/reused.jpg';
		$moved_path = '2021/01/moved-here.jpg';

		$this->assertEquals( \Automattic\VIP\Files\Acl\FILE_IS_PUBLIC, check_file_visibility( false, $file_path ) );
		$this->assertEquals( \Automattic\VIP\Files\Acl\FILE_IS_PUBLIC, check_file_visibility( false, $moved_path ) );

		$attachment_id = $this->create_attachment( $file_path, $this->factory()->post->create( [ 'post_status' => 'draft' ] ) );

		$this->assertEquals( \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_DENIED, check_file_visibility( false, $file_path ) );

		update_attached_file( $attachment_id, $moved_path );

		$this->assertEquals( \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_DENIED, check_file_visibility( false, $moved_path ) );
	}

	public function get_data__check_file_visibility__attachment_stops_using_cached_path() {
		return [
			'attachment deleted' => [ fn( $id ) => wp_delete_attachment( $id, true ) ],
			'file moved'         => [ fn( $id ) => update_attached_file( $id, '2021/01/moved.jpg' ) ],
			// Calls delete_post_meta() without a value.
			'file removed'       => [ fn( $id ) => update_attached_file( $id, '' ) ],
		];
	}

	/**
	 * A path cached as one attachment is looked up again when that attachment stops using it.
	 *
	 * @dataProvider get_data__check_file_visibility__attachment_stops_using_cached_path
	 */
	public function test__check_file_visibility__attachment_stops_using_cached_path( callable $stop_using_path ) {
		$file_path = '2021/01/shared.jpg';
		$public_id = $this->create_attachment( $file_path, $this->factory()->post->create( [ 'post_status' => 'publish' ] ) );
		$this->create_attachment( $file_path, $this->factory()->post->create( [ 'post_status' => 'draft' ] ) );

		$this->assertEquals( \Automattic\VIP\Files\Acl\FILE_IS_PUBLIC, check_file_visibility( false, $file_path ) );

		$stop_using_path( $public_id );

		$this->assertEquals( \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_DENIED, check_file_visibility( false, $file_path ) );
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
		$expected_attachment_id = $this->create_attachment( '2021/01/only-one.jpg' );
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
			'exact first'  => [ '2026/10/shared-file.jpg', 'first' ],
			'exact second' => [ '2026/10/SHARED-FILE.JPG', 'second' ],
			'no exact'     => [ '2026/10/Shared-File.jpg', 'fallback' ],
		];
	}

	/**
	 * Verify the real case-insensitive SQL returns both attachment candidates.
	 *
	 * @dataProvider duplicate_path_cases
	 */
	public function test_duplicate_attachment_path_selection( string $query_path, string $selection ): void {
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
		$this->assertEqualsCanonicalizing( [ $first_id, $second_id ], array_map( static function ( $row ) {
			return (int) $row->post_id;
		}, $rows ) );
		$expected_id = 'fallback' === $selection ? (int) $rows[0]->post_id : ( 'second' === $selection ? $second_id : $first_id );
		$this->assertSame( $expected_id, (int) get_attachment_id_from_file_path( $query_path ) );
	}

	private function strip_wpcontent_uploads( $path ) {
		$upload_dir = wp_get_upload_dir();
		$base_path  = trailingslashit( wp_parse_url( $upload_dir['baseurl'], PHP_URL_PATH ) );
		return substr( $path, strlen( $base_path ) );
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
		$attachment_id_1 = $this->create_attachment( '2021/01/purge-1.jpg', $test_post_id );
		$attachment_id_2 = $this->create_attachment( '2021/01/purge-2.jpg', $test_post_id );
		$attachment_id_3 = $this->create_attachment( '2021/01/purge-3.jpg', $test_post_id );

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

	public function get_data__purge_attachments_for_post__derived_files() {
		return [
			'unpublished post' => [ 'draft', true ],
			'published post'   => [ 'publish', false ],
		];
	}

	/**
	 * Derived files are purged after the main files, and only when they become private.
	 *
	 * @dataProvider get_data__purge_attachments_for_post__derived_files
	 */
	public function test__purge_attachments_for_post__derived_files( $post_status, $expect_derived ) {
		$post_id       = $this->factory()->post->create( [ 'post_status' => $post_status ] );
		$attachment_id = $this->create_edited_attachment( $post_id );
		$url           = wp_get_attachment_url( $attachment_id );
		$base_url      = trailingslashit( dirname( $url ) );

		$expected_urls = $expect_derived ? [
			$url,
			$base_url . 'photo.jpg',
			$base_url . 'photo-rotated-e1700000000123-300x200.jpg',
			$base_url . 'photo-rotated.jpg',
		] : [ $url ];

		$this->assertEquals( $expected_urls, purge_attachments_for_post( [], $post_id ) );
	}

	public function get_data__check_file_visibility__names_ending_in_core_suffixes() {
		return [
			'original named with a size'  => [ 'banner-3000x1000-scaled.jpg', '_wp_attachment_metadata', [ 'original_image' => 'banner-3000x1000.jpg' ], 'banner-3000x1000.jpg' ],
			'original named with -pdf'    => [ 'team-pdf-scaled.jpg', '_wp_attachment_metadata', [ 'original_image' => 'team-pdf.jpg' ], 'team-pdf.jpg' ],
			'size of a name with -pdf'    => [ 'team-pdf-scaled.jpg', '_wp_attachment_metadata', [ 'sizes' => [ 'medium' => [ 'file' => 'team-pdf-300x200.jpg' ] ] ], 'team-pdf-300x200.jpg' ],
			'copy named with a size'      => [ 'hero-1600x900-e1700000000123.jpg', '_wp_attachment_backup_sizes', [ 'full-orig' => [ 'file' => 'hero-1600x900.jpg' ] ], 'hero-1600x900.jpg' ],
			'preview of a -pdf name'      => [ 'annual-report-pdf.pdf', '_wp_attachment_metadata', [ 'sizes' => [ 'full' => [ 'file' => 'annual-report-pdf-pdf.jpg' ] ] ], 'annual-report-pdf-pdf.jpg' ],
			'preview of an -e<n> name'    => [ 'invoice-e1.pdf', '_wp_attachment_metadata', [ 'sizes' => [ 'full' => [ 'file' => 'invoice-e1-pdf.jpg' ] ] ], 'invoice-e1-pdf.jpg' ],
			'thumbnail edited separately' => [ 'x-e1700000000123.jpg', '_wp_attachment_metadata', [ 'sizes' => [ 'thumbnail' => [ 'file' => 'x-e1700000000123-temp-150x150.jpg' ] ] ], 'x-e1700000000123-temp-150x150.jpg' ],
		];
	}

	/**
	 * A name stacked with suffixes mustn't grow the lookup query without bound.
	 */
	public function test__check_file_visibility__long_suffix_chain_keeps_query_small() {
		$queries = [];
		add_filter( 'query', function ( $query ) use ( &$queries ) {
			$queries[] = $query;
			return $query;
		} );

		check_file_visibility( false, 'a' . str_repeat( '-pdf', 80 ) . '.jpg' );

		$lookup = (string) current( preg_grep( '/_wp_attached_file/', $queries ) );
		$this->assertLessThanOrEqual( 21, substr_count( $lookup, ' LIKE ' ) );
	}

	/**
	 * A file whose own name ends in something that looks like a core suffix still matches its attachment.
	 *
	 * @dataProvider get_data__check_file_visibility__names_ending_in_core_suffixes
	 */
	public function test__check_file_visibility__names_ending_in_core_suffixes( $main_file, $meta_key, $meta_value, $filename ) {
		$attachment_id = $this->create_attachment( '2021/01/' . $main_file, $this->factory()->post->create( [ 'post_status' => 'draft' ] ) );
		update_post_meta( $attachment_id, $meta_key, $meta_value );

		$this->assertEquals( \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_DENIED, check_file_visibility( false, '2021/01/' . $filename ) );
	}

	/**
	 * PDF previews are images named after the PDF, with a number added when the name is taken.
	 */
	public function test__check_file_visibility__pdf_previews() {
		$attachment_id = self::factory()->attachment->create_object( '2021/01/doc.pdf', $this->factory()->post->create( [ 'post_status' => 'draft' ] ), [ 'post_mime_type' => 'application/pdf' ] );
		wp_update_attachment_metadata( $attachment_id, [
			'sizes' => [
				'full'      => [ 'file' => 'doc-pdf-1.jpg' ],
				'thumbnail' => [ 'file' => 'doc-pdf-1-116x150.jpg' ],
			],
		] );

		$this->assertEquals( \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_DENIED, check_file_visibility( false, '2021/01/doc-pdf-1.jpg' ) );
		$this->assertEquals( \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_DENIED, check_file_visibility( false, '2021/01/doc-pdf-1-116x150.jpg' ) );
	}

	/**
	 * Deleting one of two attachments that share files mustn't leave a derived file's lookup on the deleted one.
	 */
	public function test__check_file_visibility__derived_file_of_deleted_shared_attachment() {
		$post_id   = $this->factory()->post->create( [ 'post_status' => 'draft' ] );
		$first_id  = $this->create_edited_attachment( $post_id );
		$file_path = '2021/01/photo-rotated-e1700000000123-300x200.jpg';

		// A differently cased copy of the shared main file keeps the derived lookup on the first attachment.
		$this->create_edited_attachment( $post_id, strtoupper( '2021/01/photo-rotated-e1700000000123.jpg' ) );
		$this->assertSame( $first_id, (int) get_attachment_id_from_image_path( $file_path ) );

		wp_delete_attachment( $first_id, true );

		$this->assertEquals( \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_DENIED, check_file_visibility( false, $file_path ) );
	}

	public function get_data__check_file_visibility__derived_files() {
		return [
			'original'                     => [ 'photo.jpg', \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_DENIED ],
			'copy replaced by an edit'     => [ 'photo-rotated.jpg', \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_DENIED ],
			'generated size'               => [ 'photo-rotated-e1700000000123-300x200.jpg', \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_DENIED ],
			'same base, not in metadata'   => [ 'photo-rotated-e1700000000999.jpg', \Automattic\VIP\Files\Acl\FILE_IS_PUBLIC ],
			'same base, not an image file' => [ 'photo.pdf', \Automattic\VIP\Files\Acl\FILE_IS_PUBLIC ],
		];
	}

	/**
	 * Files core keeps next to an attachment's main file follow the attachment's visibility.
	 *
	 * @dataProvider get_data__check_file_visibility__derived_files
	 */
	public function test__check_file_visibility__derived_files( $filename, $expected_file_visibility ) {
		$this->create_edited_attachment( $this->factory()->post->create( [ 'post_status' => 'draft' ] ) );

		$this->assertEquals( $expected_file_visibility, check_file_visibility( false, '2021/01/' . $filename ) );
	}

	public function test__check_file_visibility__derived_file_added_after_cached_lookup() {
		$file_path     = '2021/01/late-300x200.jpg';
		$attachment_id = $this->create_attachment( '2021/01/late.jpg', $this->factory()->post->create( [ 'post_status' => 'draft' ] ) );

		$this->assertEquals( \Automattic\VIP\Files\Acl\FILE_IS_PUBLIC, check_file_visibility( false, $file_path ) );

		wp_update_attachment_metadata( $attachment_id, [
			'file'  => '2021/01/late.jpg',
			'sizes' => [ 'medium' => [ 'file' => 'late-300x200.jpg' ] ],
		] );

		$this->assertEquals( \Automattic\VIP\Files\Acl\FILE_IS_PRIVATE_AND_DENIED, check_file_visibility( false, $file_path ) );
	}
}
