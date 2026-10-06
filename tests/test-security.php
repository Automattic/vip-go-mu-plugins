<?php

use Automattic\Test\Constant_Mocker;
require_once __DIR__ . '/class-vip-auth-ttl-test-cache.php';

class VIP_Go_Security_Test extends WP_UnitTestCase {
	private $original_post;
	private $test_username = 'iamgroot';
	private $test_ip       = '127.0.0.1';

	public function setUp(): void {
		parent::setUp();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$this->original_post = $_POST;
	}

	public function tearDown(): void {
		$_POST = $this->original_post;

		Constant_Mocker::clear();
		$this->clean_event_window_cache();

		parent::tearDown();
	}

	public function test__admin_username_restricted() {
		// Nightly WordPress can retain the installer's admin account.
		$admin_id = username_exists( 'admin' );
		if ( $admin_id ) {
			wp_set_password( 'secret1', $admin_id );
		} else {
			$this->factory()->user->create( [
				'user_login' => 'admin',
				'user_email' => 'admin@example.com',
				'user_pass'  => 'secret1',
			] );
		}

		$result = wp_authenticate( 'admin', 'secret1' );

		$this->assertWPError( $result );
		$this->assertEquals( 'restricted-login', $result->get_error_code() );
	}

	public function test__vip_machine_user_username_restricted() {
		$this->factory()->user->create( [
			'user_login' => WPCOM_VIP_MACHINE_USER_LOGIN,
			'user_email' => WPCOM_VIP_MACHINE_USER_EMAIL,
			'user_pass'  => 'secret2',
		] );

		$result = wp_authenticate( WPCOM_VIP_MACHINE_USER_LOGIN, 'secret2' );

		$this->assertWPError( $result );
		$this->assertEquals( 'restricted-login', $result->get_error_code() );
	}

	public function test__vip_machine_user_email_restricted() {
		$this->factory()->user->create( [
			'user_login' => WPCOM_VIP_MACHINE_USER_LOGIN,
			'user_email' => WPCOM_VIP_MACHINE_USER_EMAIL,
			'user_pass'  => 'secret3',
		] );

		$result = wp_authenticate( WPCOM_VIP_MACHINE_USER_EMAIL, 'secret3' );

		$this->assertWPError( $result );
		$this->assertEquals( 'restricted-login', $result->get_error_code() );
	}

	public function test__other_username_not_restricted() {
		$user_id = $this->factory()->user->create( [
			'user_login' => 'taylorswift',
			'user_email' => 'taylor@example.com',
			'user_pass'  => 'secret4',
		] );

		$result = wp_authenticate( 'taylorswift', 'secret4' );

		$this->assertNotWPError( $result );
		$this->assertEquals( $user_id, $result->ID );
	}

	public function test__other_email_not_restricted() {
		$user_id = $this->factory()->user->create( [
			'user_login' => 'taylorswift',
			'user_email' => 'taylor@example.com',
			'user_pass'  => 'secret5',
		] );

		$result = wp_authenticate( 'taylor@example.com', 'secret5' );

		$this->assertNotWPError( $result );
		$this->assertEquals( $user_id, $result->ID );
	}

	public function test__lostpassword_post_unmodified_errors() {

		$original_error_code = 'original-error-code';
		$original_error_text = 'Original Error Code';
		$errors              = new WP_Error();
		$errors->add( $original_error_code, $original_error_text );

		do_action( 'lostpassword_post', $errors, false );

		$actual_error_codes = $errors;

		$this->assertEquals( $actual_error_codes->get_error_code(), $original_error_code );
	}

	public function test__lost_password_limit() {

		// Set our login.
		$_POST = [
			'user_login' => 'taylorswift',
		];

		$errors = new WP_Error();

		/**
		 * This should match the $threshold set in wpcom_vip_username_is_limited()
		 * for the lost_password_limit cache group Currently, the $threshold is the
		 * same for restricted and unrestricted usernames, if that changes this test
		 * will need to be updated.
		 */
		$threshold            = 3;
		$just_under_threshold = $threshold - 1;

		for ( $i = 0; $i <= $just_under_threshold; $i++ ) {

			do_action( 'lostpassword_post', $errors, false );

			// Make sure we haven't received an error yet
			$this->assertEquals( $errors->get_error_code(), false );

		}

		// Do the lostpassword_post one more time to reach our threshold.
		do_action( 'lostpassword_post', $errors, false );

		// Now we should have an error.
		$this->assertEquals( $errors->get_error_code(), 'lost_password_limit_exceeded' );
	}

