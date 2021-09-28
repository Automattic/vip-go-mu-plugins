<?php

use Yoast\WPTestUtils\WPIntegration\TestCase;

class WPCOM_VIP_Utils_Wpcom_Vip_File_Get_Contents_Test extends TestCase {

	public function test__wpcom_vip_file_get_contents__cached() {
		$url = 'http://www.foo.bar';
		$extra_args = array(
			'obey_cache_control_header' => true,
			'http_api_args'             => array(),
		);
		$expected_result = '100';
		$cache_key       = md5( serialize( array_merge( $extra_args, [ 'url' => $url ] ) ) );
		$cache_group     = 'wpcom_vip_file_get_contents';

		wp_cache_set( $cache_key, $expected_result, $cache_group );

		$result = wpcom_vip_file_get_contents( $url );

		$this->assertEquals( $expected_result, $result );
	}

	public function test__wpcom_vip_file_get_contents__cache_legacy() {
		$url = 'http://www.foo.bar';
		$expected_result = '100';
		$cache_key       = md5( $url );
		$cache_group     = 'wpcom_vip_file_get_contents';

		wp_cache_set( $cache_key, $expected_result, $cache_group );

		$result = wpcom_vip_file_get_contents( $url );

		$this->assertEquals( $expected_result, $result );
	}
}
