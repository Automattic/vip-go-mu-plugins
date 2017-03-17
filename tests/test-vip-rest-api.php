<?php

namespace Automattic\VIP\Tests;

class VIP_Go_REST_API_Test extends \WP_UnitTestCase {
	/**
	 * Test prep
	 */
	public function setUp() {
		// NONCE_SALT is used to hash tokens
		if ( ! defined( 'NONCE_SALT' ) ) {
			define( 'NONCE_SALT', time() );
		}
	}

	/**
	 * Test that a valid token verifies as expected
	 */
	public function test__valid_token_creation() {
		$token = \wpcom_vip_generate_go_rest_api_request_token( 'test/valid' );

		$header = 'VIP-MACHINE-TOKEN ' . $token;

		$this->assertTrue( \wpcom_vip_verify_go_rest_api_request_authorization( 'test/valid', $header ) );
	}

	/**
	 * Test that a token doesn't verify for a different namespace
	 */
	public function test__invalid_token_creation() {
		$token = \wpcom_vip_generate_go_rest_api_request_token( 'test/invalid' );

		$header = 'VIP-MACHINE-TOKEN ' . $token;

		$this->assertFalse( \wpcom_vip_verify_go_rest_api_request_authorization( 'test/valid', $header ) );
	}
}