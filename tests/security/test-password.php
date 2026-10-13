<?php

namespace Automattic\VIP\Security;

use WP_Error;
use WP_UnitTest_Factory;
use WP_UnitTestCase;

require_once __DIR__ . '/../../security/password.php';

class Current_Password_Change_Test extends WP_UnitTestCase {
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$factory->user->create( [
			'user_login' => 'john',
			'user_email' => 'john@example.com',
			'user_pass'  => 'secret1',
		] );
	}

	public function clean_up_global_scope() {
		parent::clean_up_global_scope();
		$_REQUEST = [];
	}

	/**
	 * @dataProvider data_profile_updates_without_errors
	 */
	public function test__should_not_add_errors( bool $update, ?string $screen, array $post ) {
		$errors = $this->validate_profile_update( $update, $screen, $post );

		$this->assertFalse( $errors->has_errors() );
	}

	public function data_profile_updates_without_errors(): array {
		return [
			'creating user'            => [ false, null, [] ],
			'user-edit screen'         => [ true, 'user-edit', [] ],
			'no password update'       => [ true, 'profile', [] ],
			'correct current password' => [
				true,
				'profile',
				[
					'pass1'        => 'somepassword',
					'current_pass' => 'secret1',
				],
			],
		];
	}

	/**
	 * @dataProvider data_profile_updates_with_errors
	 */
	public function test__should_return_error( array $post, string $expected_error ) {
		$errors = $this->validate_profile_update( true, 'profile', $post );

		$this->assertTrue( $errors->has_errors() );
		$this->assertEquals( $expected_error, $errors->get_error_message( 0 ) );
	}

	public function data_profile_updates_with_errors(): array {
		return [
			'no current password'        => [
				[ 'pass1' => 'somepassword' ],
				'<strong>Error</strong>: Please enter your current password.',
			],
			'incorrect current password' => [
				[
					'pass1'        => 'somepassword',
					'current_pass' => 'incorrect',
				],
				'<strong>Error</strong>: The entered current password is not correct.',
			],
		];
	}

	/**
	 * Run the profile update validation for user john, with a valid nonce on the profile screen.
	 */
	private function validate_profile_update( bool $update, ?string $screen, array $post ): WP_Error {
		$_POST  = $post;
		$errors = new WP_Error();
		$user   = get_user_by( 'login', 'john' );
		if ( null !== $screen ) {
			if ( 'profile' === $screen ) {
				$_REQUEST['_wpnonce'] = wp_create_nonce( 'update-user_' . $user->ID );
			}
			set_current_screen( $screen );
		}
		do_action_ref_array( 'user_profile_update_errors', array( &$errors, $update, &$user ) );

		return $errors;
	}
}
