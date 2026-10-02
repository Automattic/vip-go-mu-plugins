<?php

// Normal WordPress tests already load Connection Pilot; this process must not.
define( 'VIP_GO_APP_ENVIRONMENT', 'production' );
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter, WordPress.NamingConventions.ValidFunctionName
function add_action( ...$args ) {}
function is_local_env() {
	return false;
}
require __DIR__ . '/../../../vip-helpers/vip-migrations.php';
\Automattic\VIP\Migration\run_connection_pilot_after_cleanup();
\Automattic\VIP\Migration\run_connection_pilot_after_cleanup();
