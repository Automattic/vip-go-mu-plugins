<?php

require_once __DIR__ . '/../plugin-fixes.php';

class VIP_Go_Test_WP_GraphQL_Plugin_Fixes extends WP_UnitTestCase {
	/**
	 * Load a stub of WPGraphQL's main class so the plugin looks active.
	 *
	 * Classes cannot be unloaded, so this leaks for the rest of the process. The test
	 * covering the inactive path is declared first and skips itself if the stub is
	 * already present.
	 */
	private function pretend_wp_graphql_is_active(): void {
		if ( ! class_exists( 'WPGraphQL', false ) ) {
			require_once __DIR__ . '/fixtures/plugin-fixes/mock-wp-graphql-class.php';
		}
	}

	public function test__hooks_are_registered(): void {
		self::assertSame( 10, has_action( 'plugins_loaded', 'vip_maybe_disable_wp_graphql_password_reset_mutation' ) );
		self::assertSame( 0, has_action( 'init', 'vip_maybe_disable_wp_graphql_password_reset_mutation' ) );
	}

	public function test__does_nothing_when_wp_graphql_is_not_active(): void {
		// The stub class loaded by the tests below cannot be unloaded.
		if ( class_exists( 'WPGraphQL', false ) ) {
			self::markTestSkipped( 'A WPGraphQL stub is already loaded in this process' );
		}

		self::assertFalse( vip_is_wp_graphql_active(), 'WPGraphQL should not be loaded in the test suite' );

		vip_maybe_disable_wp_graphql_password_reset_mutation();

		self::assertFalse( has_filter( 'graphql_excluded_mutations', 'vip_exclude_wp_graphql_password_reset_mutation' ) );
		self::assertFalse( has_filter( 'graphql_RootMutation_fields', 'vip_remove_wp_graphql_password_reset_field' ) );
	}

	public function test__disables_mutation_when_wp_graphql_is_active(): void {
		$this->pretend_wp_graphql_is_active();

		self::assertTrue( vip_is_wp_graphql_active() );

		vip_maybe_disable_wp_graphql_password_reset_mutation();

		self::assertNotFalse( has_filter( 'graphql_excluded_mutations', 'vip_exclude_wp_graphql_password_reset_mutation' ) );
		self::assertNotFalse( has_filter( 'graphql_RootMutation_fields', 'vip_remove_wp_graphql_password_reset_field' ) );

		self::assertContains( 'sendPasswordResetEmail', apply_filters( 'graphql_excluded_mutations', [] ) );
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.NotLowercase -- hook name is defined by WPGraphQL.
		self::assertSame( [], apply_filters( 'graphql_RootMutation_fields', [ 'sendPasswordResetEmail' => [] ] ) );
	}

	public function test__can_be_disabled_via_filter(): void {
		$this->pretend_wp_graphql_is_active();

		add_filter( 'vip_disable_wp_graphql_password_reset_mutation', '__return_false' );

		vip_maybe_disable_wp_graphql_password_reset_mutation();

		self::assertFalse( has_filter( 'graphql_excluded_mutations', 'vip_exclude_wp_graphql_password_reset_mutation' ) );
		self::assertFalse( has_filter( 'graphql_RootMutation_fields', 'vip_remove_wp_graphql_password_reset_field' ) );
	}

	public function test__filters_are_only_added_once(): void {
		vip_remove_wp_graphql_password_reset_mutation();
		vip_remove_wp_graphql_password_reset_mutation();

		self::assertCount( 1, apply_filters( 'graphql_excluded_mutations', [] ) );
	}

	public function test__exclude_password_reset_mutation(): void {
		self::assertSame( [ 'someOtherMutation', 'sendPasswordResetEmail' ], vip_exclude_wp_graphql_password_reset_mutation( [ 'someOtherMutation' ] ) );

		// Other plugins can filter this into a non-array; leave it alone rather than fataling.
		self::assertNull( vip_exclude_wp_graphql_password_reset_mutation( null ) );
	}

	/**
	 * WPGraphQL lowercases the exclusion list before comparing, and registers the
	 * RootMutation field as `lcfirst( $mutation_name )`, so match case-insensitively.
	 *
	 * @dataProvider data_password_reset_field_names
	 */
	public function test__remove_password_reset_field( string $field_name ): void {
		$fields = [
			'createUser' => [ 'type' => 'CreateUserPayload' ],
			$field_name  => [ 'type' => 'SendPasswordResetEmailPayload' ],
		];

		self::assertSame( [ 'createUser' => [ 'type' => 'CreateUserPayload' ] ], vip_remove_wp_graphql_password_reset_field( $fields ) );
	}

	public function data_password_reset_field_names(): array {
		return [
			[ 'sendPasswordResetEmail' ],
			[ 'SendPasswordResetEmail' ],
			[ 'sendpasswordresetemail' ],
		];
	}

	public function test__remove_password_reset_field_ignores_non_arrays(): void {
		self::assertNull( vip_remove_wp_graphql_password_reset_field( null ) );
	}
}
