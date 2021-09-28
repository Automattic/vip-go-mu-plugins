<?php

use Yoast\WPTestUtils\WPIntegration\TestCase;

class WPCOM_VIP_UTILS_VIP_POWERED_WPCOM_URL_TEST extends TestCase {

	public function test__vip_powered_wpcom_url() {

		$expected = 'https://wpvip.com/?utm_source=vip_powered_wpcom&utm_medium=web&utm_campaign=VIP Footer Credit&utm_term=example.org';

		$result = vip_powered_wpcom_url();

		$this->assertEquals( $expected, $result );
	}
}