	/**
	 * Failed authentication must accumulate counters and deny correct credentials at the threshold.
	 */
	public function test__failed_authentications_activate_login_limiter() {
		$user = $this->factory()->user->create_and_get( [
			'user_login' => $this->test_username,
			'user_pass'  => 'correct-password',
		] );
		$this->assertInstanceOf( WP_User::class, wp_authenticate( $user->user_login, 'correct-password' ) );
		for ( $attempt = 1; $attempt <= 5; $attempt++ ) {
			$error = wp_authenticate( $user->user_login, 'wrong-password' );
			$this->assertWPError( $error );
			$this->assertSame( $attempt, wp_cache_get( $this->test_ip . '|' . $user->user_login, CACHE_GROUP_LOGIN_LIMIT ) );
		}
		$error = wp_authenticate( $user->user_login, 'correct-password' );
		$this->assertWPError( $error );
		$this->assertSame( ERROR_CODE_LOGIN_LIMIT_EXCEEDED, $error->get_error_code() );
	}

	public function test__wpcom_vip_track_auth_attempt__defaults() {
		wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );

		$username_count = wp_cache_get( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
		$this->assertSame( 1, $username_count );

		$ip_count = wp_cache_get( $this->test_ip, CACHE_GROUP_LOGIN_LIMIT );
		$this->assertSame( 1, $ip_count );

		$ip_username_count = wp_cache_get( $this->test_ip . '|' . $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
		$this->assertSame( 1, $ip_username_count );
	}

	public function test__wpcom_vip_login_limiter_on_success__decrease_count() {
		$original_count  = 5;
		$decreased_count = $original_count - 1;
		wp_cache_set( $this->test_username, $original_count, CACHE_GROUP_LOGIN_LIMIT );
		wp_cache_set( $this->test_ip, $original_count, CACHE_GROUP_LOGIN_LIMIT );
		wp_cache_set( $this->test_ip . '|' . $this->test_username, $original_count, CACHE_GROUP_LOGIN_LIMIT );

		wpcom_vip_login_limiter_on_success( $this->test_username );

		$username_count = wp_cache_get( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
		$this->assertSame( $original_count, $username_count ); // username is NOT decreased

		$ip_count = wp_cache_get( $this->test_ip, CACHE_GROUP_LOGIN_LIMIT );
		$this->assertSame( $decreased_count, $ip_count );

		$ip_username_count = wp_cache_get( $this->test_ip . '|' . $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
		$this->assertSame( $decreased_count, $ip_username_count );
	}

	public function test__wpcom_vip_username_is_limited__should_not_limit_by_default() {
		$result = wpcom_vip_username_is_limited( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );

		$this->assertSame( false, $result );
	}
	public function test__wpcom_vip_username_is_limited__should_be_limit_after_few_tries() {
		add_filter( 'vip_login_ip_username_lockout', function ( $lockout ) {
			$this->assertSame( 60 * 5, $lockout );
			return $lockout;
		}, 10, 1 );
		$action_triggered = 0;
		add_action( 'login_limit_exceeded', function () use ( &$action_triggered ) {
			$action_triggered++;
		});

		wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
		wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
		wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
		wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
		wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );

		$result = wpcom_vip_username_is_limited( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );

		$this->assertSame( true, is_wp_error( $result ) );
		$this->assertSame( 1, $action_triggered );
	}

	public function test__wpcom_vip_username_is_limited__should_be_limit_even_after_the_event_window() {
		wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
		wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
		wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
		wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
		wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );

		$this->clean_event_window_cache();

		$result = wpcom_vip_username_is_limited( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );

		$this->assertSame( true, is_wp_error( $result ) );
	}

	public function test__wpcom_vip_username_is_limited__should_be_limit_after_3_attempts_fedramp() {
		Constant_Mocker::define( 'VIP_IS_FEDRAMP', true );

		add_filter( 'vip_login_ip_username_lockout', function ( $lockout ) {
			$this->assertSame( 60 * 30, $lockout );
			return $lockout;
		}, 10, 1 );

		wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
		wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
		wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );

		$result = wpcom_vip_username_is_limited( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );

		$this->assertSame( true, is_wp_error( $result ) );
	}

	public function test__wpcom_vip_track_auth_attempt__correct_defaults() {
		add_filter( 'vip_login_ip_username_window', function ( $window ) {
			$this->assertSame( 60 * 5, $window );
			return $window;
		}, 10, 1 );
		add_filter( 'vip_login_ip_window', function ( $window ) {
			$this->assertSame( 60 * 60, $window );
			return $window;
		}, 10, 1 );
		add_filter( 'vip_login_username_window', function ( $window ) {
			$this->assertSame( 60 * 25, $window );
			return $window;
		}, 10, 1 );

		wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
	}

