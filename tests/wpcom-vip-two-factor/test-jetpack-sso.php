<?php

// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE, WordPress.Security.ValidatedSanitizedInput

use function Automattic\VIP\TwoFactor\generate_sso_assertion;
use function Automattic\VIP\TwoFactor\is_jetpack_sso;
use function Automattic\VIP\TwoFactor\is_jetpack_sso_two_step;

require_once __DIR__ . '/../../wpcom-vip-two-factor/is-jetpack-sso.php';

class Test_Jetpack_SSO_Assertions extends WP_UnitTestCase {
	private $cookies_backup;
	private $user_id;
	private $token;
	private $expiration;

	public function setUp(): void {
		parent::setUp();

		$this->cookies_backup = $_COOKIE;
		$_COOKIE              = [];
		$this->user_id        = self::factory()->user->create();
		$this->expiration     = time() + HOUR_IN_SECONDS;
		$this->token          = WP_Session_Tokens::get_instance( $this->user_id )->create( $this->expiration );

		wp_set_current_user( $this->user_id );
		$_COOKIE[ SECURE_AUTH_COOKIE ] = wp_generate_auth_cookie( $this->user_id, $this->expiration, 'secure_auth', $this->token );
	}

	public function tearDown(): void {
		$_COOKIE = $this->cookies_backup;
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function set_valid_assertions(): void {
		$_COOKIE[ VIP_IS_JETPACK_SSO_COOKIE ]     = generate_sso_assertion( $this->user_id, $this->expiration, $this->token, 'jetpack_sso' );
		$_COOKIE[ VIP_IS_JETPACK_SSO_2SA_COOKIE ] = generate_sso_assertion( $this->user_id, $this->expiration, $this->token, 'jetpack_sso_2sa' );
	}

	public function test_valid_assertions_for_current_session(): void {
		$this->set_valid_assertions();

		$this->assertSame( $this->user_id, is_jetpack_sso() );
		$this->assertSame( $this->user_id, is_jetpack_sso_two_step() );
	}

	public function test_sso_without_two_step_does_not_exempt_user(): void {
		$_COOKIE[ VIP_IS_JETPACK_SSO_COOKIE ] = generate_sso_assertion( $this->user_id, $this->expiration, $this->token, 'jetpack_sso' );

		$this->assertSame( $this->user_id, is_jetpack_sso() );
		$this->assertFalse( is_jetpack_sso_two_step() );
	}

	public function test_logged_in_cookie_can_validate_sso_on_front_end(): void {
		$this->set_valid_assertions();
		unset( $_COOKIE[ SECURE_AUTH_COOKIE ] );
		$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $this->user_id, $this->expiration, 'logged_in', $this->token );

		$this->assertSame( $this->user_id, is_jetpack_sso_two_step() );
	}

	public function test_assertion_for_another_user_is_rejected(): void {
		$other_user_id                            = self::factory()->user->create();
		$other_token                              = WP_Session_Tokens::get_instance( $other_user_id )->create( $this->expiration );
		$_COOKIE[ VIP_IS_JETPACK_SSO_COOKIE ]     = generate_sso_assertion( $other_user_id, $this->expiration, $other_token, 'jetpack_sso' );
		$_COOKIE[ VIP_IS_JETPACK_SSO_2SA_COOKIE ] = generate_sso_assertion( $other_user_id, $this->expiration, $other_token, 'jetpack_sso_2sa' );

		$this->assertFalse( is_jetpack_sso() );
		$this->assertFalse( is_jetpack_sso_two_step() );
	}

	public function test_legacy_marker_format_is_rejected(): void {
		$_COOKIE[ VIP_IS_JETPACK_SSO_COOKIE ]     = $_COOKIE[ SECURE_AUTH_COOKIE ];
		$_COOKIE[ VIP_IS_JETPACK_SSO_2SA_COOKIE ] = $_COOKIE[ SECURE_AUTH_COOKIE ];

		$this->assertFalse( is_jetpack_sso_two_step() );
	}

	public function test_assertions_are_separate_purposes(): void {
		$_COOKIE[ VIP_IS_JETPACK_SSO_COOKIE ]     = generate_sso_assertion( $this->user_id, $this->expiration, $this->token, 'jetpack_sso' );
		$_COOKIE[ VIP_IS_JETPACK_SSO_2SA_COOKIE ] = $_COOKIE[ VIP_IS_JETPACK_SSO_COOKIE ];

		$this->assertSame( $this->user_id, is_jetpack_sso() );
		$this->assertFalse( is_jetpack_sso_two_step() );
	}

	public function test_assertions_are_bound_to_user_and_session(): void {
		$this->set_valid_assertions();
		$other_user_id = self::factory()->user->create();
		wp_set_current_user( $other_user_id );
		$this->assertFalse( is_jetpack_sso_two_step() );

		wp_set_current_user( $this->user_id );
		$other_token                   = WP_Session_Tokens::get_instance( $this->user_id )->create( $this->expiration );
		$_COOKIE[ SECURE_AUTH_COOKIE ] = wp_generate_auth_cookie( $this->user_id, $this->expiration, 'secure_auth', $other_token );
		$this->assertFalse( is_jetpack_sso_two_step() );
	}

	public function test_expired_or_modified_assertions_fail(): void {
		$this->set_valid_assertions();
		$_COOKIE[ VIP_IS_JETPACK_SSO_2SA_COOKIE ] = generate_sso_assertion( $this->user_id, time() - 1, $this->token, 'jetpack_sso_2sa' );
		$this->assertFalse( is_jetpack_sso_two_step() );

		$this->set_valid_assertions();
		$_COOKIE[ VIP_IS_JETPACK_SSO_2SA_COOKIE ] .= 'x';
		$this->assertFalse( is_jetpack_sso_two_step() );
	}

	public function test_revoked_session_cannot_use_assertions(): void {
		$this->set_valid_assertions();
		WP_Session_Tokens::get_instance( $this->user_id )->destroy( $this->token );

		$this->assertFalse( is_jetpack_sso_two_step() );
	}
}
