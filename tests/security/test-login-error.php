<?php

namespace Automattic\VIP\Security;

use WP_Error;
use WP_UnitTestCase;
use WPDieException;

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
		$location       = null;
		$redirect_caled = false;

		add_filter( 'wp_redirect', function ( $dest ) use ( &$location ) {
			$location = $dest;
			return $dest;
		} );

		// phpcs:ignore WordPressVIPMinimum.Hooks.AlwaysReturnInFilter.MissingReturnStatement
		add_filter( 'wp_redirect_status', function () use ( &$redirect_caled ) {
			$redirect_caled = true;
			throw new WPDieException( 'Redirect called' );
		} );

		$_SERVER['REQUEST_METHOD'] = 'POST';

		$errors = new WP_Error();
		$errors->add( 'error', 'Something went terribly wrong' );

		try {
			do_action( 'lost_password', $errors );
			static::fail( 'Expected exception' );
		} catch ( WPDieException $e ) {
			static::assertEquals( 'Redirect called', $e->getMessage() );
			static::assertTrue( $redirect_caled );
			static::assertIsString( $location );
			static::assertStringContainsString( 'wp-login.php?checkemail=confirm', $location );
		}
	}
}

function headers_sent() {
	return false;
}
