<?php

namespace Automattic\VIP\Config;

use Automattic\VIP\Utils\Context;

class VIP_Jetpack_Network_Launch_Redirects {
	private static $instance;

	const NETWORK_TEMP_REDIRECTS_OPTION_NAME = 'vip_network_temp_redirects';
	const REDIRECT_TTL_MINUTES               = 60;
	const LOG_FEATURE_NAME                   = 'vip_network_launch_redirects';
	// This is used in front of the source domain to avoid the S&R to replace it
	const URL_REPLACE_PREFIX = '##';

	public static function maybe_redirect_jetpack_network_launches() {
		// applies redirects only in the frontend and for multisites
		if ( ! is_multisite() || is_admin() || ! Context::is_web_request() ) {
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

		// we care only about jetpack requests
		if ( ! vip_is_jetpack_request() ) {
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
		$valid_redirects = array_filter( $network_redirects, function ( $redirect ) {
			return $redirect['timestamp'] > ( time() - self::REDIRECT_TTL_MINUTES * 60 );
		} );
		// print the string of valid redirects in logs
		// iterate on the $valid_redirects and se if the request uri matches. If it matches, redirect.
		if ( isset( $_SERVER['HTTP_HOST'] ) && isset( $_SERVER['REQUEST_URI'] ) ) {
			$redirect_url  = '';
			$uri_unslashed = untrailingslashit( $_SERVER['REQUEST_URI'] );

			$requested_url = self::URL_REPLACE_PREFIX . $_SERVER['HTTP_HOST'] . wp_parse_url( $uri_unslashed, PHP_URL_PATH );
			if ( $requested_url && array_key_exists( $requested_url, $valid_redirects ) ) {
				$redirect_url = $valid_redirects[ $requested_url ]['to'];
			}
			if ( $redirect_url ) {
				if ( did_action( 'plugins_loaded' ) ) {
					// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect, WordPressVIPMinimum.Security.ExitAfterRedirect.NoExit
					wp_redirect( $redirect_url, 301 );
				} else {
					header( "Location: {$redirect_url}", true, 301 );
				}

				exit;
			}
		}

		// TODO have a scheduled command that cleans the option from expired redirects

	}

	public function log( $severity, $message, $extra = array() ) {
		\Automattic\VIP\Logstash\log2logstash( array(
			'severity' => $severity,
			'feature'  => self::LOG_FEATURE_NAME,
			'message'  => $message,
			'extra'    => $extra,
		) );
	}
}
