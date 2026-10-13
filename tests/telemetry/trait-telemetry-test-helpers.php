<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use Automattic\Test\Constant_Mocker;
use WP_UnitTest_Factory;

/**
 * Shared fixtures for telemetry tests.
 */
trait Telemetry_Test_Helpers {
	/**
	 * Users created once per class, keyed by role. Tests that change a user's role or caps create their own.
	 *
	 * @var array<string, int>
	 */
	private static array $user_ids_by_role = [];

	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- WP_UnitTestCase hook.
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		foreach ( [ 'subscriber', 'author' ] as $role ) {
			self::$user_ids_by_role[ $role ] = $factory->user->create( [ 'role' => $role ] );
		}
	}

	/**
	 * Log in as the shared user with the given role.
	 *
	 * @return int The user ID.
	 */
	private function login_as( string $role = 'subscriber' ): int {
		wp_set_current_user( self::$user_ids_by_role[ $role ] );

		return self::$user_ids_by_role[ $role ];
	}

	/**
	 * Define the constants of a VIP production environment, where Pendo is enabled.
	 */
	private function enable_pendo_environment(): void {
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );
	}
}
