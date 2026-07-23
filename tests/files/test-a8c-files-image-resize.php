<?php

class VIP_Go_A8C_Files_Image_Resize_Test extends WP_UnitTestCase {
	private const MISSING_OPTION = '__vip_go_missing_option__';

	private $original_content_width;
	private $original_large_size_h;
	private $original_large_size_w;
	private $original_stylesheet;
	private $original_template;
	private $attachment_id;
	private $theme_directory;
	private $theme_json_layout_width_filter_calls = 0;

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'wp_theme_has_theme_json' ) ) {
			$this->markTestSkipped( 'Test requires theme.json support.' );
		}

		$this->original_content_width = $GLOBALS['content_width'] ?? null;
		unset( $GLOBALS['content_width'] );

		$this->original_large_size_w = get_option( 'large_size_w', self::MISSING_OPTION );
		$this->original_large_size_h = get_option( 'large_size_h', self::MISSING_OPTION );

		$this->original_stylesheet = get_stylesheet();
		$this->original_template   = get_template();
		$this->theme_directory     = VIP_GO_MUPLUGINS_TESTS__DIR__ . '/fixtures/themes';

		register_theme_directory( $this->theme_directory );
		wp_clean_themes_cache();
		switch_theme( 'vip-theme-json-layout' );
		$this->clean_theme_json_cache();

		$this->attachment_id = self::factory()->attachment->create_object(
			VIP_GO_MUPLUGINS_TESTS__DIR__ . '/fixtures/image.jpg',
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_type'      => 'attachment',
			)
		);

		update_post_meta( $this->attachment_id, '_wp_attached_file', 'image.jpg' );
		wp_update_attachment_metadata(
			$this->attachment_id,
			array(
				'width'  => 5472,
				'height' => 3080,
				'file'   => 'image.jpg',
				'sizes'  => array(),
			)
		);
	}

	public function tearDown(): void {
		remove_filter( 'vip_image_resize_use_theme_json_layout_widths', '__return_false' );
		remove_filter( 'vip_image_resize_use_theme_json_layout_widths', array( $this, 'count_theme_json_layout_width_filter_calls' ) );

		if ( $this->original_stylesheet ) {
			switch_theme( $this->original_template, $this->original_stylesheet );
			$this->clean_theme_json_cache();
		}

		$this->unregister_theme_directory();

		if ( null === $this->original_content_width ) {
			unset( $GLOBALS['content_width'] );
		} else {
			$GLOBALS['content_width'] = $this->original_content_width;
		}

		$this->restore_option( 'large_size_w', $this->original_large_size_w );
		$this->restore_option( 'large_size_h', $this->original_large_size_h );

		parent::tearDown();
	}

	public function test__image_resize__uses_theme_json_wide_size_for_unknown_size_when_content_width_missing() {
		$result = $this->resize_image( 'vip_unknown_size' );

		$this->assertSame( 1600, $result[1] );
		$this->assertUrlQueryArgSame( '1600', 'w', $result[0] );
	}

	public function test__image_resize__uses_theme_json_content_size_to_constrain_default_sizes() {
		update_option( 'large_size_w', 2000 );
		update_option( 'large_size_h', 2000 );

		$result = $this->resize_image( 'large' );

		$this->assertSame( 720, $result[1] );
		$this->assertUrlQueryArgSame( '720', 'w', $result[0] );
	}

	public function test__image_resize__prefers_global_content_width_over_theme_json_layout_widths() {
		$GLOBALS['content_width'] = 900;
		add_filter( 'vip_image_resize_use_theme_json_layout_widths', array( $this, 'count_theme_json_layout_width_filter_calls' ), 10, 3 );

		$result = $this->resize_image( 'vip_unknown_size' );

		$this->assertSame( 900, $result[1] );
		$this->assertUrlQueryArgSame( '900', 'w', $result[0] );
		$this->assertSame( 0, $this->theme_json_layout_width_filter_calls );
	}

	public function test__image_resize__allows_theme_json_layout_widths_to_be_disabled() {
		add_filter( 'vip_image_resize_use_theme_json_layout_widths', '__return_false' );

		$result = $this->resize_image( 'vip_unknown_size' );

		$this->assertSame( 1024, $result[1] );
		$this->assertUrlQueryArgSame( '1024', 'w', $result[0] );
	}

	public function count_theme_json_layout_width_filter_calls( $enabled ) {
		++$this->theme_json_layout_width_filter_calls;

		return $enabled;
	}

	private function resize_image( $size ) {
		$reflection = new ReflectionClass( A8C_Files::class );
		$files      = $reflection->newInstanceWithoutConstructor();

		return $files->image_resize( false, $this->attachment_id, $size );
	}

	private function assertUrlQueryArgSame( $expected, $arg, $url ) {
		$query_args = array();
		parse_str( wp_parse_url( $url, PHP_URL_QUERY ), $query_args );

		$this->assertArrayHasKey( $arg, $query_args );
		$this->assertSame( $expected, $query_args[ $arg ] );
	}

	private function clean_theme_json_cache() {
		if ( class_exists( 'WP_Theme_JSON_Resolver' ) ) {
			WP_Theme_JSON_Resolver::clean_cached_data();
		}
	}

	private function restore_option( $option, $value ) {
		if ( self::MISSING_OPTION === $value ) {
			delete_option( $option );
			return;
		}

		update_option( $option, $value );
	}

	private function unregister_theme_directory() {
		if ( $this->theme_directory && function_exists( 'unregister_theme_directory' ) ) {
			unregister_theme_directory( $this->theme_directory );
			wp_clean_themes_cache();
		}
	}
}
