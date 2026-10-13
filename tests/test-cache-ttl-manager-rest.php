<?php

namespace Automattic\VIP\Cache;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Test_REST_TestCase;

// phpcs:ignore PEAR.NamingConventions.ValidClassName.Invalid
class TTL_Manager__REST_API__Test extends WP_Test_REST_TestCase {
	use ArraySubsetAsserts;

	/** @var WP_REST_Server */
	private $server;

	public function setUp(): void {
		parent::setUp();

		// The TTL is set on `rest_post_dispatch`, so no routes need to be registered.
		$this->server = new WP_REST_Server();
	}

	/**
	 * Run the `rest_post_dispatch` filters on the response to a request to a test endpoint.
	 *
	 * @param string                 $method   HTTP method.
	 * @param WP_REST_Response|mixed $response Endpoint result; defaults to an empty, successful response.
	 */
	protected function dispatch_request( $method, $response = null ) {
		$request = new WP_REST_Request( $method, '/tests/v1/endpoint' );
		return apply_filters( 'rest_post_dispatch', rest_ensure_response( $response ), $this->server, $request );
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
	 * Write requests never get a TTL (see test__set_ttl_for_unauthenticated_write_requests).
	 *
	 * @dataProvider get_rest_read_methods
	 */
	public function test__skip_ttl_for_authenticated_requests( $method ) {
		$user_id = $this->factory()->user->create();
		wp_set_current_user( $user_id );

		$response = $this->dispatch_request( $method );

		$response_headers = $response->get_headers();

		$this->assertArrayNotHasKey( 'Cache-Control', $response_headers );
	}

	public function test__skip_ttl_for_error_responses() {
		$dispatched_response = $this->dispatch_request( 'GET', rest_convert_error_to_response( new WP_Error( 'rest_no_route', 'No route was found matching the URL and request method.', [ 'status' => 404 ] ) ) );

		$response_headers = $dispatched_response->get_headers();

		$this->assertArrayNotHasKey( 'Cache-Control', $response_headers );
	}

	public function test__skip_ttl_if_already_set_via_rest_response() {
		$response = new WP_REST_Response();
		$response->header( 'Cache-Control', 'max-age=666' );
		$dispatched_response = $this->dispatch_request( 'GET', $response );

		$response_headers = $dispatched_response->get_headers();

		$this->assertArraySubset( [
			'Cache-Control' => 'max-age=666',
		], $response_headers );
	}
}
