<?php

namespace Automattic\VIP\Security\GraphQL;

use WP_UnitTestCase;

require_once __DIR__ . '/../../security/wp-graphql.php';

class WP_GraphQL_Security_Test extends WP_UnitTestCase {
	public function test__hooks_are_registered(): void {
		self::assertSame( 10, has_action( 'plugins_loaded', __NAMESPACE__ . '\maybe_disable_password_reset_mutation' ) );
		self::assertSame( 0, has_action( 'init', __NAMESPACE__ . '\maybe_disable_password_reset_mutation' ) );
	}

	public function test__does_nothing_when_wp_graphql_is_not_active(): void {
		// The stub class registered by the tests below cannot be unregistered.
		if ( class_exists( 'WPGraphQL', false ) ) {
			self::markTestSkipped( 'A WPGraphQL stub is already registered in this process' );
		}

		self::assertFalse( is_wp_graphql_active(), 'WPGraphQL should not be loaded in the test suite' );

		maybe_disable_password_reset_mutation();

		self::assertFalse( has_filter( 'graphql_excluded_mutations', __NAMESPACE__ . '\exclude_password_reset_mutation' ) );
		self::assertFalse( has_filter( 'graphql_RootMutation_fields', __NAMESPACE__ . '\remove_password_reset_field' ) );
	}

	public function test__disables_mutation_when_wp_graphql_is_active(): void {
		// Stand in for the plugin's main class, which is what we detect.
		if ( ! class_exists( 'WPGraphQL', false ) ) {
			class_alias( \stdClass::class, 'WPGraphQL' );
		}

		self::assertTrue( is_wp_graphql_active() );

		maybe_disable_password_reset_mutation();

		self::assertNotFalse( has_filter( 'graphql_excluded_mutations', __NAMESPACE__ . '\exclude_password_reset_mutation' ) );
		self::assertNotFalse( has_filter( 'graphql_RootMutation_fields', __NAMESPACE__ . '\remove_password_reset_field' ) );

		self::assertContains( PASSWORD_RESET_MUTATION, apply_filters( 'graphql_excluded_mutations', [] ) );
		// phpcs:ignore WordPress.NamingConventions.ValidHookName.NotLowercase -- hook name is defined by WPGraphQL.
		self::assertSame( [], apply_filters( 'graphql_RootMutation_fields', [ 'sendPasswordResetEmail' => [] ] ) );
	}

	public function test__can_be_disabled_via_filter(): void {
		if ( ! class_exists( 'WPGraphQL', false ) ) {
			class_alias( \stdClass::class, 'WPGraphQL' );
		}

		add_filter( 'vip_disable_wp_graphql_password_reset_mutation', '__return_false' );

		maybe_disable_password_reset_mutation();

		self::assertFalse( has_filter( 'graphql_excluded_mutations', __NAMESPACE__ . '\exclude_password_reset_mutation' ) );
		self::assertFalse( has_filter( 'graphql_RootMutation_fields', __NAMESPACE__ . '\remove_password_reset_field' ) );
	}

	public function test__filters_are_only_added_once(): void {
		disable_password_reset_mutation();
		disable_password_reset_mutation();

		self::assertCount( 1, apply_filters( 'graphql_excluded_mutations', [] ) );
	}

	public function test__exclude_password_reset_mutation(): void {
		self::assertSame( [ 'someOtherMutation', PASSWORD_RESET_MUTATION ], exclude_password_reset_mutation( [ 'someOtherMutation' ] ) );

		// Other plugins can filter this into a non-array; leave it alone rather than fataling.
		self::assertNull( exclude_password_reset_mutation( null ) );
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

		self::assertSame( [ 'createUser' => [ 'type' => 'CreateUserPayload' ] ], remove_password_reset_field( $fields ) );
	}

	public function data_password_reset_field_names(): array {
		return [
			[ 'sendPasswordResetEmail' ],
			[ 'SendPasswordResetEmail' ],
			[ 'sendpasswordresetemail' ],
		];
	}

	public function test__remove_password_reset_field_ignores_non_arrays(): void {
		self::assertNull( remove_password_reset_field( null ) );
	}
}
