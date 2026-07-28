<?php
/**
 * Hardening for the WPGraphQL plugin (https://wordpress.org/plugins/wp-graphql/).
 *
 * WPGraphQL registers a `sendPasswordResetEmail` mutation
 * (`wp-graphql/src/Mutation/SendPasswordResetEmail.php`) which generates a password
 * reset key and emails it, bypassing the protections we apply to `wp-login.php`
 * (rate limiting/lockouts via the `lostpassword_post` hook, ambiguous confirmation
 * messages, and restricted usernames). Since the mutation is unauthenticated, it can
 * be used for user enumeration and to send password reset emails at will.
 *
 * We remove the mutation from the GraphQL schema so that it can never be resolved.
 *
 * @package Automattic\VIP\Security
 */

namespace Automattic\VIP\Security\GraphQL;

/**
 * Name of the WPGraphQL mutation we remove from the schema.
 */
const PASSWORD_RESET_MUTATION = 'sendPasswordResetEmail';

/**
 * Is WPGraphQL loaded?
 *
 * WPGraphQL bootstraps itself (defining `WPGRAPHQL_VERSION` and instantiating the
 * `WPGraphQL` class) as its plugin file loads, so this is reliable from
 * `plugins_loaded` onwards.
 */
function is_wp_graphql_active(): bool {
	return class_exists( 'WPGraphQL', false ) || defined( 'WPGRAPHQL_VERSION' );
}

/**
 * Disable the password reset mutation when WPGraphQL is in use.
 *
 * Runs on `plugins_loaded` and again on `init` to also cover sites that load
 * WPGraphQL as a Composer dependency of the theme rather than as a plugin. Both run
 * well before WPGraphQL builds its schema, which only happens while serving a
 * GraphQL request.
 */
function maybe_disable_password_reset_mutation(): void {
	if ( ! is_wp_graphql_active() ) {
		return;
	}

	/**
	 * Filters whether the WPGraphQL `sendPasswordResetEmail` mutation is removed from
	 * the schema. Password resets should go through `wp-login.php`, which is rate
	 * limited and does not leak whether an account exists.
	 *
	 * @param bool $disable Whether to remove the mutation. Default true.
	 */
	if ( ! apply_filters( 'vip_disable_wp_graphql_password_reset_mutation', true ) ) {
		return;
	}

	disable_password_reset_mutation();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\maybe_disable_password_reset_mutation' );
add_action( 'init', __NAMESPACE__ . '\maybe_disable_password_reset_mutation', 0 );

/**
 * Attach the filters that keep the mutation out of the schema.
 */
function disable_password_reset_mutation(): void {
	// WPGraphQL >= 1.14: prevents the mutation and its Input/Payload types from being registered at all.
	if ( ! has_filter( 'graphql_excluded_mutations', __NAMESPACE__ . '\exclude_password_reset_mutation' ) ) {
		add_filter( 'graphql_excluded_mutations', __NAMESPACE__ . '\exclude_password_reset_mutation' );
	}

	// Fallback for WPGraphQL < 1.14, which has no exclusion list: drop the field from the RootMutation type.
	if ( ! has_filter( 'graphql_RootMutation_fields', __NAMESPACE__ . '\remove_password_reset_field' ) ) {
		add_filter( 'graphql_RootMutation_fields', __NAMESPACE__ . '\remove_password_reset_field' );
	}
}

/**
 * Add the mutation to WPGraphQL's list of excluded mutations.
 *
 * WPGraphQL lowercases the list before comparing, so casing here does not matter.
 *
 * @param mixed $excluded_mutations Names of the mutations to exclude from the schema.
 * @return mixed
 */
function exclude_password_reset_mutation( $excluded_mutations ) {
	if ( ! is_array( $excluded_mutations ) ) {
		return $excluded_mutations;
	}

	$excluded_mutations[] = PASSWORD_RESET_MUTATION;

	return $excluded_mutations;
}

/**
 * Remove the mutation's field from the RootMutation type.
 *
 * @param mixed $fields Fields registered to the RootMutation type, keyed by field name.
 * @return mixed
 */
function remove_password_reset_field( $fields ) {
	if ( ! is_array( $fields ) ) {
		return $fields;
	}

	foreach ( array_keys( $fields ) as $field_name ) {
		if ( is_string( $field_name ) && 0 === strcasecmp( $field_name, PASSWORD_RESET_MUTATION ) ) {
			unset( $fields[ $field_name ] );
		}
	}

	return $fields;
}
