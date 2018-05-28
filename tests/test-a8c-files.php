<?php

class A8C_Files_Test extends WP_UnitTestCase {
	private $a8c_files;

	public function setUp() {
		parent::setUp();

		$this->a8c_files = new A8C_Files;
	}

	public function tearDown() {
		$this->a8c_files = null;

		parent::tearDown();
	}

	public function test__upload_size_limit__no_override() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'Not applicable for multisite installs.' );
		}

		$expected_upload_size = GB_IN_BYTES;

		$actual_upload_size = wp_max_upload_size();

		$this->assertEquals( $expected_upload_size, $actual_upload_size );
	}

	public function test__upload_size_limit__ms_no_override() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Not applicable for single site installs' );
		}

		add_filter( 'pre_option_upload_space_check_disabled', '__return_true' );

		$expected_upload_size = GB_IN_BYTES;

		$actual_upload_size = wp_max_upload_size();

		$this->assertEquals( $expected_upload_size, $actual_upload_size );
	}

	public function test__upload_size_limit__ms_with_override() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Not applicable for single site installs' );
		}

		$expected_upload_size = GB_IN_BYTES - 1;
		add_filter( 'pre_option_upload_space_check_disabled', '__return_false' );
		add_filter( 'fileupload_maxk', function() {
			// Core's filter will apply this value and pass it through for us.
			return GB_IN_BYTES - 1;
		} );

		$actual_upload_size = wp_max_upload_size();

		$this->assertEquals( $expected_upload_size, $actual_upload_size );
	}
}
