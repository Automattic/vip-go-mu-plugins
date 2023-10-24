<?php

namespace Automattic\VIP\Config;
use Automattic\VIP\Utils\Context;

class VIP_Jetpack_Network_Launch_Redirects {

	const NETWORK_TEMP_REDIRECTS_OPTION_NAME = 'vip_network_temp_redirects';
	const REDIRECT_TTL_MINUTES               = 60;
	// This is used in front of the source domain to avoid the S&R to replace it
	const URL_REPLACE_PREFIX = '##';

	/**
	 * This function should be called only in the sunrise for multisites, we're expecting the $domain and $path from the
	 * ms_site_not_found and ms_network_not_found actions.
	 * @param $domain
	 * @param $path
	 *
	 * @return void
	 */
	public static function maybe_redirect_jetpack_network_launches( $domain, $path ) {
		// applies redirects only in the frontend and for multisites
		if ( ! is_multisite() || is_admin() ) { // TODO be more specific about which pages we want to support
			return;
		}
		// check DEFINED constant 'DISABLE_VIP_LAUNCH_REDIRECTS' to skip redirects
		if ( defined( 'DISABLE_VIP_LAUNCH_REDIRECTS' ) && true === constant( 'DISABLE_VIP_LAUNCH_REDIRECTS' ) ) {
			return;
		}

		// if they are not enrolled in the SYNC IDC program, skip redirects
		if ( ! defined( 'JETPACK_SYNC_IDC_OPTIN' ) || true !== constant( 'JETPACK_SYNC_IDC_OPTIN' ) ) {
			return;
		}

		// there are lots of includes in the vip-utils file, so for now we're only checking if the user agent is jetpack instead of using vip_is_jetpack_request
		// phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$http_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
		if ( false === stripos( $http_user_agent, 'jetpack' ) ) { // TODO check if there's a way to get the real Jetpack IPs and check against them while in the sunrise.
			return;
		}


		// TODO we'll need to have some hashing in the options to avoid S&R to change it.
		// TODO should we have some kind of validation to ensure the data is consistent?
		/**
		 * $network_redirects is an array of this form this form
		 * [
		 * '##[domain]/[path]' => [
		 *        'to' => '[REDIRECT URL]',
		 *        'timestamp' => time(),
		 *    ],
		 */
		$network_redirects = get_option( self::NETWORK_TEMP_REDIRECTS_OPTION_NAME, [] );
		if ( ! is_array( $network_redirects ) ) {
			return;
		}

		// $network_redirects is an array of arrays, each of which has the following keys:
		// 'to' => string, the URL to redirect to
		// 'timestamp' => int, the time it was created
		// create an array with only the elements that have not expired
		// TODO Evaluate if we want to enable the expiration again
		// $valid_redirects = array_filter( $network_redirects, function ( $redirect ) {
		// 	return $redirect['timestamp'] > ( time() - self::REDIRECT_TTL_MINUTES * 60 );
		// } );
		$valid_redirects = $network_redirects;

		// iterate on the $valid_redirects and se if the request uri matches. If it matches, redirect.
		if ( $domain && $path ) { // TODO test this condition both for when we have a path and when we don't
			$redirect_url  = '';
			// If we're handling an xmlrpc request, get only the path up to the last folder.
			if ( false !== strpos( $path, 'xmlrpc.php' ) ) {
				$path_parts = explode( '/', $path );
				array_pop( $path_parts );
				// join the remaining elements
				$path = implode( '/', $path_parts );
			}
			// extract the query string from the path
			if ( false !== strpos( $path, '?' ) ) {
				$path_parts = explode( '?', $path );
				$path       = $path_parts[0];
			}

			$uri_unslashed = untrailingslashit( $path );
			$requested_url = self::URL_REPLACE_PREFIX . $domain . $uri_unslashed;

			if ( $requested_url && array_key_exists( $requested_url, $valid_redirects ) ) {
				$redirect_url = $valid_redirects[ $requested_url ]['to'];
				if ( isset( $_SERVER['HTTP_HOST'] ) ){
					// if we have HTTP_HOST it means the REQUEST_URI is not the full URL, so we need to add the path to the redirect URL
					$redirect_url =	$valid_redirects[ $requested_url ]['to'] . str_replace( $path, '', $_SERVER['REQUEST_URI'] );
				} else {
					$redirect_url = str_replace( 'https://' . $domain . $uri_unslashed, $valid_redirects[ $requested_url ]['to'], $_SERVER['REQUEST_URI'] );
				}
			}
			if ( $redirect_url ) {
				header( "Location: {$redirect_url}", true, 301 );
				exit;
			}
		}

		// TODO have a scheduled command that cleans the option from expired redirects

	}

	// should run only in the init, will load the $domain $path from the request
	public static function maybe_redirect_jetpack_network_launches_init() {
		$is_web_or_xmlrpc = Context::is_web_request() || Context::is_xmlrpc_api();
		if ( ! $is_web_or_xmlrpc ) {
			return;
		}
		self::maybe_redirect_jetpack_network_launches( $_SERVER['HTTP_HOST'] , $_SERVER['REQUEST_URI'] ?? '/' );
	}

}
