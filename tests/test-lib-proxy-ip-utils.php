<?php

/*
 * This file was borrowed and modified from this Symfony package:
 * https://github.com/symfony/http-foundation
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please https://github.com/symfony/http-foundation/blob/master/LICENSE
 *
 */

namespace Automattic\VIP\Tests;

use Automattic\VIP\Proxy\IpUtils;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class IpUtilsTest extends TestCase {

	/**
	 * @dataProvider data_ipv4_provider
	 */
	public function testIpv4( $matches, $remote_addr, $cidr ) {
		$this->assertSame( $matches, IpUtils::check_ip( $remote_addr, $cidr ) );
	}

	public function data_ipv4_provider() {
		return array(
			array( true, '192.168.1.1', '192.168.1.1' ),
			array( true, '192.168.1.1', '192.168.1.1/1' ),
			array( true, '192.168.1.1', '192.168.1.0/24' ),
			array( false, '192.168.1.1', '1.2.3.4/1' ),
			array( false, '192.168.1.1', '192.168.1.1/33' ), // invalid subnet
			array( true, '192.168.1.1', array( '1.2.3.4/1', '192.168.1.0/24' ) ),
			array( true, '192.168.1.1', array( '192.168.1.0/24', '1.2.3.4/1' ) ),
			array( false, '192.168.1.1', array( '1.2.3.4/1', '4.3.2.1/1' ) ),
			array( true, '1.2.3.4', '0.0.0.0/0' ),
			array( true, '1.2.3.4', '192.168.1.0/0' ),
			array( false, '1.2.3.4', '256.256.256/0' ), // invalid CIDR notation
			array( false, 'an_invalid_ip', '192.168.1.0/24' ),
		);
	}

	/**
	 * @dataProvider data_ipv6_provider
	 */
	public function testIpv6( $matches, $remote_addr, $cidr ) {
		if ( ! defined( 'AF_INET6' ) ) {
			$this->markTestSkipped( 'Only works when PHP is compiled without the option "disable-ipv6".' );
		}

		$this->assertSame( $matches, IpUtils::check_ip( $remote_addr, $cidr ) );
	}

	public function data_ipv6_provider() {
		return array(
			array( true, '2a01:198:603:0:396e:4789:8e99:890f', '2a01:198:603:0::/65' ),
			array( false, '2a00:198:603:0:396e:4789:8e99:890f', '2a01:198:603:0::/65' ),
			array( false, '2a01:198:603:0:396e:4789:8e99:890f', '::1' ),
			array( true, '0:0:0:0:0:0:0:1', '::1' ),
			array( false, '0:0:603:0:396e:4789:8e99:0001', '::1' ),
			array( true, '2a01:198:603:0:396e:4789:8e99:890f', array( '::1', '2a01:198:603:0::/65' ) ),
			array( true, '2a01:198:603:0:396e:4789:8e99:890f', array( '2a01:198:603:0::/65', '::1' ) ),
			array( false, '2a01:198:603:0:396e:4789:8e99:890f', array( '::1', '1a01:198:603:0::/65' ) ),
			array( false, '}__test|O:21:&quot;JDatabaseDriverMysqli&quot;:3:{s:2', '::1' ),
			array( false, '2a01:198:603:0:396e:4789:8e99:890f', 'unknown' ),
		);
	}

	/**
	 * @requires extension sockets
	 */
	public function testAnIpv6WithOptionDisabledIpv6() {
		if ( defined( 'AF_INET6' ) ) {
			$this->markTestSkipped( 'Only works when PHP is compiled with the option "disable-ipv6".' );
		}

		$this->expectException( RuntimeException::class );
		IpUtils::check_ip( '2a01:198:603:0:396e:4789:8e99:890f', '2a01:198:603:0::/65' );
	}
}
