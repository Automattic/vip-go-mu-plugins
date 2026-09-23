<?php

// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE, WordPress.Security.ValidatedSanitizedInput

use function Automattic\VIP\TwoFactor\generate_sso_assertion;
use function Automattic\VIP\TwoFactor\is_jetpack_sso;
use function Automattic\VIP\TwoFactor\is_jetpack_sso_two_step;

require_once __DIR__ . '/mock-sso-cookie.php';
require_once __DIR__ . '/../../wpcom-vip-two-factor/is-jetpack-sso.php';

class Test_Jetpack_SSO_Assertions extends WP_UnitTestCase {
	private $cookies_backup;
	private $user_id;
	private $token;
	private $expiration;
	private $auth_cookie_callbacks = [];

	public function setUp(): void {
		parent::setUp();

		$this->cookies_backup            = $_COOKIE;
		$_COOKIE                         = [];
		$this->user_id                   = self::factory()->user->create();
		$this->expiration                = time() + HOUR_IN_SECONDS;
		$this->token                     = WP_Session_Tokens::get_instance( $this->user_id )->create( $this->expiration );
		$GLOBALS['vip_test_sso_cookies'] = [];

		wp_set_current_user( $this->user_id );
		$_COOKIE[ SECURE_AUTH_COOKIE ] = wp_generate_auth_cookie( $this->user_id, $this->expiration, 'secure_auth', $this->token );
	}

	public function tearDown(): void {
		foreach ( $this->auth_cookie_callbacks as $callback ) {
			remove_action( 'set_auth_cookie', $callback, 10 );
		}
		unset( $GLOBALS['vip_test_sso_cookies'] );
		$_COOKIE = $this->cookies_backup;
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function set_valid_assertions(): void {
		$_COOKIE[ VIP_IS_JETPACK_SSO_COOKIE ]     = generate_sso_assertion( $this->user_id, $this->expiration, $this->token, 'jetpack_sso' );
		$_COOKIE[ VIP_IS_JETPACK_SSO_2SA_COOKIE ] = generate_sso_assertion( $this->user_id, $this->expiration, $this->token, 'jetpack_sso_2sa' );
	}

	private function trigger_sso_login( $two_step_enabled, $auth_user_id = null ): void {
		$before = $GLOBALS['wp_filter']['set_auth_cookie']->callbacks[10] ?? [];
		do_action( 'jetpack_sso_handle_login', get_user_by( 'id', $this->user_id ), (object) [ 'two_step_enabled' => $two_step_enabled ] );
		$after = $GLOBALS['wp_filter']['set_auth_cookie']->callbacks[10] ?? [];

		foreach ( array_diff_key( $after, $before ) as $callback ) {
			$this->auth_cookie_callbacks[] = $callback['function'];
		}

		// Keep the browser cookie expiry distinct from the signed assertion expiry.
		do_action( 'set_auth_cookie', 'auth-cookie', $this->expiration + HOUR_IN_SECONDS, $this->expiration, $auth_user_id ?? $this->user_id, 'secure_auth', $this->token );
	}

	public function test_login_hook_sets_session_bound_sso_and_two_step_cookies(): void {
		$this->trigger_sso_login( true );

		$this->assertCount( 2, $GLOBALS['vip_test_sso_cookies'] );
		$this->assertSame( VIP_IS_JETPACK_SSO_COOKIE, $GLOBALS['vip_test_sso_cookies'][0]['name'] );
		$this->assertSame( VIP_IS_JETPACK_SSO_2SA_COOKIE, $GLOBALS['vip_test_sso_cookies'][1]['name'] );

		foreach ( $GLOBALS['vip_test_sso_cookies'] as $cookie ) {
			$this->assertSame( $this->expiration + HOUR_IN_SECONDS, $cookie['expires'] );
			$this->assertSame( COOKIEPATH, $cookie['path'] );
			$this->assertSame( COOKIE_DOMAIN, $cookie['domain'] );
			$this->assertSame( is_ssl(), $cookie['secure'] );
			$this->assertTrue( $cookie['httponly'] );
			$this->assertSame( (string) $this->expiration, explode( '|', $cookie['value'] )[2] );
			$_COOKIE[ $cookie['name'] ] = $cookie['value'];
		}

		$this->assertSame( $this->user_id, is_jetpack_sso_two_step() );
	}

	public function test_login_hook_clears_two_step_cookie_when_disabled(): void {
		$_COOKIE[ VIP_IS_JETPACK_SSO_2SA_COOKIE ] = 'existing-marker';
		$this->trigger_sso_login( false );

		$this->assertCount( 2, $GLOBALS['vip_test_sso_cookies'] );
		$this->assertSame( VIP_IS_JETPACK_SSO_COOKIE, $GLOBALS['vip_test_sso_cookies'][0]['name'] );
		$this->assertSame( VIP_IS_JETPACK_SSO_2SA_COOKIE, $GLOBALS['vip_test_sso_cookies'][1]['name'] );
		$this->assertSame( ' ', $GLOBALS['vip_test_sso_cookies'][1]['value'] );
		$this->assertLessThan( time(), $GLOBALS['vip_test_sso_cookies'][1]['expires'] );
		$_COOKIE[ VIP_IS_JETPACK_SSO_COOKIE ] = $GLOBALS['vip_test_sso_cookies'][0]['value'];
		unset( $_COOKIE[ VIP_IS_JETPACK_SSO_2SA_COOKIE ] );
		$this->assertSame( $this->user_id, is_jetpack_sso() );
		$this->assertFalse( is_jetpack_sso_two_step() );
	}

	public function test_login_hook_does_not_set_markers_for_another_auth_user(): void {
		$this->trigger_sso_login( true, self::factory()->user->create() );

		$this->assertSame( [], $GLOBALS['vip_test_sso_cookies'] );
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
