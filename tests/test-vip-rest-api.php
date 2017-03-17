<?php

namespace Automattic\VIP\Tests;

class VIP_Go_REST_API_Test extends \WP_UnitTestCase {
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
}
