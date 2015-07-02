<?php
/**
 *
 * Test Suite for Frontend Uploader
 *
 * @since 0.5
 *
 */
class Frontend_Uploader_UnitTestCase extends WP_UnitTestCase {
	public $fu;

	/**
	 * Init
	 * @return [type] [description]
	 */
	function setup() {
		parent::setup();
		global $frontend_uploader;
		$this->fu = $frontend_uploader;
	}

	function teardown() {
	}

	// Check if settings get set up on activation
	function test_default_settings() {
		$this->assertNotEmpty( $this->fu->settings );
	}

	// Test if the post has gallery shortcode and needs to be updated with the new att id
	function test_gallery_shortcode_update() {
	}

	// Check if errors are handled properly
	function test_error_handling() {

	}

	function test_mime_types() {
		$mimes =  $this->fu->_get_mime_types();
		$this->assertNotEmpty( $mimes );
		$this->assertInternalType( 'array', $mimes );

		$this->assertGreaterThan( 0, has_filter( 'upload_mimes',  array( $this->fu, '_get_mime_types' ) ) );
	}

	function test_successful_file_upload() {

	}

	function test_failed_file_upload() {

	}

	function test_successful_post_submit() {

	}

	function test_failed_post_submit() {

	}
}