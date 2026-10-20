<?php

// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE, WordPress.Security.ValidatedSanitizedInput

use function Automattic\VIP\TwoFactor\is_jetpack_sso;
use function Automattic\VIP\TwoFactor\is_jetpack_sso_two_step;

require_once __DIR__ . '/../../two-factor.php';
require_once __DIR__ . '/../../shared-plugins/two-factor/two-factor.php';

class Test_Jetpack_SSO_Session extends WP_UnitTestCase {
	private $cookies_backup;
	private $user_id;
	private $session_filter_backup;

	public function setUp(): void {
		parent::setUp();

		$this->cookies_backup        = $_COOKIE;
		$_COOKIE                     = [];
		$this->user_id               = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$this->session_filter_backup = isset( $GLOBALS['wp_filter']['attach_session_information'] ) ? clone $GLOBALS['wp_filter']['attach_session_information'] : null;
		add_filter( 'send_auth_cookies', '__return_false' );
		add_filter( 'wpcom_vip_is_two_factor_local_testing', '__return_true' );
		add_filter( 'wpcom_vip_is_two_factor_forced', '__return_true' );
		add_filter( 'wpcom_vip_is_user_using_two_factor', '__return_false' );
		add_filter( 'wpcom_vip_use_custom_sso', '__return_false' );
	}

