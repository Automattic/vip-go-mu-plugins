<?php

namespace Automattic\VIP\Performance;

use WP_UnitTestCase;

/**
 * Unit tests for MIME_Types_Caching class.
 */
class Mime_Types_Caching_Test extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->check_wp_version();
		$this->mock_attachments_data();
	}

	protected function check_wp_version() {
		global $wp_version;

		// Skip tests if WordPress version is lower than MINIMUM_WORDPRESS_VERSION.
		if ( version_compare( $wp_version, Mime_Types_Caching::MINIMUM_WORDPRESS_VERSION, '<' ) ) {
			$this->markTestSkipped( 'This test does not run for WordPress versions below ' . Mime_Types_Caching::MINIMUM_WORDPRESS_VERSION );
		}
	}

	protected function mock_attachments_data() {
		$this->factory()->post->create(
			array(
				'post_title'     => 'Mock Attachment 01',
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
			)
		);
		$this->factory()->post->create(
			array(
				'post_title'     => 'Mock Attachment 02',
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
			)
		);
		$this->factory()->post->create(
			array(
				'post_title'     => 'Mock Attachment 03',
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
			)
		);
		$this->factory()->post->create(
			array(
				'post_title'     => 'Mock Attachment 04',
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/gif',
			)
		);
		$this->factory()->post->create(
			array(
				'post_title'     => 'Mock Attachment 05',
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/gif',
			)
		);
		$this->factory()->post->create(
			array(
				'post_title'     => 'Mock Attachment 06',
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/png',
			)
		);
		$this->factory()->post->create(
			array(
				'post_title'     => 'Mock Attachment 07',
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'application/octet-stream',
			)
		);
		$this->factory()->post->create(
			array(
				'post_title'     => 'Mock Attachment 08',
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'video/mp4',
			)
		);
		$this->factory()->post->create(
			array(
				'post_title'     => 'Mock Attachment 09',
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => 'audio/mpeg',
			)
		);
		$this->factory()->post->create(
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

	protected function get_cached_mime_types() {
		return wp_cache_get( Mime_Types_Caching::MIME_TYPES_CACHE_KEY, Mime_Types_Caching::CACHE_GROUP )['available_types'] ?? false;
	}

	protected function is_using_default_mime_types() {
		return wp_cache_get( Mime_Types_Caching::MIME_TYPES_CACHE_KEY, Mime_Types_Caching::CACHE_GROUP )['using_defaults'] ?? false;
	}

	public function test__mime_type_caching_hooked_on_init() {
		$tag      = 'pre_get_available_post_mime_types';
		$function = array( 'Automattic\VIP\Performance\Mime_Types_Caching', 'get_cached_post_mime_types' );

		// Filter is already loaded on init action.
		$before_filter_removal = remove_filter( $tag, $function );
		$after_filter_removal  = has_filter( $tag, $function );
		\vip_cache_mime_types(); // Call to class init to re-hook the filter.
		$filter_after_class_init = has_filter( $tag, $function );

		$this->assertNotFalse( has_action( 'init', 'vip_cache_mime_types' ) );
		$this->assertTrue( $before_filter_removal );
		$this->assertFalse( $after_filter_removal );
		$this->assertNotFalse( $filter_after_class_init );
	}

	public function test__default_mime_types() {
		$max_query_filter = function () {
			return 5;
		};

		add_filter( 'vip_max_posts_to_query_for_mime_type_caching', $max_query_filter );
		$returned_post_mime_types = get_available_post_mime_types();
		$using_default_mime_types = $this->is_using_default_mime_types();
		remove_filter( 'vip_max_posts_to_query_for_mime_type_caching', $max_query_filter );

		$this->assertTrue( $using_default_mime_types );
		$this->assertIsArray( $returned_post_mime_types );
		$this->assertContains( 'image', $returned_post_mime_types );
		$this->assertContains( 'audio', $returned_post_mime_types );
		$this->assertContains( 'video', $returned_post_mime_types );
	}

	public function test__get_cached_mime_types() {
		$returned_post_mime_types = get_available_post_mime_types();
		$cached_post_mime_types   = $this->get_cached_mime_types();

		// Perform a second call to get_available_post_mime_types() to ensure the cached results are returned.
		$returned_post_mime_types_2 = get_available_post_mime_types();

		$this->assertIsArray( $cached_post_mime_types );
		$this->assertEquals( $cached_post_mime_types, $returned_post_mime_types );
		$this->assertEquals( $returned_post_mime_types, $returned_post_mime_types_2 );
		$this->assertContains( 'image/jpeg', $cached_post_mime_types );
		$this->assertContains( 'video/mp4', $cached_post_mime_types );
		$this->assertContains( 'audio/mpeg', $cached_post_mime_types );
		$this->assertContains( 'application/pdf', $cached_post_mime_types );
	}

	public function test__get_cached_mime_types_with_previous_data() {
		$previous_filter = function () {
			return array( 'image/test' );
		};

		add_filter( 'pre_get_available_post_mime_types', $previous_filter, 1, 2 );
		$returned_post_mime_types = get_available_post_mime_types();
		remove_filter( 'pre_get_available_post_mime_types', $previous_filter, 1 );

		$this->assertIsArray( $returned_post_mime_types );
		$this->assertContains( 'image/test', $returned_post_mime_types );
		$this->assertContains( 'image/jpeg', $returned_post_mime_types );
	}

	public function test__media_library_form_output() {
		$matched_mime_types = array();

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
		$returned_post_mime_types = get_available_post_mime_types( 'post' );
		$cached_post_mime_types   = $this->get_cached_mime_types();

		$this->assertEmpty( $returned_post_mime_types );
		$this->assertFalse( $cached_post_mime_types );
	}

	public function test__count_query_failure() {
		global $wpdb;

		// Backup the original $wpdb object.
		$original_wpdb = $wpdb;

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Mock $wpdb.
		$mock_builder = $this->getMockBuilder( \wpdb::class )
							->setConstructorArgs( array( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST ) );

		if ( method_exists( $mock_builder, 'onlyMethods' ) ) {
			$mock_builder = $mock_builder->onlyMethods( array( 'get_var' ) );
		} else {
			$mock_builder = $mock_builder->setMethods( array( 'get_var' ) );
		}

		$wpdb = $mock_builder->getMock();
		$wpdb->method( 'get_var' )->willReturn( null );

		get_available_post_mime_types();
		$using_default_mime_types = $this->is_using_default_mime_types();

		// Restore the original $wpdb object.
		$wpdb = $original_wpdb;
		// phpcs:enable

		// If the count query fails, the default MIME types should be used.
		$this->assertTrue( $using_default_mime_types );
	}

	public function test__add_attachment() {
		$new_mime_type = 'image/bmp';

		get_available_post_mime_types();

		$using_default_mime_types      = $this->is_using_default_mime_types();
		$cached_post_mime_types_before = $this->get_cached_mime_types();
		$result                        = $this->factory()->post->create(
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
		$max_query_filter = function () {
			return 7;
		};

		add_filter( 'vip_max_posts_to_query_for_mime_type_caching', $max_query_filter );
		get_available_post_mime_types();

		$using_default_mime_types      = $this->is_using_default_mime_types();
		$cached_post_mime_types_before = $this->get_cached_mime_types();
		$new_mime_type                 = 'image/bmp';
		$result                        = $this->factory()->post->create(
			array(
				'post_title'     => 'Mock Attachment 11',
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'post_mime_type' => $new_mime_type,
			)
		);
		$cached_post_mime_types_after  = $this->get_cached_mime_types();
		remove_filter( 'vip_max_posts_to_query_for_mime_type_caching', $max_query_filter );

		$this->assertTrue( $using_default_mime_types );
		$this->assertEquals( $cached_post_mime_types_before, $cached_post_mime_types_after );
		$this->assertNotContains( $new_mime_type, $cached_post_mime_types_before );
		$this->assertNotContains( $new_mime_type, $cached_post_mime_types_after );
		$this->assertIsInt( $result );
	}

	public function test__update_attachment_title() {
		get_available_post_mime_types();

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
		get_available_post_mime_types();

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
		get_available_post_mime_types();

		$sample_post_id                = $this->get_sample_post_id();
		$cached_post_mime_types_before = $this->get_cached_mime_types();
		$result                        = wp_delete_attachment( $sample_post_id, true );
		$cached_post_mime_types_after  = $this->get_cached_mime_types();

		$this->assertInstanceOf( 'WP_Post', $result );
		$this->assertIsArray( $cached_post_mime_types_before );
		$this->assertFalse( $cached_post_mime_types_after );
	}
}
