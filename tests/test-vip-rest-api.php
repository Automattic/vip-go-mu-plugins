<?php

namespace Automattic\VIP\Tests;

use WP_REST_Server;
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

	/** @var WP_REST_Server|null */
	private $server;

	/**
	 * Test prep
	 */
	public function setUp(): void {
		parent::setUp();

		// NONCE_SALT is used to hash tokens
		if ( ! defined( 'NONCE_SALT' ) ) {
			define( 'NONCE_SALT', time() );
		}
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
	 * Dispatch GET /vip/v1/sites with the given $_SERVER values and return the response status.
	 *
	 * The REST server is built on first use, so tests that never dispatch skip rest_api_init.
	 * $request->add_header() doesn't populate the vars our endpoint checks, hence $_SERVER.
	 *
	 * @param array<string,string> $server $_SERVER keys to set for the request.
	 */
	private function dispatch_sites( array $server = [] ): int {
		if ( ! $this->server ) {
			global $wp_rest_server;
			$wp_rest_server = new WP_REST_Server();
			$this->server   = $wp_rest_server;
			do_action( 'rest_api_init' );
		}

		foreach ( $server as $key => $value ) {
			$_SERVER[ $key ] = $value;
		}

		try {
			return $this->server->dispatch( new \WP_REST_Request( 'GET', '/' . self::VALID_NAMESPACE . '/sites' ) )->get_status();
		} finally {
			foreach ( array_keys( $server ) as $key ) {
				unset( $_SERVER[ $key ] );
			}
		}
	}

	/**
	 * Test request with valid authorization
	 */
	public function test__request_with_valid_header() {
		// Retry only when the clock crosses a tick during dispatch.
		for ( $attempt = 0; $attempt < 3; ++$attempt ) {
			$tick   = ceil( time() / 120 );
			$status = $this->dispatch_sites( [
				'HTTP_AUTHORIZATION' => self::VALID_AUTH_MECHANISM . ' ' . hash_hmac( 'sha256', $tick . '|' . self::VALID_NAMESPACE, NONCE_SALT ),
			] );
			if ( ceil( time() / 120 ) === $tick ) {
				break;
			}
		}
		$this->assertSame( $tick, ceil( time() / 120 ), 'Dispatch must finish within a stable token tick.' );

		$this->assertEquals( 200, $status );
	}

	public function data_rejected_authorization_header(): array {
		return [
			'no header'                   => [ null, null ],
			'invalid auth mechanism'      => [ self::INVALID_AUTH_MECHANISM, self::VALID_NAMESPACE ],
			'token for another namespace' => [ self::VALID_AUTH_MECHANISM, self::INVALID_NAMESPACE ],
		];
	}

	/**
	 * @dataProvider data_rejected_authorization_header
	 */
	public function test__request_with_rejected_authorization_header( ?string $mechanism, ?string $token_namespace ) {
		$server = [];
		if ( null !== $mechanism ) {
			$server['HTTP_AUTHORIZATION'] = $mechanism . ' ' . \wpcom_vip_generate_go_rest_api_request_token( $token_namespace );
		}

		$this->assertEquals( 401, $this->dispatch_sites( $server ) );
	}

	public function data_basic_auth_credentials(): array {
		return [
			'empty credentials'       => [ null, 401 ],
			'user without capability' => [ [], 401 ],
			'user with manage_sites'  => [ [ 'manage_sites' ], 200 ],
		];
	}

	/**
	 * @dataProvider data_basic_auth_credentials
	 *
	 * @param string[]|null $caps Caps to grant a new user, or null to send empty credentials.
	 */
	public function test__basic_auth_credentials( ?array $caps, int $expected_status ) {
		$server = [
			'PHP_AUTH_USER' => '',
			'PHP_AUTH_PW'   => '',
		];

		if ( null !== $caps ) {
			$password = wp_generate_password( 12 );
			$user     = $this->factory()->user->create_and_get( [ 'user_pass' => $password ] );
			foreach ( $caps as $cap ) {
				$user->add_cap( $cap );
			}

			$server = [
				'PHP_AUTH_USER' => $user->user_login,
				'PHP_AUTH_PW'   => $password,
			];
		}

		$this->assertEquals( $expected_status, $this->dispatch_sites( $server ) );
	}

	/**
	 * Privileged logins must authenticate the password before authorizing the route.
	 */
	public function test__privileged_basic_auth_requires_correct_password() {
		$user = $this->factory()->user->create_and_get( [ 'user_pass' => 'correct-password' ] );
		$user->add_cap( 'vip_support' );

		$this->assertSame( 401, $this->dispatch_sites( [
			'PHP_AUTH_USER' => $user->user_login,
			'PHP_AUTH_PW'   => 'wrong-nonempty-password',
		] ) );
		$this->assertSame( 200, $this->dispatch_sites( [
			'PHP_AUTH_USER' => $user->user_login,
			'PHP_AUTH_PW'   => 'correct-password',
		] ) );
	}

	/**
	 * Tokens must independently bind the current tick, namespace and secret salt.
	 */
	public function test__independent_token_signing_contract() {
		for ( $attempt = 0; $attempt < 3; ++$attempt ) {
			$tick     = ceil( time() / 120 );
			$expected = hash_hmac( 'sha256', $tick . '|' . self::VALID_NAMESPACE, 'first-salt' );
			$actual   = \wpcom_vip_generate_go_rest_api_request_token( self::VALID_NAMESPACE, 'first-salt' );
			$other    = \wpcom_vip_generate_go_rest_api_request_token( self::VALID_NAMESPACE, 'second-salt' );
			if ( ceil( time() / 120 ) === $tick ) {
				break;
			}
		}
		$this->assertSame( $tick, ceil( time() / 120 ), 'Token generation must finish within a stable tick.' );
		$this->assertSame( $expected, $actual );
		$this->assertNotSame( $expected, $other );
		$stale = hash_hmac( 'sha256', ( $tick - 2 ) . '|' . self::VALID_NAMESPACE, NONCE_SALT );
		$this->assertFalse( \wpcom_vip_verify_go_rest_api_request_authorization( self::VALID_NAMESPACE, self::VALID_AUTH_MECHANISM . ' ' . $stale ) );
	}
}