	public function tearDown(): void {
		if ( $this->session_filter_backup ) {
			$GLOBALS['wp_filter']['attach_session_information'] = $this->session_filter_backup;
		} else {
			unset( $GLOBALS['wp_filter']['attach_session_information'] );
		}
		$_COOKIE = $this->cookies_backup;
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * Create a session through Core's actual auth-cookie path without emitting headers.
	 */
	private function create_auth_session( $user_id ): array {
		$result  = [];
		$capture = function ( $cookie, $expire, $expiration, $cookie_user_id, $scheme, $token ) use ( &$result ) {
			$result = [
				'token'      => $token,
				'expiration' => $expiration,
				'cookie'     => $cookie,
			];
		};
		add_action( 'set_logged_in_cookie', $capture, 10, 6 );
		try {
			wp_set_auth_cookie( $user_id, true );
		} finally {
			remove_action( 'set_logged_in_cookie', $capture, 10 );
		}
		$this->assertNotEmpty( $result );

		return $result;
	}

	private function create_sso_session( $two_step_enabled = true ): array {
		do_action( 'jetpack_sso_handle_login', get_user_by( 'id', $this->user_id ), (object) [ 'two_step_enabled' => $two_step_enabled ] );

		return $this->create_auth_session( $this->user_id );
	}

	private function activate_session( array $session, $user_id = null ): void {
		$_COOKIE = [ LOGGED_IN_COOKIE => $session['cookie'] ];
		wp_set_current_user( $user_id ?? $this->user_id );
	}

	public function test_sso_login_attaches_flags_to_the_created_session(): void {
		$session = $this->create_sso_session();
		$this->activate_session( $session );
		$stored = WP_Session_Tokens::get_instance( $this->user_id )->get( $session['token'] );

		$this->assertTrue( $stored['vip_jetpack_sso'] );
		$this->assertTrue( $stored['vip_jetpack_sso_two_step'] );
		$this->assertSame( $session['expiration'], $stored['expiration'] );
		$this->assertSame( $this->user_id, is_jetpack_sso() );
		$this->assertSame( $this->user_id, is_jetpack_sso_two_step() );
		$this->assertFalse( wpcom_vip_should_force_two_factor() );
		$this->assertSame( [ 'create_users' ], wpcom_vip_two_factor_filter_caps( [ 'create_users' ], 'create_users', $this->user_id, [] ) );
	}

	public function test_sso_without_two_step_keeps_two_factor_enforcement(): void {
		$this->activate_session( $this->create_sso_session( false ) );

		$this->assertSame( $this->user_id, is_jetpack_sso() );
		$this->assertFalse( is_jetpack_sso_two_step() );
		$this->assertTrue( wpcom_vip_should_force_two_factor() );
		$this->assertSame( [ 'do_not_allow' ], wpcom_vip_two_factor_filter_caps( [ 'create_users' ], 'create_users', $this->user_id, [] ) );
	}

	public function test_missing_two_step_result_does_not_grant_exemption(): void {
		do_action( 'jetpack_sso_handle_login', get_user_by( 'id', $this->user_id ), (object) [] );
		$this->activate_session( $this->create_auth_session( $this->user_id ) );

		$this->assertSame( $this->user_id, is_jetpack_sso() );
		$this->assertFalse( is_jetpack_sso_two_step() );
	}

	public function test_normal_login_has_no_sso_flags(): void {
		$this->activate_session( $this->create_auth_session( $this->user_id ) );

		$this->assertFalse( is_jetpack_sso() );
		$this->assertFalse( is_jetpack_sso_two_step() );
		$this->assertTrue( wpcom_vip_should_force_two_factor() );
	}

	public function test_other_user_session_is_not_tagged_and_filter_is_consumed_once(): void {
		$other_user_id = self::factory()->user->create();
		do_action( 'jetpack_sso_handle_login', get_user_by( 'id', $this->user_id ), (object) [ 'two_step_enabled' => true ] );
		$this->activate_session( $this->create_auth_session( $other_user_id ), $other_user_id );
		$this->assertFalse( is_jetpack_sso() );

		$this->activate_session( $this->create_auth_session( $this->user_id ) );
		$this->assertSame( $this->user_id, is_jetpack_sso_two_step() );

		$this->activate_session( $this->create_auth_session( $this->user_id ) );
		$this->assertFalse( is_jetpack_sso() );
		$this->assertFalse( is_jetpack_sso_two_step() );
	}

	public function test_new_sso_without_two_step_does_not_inherit_previous_exemption(): void {
		$this->activate_session( $this->create_sso_session() );
		$this->assertSame( $this->user_id, is_jetpack_sso_two_step() );
		$this->activate_session( $this->create_sso_session( false ) );

		$this->assertSame( $this->user_id, is_jetpack_sso() );
		$this->assertFalse( is_jetpack_sso_two_step() );
	}

	public function test_invalid_sso_user_does_not_tag_a_session(): void {
		do_action( 'jetpack_sso_handle_login', false, (object) [ 'two_step_enabled' => true ] );
		$this->activate_session( $this->create_auth_session( $this->user_id ) );

		$this->assertFalse( is_jetpack_sso() );
	}

	public function test_auth_cookie_replay_as_legacy_markers_does_not_grant_exemption(): void {
		$this->activate_session( $this->create_auth_session( $this->user_id ) );
		$other_user_id = self::factory()->user->create();
		$other         = $this->create_auth_session( $other_user_id );
		foreach ( [ [ $this->user_id, wp_get_session_token() ], [ $other_user_id, $other['token'] ] ] as [ $cookie_user_id, $token ] ) {
			$cookie = wp_generate_auth_cookie( $cookie_user_id, $other['expiration'], 'secure_auth', $token );
			$this->assertSame( $cookie_user_id, wp_validate_auth_cookie( $cookie, 'secure_auth' ) );
			$_COOKIE[ VIP_IS_JETPACK_SSO_COOKIE ]     = $cookie;
			$_COOKIE[ VIP_IS_JETPACK_SSO_2SA_COOKIE ] = $cookie;

			$this->assertFalse( is_jetpack_sso() );
			$this->assertFalse( is_jetpack_sso_two_step() );
			$this->assertTrue( wpcom_vip_should_force_two_factor() );
			$this->assertSame( [ 'do_not_allow' ], wpcom_vip_two_factor_filter_caps( [ 'create_users' ], 'create_users', $this->user_id, [] ) );
		}
	}

	public function test_flags_cannot_be_used_for_another_current_user(): void {
		$this->activate_session( $this->create_sso_session(), self::factory()->user->create() );

		$this->assertFalse( is_jetpack_sso() );
		$this->assertFalse( is_jetpack_sso_two_step() );
	}

	public function test_auth_cookie_can_read_sso_session_without_logged_in_cookie(): void {
		$session = $this->create_sso_session();
		$this->activate_session( $session );
		$_COOKIE = [ SECURE_AUTH_COOKIE => wp_generate_auth_cookie( $this->user_id, $session['expiration'], 'secure_auth', $session['token'] ) ];

		$this->assertSame( $this->user_id, is_jetpack_sso_two_step() );
	}

	public function test_tampered_auth_cookie_cannot_read_session_flags(): void {
		$this->activate_session( $this->create_sso_session() );
		$_COOKIE[ LOGGED_IN_COOKIE ] .= 'x';

		$this->assertFalse( is_jetpack_sso_two_step() );
	}

	public function test_missing_auth_cookie_cannot_read_session_flags(): void {
		$this->activate_session( $this->create_sso_session() );
		$_COOKIE = [];

		$this->assertFalse( is_jetpack_sso_two_step() );
	}

	public function test_logged_out_user_has_no_sso_status(): void {
		$this->activate_session( $this->create_sso_session() );
		wp_set_current_user( 0 );

		$this->assertFalse( is_jetpack_sso() );
		$this->assertFalse( is_jetpack_sso_two_step() );
	}

	public function test_expired_session_flags_are_rejected(): void {
		$session = $this->create_sso_session();
		$this->activate_session( $session );
		$manager              = WP_Session_Tokens::get_instance( $this->user_id );
		$stored               = $manager->get( $session['token'] );
		$stored['expiration'] = time() - 1;
		$manager->update( $session['token'], $stored );

		$this->assertFalse( is_jetpack_sso() );
		$this->assertFalse( is_jetpack_sso_two_step() );
	}

	public function test_revoked_session_flags_are_rejected(): void {
		$session = $this->create_sso_session();
		$this->activate_session( $session );
		WP_Session_Tokens::get_instance( $this->user_id )->destroy( $session['token'] );

		$this->assertFalse( is_jetpack_sso() );
		$this->assertFalse( is_jetpack_sso_two_step() );
	}

	public function test_two_step_flag_alone_is_not_an_sso_session(): void {
		$session = $this->create_auth_session( $this->user_id );
		$this->activate_session( $session );
		$manager                            = WP_Session_Tokens::get_instance( $this->user_id );
		$stored                             = $manager->get( $session['token'] );
		$stored['vip_jetpack_sso_two_step'] = true;
		$manager->update( $session['token'], $stored );

		$this->assertFalse( is_jetpack_sso_two_step() );
	}
}
