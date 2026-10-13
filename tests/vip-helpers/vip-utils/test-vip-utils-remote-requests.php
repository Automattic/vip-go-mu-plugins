<?php

class WPCOM_VIP_Utils_Remote_Requests_Test extends WP_UnitTestCase {
	/**
	 * @param mixed $mocked_response
	 * @param float $response_time Simulated response time, in seconds.
	 */
	public function mock_http_response( $mocked_response, $response_time = 0 ) {
		add_filter( 'pre_http_request', function () use ( $mocked_response, $response_time ) {
			if ( $response_time > 0 ) {
				usleep( (int) ( $response_time * 1000000 ) );
			}

			return $mocked_response;
		}, 10, 3 );
	}

	public function data_provider_safe_remote_functions(): array {
		return [
			'vip_safe_wp_remote_request' => [ 'vip_safe_wp_remote_request' ],
			'vip_safe_wp_remote_get'     => [ 'vip_safe_wp_remote_get' ],
		];
	}

	/**
	 * Test that fast responses never trigger the fallback with the default args
	 *
	 * @dataProvider data_provider_safe_remote_functions
	 */
	public function test__normal_response( callable $remote_function ) {
		$url           = 'https://localhost';
		$response      = 'mock_response';
		$observed_args = null;

		add_filter( 'pre_http_request', function ( $preempt, $args ) use ( $response, &$observed_args ) {
			$observed_args = $args;
			return $response;
		}, 10, 2 );

		// We can call it 4 times (more than the default threshold of 3) and it always returns the expected response (no failure / fallback)
		for ( $i = 0; $i < 4; $i++ ) {
			$res = $remote_function( $url );

			$this->assertEquals( $response, $res, 'Response for call ' . $i . ' was incorrect' );
		}

		// The default 1 second timeout is sent with the request and is also the slow-response limit.
		$this->assertSame( 1, $observed_args['timeout'] );
	}

	public function data_provider_all_args(): array {
		return [
			'vip_safe_wp_remote_request' => [ 'vip_safe_wp_remote_request', [ 'method' => 'POST' ], [ 'method' => 'GET' ] ],
			'vip_safe_wp_remote_get'     => [ 'vip_safe_wp_remote_get', [ 'some' => 'thing' ], [ 'method' => 'POST' ] ],
		];
	}

	/**
	 * Test that fast responses never trigger the fallback with custom args
	 *
	 * @dataProvider data_provider_all_args
	 */
	public function test__normal_response_with_all_args( callable $remote_function, array $args ) {
		$url      = 'https://localhost';
		$response = 'mock_response';

		$this->mock_http_response( $response );

		// We can call it 4 times (which is above our custom threshold from args) and it always returns the expected response (no failure / fallback)
		for ( $i = 0; $i < 4; $i++ ) {
			$res = $remote_function( $url, 'custom_fallback', 1, 2, 10, $args );

			$this->assertEquals( $response, $res, 'Response for call ' . $i . ' was incorrect' );
		}
	}

	/**
	 * Test vip_safe_wp_remote_request() behavior with slow response - returns fallback after 3 failures
	 *
	 * A timeout of 0 seconds makes every request count as slow, so the default threshold can be
	 * tested without waiting for the default 1 second timeout. vip_safe_wp_remote_get() uses the
	 * same defaults and code path.
	 */
	public function test__vip_safe_wp_remote_request_with_slow_response() {
		$url      = 'https://localhost';
		$response = 'mock_response';

		$this->mock_http_response( $response, 0.001 );

		// We can call it 3 times and it always returns the expected response (no failure / fallback)
		for ( $i = 0; $i < 3; $i++ ) {
			$res = vip_safe_wp_remote_request( $url, timeout: 0 );

			$this->assertEquals( $response, $res, 'Response for call ' . $i . ' was incorrect' );
		}

		// But on the 4th time, it returns the error
		$res = vip_safe_wp_remote_request( $url, timeout: 0 );

		$this->assertEquals( true, is_wp_error( $res ), '4th request did not return WP_Error' );
		$this->assertEquals( 'remote_request_disabled', $res->get_error_code(), 'Error code for 4th request was incorrect' );
	}

	/**
	 * Test slow responses with custom threshold, timeout and fallback args
	 *
	 * A timeout of 0 seconds makes every request count as slow, so the custom threshold
	 * and fallback can be tested without waiting for a real timeout. The custom timeout
	 * must be honored for this to work, since the default (1s) would not trigger the fallback.
	 *
	 * @dataProvider data_provider_all_args
	 */
	public function test__slow_response_with_all_args( callable $remote_function, array $args, array $other_args ) {
		$url             = 'https://localhost';
		$response        = 'mock_response';
		$custom_fallback = 'custom_fallback';

		$this->mock_http_response( $response, 0.001 );

		// First time returns the expected response (no failure / fallback)
		$res = $remote_function( $url, $custom_fallback, 1, 0, 10, $args );

		$this->assertEquals( $response, $res, 'Initial call response was incorrect' );

		// But on the 2nd time, it returns the fallback
		$res = $remote_function( $url, $custom_fallback, 1, 0, 10, $args );

		$this->assertEquals( $custom_fallback, $res, 'Second call response was incorrect' );

		// And if we call it with a different method, it uses a different cache
		$res = $remote_function( $url, $custom_fallback, 1, 0, 10, $other_args );

		$this->assertEquals( $response, $res, 'Third call (with a different method) response was incorrect' );
	}

	/**
	 * The GET convenience function forwards its arguments to the shared request implementation.
	 */
	public function test__vip_safe_wp_remote_get_forwards_arguments_and_returns_response() {
		$response      = 'mock_response';
		$observed_args = null;
		add_filter( 'pre_http_request', function ( $preempt, $args ) use ( $response, &$observed_args ) {
			$observed_args = $args;
			return $response;
		}, 10, 2 );

		$result = vip_safe_wp_remote_get( 'https://localhost', 'fallback', 2, 1, 20, [ 'headers' => [ 'X-Test' => 'yes' ] ] );

		$this->assertSame( $response, $result );
		$this->assertSame( 'GET', $observed_args['method'] );
		$this->assertSame( 'yes', $observed_args['headers']['X-Test'] );
	}
}
