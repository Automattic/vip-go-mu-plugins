<?php

namespace Automattic\VIP\Security;

use WP_UnitTest_Factory;
use WP_UnitTestCase;
use WP_User;

require_once __DIR__ . '/../../security/machine-user.php';

class Machine_User_Test extends WP_UnitTestCase {
	/**
	 * Shared, read-only actors keyed by name: `machine`, `editor`, `manager` (an editor granted the
	 * user-management caps, as a custom role would), `administrator`, `super_admin` and the `target` author.
	 *
	 * @var array<string, WP_User>
	 */
	private static $users = [];

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$users = [
			'machine'       => $factory->user->create_and_get( [
				'user_login' => WPCOM_VIP_MACHINE_USER_LOGIN,
				'user_email' => WPCOM_VIP_MACHINE_USER_EMAIL,
				'role'       => WPCOM_VIP_MACHINE_USER_ROLE,
			] ),
			'editor'        => $factory->user->create_and_get( [ 'role' => 'editor' ] ),
			'manager'       => $factory->user->create_and_get( [ 'role' => 'editor' ] ),
			'administrator' => $factory->user->create_and_get( [ 'role' => 'administrator' ] ),
			'super_admin'   => $factory->user->create_and_get( [ 'role' => 'administrator' ] ),
			'target'        => $factory->user->create_and_get( [ 'role' => 'author' ] ),
		];

		foreach ( [ 'edit_users', 'delete_users', 'remove_users', 'promote_users', 'manage_network_users' ] as $user_management_cap ) {
			self::$users['manager']->add_cap( $user_management_cap );
		}

		if ( is_multisite() ) {
			grant_super_admin( self::$users['super_admin']->ID );
		}
	}

	public static function wpTearDownAfterClass() {
		if ( is_multisite() ) {
			revoke_super_admin( self::$users['super_admin']->ID );
		}
	}

	public function data_machine_user_guard(): iterable {
		return self::actors_and_caps( [ 'machine', 'manager', 'administrator', 'super_admin' ] );
	}

	public function data_user_managers(): iterable {
		return self::actors_and_caps( [ 'manager', 'administrator', 'super_admin' ] );
	}

	public function data_user_modification_caps(): iterable {
		return self::actors_and_caps( [ 'editor' ] );
	}

	private static function actors_and_caps( array $actors ): iterable {
		// Listed here rather than read from USER_MODIFICATION_CAPS, so dropping a cap there fails the tests.
		foreach ( $actors as $actor ) {
			foreach ( [ 'edit_user', 'delete_user', 'remove_user', 'promote_user' ] as $cap ) {
				yield "$actor $cap" => [ $actor, $cap ];
			}
		}
	}

	/**
	 * Nobody, the machine user included, may modify the machine user. The other actors can modify
	 * regular users (see test__can_still_modify_others), so the machine user guard is what denies access.
	 *
	 * @dataProvider data_machine_user_guard
	 */
	public function test__cannot_modify_machine_user( string $actor, string $cap ) {
		$this->skip_unsupported( $actor, $cap );

		$this->assertFalse( self::$users[ $actor ]->has_cap( $cap, self::$users['machine']->ID ) );
	}

	/**
	 * @dataProvider data_user_managers
	 */
	public function test__can_still_modify_others( string $actor, string $cap ) {
		if ( is_multisite() && 'administrator' === $actor && in_array( $cap, [ 'edit_user', 'delete_user' ], true ) ) {
			$this->markTestSkipped( 'Administrators without super admin cannot edit or delete other users on multisite.' );
		}

		$this->skip_unsupported( $actor, $cap );

		$this->assertTrue( self::$users[ $actor ]->has_cap( $cap, self::$users['target']->ID ) );
	}

	/**
	 * The machine user guard leaves core's checks in place for everyone else.
	 *
	 * @dataProvider data_user_modification_caps
	 */
	public function test__users_without_user_management_caps_cannot_modify_others( string $actor, string $cap ) {
		$this->assertFalse( self::$users[ $actor ]->has_cap( $cap, self::$users['target']->ID ) );
	}

	/**
	 * @dataProvider data_non_machine_actor
	 */
	public function test__can_still_modify_self( string $actor ) {
		$this->skip_unsupported( $actor, 'edit_user' );

		$user = self::$users[ $actor ];

		$this->assertTrue( $user->has_cap( 'edit_user', $user->ID ) );
	}

	public function data_non_machine_actor(): array {
		return [
			'editor'        => [ 'editor' ],
			'administrator' => [ 'administrator' ],
			'super_admin'   => [ 'super_admin' ],
		];
	}

	private function skip_unsupported( string $actor, string $cap ): void {
		if ( 'super_admin' === $actor && ! is_multisite() ) {
			$this->markTestSkipped( 'No superadmins on single site installs.' );
		}

		if ( 'manager' === $actor && 'delete_user' === $cap && is_multisite() ) {
			$this->markTestSkipped( 'Core only allows super admins to delete users on multisite.' );
		}
	}
}
