<?php

namespace Automattic\VIP\Security;

use WP_Error;
use WP_UnitTestCase;

class Login_Error_Test extends WP_UnitTestCase {
	public function tearDown(): void {
		global $errors;

		unset( $errors );
		parent::tearDown();
	}

	public function test_has_filters(): void {
		self::assertEquals( 99, has_filter( 'login_errors', __NAMESPACE__ . '\use_ambiguous_login_error' ) );
		self::assertEquals( 99, has_filter( 'wp_login_errors', __NAMESPACE__ . '\use_ambiguous_confirmation' ) );
	}

	public function test_use_ambiguous_confirmation(): void {
		$errors = new WP_Error();
		$errors->add(
			'confirm',
			sprintf(
				'Check your email for the confirmation link, then visit the <a href="%s">login page</a>.',
				wp_login_url()
			),
			'message'
		);

		$_GET['checkemail'] = 'confirm';
		$actual             = apply_filters( 'wp_login_errors', $errors, admin_url() );

		self::assertInstanceOf( WP_Error::class, $actual );
		self::assertContains(
			'If there is an account associated with the username/email address, you will receive an email with a link to reset your password.',
			$actual->get_error_messages( 'confirm' )
		);
	}

	public function test_ambiguous_reset(): void {
		global $errors;

		$message = 'Something went terribly wrong';

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$errors = new WP_Error();
		$errors->add( 'error', $message );

		$_GET['action'] = 'lostpassword';

		$actual = apply_filters( 'login_errors', $message );
		self::assertSame(
			'If there is an account associated with the username/email address, you will receive an email with a link to reset your password.',
			$actual
		);
	}
}
