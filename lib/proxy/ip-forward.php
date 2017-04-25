<?php

namespace Automattic\VIP\Proxy;

function is_valid_ip( $ip ) {
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 )
		&& ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return false;
	}

	return true;
}

function set_remote_addr( $ip ) {
	$_SERVER['REMOTE_ADDR'] = $ip;
}

/**
 * Set REMOTE_ADDR to the end-user's IP address.
 *
 * This allows more securely forwarding the origin IP address when your site is fronted by a proxy like Cloudflare or Akamai.
 *
 * Without this, the Application will see the Remote Proxy's IP address as the REMOTE_ADDR.
 * With this, if Remote Proxy's IP address matches a known whitelist, the Application will see the User's real IP address as REMOTE_ADDR.
 *
 * @param (string) $user_ip IP Address of the end-user passed through by the proxy.
 * @param (string) $remote_proxy_ip IP Address of the remote proxy.
 * @param (string|array) $proxy_ip_whitelist Whitelisted IP addresses for the remote proxy. Supports IPv4 and IPv6, including CIDR format.
 *
 * @return (bool) true, if REMOTE_ADDR updated; false, if not.
 */
function fix_remote_address( $user_ip, $remote_proxy_ip, $proxy_ip_whitelist ) {
	if ( ! is_valid_ip( $user_ip ) ) {
		return false;
	}

	require_once( __DIR__ . '/ip-utils.php' );

	// Verify that the remote proxy matches our whitelist
	$is_whitelisted_proxy_ip = IpUtils::checkIp( $remote_proxy_ip, $proxy_ip_whitelist );

	if ( ! $is_whitelisted_proxy_ip ) {
		return false;
	}

	// Everything looks good so we can set our SERVER var
	set_remote_addr( $user_ip );

	return true;
}

/**
 * Set REMOTE_ADDR to the end-user's IP address from a trail of IP Addresses.
 *
 * This allows more securely forwarding the origin IP address when there are multiple proxies in play.
 *
 * Example setup:
 * User => Remote Proxy (e.g. Cloudflare) => Local Proxy (Varnish) => Application (PHP/WP)
 *
 * Without this, the Application will see the Remote Proxy's IP address as the REMOTE_ADDR.
 * With this, if Remote Proxy's IP address matches a known whitelist, the Application will see the User's real IP address as REMOTE_ADDR.
 *
 * Only two levels of proxies are supported.
 *
 * @param (string) $ip_trail Comma-separated list of IPs (something like `user_ip, proxy_ip`)
 * @param (string|array) $proxy_ip_whitelist Whitelisted IP addresses for the remote proxy. Supports IPv4 and IPv6, including CIDR format.
 *
 * @return (bool) true, if REMOTE_ADDR updated; false, if not.
 */
function fix_remote_address_from_ip_trail( $ip_trail, $proxy_ip_whitelist ) {
	// If X-Forwarded-For is not set, we're not dealing with a remote proxy or something in the proxy configs is doing it wrong.
	if ( ! isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		return false;
	}

	// Verify that the IP trail has multiple IPs but only two levels (remote + local).
	$ip_addresses = explode( ',', $ip_trail );
	$ip_addresses = array_map( 'trim', $ip_addresses );
	if ( 2 !== count( $ip_addresses ) ) {
		return false;
	}

	list( $user_ip, $remote_proxy_ip ) = $ip_addresses;

	// This should probably never happen, but validate just in case.
	if ( $remote_proxy_ip !== $_SERVER['HTTP_X_FORWARDED_FOR'] ) {
		return false;
	}

	return fix_remote_address( $user_ip, $remote_proxy_ip, $proxy_ip_whitelist );
}

/**
 * Set REMOTE_ADDR to the end-user's IP address, if the verification key matches
 *
 * When an IP whitelist isn't possible, we rely on a verification key being sent as a request header as our method of safely forwarding the IP.
 *
 * @param (string) $user_ip IP Address of the end-user passed through by the proxy.
 * @param (string) $verification_key Verification key passed through request headers
 *
 * @return (bool) true, if REMOTE_ADDR updated; false, if not.
 *
 */
function fix_remote_address_with_verification_key( $user_ip, $verification_key ) {
	if ( ! is_valid_ip( $user_ip ) ) {
		return false;
	}

	if ( ! hash_equals( $verification_key, WPCOM_VIP_IP_VERIFICATION_KEY ) ) {
		return false;
	}

	set_remote_addr( $user_ip );

	return true;
}
