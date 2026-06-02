<?php

namespace Automattic\VIP\LargeMediaUploadWarning;

use Automattic\Test\Constant_Mocker;
use WP_UnitTestCase;

class Large_Media_Upload_Warning_Test extends WP_UnitTestCase {
	private Large_Media_Upload_Warning $instance;

	public function setUp(): void {
		parent::setUp();
		$this->instance = new Large_Media_Upload_Warning();
	}

	public function tearDown(): void {
		// Explicitly clear every filter this module exposes. `WP_UnitTestCase`
		// already restores hooks via its own mechanism, but tests that add
		// throwing or otherwise-disruptive callbacks (see
		// test_filter_swallows_exceptions_and_returns_unmodified_file) must
		// not leak even if an assertion failure short-circuits the test body
		// before any inline cleanup runs.
		remove_all_filters( 'vip_large_media_warning_enabled' );
		remove_all_filters( 'vip_large_media_warning_threshold_bytes' );
		remove_all_filters( 'vip_large_media_warning_mime_types' );
		remove_all_filters( 'pre_vip_large_media_warning_log' );

		Constant_Mocker::clear();
		parent::tearDown();
	}

	public function test_disabled_by_default_at_first_release(): void {
		$this->assertFalse( $this->instance->is_enabled() );
	}

	public function test_enabled_filter_overrides_default(): void {
		add_filter( 'vip_large_media_warning_enabled', '__return_true' );
		$this->assertTrue( $this->instance->is_enabled() );
	}

	public function test_constant_enables_module(): void {
		Constant_Mocker::define( 'VIP_LARGE_MEDIA_WARNING_ENABLED', true );
		$this->assertTrue( $this->instance->is_enabled() );
	}

	public function test_default_threshold_is_8mb(): void {
		$this->assertSame( 8 * 1024 * 1024, $this->instance->get_threshold_bytes() );
	}

	public function test_threshold_filter_overrides_default(): void {
		add_filter( 'vip_large_media_warning_threshold_bytes', fn() => 524288 );
		$this->assertSame( 524288, $this->instance->get_threshold_bytes() );
	}

	public function test_threshold_constant_overrides_default(): void {
		Constant_Mocker::define( 'VIP_LARGE_MEDIA_WARNING_THRESHOLD_BYTES', 12345 );
		$this->assertSame( 12345, $this->instance->get_threshold_bytes() );
	}

	public function test_default_mime_allowlist_contains_jpeg_png_webp(): void {
		$mimes = $this->instance->get_allowed_mime_types();
		$this->assertContains( 'image/jpeg', $mimes );
		$this->assertContains( 'image/png', $mimes );
		$this->assertContains( 'image/webp', $mimes );
	}

	public function test_mime_filter_overrides_default(): void {
		add_filter( 'vip_large_media_warning_mime_types', fn() => [ 'image/avif' ] );
		$this->assertSame( [ 'image/avif' ], $this->instance->get_allowed_mime_types() );
	}

	public function test_filter_large_image_logs_to_logstash(): void {
		$captured = [];
		add_filter( 'pre_vip_large_media_warning_log', function ( $value, $data ) use ( &$captured ) {
			$captured[] = $data;
			return true; // non-null short-circuits the real Logstash call
		}, 10, 2 );

		add_filter( 'vip_large_media_warning_threshold_bytes', fn() => 1024 );

		$file_in = [
			'name'     => 'big.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => '/tmp/whatever',
			'error'    => 0,
			'size'     => 2048,
		];

		$file_out = $this->instance->maybe_log_large_upload( $file_in );

		$this->assertSame( $file_in, $file_out, 'Filter must never mutate the file array.' );
		$this->assertCount( 1, $captured );
		$this->assertSame( 'large_media_upload_attempted', $captured[0]['feature'] );
		$this->assertSame( 2048, $captured[0]['extra']['size'] );
		$this->assertSame( 'image/jpeg', $captured[0]['extra']['mime'] );
	}

	public function test_filter_small_image_does_not_log(): void {
		$captured = [];
		add_filter( 'pre_vip_large_media_warning_log', function ( $value, $data ) use ( &$captured ) {
			$captured[] = $data;
			return true; // non-null short-circuits the real Logstash call
		}, 10, 2 );

		add_filter( 'vip_large_media_warning_threshold_bytes', fn() => 10000 );

		$file_in = [
			'name'     => 'small.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => '/tmp/small',
			'error'    => 0,
			'size'     => 1234,
		];

		$file_out = $this->instance->maybe_log_large_upload( $file_in );

		$this->assertSame( $file_in, $file_out );
		$this->assertCount( 0, $captured );
	}

	public function test_filter_non_image_mime_does_not_log(): void {
		$captured = [];
		add_filter( 'pre_vip_large_media_warning_log', function ( $value, $data ) use ( &$captured ) {
			$captured[] = $data;
			return true; // non-null short-circuits the real Logstash call
		}, 10, 2 );
		add_filter( 'vip_large_media_warning_threshold_bytes', fn() => 1024 );

		$file_in = [
			'name'     => 'big.pdf',
			'type'     => 'application/pdf',
			'tmp_name' => '/tmp/x',
			'error'    => 0,
			'size'     => 9999,
		];

		$file_out = $this->instance->maybe_log_large_upload( $file_in );

		$this->assertSame( $file_in, $file_out );
		$this->assertCount( 0, $captured );
	}

	public function test_filter_never_sets_error_even_for_oversized(): void {
		add_filter( 'vip_large_media_warning_threshold_bytes', fn() => 1 );

		$file_in = [
			'name'     => 'huge.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => '/tmp/huge',
			'error'    => 0,
			'size'     => 100 * 1024 * 1024,
		];

		$file_out = $this->instance->maybe_log_large_upload( $file_in );

		$this->assertArrayHasKey( 'error', $file_out );
		$this->assertSame( 0, $file_out['error'] );
	}

	public function test_filter_swallows_exceptions_and_returns_unmodified_file(): void {
		// phpcs:ignore WordPressVIPMinimum.Hooks.AlwaysReturnInFilter.MissingReturnStatement -- Deliberate throw inside the filter is the test seam for the try/catch path.
		add_filter( 'vip_large_media_warning_threshold_bytes', function () {
			throw new \RuntimeException( 'simulated failure inside try block' );
		} );

		$file_in = [
			'name'     => 'oops.jpg',
			'type'     => 'image/jpeg',
			'tmp_name' => '/tmp/oops',
			'error'    => 0,
			'size'     => 5 * 1024 * 1024,
		];

		$file_out = $this->instance->maybe_log_large_upload( $file_in );

		$this->assertSame( $file_in, $file_out );
		// Cleanup of the throwing filter is handled in tearDown() — see the
		// comment there for why this is in tearDown rather than inline.
	}
}
