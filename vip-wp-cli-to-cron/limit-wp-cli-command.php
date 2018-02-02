<?php

/**
 * Define max execution time for the child `wp` process.
 *
 * Use the value provided via `--wpcom-vip-limit` param, unless it's equal to 0.
 * Defaults to 600 seconds (10 minutes)
 */
function wpcom_vip_wp_cli_set_time_limit() {
	$limit = 600; // 10 minutes by default.

	if ( false !== getenv( 'WPCOM_VIP_WP_CLI_LIMIT' ) && 0 !== abs( intval( getenv( 'WPCOM_VIP_WP_CLI_LIMIT' ) ) ) ) {
		$limit = abs( intval( getenv( 'WPCOM_VIP_WP_CLI_LIMIT' ) ) );
	}
	set_time_limit( $limit );
}

wpcom_vip_wp_cli_set_time_limit();
