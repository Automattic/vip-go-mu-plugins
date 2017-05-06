<?php

namespace Automattic\VIP\Split_Home_Site_URLs;

/**
 * Allow asset rewriting to be bypassed for all requests
 *
 * Some sites, such as those behind a reverse proxy, never need asset rewriting.
 * Due to loading order, the theme can't use this filter, so we provide an option to aid.
 * Set the `wpcom_vip_disable_split_url_asset_rewriting` option to `1` to utilize this.
 *
 * @param mixed   bool|null  Whether or not to skip rewriting for this request
 * @return mixed  bool|null
 */
function disable_asset_rewriting( $disable ) {
	$option = (bool) get_option( 'wpcom_vip_disable_split_url_asset_rewriting' );

	// If the option is truthy, we want to skip this request, which requires a boolean false
	if ( true === $option ) {
		return false;
	}

	return $disable;
}
add_filter( 'wpcom_vip_asset_urls_skip_rewrites_for_request', __NAMESPACE__ . '\disable_asset_rewriting' );

/**
* Parse home URL into pieces needed by setcookie()
*/
function parse_home_url_for_cookie() {
	$url    = home_url( '/' );
	$domain = parse_url( $url, PHP_URL_HOST );
	$path   = parse_url( $url, PHP_URL_PATH );
	$secure = 'https' === parse_url( $url, PHP_URL_SCHEME );

	return compact( 'domain', 'path', 'secure' );
}
