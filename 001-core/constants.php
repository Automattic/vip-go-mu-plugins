<?php

namespace Automattic\VIP\Core\Constants;

/**
 * Define DB_HOST, DB_USER, DB_PASSWORD, and DB_NAME constants.
 *
 * WPVIP uses use HyperDB, so DB_* constants are not automatically defined.
 * As a best practice, these constants should not be used directly, but we define them here in
 * case any customer code does to avoid fatal errors in PHP 8+.
 *
 * @param object $hyperdb
 */
function define_db_constants( $hyperdb ): void {
	if ( defined( 'DB_NAME' ) || defined( 'DB_HOST' ) || defined( 'DB_PASSWORD' ) || defined( 'DB_USER' ) ) {
		return;
	}
	require_once __DIR__ . '/../vip-helpers/vip-utils.php';
	if ( ! function_exists( 'vip_get_hyper_servers' ) ) {
		return;
	}

	$db_servers = vip_get_hyper_servers( $hyperdb, 'write' );
	if ( ! is_array( $db_servers ) || empty( $db_servers ) ) {
		return;
	}

	if ( isset( $db_servers[1] ) ) {
		// The index is based on the "write priority", which defaults to 1.
		// See https://github.com/Automattic/HyperDB/blob/06dc2449535c35aaaf2f7e1e8a28fb40e59ff23e/db.php#L257-L267
		$db = $db_servers[1];
	} else {
		$keys = array_keys( $db_servers );
		ksort( $keys );
		$db = $db_servers[ $keys[0] ] ?? null;
	}

	if ( isset( $db[0] ) && is_array( $db[0] ) ) {
		define( 'DB_HOST', $db[0]['host'] );
		define( 'DB_USER', $db[0]['user'] );
		define( 'DB_PASSWORD', $db[0]['password'] );
		define( 'DB_NAME', $db[0]['name'] );

		if ( ! isset( $GLOBALS['wpdb']->dbname ) && \Automattic\VIP\Utils\Context::is_wp_cli() ) {
			// Only assign the property for the database name if we're in WP-CLI (for now).
			$GLOBALS['wpdb']->dbname = constant( 'DB_NAME' );
		}
	}
}

/**
 * Define VIP_MU_PLUGINS_BRANCH and VIP_MU_PLUGINS_BRANCH_ID.
 * Uses the mu-plugins meta info from version file.
 */
function define_mu_plugins_constants(): void {
	if ( defined( 'VIP_MU_PLUGINS_BRANCH' ) || defined( 'VIP_MU_PLUGINS_BRANCH_ID' ) ) {
		return;
	}

	if ( defined( 'VIP_GO_ENV' ) && 'local' === constant( 'VIP_GO_ENV' ) ) {
		return;
	}

	$version_file = WPMU_PLUGIN_DIR . '/.version';
	if ( ! file_exists( $version_file ) ) {
		return;
	}

	// phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
	$info = file_get_contents( $version_file );
	if ( ! $info ) {
		return;
	}

	$info      = json_decode( $info );
	$tag       = $info->tag ?? null;
	$branch_id = $info->stack_version ?? null;
	if ( $tag ) {
		define( 'VIP_MU_PLUGINS_BRANCH', 'prod' === $tag ? 'production' : $tag );
	}
	if ( $branch_id ) {
		define( 'VIP_MU_PLUGINS_BRANCH_ID', $branch_id );
	}

}
