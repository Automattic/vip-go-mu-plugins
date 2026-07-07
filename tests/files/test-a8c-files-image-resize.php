<?php

class VIP_Go_A8C_Files_Image_Resize_Test extends WP_UnitTestCase {
	private $original_content_width;
	private $original_stylesheet;
	private $attachment_id;

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'wp_theme_has_theme_json' ) ) {
			$this->markTestSkipped( 'Test requires theme.json support.' );
		}

		$this->original_content_width = $GLOBALS['content_width'] ?? null;
		unset( $GLOBALS['content_width'] );

		$this->original_stylesheet = get_stylesheet();

		register_theme_directory( VIP_GO_MUPLUGINS_TESTS__DIR__ . '/fixtures/themes' );
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
		if ( $this->original_stylesheet ) {
			switch_theme( $this->original_stylesheet );
			$this->clean_theme_json_cache();
		}

		if ( null === $this->original_content_width ) {
			unset( $GLOBALS['content_width'] );
		} else {
			$GLOBALS['content_width'] = $this->original_content_width;
		}

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

		$result = $this->resize_image( 'vip_unknown_size' );

		$this->assertSame( 900, $result[1] );
		$this->assertUrlQueryArgSame( '900', 'w', $result[0] );
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
}
