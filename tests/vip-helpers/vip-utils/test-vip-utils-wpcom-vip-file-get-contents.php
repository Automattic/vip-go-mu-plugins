<?php

class WPCOM_VIP_Utils_Wpcom_Vip_File_Get_Contents_Test extends WP_UnitTestCase {

	public function test__wpcom_vip_file_get_contents__cached() {
		$url             = 'http://www.foo.bar';
		$extra_args      = array(
			'obey_cache_control_header' => true,
			'http_api_args'             => array(),
		);
		$expected_result = '100';
		$cache_key       = md5( serialize( array_merge( $extra_args, [ 'url' => $url ] ) ) );   // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$cache_group     = 'wpcom_vip_file_get_contents';

		wp_cache_set( $cache_key, $expected_result, $cache_group );

		$result = wpcom_vip_file_get_contents( $url );

		$this->assertEquals( $expected_result, $result );
	}

	public function test__wpcom_vip_file_get_contents__cache_legacy() {
		$url             = 'http://www.foo.bar';
		$expected_result = '100';
		$cache_key       = md5( $url );
		$cache_group     = 'wpcom_vip_file_get_contents';

		wp_cache_set( $cache_key, $expected_result, $cache_group );

		$result = wpcom_vip_file_get_contents( $url );

		$this->assertEquals( $expected_result, $result );
	}

	public function test__wpcom_vip_file_get_contents__failure_with_backup_backs_off() {
		$url        = 'http://www.foo.bar';
		$extra_args = array(
			'obey_cache_control_header' => true,
			'http_api_args'             => array(),
		);
		$cache_key  = md5( serialize( array_merge( $extra_args, [ 'url' => $url ] ) ) );   // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize

		wp_cache_set( $cache_key . '_backup', 'backup', 'wpcom_vip_file_get_contents' );

		$requests = 0;
		add_filter( 'pre_http_request', function () use ( &$requests ) {
			++$requests;
			return new WP_Error( 'http_request_failed', 'Operation timed out' );
		} );

		// Ignore the failure notice.
		set_error_handler( '__return_true', E_USER_NOTICE ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		try {
			$this->assertSame( 'backup', wpcom_vip_file_get_contents( $url ) );
			$this->assertSame( 'backup', wpcom_vip_file_get_contents( $url ) );
		} finally {
			restore_error_handler();
		}

		$this->assertSame( 1, $requests );
	}
}
