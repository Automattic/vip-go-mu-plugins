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
		$_POST                  = $this->original_post;
		$_SERVER['REMOTE_ADDR'] = $this->test_ip; // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__

		$this->clean_event_window_cache();

		parent::tearDown();
	}

	public function data_restricted_logins(): array {
		return [
			'admin username'             => [ 'admin', 'admin@example.com', 'admin' ],
			'admin username, other case' => [ 'admin', 'admin@example.com', 'Admin' ],
			'admin email'                => [ 'admin', 'admin@example.com', 'Admin@Example.com' ],
			'admin username, fullwidth'  => [ 'admin', 'admin@example.com', "\u{FF41}\u{FF44}\u{FF4D}\u{FF49}\u{FF4E}" ],
			'machine user username'      => [ WPCOM_VIP_MACHINE_USER_LOGIN, WPCOM_VIP_MACHINE_USER_EMAIL, WPCOM_VIP_MACHINE_USER_LOGIN ],
			'machine user email'         => [ WPCOM_VIP_MACHINE_USER_LOGIN, WPCOM_VIP_MACHINE_USER_EMAIL, WPCOM_VIP_MACHINE_USER_EMAIL ],
		];
	}

	/**
	 * @dataProvider data_restricted_logins
	 */
	public function test__restricted_login( string $user_login, string $user_email, string $log_in_with ) {
		$this->set_up_restricted_account( $user_login, $user_email );

		$result = wp_authenticate( $log_in_with, 'secret1' );

		$this->assertWPError( $result );
		$this->assertEquals( 'restricted-login', $result->get_error_code() );
	}

	/**
	 * A wrong password for a restricted account's email gets the usual error, not the restricted-login one.
	 */
	public function test__restricted_account_email_with_wrong_password_gets_usual_error() {
		$this->set_up_restricted_account( 'admin', 'admin@example.com' );

		$result = wp_authenticate( 'admin@example.com', 'wrong-password' );

		$this->assertWPError( $result );
		$this->assertSame( 'incorrect_password', $result->get_error_code() );
	}

	/**
	 * Create alice, whose login is mallory's email, so core checks both accounts' passwords for that username.
	 */
	private function create_accounts_sharing_a_username(): void {
		$this->factory()->user->create( [
			'user_login' => 'alice@example.com',
			'user_email' => 'alice-new@example.com',
			'user_pass'  => 'alice-password',
		] );
		$this->factory()->user->create( [
			'user_login' => 'mallory',
			'user_email' => 'alice@example.com',
			'user_pass'  => 'mallory-password',
		] );
	}

	private function set_up_restricted_account( string $user_login, string $user_email ): void {
		global $wpdb;

		// VIP blocks creating `admin`, but sites can still have one, like the installer's account.
		remove_filter( 'illegal_user_logins', 'vip_illegal_user_logins' );
		$user_id = username_exists( $user_login ) ?: $this->factory()->user->create( [ 'user_login' => $user_login ] );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users
		$wpdb->update( $wpdb->users, [ 'user_email' => $user_email ], [ 'ID' => $user_id ] );
		clean_user_cache( $user_id );
		wp_set_password( 'secret1', $user_id );
	}

	public function data_unrestricted_logins(): array {
		return [
			'username' => [ 'taylorswift' ],
			'email'    => [ 'taylor@example.com' ],
		];
	}

	/**
	 * @dataProvider data_unrestricted_logins
	 */
	public function test__other_login_not_restricted( string $log_in_with ) {
		$user_id = $this->factory()->user->create( [
			'user_login' => 'taylorswift',
			'user_email' => 'taylor@example.com',
			'user_pass'  => 'secret4',
		] );

		$result = wp_authenticate( $log_in_with, 'secret4' );

		$this->assertNotWPError( $result );
		$this->assertEquals( $user_id, $result->ID );
	}

	/**
	 * A request that already failed keeps its errors: the limit error isn't added even for a locked username.
	 */
	public function test__lostpassword_post_unmodified_errors() {
		$_POST = [
			'user_login' => $this->test_username,
		];
		wp_cache_set( CACHE_KEY_LOCK_PREFIX . $this->cache_keys()['username_cache_key'], true, CACHE_GROUP_LOST_PASSWORD_LIMIT );

		$errors = new WP_Error( 'original-error-code', 'Original Error Code' );

		do_action( 'lostpassword_post', $errors, false );

		$this->assertSame( [ 'original-error-code' ], $errors->get_error_codes() );
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
			$this->assertSame( $attempt, wp_cache_get( $this->cache_keys()['ip_username_cache_key'], CACHE_GROUP_LOGIN_LIMIT ) );
		}
		$error = wp_authenticate( $user->user_login, 'correct-password' );
		$this->assertWPError( $error );
		$this->assertSame( ERROR_CODE_LOGIN_LIMIT_EXCEEDED, $error->get_error_code() );
	}

	/**
	 * Attempts count against the account core found, whatever form of the username they log in with,
	 * and a locked account is refused before its password is checked.
	 */
	public function test__failed_authentications_count_against_the_account() {
		$this->factory()->user->create( [
			'user_login' => $this->test_username,
			'user_email' => 'groot@example.com',
			'user_pass'  => 'correct-password',
		] );
		foreach ( [ 'IAmGroot', 'groot@example.com', "\u{FF49}amgroot", 'iamGROOT', 'Groot@Example.com' ] as $log_in_with ) {
			$this->assertWPError( wp_authenticate( $log_in_with, 'wrong-password' ) );
		}

		$password_checks = 0;
		add_filter( 'check_password', function ( $check ) use ( &$password_checks ) {
			$password_checks++;
			return $check;
		} );
		foreach ( [ $this->test_username, 'Groot@Example.com' ] as $log_in_with ) {
			$error = wp_authenticate( $log_in_with, 'correct-password' );
			$this->assertWPError( $error );
			$this->assertSame( ERROR_CODE_LOGIN_LIMIT_EXCEEDED, $error->get_error_code() );
		}
		$this->assertSame( 0, $password_checks );
	}

	/**
	 * When a username is one account's login and another's email, core checks both passwords, so both count,
	 * and the IP address counts once.
	 */
	public function test__failed_authentications_count_against_every_account_checked() {
		$this->create_accounts_sharing_a_username();
		for ( $attempt = 1; $attempt <= 5; $attempt++ ) {
			$this->assertWPError( wp_authenticate( 'alice@example.com', 'wrong-password' ) );
		}
		$this->assertSame( 5, wp_cache_get( $this->cache_keys()['ip_cache_key'], CACHE_GROUP_LOGIN_LIMIT ) );

		$logins = [
			'alice@example.com' => 'alice-password',
			'mallory'           => 'mallory-password',
		];
		foreach ( $logins as $log_in_with => $password ) {
			$error = wp_authenticate( $log_in_with, $password );
			$this->assertWPError( $error );
			$this->assertSame( ERROR_CODE_LOGIN_LIMIT_EXCEEDED, $error->get_error_code() );
		}
	}

	public function data_other_handler_locks(): array {
		return [
			'account locked'          => [ 'iamgroot' ],
			'entered username locked' => [ 'member-12345' ],
		];
	}

	/**
	 * Both locks hold when another authentication handler logs the account in by a different username.
	 *
	 * @dataProvider data_other_handler_locks
	 */
	public function test__lockout_applies_to_account_found_by_other_handler( string $locked_username ) {
		$user = $this->factory()->user->create_and_get( [ 'user_login' => $this->test_username ] );
		wp_cache_set( CACHE_KEY_LOCK_PREFIX . _vip_login_cache_keys( $locked_username )['username_cache_key'], true, CACHE_GROUP_LOGIN_LIMIT );
		add_filter( 'authenticate', static fn( $result, $username ) => 'member-12345' === $username ? $user : $result, 10, 2 );

		$error = wp_authenticate( 'member-12345', 'any-password' );
		$this->assertWPError( $error );
		$this->assertSame( ERROR_CODE_LOGIN_LIMIT_EXCEEDED, $error->get_error_code() );
	}

	/**
	 * A failed check of one account counts even when the same attempt logs into another account,
	 * including where wp_login doesn't fire, as on XML-RPC.
	 */
	public function test__failed_check_counts_when_attempt_logs_into_other_account() {
		$this->create_accounts_sharing_a_username();
		for ( $attempt = 1; $attempt <= 5; $attempt++ ) {
			$this->assertSame( 'mallory', wp_authenticate( 'alice@example.com', 'mallory-password' )->user_login );
		}
		$this->assertFalse( wp_cache_get( $this->cache_keys()['ip_cache_key'], CACHE_GROUP_LOGIN_LIMIT ) );

		$error = wp_authenticate( 'alice@example.com', 'alice-password' );
		$this->assertWPError( $error );
		$this->assertSame( ERROR_CODE_LOGIN_LIMIT_EXCEEDED, $error->get_error_code() );
	}

	/**
	 * A locked account found by email keeps the lock error when a later handler replaces it,
	 * as the application password handler does on XML-RPC.
	 */
	public function test__lock_error_survives_later_handler() {
		$this->factory()->user->create( [
			'user_login' => $this->test_username,
			'user_email' => 'groot@example.com',
		] );
		wp_cache_set( CACHE_KEY_LOCK_PREFIX . $this->cache_keys()['username_cache_key'], true, CACHE_GROUP_LOGIN_LIMIT );
		add_filter( 'authenticate', static fn( $result ) => is_wp_error( $result ) ? new WP_Error( 'application_passwords_disabled', 'Disabled' ) : $result, 25 );

		$error = wp_authenticate( 'groot@example.com', 'any-password' );
		$this->assertWPError( $error );
		$this->assertSame( ERROR_CODE_LOGIN_LIMIT_EXCEEDED, $error->get_error_code() );
	}

	/**
	 * Fullwidth variants of a username share its limits even when no account has it.
	 */
	public function test__username_variants_share_limits_without_account() {
		for ( $attempt = 1; $attempt <= 5; $attempt++ ) {
			$this->assertWPError( wp_authenticate( "\u{FF4E}obody", 'wrong-password' ) );
		}

		$error = wp_authenticate( 'nobody', 'wrong-password' );
		$this->assertWPError( $error );
		$this->assertSame( ERROR_CODE_LOGIN_LIMIT_EXCEEDED, $error->get_error_code() );
	}

	/**
	 * Limit filters get the account's login, so a site's per-user threshold applies however the user logs in.
	 */
	public function test__limit_filters_get_account_login() {
		$this->factory()->user->create( [
			'user_login' => $this->test_username,
			'user_email' => 'groot@example.com',
		] );
		$filtered = [];
		add_filter( 'wpcom_vip_ip_username_login_threshold', function ( $threshold, $ip, $username ) use ( &$filtered ) {
			$filtered[] = $username;
			return $threshold;
		}, 10, 3 );

		wp_authenticate( 'Groot@Example.com', 'wrong-password' );

		$this->assertSame( [ $this->test_username ], $filtered );
	}

	/**
	 * A locked account is refused before other wp_authenticate_user callbacks run, such as ones that check
	 * its password, so they get the error instead of the account.
	 */
	public function test__locked_account_refused_before_other_callbacks() {
		$this->factory()->user->create( [
			'user_login' => $this->test_username,
			'user_pass'  => 'correct-password',
		] );
		wp_cache_set( CACHE_KEY_LOCK_PREFIX . $this->cache_keys()['username_cache_key'], true, CACHE_GROUP_LOGIN_LIMIT );
		$received = null;
		add_filter( 'wp_authenticate_user', function ( $user ) use ( &$received ) {
			$received = $user;
			return $user;
		} );

		$error = wp_authenticate( $this->test_username, 'correct-password' );

		$this->assertWPError( $received );
		$this->assertSame( ERROR_CODE_LOGIN_LIMIT_EXCEEDED, $error->get_error_code() );
	}

	/**
	 * Sites that unhook the authenticate limiter, for example for API logins, keep the limit off.
	 */
	public function test__unhooked_login_limiter_allows_locked_account() {
		$user_id = $this->factory()->user->create( [
			'user_login' => $this->test_username,
			'user_pass'  => 'correct-password',
		] );
		wp_cache_set( CACHE_KEY_LOCK_PREFIX . $this->cache_keys()['username_cache_key'], true, CACHE_GROUP_LOGIN_LIMIT );
		remove_filter( 'authenticate', 'wpcom_vip_login_limiter_authenticate', 30 );

		$this->assertSame( $user_id, wp_authenticate( $this->test_username, 'correct-password' )->ID );
	}

	/**
	 * A username shaped like an IP address has its own lockout, separate from that IP's.
	 */
	public function test__username_lockout_does_not_lock_out_matching_ip() {
		add_filter( 'wpcom_vip_username_login_threshold', static fn() => 1 );
		wpcom_vip_track_auth_attempt( '203.0.113.5', CACHE_GROUP_LOGIN_LIMIT );
		$this->assertWPError( wpcom_vip_username_is_limited( '203.0.113.5', CACHE_GROUP_LOGIN_LIMIT ) );

		$_SERVER['REMOTE_ADDR'] = '203.0.113.5'; // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__
		$this->assertFalse( wpcom_vip_username_is_limited( $this->test_username, CACHE_GROUP_LOGIN_LIMIT ) );
	}

	/**
	 * Users are network-wide, so a lockout from one site applies on the others.
	 */
	public function test__lockout_applies_across_the_network() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		for ( $attempt = 1; $attempt <= 5; $attempt++ ) {
			wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
		}

		switch_to_blog( self::factory()->blog->create() );
		$result = wpcom_vip_username_is_limited( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
		restore_current_blog();
		$this->assertWPError( $result );
	}

	public function test__wpcom_vip_track_auth_attempt__defaults() {
		$filtered_ip = null;
		add_filter( 'wpcom_vip_ip_login_threshold', function ( $threshold, $ip ) use ( &$filtered_ip ) {
			$filtered_ip = $ip;
			return $threshold;
		}, 10, 2 );

		wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );

		$this->assertSame( $this->test_ip, $filtered_ip );

		$cache_keys = $this->cache_keys();

		$username_count = wp_cache_get( $cache_keys['username_cache_key'], CACHE_GROUP_LOGIN_LIMIT );
		$this->assertSame( 1, $username_count );

		$ip_count = wp_cache_get( $cache_keys['ip_cache_key'], CACHE_GROUP_LOGIN_LIMIT );
		$this->assertSame( 1, $ip_count );

		$ip_username_count = wp_cache_get( $cache_keys['ip_username_cache_key'], CACHE_GROUP_LOGIN_LIMIT );
		$this->assertSame( 1, $ip_username_count );
	}

	public function test__wpcom_vip_login_limiter_on_success__decrease_count() {
		$original_count  = 5;
		$decreased_count = $original_count - 1;
		$cache_keys      = $this->cache_keys();
		wp_cache_set( $cache_keys['username_cache_key'], $original_count, CACHE_GROUP_LOGIN_LIMIT );
		wp_cache_set( $cache_keys['ip_cache_key'], $original_count, CACHE_GROUP_LOGIN_LIMIT );
		wp_cache_set( $cache_keys['ip_username_cache_key'], $original_count, CACHE_GROUP_LOGIN_LIMIT );

		wpcom_vip_login_limiter_on_success( $this->test_username );

		$username_count = wp_cache_get( $cache_keys['username_cache_key'], CACHE_GROUP_LOGIN_LIMIT );
		$this->assertSame( $original_count, $username_count ); // username is NOT decreased

		$ip_count = wp_cache_get( $cache_keys['ip_cache_key'], CACHE_GROUP_LOGIN_LIMIT );
		$this->assertSame( $decreased_count, $ip_count );

		$ip_username_count = wp_cache_get( $cache_keys['ip_username_cache_key'], CACHE_GROUP_LOGIN_LIMIT );
		$this->assertSame( $decreased_count, $ip_username_count );
	}

	public function test__wpcom_vip_username_is_limited__should_not_limit_by_default() {
		$result = wpcom_vip_username_is_limited( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );

		$this->assertSame( false, $result );
	}
	public function data_login_limit_defaults(): array {
		return [
			'default' => [ false, 5, 60 * 5 ],
			'FedRAMP' => [ true, 3, 60 * 30 ],
		];
	}

	/**
	 * Reaching the IP + username threshold locks the username for the default lockout, and the lock
	 * outlives the counters' event window.
	 *
	 * @dataProvider data_login_limit_defaults
	 */
	public function test__wpcom_vip_username_is_limited__after_threshold( bool $is_fedramp, int $attempts, int $expected_lockout ) {
		if ( $is_fedramp ) {
			Constant_Mocker::define( 'VIP_IS_FEDRAMP', true );
		}

		$lockout = null;
		add_filter( 'vip_login_ip_username_lockout', function ( $value ) use ( &$lockout ) {
			$lockout = $value;
			return $value;
		}, 10, 1 );
		$action_triggered = 0;
		add_action( 'login_limit_exceeded', function () use ( &$action_triggered ) {
			$action_triggered++;
		});

		for ( $attempt = 1; $attempt <= $attempts; $attempt++ ) {
			wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
		}

		$this->assertWPError( wpcom_vip_username_is_limited( $this->test_username, CACHE_GROUP_LOGIN_LIMIT ) );
		$this->assertSame( $expected_lockout, $lockout );
		$this->assertSame( 1, $action_triggered );

		$this->clean_event_window_cache();

		$this->assertWPError( wpcom_vip_username_is_limited( $this->test_username, CACHE_GROUP_LOGIN_LIMIT ) );
	}

	public function data_auth_window_defaults(): array {
		return [
			'default' => [
				false,
				[
					'vip_login_ip_username_window' => 60 * 5,
					'vip_login_ip_window'          => 60 * 60,
					'vip_login_username_window'    => 60 * 25,
				],
			],
			'FedRAMP' => [
				true,
				[
					'vip_login_ip_username_window' => 60 * 15,
					'vip_login_ip_window'          => 60 * 15,
					'vip_login_username_window'    => 60 * 15,
				],
			],
		];
	}

	/**
	 * @dataProvider data_auth_window_defaults
	 */
	public function test__wpcom_vip_track_auth_attempt__correct_defaults( bool $is_fedramp, array $expected_windows ) {
		if ( $is_fedramp ) {
			Constant_Mocker::define( 'VIP_IS_FEDRAMP', true );
		}

		$windows = [];
		foreach ( array_keys( $expected_windows ) as $filter ) {
			add_filter( $filter, function ( $window ) use ( &$windows, $filter ) {
				$windows[ $filter ] = $window;
				return $window;
			}, 10, 1 );
		}

		wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );

		$this->assertEquals( $expected_windows, $windows );
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
		$cache_keys = $this->cache_keys();
		try {
			wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
			$this->assertSame( 7, $cache->expirations[ $cache_keys['ip_cache_key'] ] );
			$this->assertSame( 11, $cache->expirations[ $cache_keys['ip_username_cache_key'] ] );
			$this->assertSame( 13, $cache->expirations[ $cache_keys['username_cache_key'] ] );
			for ( $attempt = 1; $attempt < 5; $attempt++ ) {
				wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
			}
			$this->assertSame( 17, $cache->expirations[ CACHE_KEY_LOCK_PREFIX . $cache_keys['ip_username_cache_key'] ] );
			$this->assertWPError( wpcom_vip_username_is_limited( $this->test_username, CACHE_GROUP_LOGIN_LIMIT ) );
			$cache->now = 16;
			$this->assertWPError( wpcom_vip_username_is_limited( $this->test_username, CACHE_GROUP_LOGIN_LIMIT ) );
			$cache->now = 17;
			$this->assertFalse( wpcom_vip_username_is_limited( $this->test_username, CACHE_GROUP_LOGIN_LIMIT ) );
		} finally {
			$wp_object_cache = $original_cache;
		}
	}

	private function cache_keys(): array {
		return _vip_login_cache_keys( $this->test_username );
	}

	private function clean_event_window_cache() {
		foreach ( $this->cache_keys() as $key ) {
			wp_cache_delete( $key, CACHE_GROUP_LOGIN_LIMIT );
		}
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
