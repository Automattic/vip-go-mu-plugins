<?php

class VIP_Go_Security_Test extends WP_UnitTestCase {
	private $original_post;

	public function test__admin_username_restricted() {
		$this->factory->user->create( [
			'user_login' => 'admin',
			'user_email' => 'admin@example.com',
			'user_pass'  => 'secret1',
		] );

		$result = wp_authenticate( 'admin', 'secret1' );

		$this->assertWPError( $result );
		$this->assertEquals( 'restricted-login', $result->get_error_code() );
	}

	public function test__vip_machine_user_username_restricted() {
		$this->factory->user->create( [
			'user_login' => WPCOM_VIP_MACHINE_USER_LOGIN,
			'user_email' => WPCOM_VIP_MACHINE_USER_EMAIL,
			'user_pass'  => 'secret2',
		] );

		$result = wp_authenticate( WPCOM_VIP_MACHINE_USER_LOGIN, 'secret2' );

		$this->assertWPError( $result );
		$this->assertEquals( 'restricted-login', $result->get_error_code() );
	}

	public function test__vip_machine_user_email_restricted() {
		$this->factory->user->create( [
			'user_login' => WPCOM_VIP_MACHINE_USER_LOGIN,
			'user_email' => WPCOM_VIP_MACHINE_USER_EMAIL,
			'user_pass'  => 'secret3',
		] );

		$result = wp_authenticate( WPCOM_VIP_MACHINE_USER_EMAIL, 'secret3' );

		$this->assertWPError( $result );
		$this->assertEquals( 'restricted-login', $result->get_error_code() );
	}

	public function test__other_username_not_restricted() {
		$user_id = $this->factory->user->create( [
			'user_login' => 'taylorswift',
			'user_email' => 'taylor@example.com',
			'user_pass'  => 'secret4',
		] );

		$result = wp_authenticate( 'taylorswift', 'secret4' );

		$this->assertNotWPError( $result );
		$this->assertEquals( $user_id, $result->ID );
	}

	public function test__other_email_not_restricted() {
		$user_id = $this->factory->user->create( [
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

		do_action( 'lostpassword_post', $errors );

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

			do_action( 'lostpassword_post', $errors );

			// Make sure we haven't received an error yet
			$this->assertEquals( $errors->get_error_code(), false );

		}

		// Do the lostpassword_post one more time to reach our threshold.
		do_action( 'lostpassword_post', $errors );

		// Now we should have an error.
		$this->assertEquals( $errors->get_error_code(), 'lost_password_limit_exceeded' );

	}

	public function setUp(): void {
		parent::setUp();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$this->original_post = $_POST;
	}

	public function tearDown(): void {
		$_POST = $this->original_post;

		parent::tearDown();
	}

}
