<?php

class WPCOM_VIP_Utils_Remote_Requests_Test extends WP_UnitTestCase {
	public function mock_http_response( $mocked_response, $response_time = 1 ) {
		add_filter( 'pre_http_request', function( $response, $args, $url ) use ( $mocked_response, $response_time ) {
			usleep( $response_time * 1000000 );

			return $mocked_response;
		}, 10, 3 );
	}

	/**
	 * Test vip_safe_wp_remote_request() behavior with normal responses
	 */
	public function test__vip_safe_wp_remote_request_with_normal_response() {
		$url      = 'https://localhost';
		$response = 'mock_response';

		$this->mock_http_response( $response, 0.10 );

		// We can call it 4 times (more than the default threshold of 3) and it always returns the expected response (no failure / fallback)
		for ( $i = 0; $i < 4; $i++ ) {
			$res = vip_safe_wp_remote_request( $url );

			$this->assertEquals( $response, $res, 'Response for call ' . $i . ' was incorrect' );
		}
	}

	/**
	 * Test vip_safe_wp_remote_request() behavior with slow response - returns fallback after 3 failures
	 */
	public function test__vip_safe_wp_remote_request_with_slow_response() {
		$url      = 'https://localhost';
		$response = 'mock_response';

		$this->mock_http_response( $response, 1.1 ); // 1.1 seconds, over the default 1 second threshold

		// We can call it 3 times and it always returns the expected response (no failure / fallback)
		for ( $i = 0; $i < 3; $i++ ) {
			$res = vip_safe_wp_remote_request( $url );

			$this->assertEquals( $response, $res, 'Response for call ' . $i . ' was incorrect' );
		}

		// But on the 4th time, it returns the error
		$res = vip_safe_wp_remote_request( $url );

		$this->assertEquals( true, is_wp_error( $res ), '4th request did not return WP_Error' );
		$this->assertEquals( 'remote_request_disabled', $res->get_error_code(), 'Error code for 4th request was incorrect' );
	}

	/**
	 * Test vip_safe_wp_remote_request() behavior with normal responses, with all args
	 */
	public function test__vip_safe_wp_remote_request_with_normal_response_with_all_args() {
		$url      = 'https://localhost';
		$response = 'mock_response';

		$this->mock_http_response( $response, 0.10 );

		// We can call it 3 times (which is above our custom threshold from args) and it always returns the expected response (no failure / fallback)
		for ( $i = 0; $i < 4; $i++ ) {
			$res = vip_safe_wp_remote_request( $url, 'custom_fallback', 1, 2, 10, array( 'method' => 'POST' ) );

			$this->assertEquals( $response, $res, 'Response for call ' . $i . ' was incorrect' );
		}
	}

	/**
	 * Test vip_safe_wp_remote_request() behavior with slow response with all args
	 */
	public function test__vip_safe_wp_remote_request_with_slow_response_with_all_args() {
		$url             = 'https://localhost';
		$response        = 'mock_response';
		$custom_fallback = 'custom_fallback';

		$this->mock_http_response( $response, 2.1 ); // Longer than the threshold in our args

		// First time returns the expected response (no failure / fallback)
		$res = vip_safe_wp_remote_request( $url, $custom_fallback, 1, 2, 10, array( 'method' => 'POST' ) );

		$this->assertEquals( $response, $res, 'Initial call response was incorrect' );

		// But on the 2nd time, it returns the error
		$res = vip_safe_wp_remote_request( $url, $custom_fallback, 1, 2, 10, array( 'method' => 'POST' ) );

		$this->assertEquals( $custom_fallback, $res, 'Second call response was incorrect' );

		// And if we call it with different query args, it uses a different cache
		$res = vip_safe_wp_remote_request( $url, $custom_fallback, 1, 2, 10, array( 'method' => 'GET' ) );

		$this->assertEquals( $response, $res, 'Third call (with different query args) response was incorrect' );
	}

	/**
	 * Test vip_safe_wp_remote_get() behavior with normal responses
	 */
	public function test__vip_safe_wp_remote_get_with_normal_response() {
		$url      = 'https://localhost';
		$response = 'mock_response';

		$this->mock_http_response( $response, 0.10 );

		// We can call it 4 times (more than the default threshold of 3) and it always returns the expected response (no failure / fallback)
		for ( $i = 0; $i < 4; $i++ ) {
			$res = vip_safe_wp_remote_get( $url );

			$this->assertEquals( $response, $res, 'Response for call ' . $i . ' was incorrect' );
		}
	}

	/**
	 * Test vip_safe_wp_remote_get() behavior with slow response - returns fallback after 3 failures
	 */
	public function test__vip_safe_wp_remote_get_with_slow_response() {
		$url      = 'https://localhost';
		$response = 'mock_response';

		$this->mock_http_response( $response, 1.1 ); // 1.1 seconds, over the default 1 second threshold

		// We can call it 3 times and it always returns the expected response (no failure / fallback)
		for ( $i = 0; $i < 3; $i++ ) {
			$res = vip_safe_wp_remote_get( $url );

			$this->assertEquals( $response, $res, 'Response for call ' . $i . ' was incorrect' );
		}

		// But on the 4th time, it returns the error
		$res = vip_safe_wp_remote_get( $url );

		$this->assertEquals( true, is_wp_error( $res ), '4th request did not return a WP_Error' );
		$this->assertEquals( 'remote_request_disabled', $res->get_error_code(), 'Error code for 4th request was incorrect' );
	}

	/**
	 * Test vip_safe_wp_remote_get() behavior with normal responses, with all args
	 */
	public function test__vip_safe_wp_remote_get_with_normal_response_with_all_args() {
		$url      = 'https://localhost';
		$response = 'mock_response';

		$this->mock_http_response( $response, 0.10 );

		// We can call it 3 times (which is above our custom threshold from args) and it always returns the expected response (no failure / fallback)
		for ( $i = 0; $i < 4; $i++ ) {
			$res = vip_safe_wp_remote_get( $url, 'custom_fallback', 1, 2, 10, array( 'some' => 'thing' ) );

			$this->assertEquals( $response, $res, 'Response for call ' . $i . ' was incorrect' );
		}
	}

	/**
	 * Test vip_safe_wp_remote_get() behavior with slow response with all args
	 */
	public function test__vip_safe_wp_remote_get_with_slow_response_with_all_args() {
		$url             = 'https://localhost';
		$response        = 'mock_response';
		$custom_fallback = 'custom_fallback';

		$this->mock_http_response( $response, 2.1 ); // Longer than the threshold in our args

		// First time returns the expected response (no failure / fallback)
		$res = vip_safe_wp_remote_get( $url, $custom_fallback, 1, 2, 10, array( 'some' => 'thing' ) );

		$this->assertEquals( $response, $res, 'Initial call response was incorrect' );

		// But on the 2nd time, it returns the error
		$res = vip_safe_wp_remote_get( $url, $custom_fallback, 1, 2, 10, array( 'some' => 'thing' ) );

		$this->assertEquals( $custom_fallback, $res, 'Second call response was incorrect' );

		// And if we call it with different query args, it uses a different cache
		$res = vip_safe_wp_remote_get( $url, $custom_fallback, 1, 2, 10, array( 'method' => 'POST' ) );

		$this->assertEquals( $response, $res, 'Final call (with different query args) response was incorrect' );
	}
}
