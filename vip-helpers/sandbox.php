<?php

if ( ! defined( 'WPCOM_SANDBOXED' ) || ! WPCOM_SANDBOXED ) {
	return;
}

function wpcom_add_sandbox_badge() {
	if ( ! class_exists( \Automattic\VIP\Helpers\Environment_Badges::class ) || ! apply_filters( 'wpcom_show_sandbox_bar', false ) ) {
		return;
	}

	$template   = get_template();
	$stylesheet = get_stylesheet();
	$theme      = ( $stylesheet != $template ) ? sprintf( '%s => %s', $stylesheet, $template ) : $template;

	$debug_info = array(
		'sandbox' => str_replace( '.wordpress.com', '', php_uname( 'n' ) ),
		'theme'   => $theme,
		'your ip' => $_SERVER['REMOTE_ADDR'] ?? '', // phpcs:ignore
	);

	\Automattic\VIP\Helpers\Environment_Badges::add( 'sandbox', [
		'label'   => 'Sandbox',
		'short'   => 'SBX',
		'details' => apply_filters( 'wpcom_sandbox_bar_debug_info', $debug_info ),
		'order'   => 20,
	] );
}

add_action( 'init', 'wpcom_add_sandbox_badge' );

add_filter( 'wpcom_show_sandbox_bar', '__return_true' );

/**
 * Filters URLs to use sandboxed hostnames.
 *
 * @param string $url The complete URL including scheme and path.
 *
 * @return string Filtered URL using sandboxed hostname.
 */
function wpvip_filter_sandbox_url( $url ) {
	/** @var array<string,string> */
	global $sandbox_vhosts;
	$host = $_SERVER['HTTP_HOST'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	if ( empty( $host ) || ! is_array( $sandbox_vhosts ) ) {
		// Not a k8s sandbox or valid host, bail.
		return $url;
	}
	/*
	 * $sandbox_vhosts is something like ['subdomain.uuid.sbx-sid.ingress-api.vip-ditto.k8s.dfw.vipv2.net' => 'subdomain.go-vip.net']
	 * `sandbox-hosts-config.php` replaces the "sandbox" (key) domain with the "original" (value) domain in `$_SERVER['HTTP_HOST']`.
	 * In this filter, we need to reverse that for the given `$url`
	 *
	 * 1. We need to look up the key matching `$_SERVER['HTTP_HOST']` in `$sandbox_vhosts`
	 * 2. We need to replace `://{$_SERVER['HTTP_HOST']}` with `://{$key}`  in `$url`
	 */
	if ( in_array( $host, $sandbox_vhosts, true ) ) {
		$flipped = array_flip( $sandbox_vhosts );
		$key     = $flipped[ $host ];
		$url     = str_replace( '://' . $host, '://' . $key, $url );
	}

	return $url;
}
add_filter( 'plugins_url', 'wpvip_filter_sandbox_url', 1000 );
add_filter( 'admin_url', 'wpvip_filter_sandbox_url', 1000 );
