<?php

namespace Automattic\VIP\Tests;

use WP_UnitTestCase;

// phpcs:disable WordPressVIPMinimum.Variables.ServerVariables.BasicAuthentication

class VIP_Go_REST_API_Test extends WP_UnitTestCase {
	/**
	 * Let's reduce repetition
	 */
	const VALID_NAMESPACE   = 'vip/v1';
	const INVALID_NAMESPACE = 'test/invalid';

	const VALID_AUTH_MECHANISM   = 'VIP-MACHINE-TOKEN';
	const INVALID_AUTH_MECHANISM = 'Basic';

	/**
	 * Test prep
	 */
	public function setUp(): void {
		parent::setUp();

		// NONCE_SALT is used to hash tokens
		if ( ! defined( 'NONCE_SALT' ) ) {
			define( 'NONCE_SALT', time() );
		}

		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );
	}

	/**
	 * Clean up after our tests
	 */
	public function tearDown(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tearDown();
	}

	/**
	 * Test that a valid token verifies as expected
	 */
	public function test__valid_token_creation() {
		$token = \wpcom_vip_generate_go_rest_api_request_token( self::VALID_NAMESPACE );

		$header = self::VALID_AUTH_MECHANISM . ' ' . $token;

		$this->assertTrue( \wpcom_vip_verify_go_rest_api_request_authorization( self::VALID_NAMESPACE, $header ) );
	}

	/**
	 * Test that a token doesn't verify for a different namespace
	 */
	public function test__invalid_token_creation() {
		$token = \wpcom_vip_generate_go_rest_api_request_token( self::INVALID_NAMESPACE );

		$header = self::VALID_AUTH_MECHANISM . ' ' . $token;

		$this->assertFalse( \wpcom_vip_verify_go_rest_api_request_authorization( self::VALID_NAMESPACE, $header ) );
	}

	/**
	 * Test that a valid token doesn't verify with an invalid header
	 */
	public function test__invalid_header() {
		$token = \wpcom_vip_generate_go_rest_api_request_token( self::VALID_NAMESPACE );

		$header = self::INVALID_AUTH_MECHANISM . ' ' . $token;

		$this->assertFalse( \wpcom_vip_verify_go_rest_api_request_authorization( self::VALID_NAMESPACE, $header ) );
	}

	/**
	 * Test request with valid authorization
	 */
	public function test__request_with_valid_header() {
		$request = new \WP_REST_Request( 'GET', '/' . self::VALID_NAMESPACE . '/sites' );

		// $request->add_header() doesn't populate the vars our endpoint checks
		$_SERVER['HTTP_AUTHORIZATION'] = self::VALID_AUTH_MECHANISM . ' ' . \wpcom_vip_generate_go_rest_api_request_token( self::VALID_NAMESPACE );

		$response = $this->server->dispatch( $request );

		unset( $_SERVER['HTTP_AUTHORIZATION'] );

		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test request with invalid auth mechanism
	 */
	public function test__request_with_invalid_mechanism() {
		$request = new \WP_REST_Request( 'GET', '/' . self::VALID_NAMESPACE . '/sites' );

		// $request->add_header() doesn't populate the vars our endpoint checks
		$_SERVER['HTTP_AUTHORIZATION'] = self::INVALID_AUTH_MECHANISM . ' ' . \wpcom_vip_generate_go_rest_api_request_token( self::VALID_NAMESPACE );

		$response = $this->server->dispatch( $request );

		unset( $_SERVER['HTTP_AUTHORIZATION'] );

		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Test request with token for different namespace
	 */
	public function test__request_with_invalid_token() {
		$request = new \WP_REST_Request( 'GET', '/' . self::VALID_NAMESPACE . '/sites' );

		// $request->add_header() doesn't populate the vars our endpoint checks
		$_SERVER['HTTP_AUTHORIZATION'] = self::VALID_AUTH_MECHANISM . ' ' . \wpcom_vip_generate_go_rest_api_request_token( self::INVALID_NAMESPACE );

		$response = $this->server->dispatch( $request );

		unset( $_SERVER['HTTP_AUTHORIZATION'] );

		$this->assertEquals( 401, $response->get_status() );
	}

	/**
	 * Test request without any auth header
	 */
	public function test__request_without_header() {
		$request = new \WP_REST_Request( 'GET', '/' . self::VALID_NAMESPACE . '/sites' );

		$response = $this->server->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );
	}

	public function test__invalid_basic_auth_credentials() {
		$request = new \WP_REST_Request( 'GET', '/' . self::VALID_NAMESPACE . '/sites' );

		// $request->add_header() doesn't populate the vars our endpoint checks
		$_SERVER['PHP_AUTH_USER'] = '';
		$_SERVER['PHP_AUTH_PW']   = '';

		$response = $this->server->dispatch( $request );

		unset( $_SERVER['PHP_AUTH_USER'] );
		unset( $_SERVER['PHP_AUTH_PW'] );

		$this->assertEquals( 401, $response->get_status() );
	}

	public function test__insufficient_basic_auth_credentials() {
		$request = new \WP_REST_Request( 'GET', '/' . self::VALID_NAMESPACE . '/sites' );

		list( $random_username, $random_password ) = self::get_test_username_password();
		wp_create_user( $random_username, $random_password, $random_username . '@example.com' );

		// $request->add_header() doesn't populate the vars our endpoint checks
		$_SERVER['PHP_AUTH_USER'] = $random_username;
		$_SERVER['PHP_AUTH_PW']   = $random_password;

		$response = $this->server->dispatch( $request );

		unset( $_SERVER['PHP_AUTH_USER'] );
		unset( $_SERVER['PHP_AUTH_PW'] );

		$this->assertEquals( 401, $response->get_status() );
	}

	public function test__valid__vip_support_basic_auth_credentials() {
		$request = new \WP_REST_Request( 'GET', '/' . self::VALID_NAMESPACE . '/sites' );

		list( $random_username, $random_password ) = self::get_test_username_password();
		$user_id                                   = wp_create_user( $random_username, $random_password, $random_username . '@example.com' );
		$user                                      = get_user_by( 'id', $user_id );
		$user->add_cap( 'vip_support' );

		// $request->add_header() doesn't populate the vars our endpoint checks
		$_SERVER['PHP_AUTH_USER'] = $random_username;
		$_SERVER['PHP_AUTH_PW']   = $random_password;

		$response = $this->server->dispatch( $request );

		unset( $_SERVER['PHP_AUTH_USER'] );
		unset( $_SERVER['PHP_AUTH_PW'] );

		$this->assertEquals( 200, $response->get_status() );
	}

	public function test__valid_basic_auth_credentials() {
		$request = new \WP_REST_Request( 'GET', '/' . self::VALID_NAMESPACE . '/sites' );

		list( $random_username, $random_password ) = self::get_test_username_password();
		$user_id                                   = wp_create_user( $random_username, $random_password, $random_username . '@example.com' );
		$user                                      = get_user_by( 'id', $user_id );
		$user->add_cap( 'manage_sites' );

		// $request->add_header() doesn't populate the vars our endpoint checks
		$_SERVER['PHP_AUTH_USER'] = $random_username;
		$_SERVER['PHP_AUTH_PW']   = $random_password;

		$response = $this->server->dispatch( $request );

		unset( $_SERVER['PHP_AUTH_USER'] );
		unset( $_SERVER['PHP_AUTH_PW'] );

		$this->assertEquals( 200, $response->get_status() );
	}

	// Helper function to generate random username and password
	public static function get_test_username_password() {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
		$username = 'testuser_' . mt_rand();
		$password = wp_generate_password( 12 );
		return array( $username, $password );
	}
}
