<?php

 /***************************************************************************************************
 *                                                                                                  *
 * This IS NOT ready for client use. If you are interested in learning more, please contact VIP Support. *
 *                                                                                                  *
 ***************************************************************************************************/

/**
 * Call a WP-CLI command using `shell_exec()`.
 *
 * Useful for calling commands with the full absolute path, so things
 * will work both sandboxed and in production.
 *
 * @param string $command Name of the command to run. Required.
 * @param string $subcommand Name of the subcommand to run. Required.
 * @param array $args Any additional arguments to pass; can be an ARRAY or ARRAY_A. Optional.
 * @return mixed Result of the `shell_exec()` call.
 */
function wpj_run_wpcli_command( $command, $subcommand, $args = array() ) {
	$args_string = '';

	$command = sanitize_key( $command );
	$subcommand = sanitize_key( $subcommand );

	$url = isset( $args['url'] ) ? esc_url_raw( $args['url'] ) : home_url();
	$hostname = gethostname();

	wpcom_vip_irc( '#vip-go-wp-cli', sprintf( '%s called for `wp %s %s` on %s (%s)', __FUNCTION__, $command, $subcommand, $url, $hostname ) );

	// Optional arguments.
	foreach ( $args as $arg => $value ) {
		if ( 'wpcom-vip-output-mail' === $arg ) {
			continue;
		}
		if ( 'wpcom-vip-limit' === $arg ) {
			continue;
		}
		if ( is_numeric( $arg ) ) {
			$args_string .= sprintf( ' %s', escapeshellarg( $value ) );
		} else {
			$value = maybe_serialize( $value );
			$args_string .= sprintf( ' --%s=%s', sanitize_key( $arg ), escapeshellarg( $value ) );
		}
	}

	$whitelisted_commands = array();
	if ( true === function_exists( 'wpcom_vip_is_allowed_cli_passthrough' ) ) {
		$whitelisted_commands = wpcom_vip_is_allowed_cli_passthrough();
	}
	if ( false === array_key_exists( $command, $whitelisted_commands ) || false === in_array( $subcommand, $whitelisted_commands[ $command ], true ) ) {
		if ( false !== is_email( $args['wpcom-vip-output-mail'] ) ) {
			wp_mail( $args['wpcom-vip-output-mail'], sprintf( 'Command %s %s is not whitelisted.', $command, $subcommand ), sprintf( 'Command %s %s is not whitelisted on %s.', $command, $subcommand, $url ) );
		}

		wpcom_vip_irc( '#vip-go-wp-cli', sprintf( '%s exiting; command `wp %s %s` not whitelisted on %s (%s)', __FUNCTION__, $command, $subcommand, $url, $hostname ) );

		return; // Bail as the command is not whitelisted.
	}

	$cli_command = sprintf( 'wp --allow-root %s %s %s',
		sanitize_key( $command ),
		sanitize_key( $subcommand ),
		$args_string
	);

	$cli_command .= sprintf( ' --require=%s', __DIR__ . '/vip-wp-cli-to-cron/limit-wp-cli-command.php' );

	$limit = 600; // Default to 10 minutes for execution.
	if ( true === isset( $args['wpcom-vip-limit'] ) && 0 !== absint( $args['wpcom-vip-limit'] ) ) {
		$limit = absint( $args['wpcom-vip-limit'] );
	}

	// Temporary fix to allow WP-CLI to work outside the root of the WP install and set max execution time limit.
	$cli_command = sprintf( 'cd %s; export WPCOM_VIP_WP_CLI_LIMIT=%d; %s 2>&1; unset WPCOM_VIP_WP_CLI_LIMIT;', ABSPATH, $limit, $cli_command );

	wp_mail( $args['wpcom-vip-output-mail'], sprintf( 'Running `wp %s %s` on %s (%s)', $command, $subcommand, $url, $hostname ), $cli_command );
	wpcom_vip_irc( '#vip-go-wp-cli', sprintf( '%s running `wp %s %s` on %s (%s)', __FUNCTION__, $command, $subcommand, $url, $hostname ) );

	$output = shell_exec( $cli_command );

	wp_mail( $args['wpcom-vip-output-mail'], sprintf( 'Output for `wp %s %s` on %s (%s)', $command, $subcommand, $url, $hostname ), $output );
	wpcom_vip_irc( '#vip-go-wp-cli', sprintf( '%s finished running `wp %s %s` on %s (%s)', __FUNCTION__, $command, $subcommand, $url, $hostname ) );

	return $output;
}

function wpcom_vip_wp_cli_command_to_cron( $command, $subcommand, $args ) {
	wpj_run_wpcli_command( $command, $subcommand, $args );
}
add_action( 'wpcom_vip_wp_cli_command_to_cron', 'wpcom_vip_wp_cli_command_to_cron', 10, 3 );

// This is not ready for client use. If you are interested in learning, please contact VIP Support.
add_filter( 'schedule_event', function( $event ) {
	if ( is_object( $event ) && 'wpcom_vip_wp_cli_command_to_cron' === $event->hook ) {
		if ( false === defined( 'WPCOM_SANDBOXED' ) || true !== constant( 'WPCOM_SANDBOXED' ) ) {
			$event = false;
		}
		if ( false === is_array( $event->args[2] ) || false === array_key_exists( 'wpcom-vip-output-mail', $event->args[2] ) || false === is_email( $event->args[2]['wpcom-vip-output-mail'] ) ) {
			if ( true === defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::line( 'Error: No, or invalid email provided. Please, specify valid email address in command args array via wpcom-vip-output-mail key' );
			}
			$event = false;
		}
		WP_CLI::line( 'Success: WP CLI command was scheduled.' );
	}
	return $event;
}, PHP_INT_MAX, 1 );
