<?php

class VIP_Intermediate_Images_Test extends WP_UnitTestCase {
	public function get_data_for_is_valid_image_url() {
		return [
			'image_with_not_allowed_types' => [
				'http://external-url.com/image.tiff',
				false,
			],

			'image_on_home_url' => [
				'http://example.com/image.jpg',
				true,
			],

			'image_on_site_url' => [
				'http://subdomain.example.com/image.jpg',
				true,
			],

			'image_on_go-vip-co_url' => [
				'http://example.go-vip.co/image.jpg',
				true,
			],

			'image_on_go-vip-net_url' => [
				'http://example.go-vip.net/image.jpg',
				true,
			],

			'image_on_external_url' => [
				'http://external-url.com/image.jpg',
				false,
			],
		];
	}

	/**
	 * @covers       VIP_Intermediate_Images::is_valid_image_url
	 * @dataProvider get_data_for_is_valid_image_url
	 */
	public function test__is_valid_image_url( $image_url, $expected_boolean ) {
		add_filter( 'home_url', function () {
			return 'http://example.com';
		} );

		add_filter( 'site_url', function () {
			return 'http://subdomain.example.com';
		} );

		$actual_boolean = VIP_Intermediate_Images::is_valid_image_url( $image_url );

		$this->assertSame( $expected_boolean, $actual_boolean );
	}

	public function get_data_for_convert_dimensions_from_filename() {
		return [
			'invalid-url'   => [
				'invalid-url',
				'invalid-url',
			],

			'no-dimensions' => [
				'https://example.com/wp-content/uploads/image.jpg',
				'https://example.com/wp-content/uploads/image.jpg',
			],

			'dimensions-jpg' => [
				'https://example.com/wp-content/uploads/image-800x400.jpg',
				'https://example.com/wp-content/uploads/image.jpg?resize=800,400',
			],

			'dimensions-png' => [
				'https://example.com/wp-content/uploads/image-800x400.png',
				'https://example.com/wp-content/uploads/image.png?resize=800,400',
			],

			'dimensions-gif' => [
				'https://example.com/wp-content/uploads/image-800x400.gif',
				'https://example.com/wp-content/uploads/image.gif?resize=800,400',
			],

			'dimensions-jpeg' => [
				'https://example.com/wp-content/uploads/image-800x400.jpeg',
				'https://example.com/wp-content/uploads/image.jpeg?resize=800,400',
			],

			'dimensions-jpg-case-insensitive' => [
				'https://example.com/wp-content/uploads/Image-800x400.jPg',
				'https://example.com/wp-content/uploads/Image.jPg?resize=800,400',
			],

			'double-dimensions' => [
				'https://example.com/wp-content/uploads/image-800x400-350x120.jpg',
				'https://example.com/wp-content/uploads/image-800x400.jpg?resize=350,120',
			],

			'double-same-dimensions' => [
				'https://example.com/wp-content/uploads/image-400x400-400x400.jpg',
				'https://example.com/wp-content/uploads/image-400x400.jpg?resize=400,400',
			],

			'dimensions-with-resize-param' => [
				'https://example.com/wp-content/uploads/image-400x450.png?resize=338%2C600&strip=all',
				'https://example.com/wp-content/uploads/image.png?resize=400,450&strip=all',
			],

			'dimensions-with-querystring' => [
				'https://example.com/wp-content/uploads/image-400x450.png?q=value',
				'https://example.com/wp-content/uploads/image.png?q=value&resize=400,450',
			],
		];
	}

	/**
	 * @covers       VIP_Intermediate_Images::convert_dimensions_from_filename
	 * @dataProvider get_data_for_convert_dimensions_from_filename
	 */
	public function test__convert_dimensions_from_filename( $source, $expected ) {
		$actual = VIP_Intermediate_Images::convert_dimensions_from_filename( $source );

		$this->assertSame( $expected, $actual );
	}
}