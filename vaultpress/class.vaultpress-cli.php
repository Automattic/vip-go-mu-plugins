<?php

WP_CLI::add_command( 'vaultpress', 'VaultPress_CLI' );

/**
 * Filter spam comments.
 */
class VaultPress_CLI extends WP_CLI_Command {
	/**
	 * Automatically registers VaultPress via the Jetpack plugin (if already signed up).
	 *
	 * ## EXAMPLES
	 *
	 *     wp vaultpress register_via_jetpack
	 *
	 * @alias comment-check
	 */
	public function register_via_jetpack() {
		$result = VaultPress::init()->register_via_jetpack( true );
		if ( is_wp_error( $result ) ) {
			// VIP: Replaced `error()` with `line()` to allow graceful fails
			// VIP: see https://github.com/Automattic/vip-go-mu-plugins/pull/907
			WP_CLI::warning( 'Failed to register VaultPress: ' . $result->get_error_message() );
		} else {
			WP_CLI::line( 'Successfully registered VaultPress via Jetpack.' );
		}
	}
}
