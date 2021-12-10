<?php

namespace Automattic\VIP\Security;

use WP_Error;
use WP_UnitTestCase;

require_once __DIR__ . '/../../security/password.php';

class Current_Password_Change_Test extends WP_UnitTestCase {
	public function setUp(): void {
		parent::setUp();

		$this->factory->user->create( [
			'user_login' => 'john',
			'user_email' => 'john@example.com',
			'user_pass'  => 'secret1',
		] );
	}

	public function clean_up_global_scope() {
		parent::clean_up_global_scope();
		$_REQUEST = [];
	}

	public function test__should_return_if_creating_user() {
		$errors = new WP_Error();
		$user   = get_user_by( 'login', 'john' );
		do_action_ref_array( 'user_profile_update_errors', array( &$errors, false, &$user ) );

		$this->assertFalse( $errors->has_errors() );
	}

	public function test__should_return_in_user_edit() {
		$errors = new WP_Error();
		$user   = get_user_by( 'login', 'john' );
		set_current_screen( 'user-edit' );
		do_action_ref_array( 'user_profile_update_errors', array( &$errors, true, &$user ) );

		$this->assertFalse( $errors->has_errors() );
	}

	public function test__should_return_if_no_pass_update() {
		$errors               = new WP_Error();
		$user                 = get_user_by( 'login', 'john' );
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'update-user_' . $user->ID );
		set_current_screen( 'profile' );
		do_action_ref_array( 'user_profile_update_errors', array( &$errors, true, &$user ) );

		$this->assertFalse( $errors->has_errors() );
	}

	public function test__should_return_error_if_no_current_pass() {
		$_POST                = [
			'pass1' => 'somepassword',
		];
		$errors               = new WP_Error();
		$user                 = get_user_by( 'login', 'john' );
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'update-user_' . $user->ID );
		set_current_screen( 'profile' );
		do_action_ref_array( 'user_profile_update_errors', array( &$errors, true, &$user ) );

		$expected_error = '<strong>Error</strong>: Please enter your current password.';

		$this->assertTrue( $errors->has_errors() );
		$this->assertEquals( $expected_error, $errors->get_error_message( 0 ) );
	}

	public function test__should_return_error_if_current_pass_incorrect() {
		$_POST                = [
			'pass1'        => 'somepassword',
			'current_pass' => 'incorrect',
		];
		$errors               = new WP_Error();
		$user                 = get_user_by( 'login', 'john' );
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'update-user_' . $user->ID );
		set_current_screen( 'profile' );
		do_action_ref_array( 'user_profile_update_errors', array( &$errors, true, &$user ) );

		$expected_error = '<strong>Error</strong>: The entered current password is not correct.';

		$this->assertTrue( $errors->has_errors() );
		$this->assertEquals( $expected_error, $errors->get_error_message( 0 ) );
	}

	public function test__should_succeed_with_correct_password() {
		$_POST                = [
			'pass1'        => 'somepassword',
			'current_pass' => 'secret1',
		];
		$errors               = new WP_Error();
		$user                 = get_user_by( 'login', 'john' );
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'update-user_' . $user->ID );
		set_current_screen( 'profile' );
		do_action_ref_array( 'user_profile_update_errors', array( &$errors, true, &$user ) );

		$this->assertFalse( $errors->has_errors() );
	}
}
