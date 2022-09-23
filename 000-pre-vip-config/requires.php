<?php
/**
 * This file contains everything that's loaded before vip-config.php
 *
 * You should make sure to include this file locally in case you're not using VIP Local Development Environment
 * @see https://docs.wpvip.com/how-tos/local-development/use-the-vip-local-development-environment/
 */

$mu_plugins_base = dirname( __DIR__ );

$files = [
	'/lib/wpcom-error-handler/wpcom-error-handler.php',
	'/lib/class-vip-request-block.php',
	'/lib/environment/class-environment.php',
	'/lib/helpers/environment.php',
	'/lib/utils/class-context.php',
];

$cli_files = [
	// '/lib/helpers/wp-cli-db.php', - Reverting as it breaks dev-env import
];

foreach ( $files as $file ) {
	if ( file_exists( $mu_plugins_base . $file ) ) {
		require_once $mu_plugins_base . $file;
	}
}

unset( $files );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	foreach ( $cli_files as $file ) {
		if ( file_exists( $mu_plugins_base . $file ) ) {
			require_once $mu_plugins_base . $file;
		}
	}
}

unset( $cli_files );
unset( $file );
unset( $mu_plugins_base );
