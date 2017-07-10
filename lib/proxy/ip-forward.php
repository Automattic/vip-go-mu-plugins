<?php

namespace Automattic\VIP\Proxy;

/**
 * Is the supplied IP address valid?
 *
 * Supports v4 and v6 IP addresses
 *
 * @param string $ip The IP address to validate
 *
 * @return bool True if the IP address is valid
 */
function is_valid_ip( $ip ) {
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 )
		&& ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return false;
	}

	return true;
}

/**
 * Set the remote address in PHP
 *
 * @param string $ip The IP address to set the remote address to
 */
function set_remote_address( $ip ) {
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
	set_remote_address( $user_ip );

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
	$ip_addresses = get_ip_addresses_from_ip_trail( $ip_trail );
	if ( false === $ip_addresses ) {
		return false;
	}

	list( $user_ip, $remote_proxy_ip ) = $ip_addresses;

	return fix_remote_address( $user_ip, $remote_proxy_ip, $proxy_ip_whitelist );
}

/**
 * Return the defined verification key for a site
 *
 * @return string|int The verification key if available, or a random integer if no key is configured.
 */
function get_proxy_verification_key() {
	if ( defined( 'WPCOM_VIP_PROXY_VERIFICATION' ) && ! empty( WPCOM_VIP_PROXY_VERIFICATION ) ) {
		return WPCOM_VIP_PROXY_VERIFICATION;
	}

	// If not properly defined for some reason, return a random number to avoid guessing the key.
	return rand();
}

/**
 * Set REMOTE_ADDR to the end-user's IP address, if the verification key matches
 *
 * When an IP whitelist isn't possible, we rely on a verification key being sent as a request header as our method of safely forwarding the IP.
 *
 * @param (string) $user_ip IP Address of the end-user passed through by the proxy.
 * @param (string) $submitted_verification_key Verification key passed through request headers
 *
 * @return (bool) true, if REMOTE_ADDR updated; false, if not.
 *
 */
function fix_remote_address_with_verification_key( $user_ip, $submitted_verification_key ) {
	if ( ! is_valid_ip( $user_ip ) ) {
		return false;
	}

	if ( ! is_valid_proxy_verification_key( $submitted_verification_key ) ) {
		return false;
	}

	set_remote_address( $user_ip );

	return true;
}

/**
 * Set REMOTE_ADDR to the end-user's IP address from a trail of IP Addresses.
 *
 * Validate the proxy using a shared secret verification key.
 *
 * This allows satisfactorily forwarding the origin IP address when there are multiple
 * proxies in play.
 *
 * Example setup:
 * User => Remote Proxy (e.g. Cloudflare) => Local Proxy (Varnish) => Application (PHP/WP)
 *
 * Without this, the Application will see the Remote Proxy's IP address as the REMOTE_ADDR.
 * With this, if the secret verification key is correct, the Application will see the User's
 * real IP address as REMOTE_ADDR.
 *
 * Only two levels of proxies are supported.
 *
 * @param (string) $ip_trail Comma-separated list of IPs (something like `user_ip, proxy_ip`)
 * @param (string) $submitted_verification_key Verification key passed through request headers
 *
 * @return (bool) true, if REMOTE_ADDR updated; false, if not.
 */
function fix_remote_address_from_ip_trail_with_verification_key( $ip_trail, $submitted_verification_key ) {
	$ip_addresses = get_ip_addresses_from_ip_trail( $ip_trail );
	if ( false === $ip_addresses ) {
		return false;
	}

	$user_ip = $ip_addresses[0];

	if ( ! is_valid_proxy_verification_key( $submitted_verification_key ) ) {
		return false;
	}

	set_remote_address( $user_ip );

	return true;
}

/**
 * Validate the provided verification key against the one in config.
 *
 * @param string $submitted_verification_key The key to validate
 *
 * @return bool True if the key is valid
 */
function is_valid_proxy_verification_key( $submitted_verification_key ) {
	$expected_verification_key = get_proxy_verification_key();
	if ( ! hash_equals( $submitted_verification_key, $expected_verification_key ) ) {
		return false;
	}
	return true;
}

/**
 * Get a list of validated IP addresses from a comma-separated string expected to
 * be passed as the X-IP-Trail HTTP request header
 *
 * Takes IP v4 or v6
 *
 * Fails if there's more than two IP addresses
 * Fails if any IP address is invalid
 *
 * Also checks the X-Forwarded-For header makes sense.
 *
 * @param string $ip_trail A comma separated string of IP addresses
 *
 * @return array|bool An array of validated IP addresses, or false if
 */
function get_ip_addresses_from_ip_trail( $ip_trail ) {
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

	foreach ( $ip_addresses as $ip_address ) {
		if ( empty( $ip_address ) ) {
			return false;
		}
		if ( ! is_valid_ip( $ip_address ) ) {
			return false;
		}
	}

	// This should probably never happen, but validate just in case.
	$remote_proxy_ip = $ip_addresses[1];
	if ( $remote_proxy_ip !== $_SERVER['HTTP_X_FORWARDED_FOR'] ) {
		return false;
	}

	return $ip_addresses;
}
