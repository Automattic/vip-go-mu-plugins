<?php

namespace Automattic\VIP\Tests;

class VIP_Go_Cache_Manager_REST_API_Test extends \WP_UnitTestCase {
	/**
	 * Let's reduce repetition
	 */
	const VALID_NAMESPACE   = 'cache-manager/v1';

	const VALID_AUTH_MECHANISM   = 'VIP-MACHINE-TOKEN';

	/**
	 * Test prep
	 */
	public function setUp() {
		parent::setUp();

		// NONCE_SALT is used to hash tokens
		if ( ! defined( 'NONCE_SALT' ) ) {
			define( 'NONCE_SALT', time() );
		}

		global $wp_rest_server;
		$this->server = $wp_rest_server = new \WP_REST_Server;
		do_action( 'rest_api_init' );
	}

	/**
	 * Clean up after our tests
	 */
	function tearDown() {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tearDown();
	}


	/**
	 * Test request with valid authorization
	 */
	public function test__request_with_valid_header() {
		$request = new \WP_REST_Request( 'POST', '/' . self::VALID_NAMESPACE . '/purge' );
	
		$request->set_header('content-Type', 'application/json');
		$urls = array();
		$urls['urls'] = [ 'http://localhost/someurl.jpg', 'http://localhost/someotherurl.js' ];	
		$request->set_body(wp_json_encode($urls));

		// $request->add_header() doesn't populate the vars our endpoint checks
		$_SERVER['HTTP_AUTHORIZATION'] = self::VALID_AUTH_MECHANISM . ' ' . \wpcom_vip_generate_go_rest_api_request_token( self::VALID_NAMESPACE );

		$response = $this->server->dispatch( $request );

		unset( $_SERVER['HTTP_AUTHORIZATION'] );

		$this->assertEquals( 200, $response->get_status() );
	}



}
