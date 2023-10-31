<?php

namespace Automattic\VIP\Performance;

use WP_UnitTestCase;

require_once __DIR__ . '/../../performance/class-media-library-caching.php';

// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited

class Media_Library_Caching_Test extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		global $wp_version;

		// Save the original value so we can restore it later.
		$this->original_wp_version = $wp_version;

		// Only for code coverage testing purposes. It will be removed later.
		$wp_version = Media_Library_Caching::MINIMUM_WORDPRESS_VERSION;

		$this->mock_attachments_data();
	}

	protected function tearDown(): void {
		parent::tearDown();
		remove_all_filters( 'vip_cache_mime_types' );
		remove_all_filters( 'vip_max_posts_to_query_for_mime_type_caching' );

		global $wp_version;

		// Restore the original value after the test is done.
		$wp_version = $this->original_wp_version;
	}

	protected function check_wp_version() {
		global $wp_version;

		// Skip tests if WordPress version is lower than MINIMUM_WORDPRESS_VERSION.
		if ( version_compare( $wp_version, Media_Library_Caching::MINIMUM_WORDPRESS_VERSION, '<' ) ) {
			$this->markTestSkipped( 'This test does not run for WordPress versions below ' . Media_Library_Caching::MINIMUM_WORDPRESS_VERSION );
		}
	}

	protected function call_init() {
		// Required so that EP registers the Indexables
		do_action( 'plugins_loaded' );
		do_action( 'init' );
	}

	protected function mock_attachments_data() {
		$this->factory->post->create(
			array(
				'post_title'     => 'Mock Attachment 01',
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
			)
		);
		$this->factory->post->create(
			array(
				'post_title'     => 'Mock Attachment 02',
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
			)
		);
		$this->factory->post->create(
			array(
				'post_title'     => 'Mock Attachment 03',
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
			)
		);
		$this->factory->post->create(
			array(
				'post_title'     => 'Mock Attachment 04',
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/gif',
			)
		);
		$this->factory->post->create(
			array(
				'post_title'     => 'Mock Attachment 05',
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/gif',
			)
		);
		$this->factory->post->create(
			array(
				'post_title'     => 'Mock Attachment 06',
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/png',
			)
		);
		$this->factory->post->create(
			array(
				'post_title'     => 'Mock Attachment 07',
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'application/octet-stream',
			)
		);
		$this->factory->post->create(
			array(
				'post_title'     => 'Mock Attachment 08',
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'video/mp4',
			)
		);
		$this->factory->post->create(
			array(
				'post_title'     => 'Mock Attachment 09',
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'audio/mpeg',
			)
		);
		$this->factory->post->create(
			array(
				'post_title'     => 'Mock Attachment 10',
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'application/pdf',
			)
		);
	}

	protected function get_sample_post_id() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var( "SELECT ID FROM $wpdb->posts WHERE post_type = 'attachment' ORDER BY RAND() LIMIT 1;" );
	}

	protected function set_max_posts_to_query( $max_posts_to_query ) {
		add_filter(
			'vip_max_posts_to_query_for_mime_type_caching',
			function () use ( $max_posts_to_query ) {
				return $max_posts_to_query;
			}
		);
	}

	protected function get_cached_mime_types() {
		return wp_cache_get( Media_Library_Caching::AVAILABLE_MIME_TYPES_CACHE_KEY, Media_Library_Caching::CACHE_GROUP );
	}

	protected function is_using_default_mime_types() {
		return wp_cache_get( Media_Library_Caching::USING_DEFAULT_MIME_TYPES_CACHE_KEY, Media_Library_Caching::CACHE_GROUP );
	}

	public function test__filters_not_loaded_for_old_versions() {
		global $wp_version;

		$wp_version = '6.3';
		$this->call_init();

		$this->assertFalse( has_filter( 'pre_get_available_post_mime_types' ) );
	}

	public function test__filters_not_loaded_when_caching_disabled() {
		$this->check_wp_version();

		add_filter( 'vip_cache_mime_types', '__return_false' );
		$this->call_init();

		$this->assertFalse( has_filter( 'pre_get_available_post_mime_types' ) );
	}

	public function test__default_mime_types() {
		$this->check_wp_version();

		$this->set_max_posts_to_query( 5 );
		$returned_post_mime_types = Media_Library_Caching::get_cached_post_mime_types( null, 'attachment' );
		$using_default_mime_types = $this->is_using_default_mime_types();

		$this->assertTrue( $using_default_mime_types );
		$this->assertIsArray( $returned_post_mime_types );
		$this->assertContains( 'image', $returned_post_mime_types );
		$this->assertContains( 'audio', $returned_post_mime_types );
		$this->assertContains( 'video', $returned_post_mime_types );
	}

	public function test__get_cached_mime_types() {
		$this->check_wp_version();

		$returned_post_mime_types = Media_Library_Caching::get_cached_post_mime_types( null, 'attachment' );
		$cached_post_mime_types   = $this->get_cached_mime_types();

		$this->assertIsArray( $cached_post_mime_types );
		$this->assertEquals( $cached_post_mime_types, $returned_post_mime_types );
		$this->assertContains( 'image/jpeg', $cached_post_mime_types );
		$this->assertContains( 'video/mp4', $cached_post_mime_types );
		$this->assertContains( 'audio/mpeg', $cached_post_mime_types );
		$this->assertContains( 'application/pdf', $cached_post_mime_types );
	}

	public function test__get_cached_mime_types_with_previous_data() {
		$this->check_wp_version();

		$mime_types               = array( 'image/test' );
		$returned_post_mime_types = Media_Library_Caching::get_cached_post_mime_types( $mime_types, 'attachment' );

		$this->assertIsArray( $returned_post_mime_types );
		$this->assertContains( 'image/test', $returned_post_mime_types );
		$this->assertContains( 'image/jpeg', $returned_post_mime_types );
	}

	public function test__media_library_form_output() {
		$this->check_wp_version();

		$matched_mime_types = array();
		$this->call_init();

		// Extract the logic from media_upload_library_form() in WP core that populates the media types dropdown in wp-admin.
		list( $post_mime_types, $avail_post_mime_types ) = wp_edit_attachments_query();

		foreach ( $post_mime_types as $mime_type => $label ) {
			if ( wp_match_mime_types( $mime_type, $avail_post_mime_types ) ) {
				$matched_mime_types[] = $label[0];
			}
		}

		$this->assertContains( 'Images', $matched_mime_types );
		$this->assertContains( 'Audio', $matched_mime_types );
		$this->assertContains( 'Video', $matched_mime_types );
		$this->assertContains( 'Documents', $matched_mime_types );
	}

	public function test__get_cached_mime_types_other_post_types() {
		$this->check_wp_version();

		$returned_post_mime_types = Media_Library_Caching::get_cached_post_mime_types( null, 'post' );

		$this->assertNull( $returned_post_mime_types );
	}

	public function test__add_attachment() {
		$this->check_wp_version();
		$new_mime_type = 'image/bmp';

		// Register hooks and populate cache.
		$this->call_init();
		Media_Library_Caching::get_cached_post_mime_types( null, 'attachment' );

		$using_default_mime_types      = $this->is_using_default_mime_types();
		$cached_post_mime_types_before = $this->get_cached_mime_types();
		$result                        = $this->factory->post->create(
			array(
				'post_title'     => 'Mock Attachment 11',
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => $new_mime_type,
			)
		);
		$cached_post_mime_types_after  = $this->get_cached_mime_types();

		$this->assertFalse( $using_default_mime_types );
		$this->assertNotEquals( $cached_post_mime_types_before, $cached_post_mime_types_after );
		$this->assertNotContains( $new_mime_type, $cached_post_mime_types_before );
		$this->assertContains( $new_mime_type, $cached_post_mime_types_after );
		$this->assertIsInt( $result );
	}

	public function test__add_attachment_when_using_default_mime_types() {
		$this->check_wp_version();
		$new_mime_type = 'image/bmp';

		$this->set_max_posts_to_query( 5 );

		// Register hooks and populate cache.
		$this->call_init();
		Media_Library_Caching::get_cached_post_mime_types( null, 'attachment' );

		$using_default_mime_types      = $this->is_using_default_mime_types();
		$cached_post_mime_types_before = $this->get_cached_mime_types();
		$result                        = $this->factory->post->create(
			array(
				'post_title'     => 'Mock Attachment 11',
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => $new_mime_type,
			)
		);
		$cached_post_mime_types_after  = $this->get_cached_mime_types();

		$this->assertTrue( $using_default_mime_types );
		$this->assertEquals( $cached_post_mime_types_before, $cached_post_mime_types_after );
		$this->assertNotContains( $new_mime_type, $cached_post_mime_types_before );
		$this->assertNotContains( $new_mime_type, $cached_post_mime_types_after );
		$this->assertIsInt( $result );
	}

	public function test__update_attachment_title() {
		$this->check_wp_version();

		// Register hooks and populate cache.
		$this->call_init();
		Media_Library_Caching::get_cached_post_mime_types( null, 'attachment' );

		$sample_post_id                = $this->get_sample_post_id();
		$cached_post_mime_types_before = $this->get_cached_mime_types();
		$result                        = wp_update_post(
			array(
				'ID'         => $sample_post_id,
				'post_title' => 'New Post Title',
			)
		);
		$cached_post_mime_types_after  = $this->get_cached_mime_types();

		$this->assertNotInstanceOf( 'WP_Error', $result );
		$this->assertEquals( $sample_post_id, $result );
		$this->assertIsArray( $cached_post_mime_types_before );
		$this->assertEquals( $cached_post_mime_types_before, $cached_post_mime_types_after );
	}

	public function test__update_attachment_mime_type() {
		$this->check_wp_version();

		// Register hooks and populate cache.
		$this->call_init();
		Media_Library_Caching::get_cached_post_mime_types( null, 'attachment' );

		$sample_post_id                = $this->get_sample_post_id();
		$cached_post_mime_types_before = $this->get_cached_mime_types();
		$result                        = wp_update_post(
			array(
				'ID'             => $sample_post_id,
				'post_mime_type' => 'image/tiff',
			)
		);
		$cached_post_mime_types_after  = $this->get_cached_mime_types();

		$this->assertNotInstanceOf( 'WP_Error', $result );
		$this->assertEquals( $sample_post_id, $result );
		$this->assertIsArray( $cached_post_mime_types_before );
		$this->assertFalse( $cached_post_mime_types_after );
	}

	public function test__delete_attachment() {
		$this->check_wp_version();

		// Register hooks and populate cache.
		$this->call_init();
		Media_Library_Caching::get_cached_post_mime_types( null, 'attachment' );

		$sample_post_id                = $this->get_sample_post_id();
		$cached_post_mime_types_before = $this->get_cached_mime_types();
		$result                        = wp_delete_attachment( $sample_post_id, true );
		$cached_post_mime_types_after  = $this->get_cached_mime_types();

		$this->assertInstanceOf( 'WP_Post', $result );
		$this->assertIsArray( $cached_post_mime_types_before );
		$this->assertFalse( $cached_post_mime_types_after );
	}
}
