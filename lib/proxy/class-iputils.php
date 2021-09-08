<?php

/*
 * This file was borrowed and modified from this Symfony package:
 * https://github.com/symfony/http-foundation
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please see https://github.com/symfony/http-foundation/blob/master/LICENSE
 */

namespace Automattic\VIP\Proxy;

/**
 * Http utility functions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class IpUtils {

	/**
	 * This class should not be instantiated.
	 */
	private function __construct() {
	}

	/**
	 * Checks if an IPv4 or IPv6 address is contained in the list of given IPs or subnets.
	 *
	 * @param string       $request_ip IP to check
	 * @param string|array $ips       List of IPs or subnets (can be a string if only a single one)
	 *
	 * @return bool Whether the IP is valid
	 */
	public static function check_ip( $request_ip, $ips ) {
		if ( ! is_array( $ips ) ) {
			$ips = array( $ips );
		}

		$method = substr_count( $request_ip, ':' ) > 1 ? 'check_ip6' : 'check_ip4';

		foreach ( $ips as $ip ) {
			if ( self::$method( $request_ip, $ip ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Compares two IPv4 addresses.
	 * In case a subnet is given, it checks if it contains the request IP.
	 *
	 * @param string $request_ip IPv4 address to check
	 * @param string $ip        IPv4 address or subnet in CIDR notation
	 *
	 * @return bool Whether the request IP matches the IP, or whether the request IP is within the CIDR subnet
	 */
	public static function check_ip4( $request_ip, $ip ) {
		if ( ! filter_var( $request_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return false;
		}

		if ( false !== strpos( $ip, '/' ) ) {
			list($address, $netmask) = explode( '/', $ip, 2 );

			if ( '0' === $netmask ) {
				return filter_var( $address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
			}

			if ( $netmask < 0 || $netmask > 32 ) {
				return false;
			}
		} else {
			$address = $ip;
			$netmask = 32;
		}

		return 0 === substr_compare( sprintf( '%032b', ip2long( $request_ip ) ), sprintf( '%032b', ip2long( $address ) ), 0, $netmask );
	}

	/**
	 * Compares two IPv6 addresses.
	 * In case a subnet is given, it checks if it contains the request IP.
	 *
	 * @author David Soria Parra <dsp at php dot net>
	 *
	 * @see https://github.com/dsp/v6tools
	 *
	 * @param string $request_ip IPv6 address to check
	 * @param string $ip        IPv6 address or subnet in CIDR notation
	 *
	 * @return bool Whether the IP is valid
	 *
	 * @throws \RuntimeException When IPV6 support is not enabled
	 */
	public static function check_ip6( $request_ip, $ip ) {
		if ( ! ( ( extension_loaded( 'sockets' ) && defined( 'AF_INET6' ) ) || inet_pton( '::1' ) ) ) {
			throw new \RuntimeException( 'Unable to check Ipv6. Check that PHP was not compiled with option "disable-ipv6".' );
		}

		if ( false !== strpos( $ip, '/' ) ) {
			list($address, $netmask) = explode( '/', $ip, 2 );

			if ( $netmask < 1 || $netmask > 128 ) {
				return false;
			}
		} else {
			$address = $ip;
			$netmask = 128;
		}

		$bytes_addr = unpack( 'n*', @inet_pton( $address ) );      // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- inet_pton() spits a warning on failures
		$bytes_test = unpack( 'n*', @inet_pton( $request_ip ) );   // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( ! $bytes_addr || ! $bytes_test ) {
			return false;
		}

		for ( $i = 1, $ceil = ceil( $netmask / 16 ); $i <= $ceil; ++$i ) {
			$left = $netmask - 16 * ( $i - 1 );
			$left = ( $left <= 16 ) ? $left : 16;
			$mask = ~( 0xffff >> $left ) & 0xffff;
			if ( ( $bytes_addr[ $i ] & $mask ) != ( $bytes_test[ $i ] & $mask ) ) {
				return false;
			}
		}

		return true;
	}
}
