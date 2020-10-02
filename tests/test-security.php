<?php

include_once( ABSPATH . WPINC . '/class-IXR.php' );
include_once( ABSPATH . WPINC . '/class-wp-xmlrpc-server.php' );

class VIP_Go_Security_Test extends WP_UnitTestCase {
	public function test__admin_username_restricted() {
		$this->factory->user->create( [
			'user_login' => 'admin',
			'user_email' => 'admin@example.com',
			'user_pass'   => 'secret1',
		] );

		$result = wp_authenticate( 'admin', 'secret1' );

		$this->assertWPError( $result );
		$this->assertEquals( 'restricted-login', $result->get_error_code() );
	}

	public function test__vip_machine_user_username_restricted() {
		$this->factory->user->create( [
			'user_login' => WPCOM_VIP_MACHINE_USER_LOGIN,
			'user_email' => WPCOM_VIP_MACHINE_USER_EMAIL,
			'user_pass'   => 'secret2',
		] );

		$result = wp_authenticate( WPCOM_VIP_MACHINE_USER_LOGIN, 'secret2' );

		$this->assertWPError( $result );
		$this->assertEquals( 'restricted-login', $result->get_error_code() );
	}

	public function test__vip_machine_user_email_restricted() {
		$this->factory->user->create( [
			'user_login' => WPCOM_VIP_MACHINE_USER_LOGIN,
			'user_email' => WPCOM_VIP_MACHINE_USER_EMAIL,
			'user_pass'   => 'secret3',
		] );

		$result = wp_authenticate( WPCOM_VIP_MACHINE_USER_EMAIL, 'secret3' );

		$this->assertWPError( $result );
		$this->assertEquals( 'restricted-login', $result->get_error_code() );
	}

	public function test__other_username_not_restricted() {
		$user_id = $this->factory->user->create( [
			'user_login' => 'taylorswift',
			'user_email' => 'taylor@example.com',
			'user_pass'   => 'secret4',
		] );

		$result = wp_authenticate( 'taylorswift', 'secret4' );

		$this->assertNotWPError( $result );
		$this->assertEquals( $user_id, $result->ID );
	}

	public function test__other_email_not_restricted() {
		$user_id = $this->factory->user->create( [
			'user_login' => 'taylorswift',
			'user_email' => 'taylor@example.com',
			'user_pass'   => 'secret5',
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

	public function test__login_system_multicall_rate_limit() {
		add_filter( 'pre_option_enable_xmlrpc', '__return_true' );
		$myxmlrpcserver = new wp_xmlrpc_server();

		$method = array(
			'methodName' => 'wp.getUsersBlogs',
			'params'     => array(
				0,
				'admin20',
				'password',
			),
		);

		$method_calls = array();

		$limit_threshold = 50;
		$last_threshold_index = $limit_threshold - 1;

		for ( $i = 1; $i <= $limit_threshold + 2; $i++ ) {
			array_push( $method_calls, $method );
		}

		$myxmlrpcserver->callbacks = $myxmlrpcserver->methods;

		$this->_error_level = error_reporting();
		error_reporting( $this->_error_level & ~E_WARNING );
		$result = $myxmlrpcserver->multiCall( $method_calls );
		error_reporting( $this->_error_level );

		$this->assertEquals( 403, $result[ $last_threshold_index ]['faultCode'] );
		$this->assertEquals( 429, $result[ $last_threshold_index + 1 ]['faultCode'] );
		$this->assertNotEmpty( $result[ $last_threshold_index + 1 ]['faultString'] );
		$this->assertEquals( 429, $result[ $last_threshold_index + 2 ]['faultCode'] );
		$this->assertNotEmpty( $result[ $last_threshold_index + 2 ]['faultString'] );
	}


	public function setUp() {

		parent::setUp();

		$this->original_POST = $_POST;

	}

	public function tearDown() {

		$_POST = $this->original_POST;

		parent::tearDown();

	}

}