	public function test__wpcom_vip_track_auth_attempt__correct_defaults_fedramp() {
		Constant_Mocker::define( 'VIP_IS_FEDRAMP', true );

		add_filter( 'vip_login_ip_username_window', function ( $window ) {
			$this->assertSame( 60 * 15, $window );
			return $window;
		}, 10, 1 );
		add_filter( 'vip_login_ip_window', function ( $window ) {
			$this->assertSame( 60 * 15, $window );
			return $window;
		}, 10, 1 );
		add_filter( 'vip_login_username_window', function ( $window ) {
			$this->assertSame( 60 * 15, $window );
			return $window;
		}, 10, 1 );

		wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
	}

	/**
	 * Filtered windows and lockouts must reach the cache and expire at that boundary.
	 */
	public function test__filtered_auth_windows_expire_at_cache_boundary() {
		global $wp_object_cache;
		$original_cache  = $wp_object_cache;
		$cache           = new VIP_Auth_TTL_Test_Cache();
		$wp_object_cache = $cache;
		add_filter( 'vip_login_ip_window', static fn() => 7 );
		add_filter( 'vip_login_ip_username_window', static fn() => 11 );
		add_filter( 'vip_login_username_window', static fn() => 13 );
		add_filter( 'vip_login_ip_username_lockout', static fn() => 17 );
		try {
			wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
			$this->assertSame( 7, $cache->expirations[ $this->test_ip ] );
			$this->assertSame( 11, $cache->expirations[ $this->test_ip . '|' . $this->test_username ] );
			$this->assertSame( 13, $cache->expirations[ $this->test_username ] );
			for ( $attempt = 1; $attempt < 5; $attempt++ ) {
				wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
			}
			$this->assertSame( 17, $cache->expirations[ CACHE_KEY_LOCK_PREFIX . $this->test_ip . '|' . $this->test_username ] );
			$this->assertWPError( wpcom_vip_username_is_limited( $this->test_username, CACHE_GROUP_LOGIN_LIMIT ) );
			$cache->now = 16;
			$this->assertWPError( wpcom_vip_username_is_limited( $this->test_username, CACHE_GROUP_LOGIN_LIMIT ) );
			$cache->now = 17;
			$this->assertFalse( wpcom_vip_username_is_limited( $this->test_username, CACHE_GROUP_LOGIN_LIMIT ) );
		} finally {
			$wp_object_cache = $original_cache;
		}
	}

	private function clean_event_window_cache() {
		wp_cache_delete( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
		wp_cache_delete( $this->test_ip, CACHE_GROUP_LOGIN_LIMIT );
		wp_cache_delete( $this->test_ip . '|' . $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
	}

	public function test_create_admin_user() {
		global $wpdb;

		// The installer's admin account would make core reject the login as a duplicate, masking the VIP restriction.
		$installer_admin = get_user_by( 'login', 'admin' );
		if ( $installer_admin ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users
			$wpdb->update( $wpdb->users, [ 'user_login' => 'installer-admin' ], [ 'ID' => $installer_admin->ID ] );
			clean_user_cache( $installer_admin );
		}

		$this->assertFalse( username_exists( 'admin' ) );

		$result = wp_insert_user( [
			'user_login' => 'admin',
			'user_email' => 'admin@example.com',
			'user_pass'  => '53cr3t!',
		] );

		$this->assertWPError( $result );
		$this->assertSame( 'invalid_username', $result->get_error_code() );
	}

	/**
	 * Test that vipgo username is restricted in non-local environments.
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__vipgo_username_restricted_in_non_local() {
		define( 'WP_ENVIRONMENT_TYPE', 'production' );

		$this->factory()->user->create( [
			'user_login' => 'vipgo',
			'user_email' => 'vipgo@example.com',
			'user_pass'  => 'secret6',
		] );

		$result = wp_authenticate( 'vipgo', 'secret6' );

		$this->assertWPError( $result );
		$this->assertEquals( 'restricted-login', $result->get_error_code() );
	}

	/**
	 * Test that vipgo username is not restricted in local environment.
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__vipgo_username_not_restricted_in_local() {
		define( 'WP_ENVIRONMENT_TYPE', 'local' );

		$user_id = $this->factory()->user->create( [
			'user_login' => 'vipgo',
			'user_email' => 'vipgo@example.com',
			'user_pass'  => 'secret7',
		] );

		$result = wp_authenticate( 'vipgo', 'secret7' );

		$this->assertNotWPError( $result );
		$this->assertEquals( $user_id, $result->ID );
	}

	public function test_session_expiration(): void {
		remove_all_filters( 'authenticate' );

		$filter_invoked = false;

		add_filter( 'authenticate', fn () => new WP_Error( 'expired_session', 'Your session has expired. Please log in again.' ), 99 );
		add_filter( 'vip_login_username_window', static function ( $window ) use ( &$filter_invoked ) {
			$filter_invoked = true;
			return $window;
		}, 10, 1 );

		$user = wp_authenticate( 'some-user', 'some-password' );
		$this->assertWPError( $user );
		static::assertFalse( $filter_invoked );
	}
}
