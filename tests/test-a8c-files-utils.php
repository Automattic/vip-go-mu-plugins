<?php

class VIP_Go_A8C_Files_Utils_Test extends WP_UnitTestCase {
	public function get_data_for_normalize_quality() {
		return [
			'100_is_forced_lower' => [
				100,
				99,
				true,
			],

			'string_100_is_also_forced_lower' => [
				'100',
				99,
				true,
			],

			'below_100_is_fine' => [
				99,
				99,
				false,
			],

			'string_99_is_also_fine' => [
				'99',
				99,
				false,
			],

			'special_const_allows_100' => [
				WPCOM_VIP_IMAGE_QUALITY_100,
				100,
				false,
			],
		];
	}

	public function get_data_for_filter_photon_domain() {
		return [
			'image_on_home_url' => [
				'http://example.com/image.jpg',
				'http://example.com',
			],

			'image_on_go-vip_url' => [
				'http://example.go-vip.co/image.jpg',
				'http://example.go-vip.co',
			],

			'image_on_external_url' => [
				'http://external-url.com/image.jpg',
				'http://i0.wp.com',
			],
		];
	}

	/**
	 * @dataProvider get_data_for_normalize_quality
	 */
	public function test__normalize_quality( $quality, $expected, $expected_doing_it_wrong = false ) {
		if ( $expected_doing_it_wrong ) {
			$this->setExpectedIncorrectUsage( 'A8C_Files_Utils::normalize_quality' ); 
		}	
		$actual = A8C_Files_Utils::normalize_quality( $quality );

		$this->assertEquals( $actual, $expected );
  }

	/*
	 * @dataProvider get_data_for_filter_photon_domain
	 */
	public function test__filter_photon_domain( $image_url, $expected_photon_url ) {
		add_filter( 'home_url', function() {
			return 'http://example.com';
		} );

		$actual_photon_url = A8C_Files_Utils::filter_photon_domain( 'http://i0.wp.com', $image_url );

		$this->assertEquals( $expected_photon_url, $actual_photon_url );
	}

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

			'dimensions-with-querystring' => [
				'https://example.com/wp-content/uploads/image-400x450.png?resize=338%2C600&strip=all',
				'https://example.com/wp-content/uploads/image.png?resize=338%2C600&strip=all',
			],
		];
	}

	/**
	 * @dataProvider get_data_for_strip_dimensions_from_url_path
	 */
	public function test__strip_dimensions_from_url_path( $source, $expected ) {
		$actual = A8C_Files_Utils::strip_dimensions_from_url_path( $source );

		$this->assertEquals( $expected, $actual );
	}
}
