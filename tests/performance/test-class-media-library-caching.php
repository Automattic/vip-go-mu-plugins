<?php

namespace Automattic\VIP\Performance;

use WP_UnitTestCase;

require_once __DIR__ . '/../../performance/class-media-library-caching.php';

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

	protected function mock_attachments_data() {
		$this->factory->post->create( array( 'post_title' => 'Mock Attachment 01', 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image/jpeg' ) );
		$this->factory->post->create( array( 'post_title' => 'Mock Attachment 02', 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image/jpeg' ) );
		$this->factory->post->create( array( 'post_title' => 'Mock Attachment 03', 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image/jpeg' ) );
		$this->factory->post->create( array( 'post_title' => 'Mock Attachment 04', 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image/gif' ) );
		$this->factory->post->create( array( 'post_title' => 'Mock Attachment 05', 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image/gif' ) );
		$this->factory->post->create( array( 'post_title' => 'Mock Attachment 06', 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image/png' ) );
		$this->factory->post->create( array( 'post_title' => 'Mock Attachment 07', 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'application/octet-stream' ) );
		$this->factory->post->create( array( 'post_title' => 'Mock Attachment 08', 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'video/mp4' ) );
		$this->factory->post->create( array( 'post_title' => 'Mock Attachment 09', 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'audio/mpeg' ) );
		$this->factory->post->create( array( 'post_title' => 'Mock Attachment 10', 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'application/pdf' ) );
	}

	public function test__filters_not_loaded_for_old_versions() {
		global $wp_version;

		$wp_version = '6.3';
		Media_Library_Caching::init();

		$this->assertEquals( false, has_filter( 'pre_get_available_post_mime_types' ) );
	}

	public function test__default_mime_types() {
		$this->check_wp_version();

		$default_mime_types = Media_Library_Caching::get_default_mime_types();

		$this->assertIsArray( $default_mime_types );
		$this->assertContains( 'image', $default_mime_types );
		$this->assertContains( 'audio', $default_mime_types );
		$this->assertContains( 'video', $default_mime_types );
	}

	public function test__get_cached_mime_types() {
		$this->check_wp_version();

		$returned_post_mime_types = Media_Library_Caching::get_cached_post_mime_types( null, 'attachment' );
		$cached_post_mime_types   = wp_cache_get( Media_Library_Caching::AVAILABLE_MIME_TYPES_CACHE_KEY, Media_Library_Caching::CACHE_GROUP );

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
		Media_Library_Caching::init();

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
}
