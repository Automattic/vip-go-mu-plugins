<?php

class VIP_Go_A8C_Files_Utils_Test extends WP_UnitTestCase {
	public function get_data_for_strip_dimensions_from_url_path() {
		return [
			'invalid-url' => [
				'invalid-url',
				'invalid-url',
			],

			'no-dimensions' => [
				'https://example.com/wp-content/uploads/image.jpg',
				'https://example.com/wp-content/uploads/image.jpg',
			],

			'dimensions-jpg' => [
				'https://example.com/wp-content/uploads/image-800x400.jpg',
				'https://example.com/wp-content/uploads/image.jpg',
			],

			'dimensions-png' => [
				'https://example.com/wp-content/uploads/image-800x400.png',
				'https://example.com/wp-content/uploads/image.png',
			],

			'dimensions-gif' => [
				'https://example.com/wp-content/uploads/image-800x400.gif',
				'https://example.com/wp-content/uploads/image.gif',
			],

			'dimensions-jpeg' => [
				'https://example.com/wp-content/uploads/image-800x400.jpeg',
				'https://example.com/wp-content/uploads/image.jpeg',
			],

			'dimensions-jpg-case-insensitive' => [
				'https://example.com/wp-content/uploads/Image-800x400.jPg',
				'https://example.com/wp-content/uploads/Image.jPg',
			],

			'double-dimensions' => [
				'https://example.com/wp-content/uploads/image-800x400-350x120.jpg',
				'https://example.com/wp-content/uploads/image-800x400.jpg',
			],

			'double-same-dimensions' => [
				'https://example.com/wp-content/uploads/image-400x400-400x400.jpg',
				'https://example.com/wp-content/uploads/image-400x400.jpg',
			],
		];
	}

	/**
	 * @dataProvider get_data_for_strip_dimensions_from_url_path
	 */
	public function test__strip_dimensions_from_url_path( $source, $expected ) {
		$actual = A8C_Files_Utils::strip_dimensions_from_url_path( $source );

		$this->assertEquals( $actual, $expected );
	}
}
