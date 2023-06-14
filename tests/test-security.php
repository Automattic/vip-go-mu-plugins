<?php

use Automattic\Test\Constant_Mocker;

class VIP_Go_Security_Test extends WP_UnitTestCase {
	private $original_post;
	private $test_username = 'IamGroot';
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
		$this->factory()->user->create( [
			'user_login' => 'admin',
			'user_email' => 'admin@example.com',
			'user_pass'  => 'secret1',
		] );

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
		add_filter( 'vip_login_ip_username_lockout', function( $lockout ) {
			$this->assertSame( 60 * 5, $lockout );
			return $lockout;
		}, 10, 1 );

		wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
		wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
		wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
		wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
		wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );

		$result = wpcom_vip_username_is_limited( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );

		$this->assertSame( true, is_wp_error( $result ) );
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

		add_filter( 'vip_login_ip_username_lockout', function( $lockout ) {
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
		add_filter( 'vip_login_ip_username_window', function( $window ) {
			$this->assertSame( 60 * 5, $window );
			return $window;
		}, 10, 1 );
		add_filter( 'vip_login_ip_window', function( $window ) {
			$this->assertSame( 60 * 60, $window );
			return $window;
		}, 10, 1 );
		add_filter( 'vip_login_username_window', function( $window ) {
			$this->assertSame( 60 * 25, $window );
			return $window;
		}, 10, 1 );

		wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
	}

	public function test__wpcom_vip_track_auth_attempt__correct_defaults_fedramp() {
		Constant_Mocker::define( 'VIP_IS_FEDRAMP', true );

		add_filter( 'vip_login_ip_username_window', function( $window ) {
			$this->assertSame( 60 * 15, $window );
			return $window;
		}, 10, 1 );
		add_filter( 'vip_login_ip_window', function( $window ) {
			$this->assertSame( 60 * 15, $window );
			return $window;
		}, 10, 1 );
		add_filter( 'vip_login_username_window', function( $window ) {
			$this->assertSame( 60 * 15, $window );
			return $window;
		}, 10, 1 );

		wpcom_vip_track_auth_attempt( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
	}

	private function clean_event_window_cache() {
		wp_cache_delete( $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
		wp_cache_delete( $this->test_ip, CACHE_GROUP_LOGIN_LIMIT );
		wp_cache_delete( $this->test_ip . '|' . $this->test_username, CACHE_GROUP_LOGIN_LIMIT );
	}
}
