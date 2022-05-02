<?php
/**
 * This file contains everything that's loaded after the VIP configuration is applied.
 * This is one of the last things that happens before WordPress starts to load.
 *
 * You should make sure to include this file locally in case you're not using VIP Local Development Environment
 * @see https://docs.wpvip.com/how-tos/local-development/use-the-vip-local-development-environment/
 */

$mu_plugins_base = dirname( __DIR__ );

$files = [
	'/lib/helpers/wp-cli-db.php',
];

foreach ( $files as $file ) {
	if ( file_exists( $mu_plugins_base . $file ) ) {
		require_once $mu_plugins_base . $file;
	}
}
