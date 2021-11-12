<?php
/**
 * This file contains everything that's loaded before vip-config.php
 *
 * You should make sure to include this file locally in case you're not using VIP Local Development Environment
 * @see https://docs.wpvip.com/how-tos/local-development/use-the-vip-local-development-environment/
 */

$mu_plugins_base = dirname( __DIR__ );

// Load custom error logging functions, if available
if ( file_exists( $mu_plugins_base . '/lib/wpcom-error-handler/wpcom-error-handler.php' ) ) {
	require_once $mu_plugins_base . '/lib/wpcom-error-handler/wpcom-error-handler.php';
}

// Load VIP_Request_Block utility class, if available
if ( file_exists( $mu_plugins_base . '/lib/class-vip-request-block.php' ) ) {
	require_once $mu_plugins_base . '/lib/class-vip-request-block.php';
}

// Load Environment utility class and its helpers, if available
if ( file_exists( $mu_plugins_base . '/lib/environment/class-environment.php' ) ) {
	require_once $mu_plugins_base . '/lib/environment/class-environment.php';
}

if ( file_exists( $mu_plugins_base . '/lib/helpers/environment.php' ) ) {
	require_once $mu_plugins_base . '/lib/helpers/environment.php';
}
