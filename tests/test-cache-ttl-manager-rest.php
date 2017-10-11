<?php

namespace Automattic\VIP\Cache;

class TTL_Manager__REST_API__Test extends \WP_Test_REST_TestCase {
	public function setUp() {
		parent::setUp();

		global $wp_rest_server;
		$this->server = $wp_rest_server = new \WP_REST_Server;
		do_action( 'rest_api_init' );

		register_rest_route( 'tests/v1', '/endpoint', [
			'methods' => [ 'GET', 'HEAD', 'POST', 'PUT', 'DELETE' ],
			'callback' => '__return_null',
		] );
	}

	public function tearDown() {
		parent::tearDown();

		global $wp_rest_server;
		$wp_rest_server = null;
	}

	protected function dispatch_request( $method ) {
		$request = new \WP_REST_Request( $method, '/tests/v1/endpoint' );
		$response = $this->server->dispatch( $request );
		$dispatched_response = apply_filters( 'rest_post_dispatch', rest_ensure_response( $response ), $this->server, $request );

		return $dispatched_response;
	}

	public function get_rest_read_methods() {
		return [
			[ 'GET' ],
			[ 'HEAD' ],
		];
	}

	public function get_rest_write_methods() {
		return [
			[ 'POST' ],
			[ 'PUT' ],
			[ 'DELETE' ],
		];
	}

	/**
	 * @dataProvider get_rest_read_methods
	 */
	public function test__set_ttl_for_unauthenticated_read_requests( $method ) {
		$response = $this->dispatch_request( $method );

		$response_headers = $response->get_headers();

		$this->assertArraySubset( [ 'Cache-Control' => 'max-age=60' ], $response_headers );
	}

	/**
	 * @dataProvider get_rest_write_methods
	 */
	public function test__set_ttl_for_unauthenticated_write_requests( $method ) {
		$response = $this->dispatch_request( $method );

		$response_headers = $response->get_headers();

		$this->assertArrayNotHasKey( 'Cache-Control', $response_headers );
	}

	/**
	 * @dataProvider get_rest_read_methods
	 */
	public function test__skip_ttl_for_authenticated_read_requests( $method ) {
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		$response = $this->dispatch_request( $method );

		$response_headers = $response->get_headers();

		$this->assertArrayNotHasKey( 'Cache-Control', $response_headers );
	}

	/**
	 * @dataProvider get_rest_write_methods
	 */
	public function test__skip_ttl_for_authenticated_write_requests( $method ) {
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		$response = $this->dispatch_request( $method );

		$response_headers = $response->get_headers();

		$this->assertArrayNotHasKey( 'Cache-Control', $response_headers );
	}

	public function test__skip_ttl_for_error_responses() {
		$request = new \WP_REST_Request( 'GET', '/tests/v1/this-does-not-exist' );
		$response = $this->server->dispatch( $request );
		$dispatched_response = apply_filters( 'rest_post_dispatch', rest_ensure_response( $response ), $this->server, $request );

		$response_headers = $dispatched_response->get_headers();

		$this->assertArrayNotHasKey( 'Cache-Control', $response_headers );
	}

	public function test__skip_ttl_if_already_set() {
		$request = new \WP_REST_Request( 'GET', '/tests/v1/endpoint' );
		$response = $this->server->dispatch( $request );
		$response->header( 'Cache-Control', 'max-age=666' );
		$dispatched_response = apply_filters( 'rest_post_dispatch', rest_ensure_response( $response ), $this->server, $request );

		$response_headers = $dispatched_response->get_headers();

		$this->assertArraySubset( [ 'Cache-Control' => 'max-age=666' ], $response_headers );
	}
}
