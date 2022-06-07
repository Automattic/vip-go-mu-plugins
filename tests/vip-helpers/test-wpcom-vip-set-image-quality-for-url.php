<?php

namespace Automattic\VIP\Tests;

use WP_UnitTestCase;

class WPCOM_VIP_Set_Image_Quality_For_Url_Test extends WP_UnitTestCase {

	private const ATTACHMENT_URL = 'https://example.com/wp-contents/uploads/2020/08/foo.jpg';

	public function get_data_for_testing_args() {
		return [
			'Valid args'                    => [
				null,
				80,
				'info',
				'?quality=80&strip=info',
			],
			'Quality greater than 100'      => [ // How does VIP File Service handle this?
				null,
				101,
				false,
				'?quality=101',
			],
			'Quality is a negative integer' => [
				null,
				-20,
				false,
				'?quality=20',
			],
			'Quality is a string'           => [
				null,
				'ninety-nine',
				false,
				'?quality=0', // Should there be a better value if the argument isn't valid?
			],
			'Quality is boolean true'       => [
				null,
				true,
				false,
				'?quality=1', // Should there be a better value if the argument isn't valid?
			],
			'Quality is boolean false'      => [
				null,
				false,
				false,
				'?quality=0', // Should there be a better value if the argument isn't valid?
			],
			'Strip is boolean true'         => [
				null,
				100,
				true,
				'?quality=100&strip=all',
			],
			'Strip is a positive int'       => [
				null,
				100,
				99,
				'?quality=100&strip=99', // Should there be a better value if the argument isn't valid?
			],
			'Strip is a zero int'           => [
				null,
				100,
				0,
				'?quality=100',
			],
			'Strip is a negative int'       => [
				null,
				100,
				-99,
				'?quality=100&strip=-99', // Should there be a better value if the argument isn't valid?
			],
			'URL is valid, but does not contain a jpg or jpeg' => [
				'https://example.com/foo.png?foo=bar',
				100,
				false,
				'',
			],
			'URL is valid, contains jpg or jpeg in the path but not in an extension' => [
				'https://example.com/jpgs/foo.png',
				80,
				'all',
				'',
			],
			'URL is valid, contains jpg or jpeg in the querystring but not in an extension' => [
				'https://example.com/foo.png?image-type=jpeg',
				80,
				'all',
				'',
			],
			'URL is not a URL string'       => [
				'not a URL',
				100,
				false,
				'',
			],
		];
	}

	/**
	 * @dataProvider get_data_for_testing_args
	 */
	public function test__args( $attachment_url, $quality, $strip, $expected_qs ) {
		$attachment_url = $attachment_url ?? self::ATTACHMENT_URL;

		$this->assertEquals(
			$attachment_url . $expected_qs,
			wpcom_vip_set_image_quality_for_url( $attachment_url, $quality, $strip )
		);
	}

	public function test__default_args() {
		$this->assertEquals(
			self::ATTACHMENT_URL . '?quality=100',
			wpcom_vip_set_image_quality_for_url( self::ATTACHMENT_URL )
		);
	}
}
