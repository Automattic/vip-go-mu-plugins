<?php

class WPCOM_VIP_Get_Resized_Attachment_Url_Test extends \WP_UnitTestCase {
	public function tearDown() {
		remove_all_filters( 'pre_http_request' );

		parent::tearDown();
	}

	public function mock_slow_http_response( $mocked_response, $response_time = 1 ) {
		add_filter( 'pre_http_request', function( $response, $args, $url ) use ( $mocked_response, $response_time ) {
			sleep( $response_time );

			return $mocked_response;
		}, 10, 3 );
	}

	public function test__invalid_attachment() {
		$attachment_id = 99999999;

		$actual_url = wpcom_vip_get_resized_attachment_url( $attachment_id, 100, 101 );

		$this->assertFalse( $actual_url );
	}

	public function test__valid_attachment() {
		$expected_end_of_url = '/image.jpg?w=100&h=101';

		$attachment_id = $this->factory->attachment->create_object( [
			'file' => 'image.jpg',
		] );

		$actual_url = wpcom_vip_get_resized_attachment_url( $attachment_id, 100, 101 );

		$actual_end_of_url = substr( $actual_url, strrpos( $actual_url, '/' ) );

		$this->assertEquals( $expected_end_of_url, $actual_end_of_url );
	}

	function vip_safe_wp_remote_request_provider() {
		return array(
			'first' => array(
				'https://localhost', // Url
				'mocked_response', // Mocked response
				2, // Mocked HTTP request time
				'mocked_response', // Expected response
			),
			'second' => array(
				'https://localhost', // Url
				'mocked_response', // Mocked response
				2, // Mocked HTTP request time
				'mocked_response', // Expected response
			),
			'third' => array(
				'https://localhost', // Url
				'mocked_response', // Mocked response
				2, // Mocked HTTP request time
				'mocked_response', // Expected response
			),

			// After 3 timeouts, subsequent requests will fail
			'now fails' => array(
				'https://localhost', // Url
				'mocked_response', // Mocked response
				2, // Mocked HTTP request time
				new WP_Error( 'remote_request_disabled' ), // Expected response
			),

			// But not for other urls
			'different_url' => array(
				'https://localhost/other', // Url
				'mocked_response', // Mocked response
				2, // Mocked HTTP request time
				'mocked_response', // Expected response
			),
		);
	}

	/**
	 * Test vip_safe_wp_remote_request() behavior
	 * 
	 * @dataProvider vip_safe_wp_remote_request_provider
	 */
	public function test__vip_safe_wp_remote_request_with_default_args( $url, $mocked_response, $mocked_response_time, $expected_response ) {
		$this->mock_slow_http_response( $mocked_response, $mocked_response_time );

		$res = vip_safe_wp_remote_request( $url );

		$this->assertEquals( $res, $expected_response );
	}

	/*function vip_safe_wp_remote_request_provider_with_all_args() {
		return array(
			'first' => array(
				'https://localhost/1-threshold', // Url
				'mocked_response', // Mocked response
				2, // Mocked HTTP request time
				'mocked_response', // Expected response
				'fallback_value', // Fallback
				1, // Threshold
				1, // Timeout
				1, // Retry
				array(), // Args
			),

			// Threshold of 1 exceeded, should fail
			'now_fails' => array(
				'https://localhost/1-threshold', // Url
				'mocked_response', // Mocked response
				2, // Mocked HTTP request time
				'fallback_value', // Expected response
				'fallback_value', // Fallback
				1, // Threshold
				1, // Timeout
				1, // Retry
				array(), // Args
			),
		);
	}*/

	/**
	 * Test vip_safe_wp_remote_request() behavior, with all args
	 * 
	 * @dataProvider vip_safe_wp_remote_request_provider_with_all_args
	 */
	/*public function test__vip_safe_wp_remote_request_with_default_args( $url, $mocked_response, $mocked_response_time, $expected_response ) {
		$this->mock_slow_http_response( $mocked_response, $mocked_response_time );

		$res = vip_safe_wp_remote_request( $url );

		$this->assertEquals( $res, $expected_response );
	}*/

	/*public function test__vip_safe_wp_remote_get() {
		
	}*/
}
