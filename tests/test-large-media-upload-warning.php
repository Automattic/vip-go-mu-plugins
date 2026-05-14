<?php

namespace Automattic\VIP\LargeMediaUploadWarning;

use WP_UnitTestCase;

class Large_Media_Upload_Warning_Test extends WP_UnitTestCase {
	private Large_Media_Upload_Warning $instance;

	public function setUp(): void {
		parent::setUp();
		$this->instance = new Large_Media_Upload_Warning();
	}

	public function test_disabled_by_default_at_first_release(): void {
		$this->assertFalse( $this->instance->is_enabled() );
	}

	public function test_enabled_filter_overrides_default(): void {
		add_filter( 'vip_large_media_warning_enabled', '__return_true' );
		$this->assertTrue( $this->instance->is_enabled() );
		remove_filter( 'vip_large_media_warning_enabled', '__return_true' );
	}

	public function test_constant_enables_module(): void {
		if ( ! defined( 'VIP_LARGE_MEDIA_WARNING_ENABLED' ) ) {
			define( 'VIP_LARGE_MEDIA_WARNING_ENABLED', true );
		}
		$this->assertTrue( $this->instance->is_enabled() );
	}

	public function test_default_threshold_is_8mb(): void {
		$this->assertSame( 8 * 1024 * 1024, $this->instance->get_threshold_bytes() );
	}

	public function test_threshold_filter_overrides_default(): void {
		add_filter( 'vip_large_media_warning_threshold_bytes', fn() => 524288 );
		$this->assertSame( 524288, $this->instance->get_threshold_bytes() );
		remove_all_filters( 'vip_large_media_warning_threshold_bytes' );
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
		remove_all_filters( 'vip_large_media_warning_mime_types' );
	}
}
