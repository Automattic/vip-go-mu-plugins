<?php

if ( ! defined( 'VIP_GO_APP_ENVIRONMENT' ) || 'production' === constant( 'VIP_GO_APP_ENVIRONMENT' ) ) {
	return;
}

// Added in the same release as this file; a non-atomic deploy can deliver this file first.
if ( file_exists( __DIR__ . '/class-environment-badges.php' ) ) {
	require_once __DIR__ . '/class-environment-badges.php';
}

/**
 * No longer outputs anything: the badge now lives in the admin bar or the corner dock.
 *
 * Still hooked so sites that hid the old bar with
 * `remove_action( 'wp_footer', 'vip_do_non_prod_bar', 10000 )` keep it hidden.
 */
function vip_do_non_prod_bar() {}

/**
 * Registers the badge. Sites can still hide it with the `vip_show_non_prod_bar` filter, or by removing
 * this function from `wp_enqueue_scripts` / `admin_enqueue_scripts`.
 */
function vip_non_prod_enqueue_scripts() {
	if ( ! class_exists( \Automattic\VIP\Helpers\Environment_Badges::class ) || ! current_user_can( 'edit_posts' ) || ! apply_filters( 'vip_show_non_prod_bar', true ) ) {
		return;
	}

	$environment = constant( 'VIP_GO_APP_ENVIRONMENT' );
	if ( 'local' === $environment && 'true' === getenv( 'CODESPACES' ) ) {
		$environment = 'codespaces';
	}

	// Short enough that the admin bar stays on one row on phones, next to core's icons.
	$short_names = [
		'local'       => 'LOC',
		'codespaces'  => 'CS',
		'develop'     => 'DEV',
		'development' => 'DEV',
		'dev'         => 'DEV',
		'preprod'     => 'PRE',
		'staging'     => 'STG',
		'stage'       => 'STG',
		'testing'     => 'TEST',
		'uat'         => 'UAT',
	];

	\Automattic\VIP\Helpers\Environment_Badges::add( 'non-production', [
		'label'     => $environment,
		'short'     => $short_names[ $environment ] ?? strtoupper( substr( $environment, 0, 3 ) ),
		'sr_text'   => ', non-production environment',
		'title'     => 'Non-production environment',
		'order'     => 10,
		// Sites' CSS for the old bar (e.g. `#vip-non-prod-bar { display: none; }`) still hides the badge.
		'legacy_id' => 'vip-non-prod-bar',
		'show'      => fn() => (bool) has_action( is_admin() ? 'admin_footer' : 'wp_footer', 'vip_do_non_prod_bar' ),
	] );
}

add_action( 'wp_enqueue_scripts', 'vip_non_prod_enqueue_scripts' );
add_action( 'admin_enqueue_scripts', 'vip_non_prod_enqueue_scripts' );

// Added here, rather than when the badge registers, so a site's remove_action() works whenever it runs.
add_action( 'wp_footer', 'vip_do_non_prod_bar', 10000 );
add_action( 'admin_footer', 'vip_do_non_prod_bar', 10000 );
