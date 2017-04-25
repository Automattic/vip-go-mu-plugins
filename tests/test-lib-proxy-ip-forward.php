<?php

namespace Automattic\VIP\Tests;

use function Automattic\VIP\Proxy\is_valid_ip;
use function Automattic\VIP\Proxy\fix_remote_address;
use function Automattic\VIP\Proxy\fix_remote_address_from_ip_trail;
use function Automattic\VIP\Proxy\fix_remote_address_with_verification_key;

class IP_Foward_Test extends \PHPUnit_Framework_TestCase {
	const DEFAULT_REMOTE_ADDR = '1.0.1.0';

	public function setUp() {
		$this->original_remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : null;
		$this->original_x_forwarded_for = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : null;

		$_SERVER['REMOTE_ADDR'] = self::DEFAULT_REMOTE_ADDR;
	}

	public function tearDown() {
		if ( $this->original_remote_addr ) {
			$_SERVER['REMOTE_ADDR'] = $this->original_remote_addr;
		}

		if ( $this->original_x_forwarded_for ) {
			$_SERVER['HTTP_X_FORWARDED_FOR'] = $this->original_x_forwarded_for;
		}
	}

	// is_valid_ip
	public function test__is_valid_ip__invalid() {
		$ip = 'bad_ip';

		$result = is_valid_ip( $ip );

		$this->assertFalse( $result );
	}

	public function test__is_valid_ip__valid_ip4() {
		$ip = '1.2.3.4';

		$result = is_valid_ip( $ip );

		$this->assertTrue( $result );
	}

	public function test__is_valid_ip__valid_ip6() {
		$ip = '2001:db8::1234:ace:6006:1e';

		$result = is_valid_ip( $ip );

		$this->assertTrue( $result );
	}

	// fix_remote_address
	public function test__fix_remote_address__invalid_user_ip() {
		$user_ip = 'bad_ip';
		$proxy_ip = '5.6.7.8';
		$whitelist = [ '5.6.7.8' ];

		$result = fix_remote_address( $user_ip, $proxy_ip, $whitelist );

		$this->assertFalse( $result );
		$this->assertEquals( self::DEFAULT_REMOTE_ADDR, $_SERVER['REMOTE_ADDR'] );
	}

	public function test__fix_remote_address__ip_not_in_whitelist() {
		$user_ip = '1.2.3.4';
		$proxy_ip = '5.6.7.8';
		$whitelist = [ '0.0.0.0' ];

		$result = fix_remote_address( $user_ip, $proxy_ip, $whitelist );

		$this->assertFalse( $result );
		$this->assertEquals( self::DEFAULT_REMOTE_ADDR, $_SERVER['REMOTE_ADDR'] );
	}

	public function test__fix_remote_address__ip_in_whitelist_ipv4() {
		$user_ip = '1.2.3.4';
		$proxy_ip = '5.6.7.8';
		$whitelist = [ '5.6.7.8' ];

		$result = fix_remote_address( $user_ip, $proxy_ip, $whitelist );

		$this->assertTrue( $result );
		$this->assertEquals( '1.2.3.4', $_SERVER['REMOTE_ADDR'] );
	}

	public function test__fix_remote_address__ip_in_whitelist_ipv6() {
		$user_ip = '2001:db8::1234:ace:6006:1e';
		$proxy_ip = '5.6.7.8';
		$whitelist = [ '5.6.7.8' ];

		$result = fix_remote_address( $user_ip, $proxy_ip, $whitelist );

		$this->assertTrue( $result );
		$this->assertEquals( '2001:db8::1234:ace:6006:1e', $_SERVER['REMOTE_ADDR'] );
	}

	// fix_remote_address_from_ip_trail
	public function test__fix_remote_address_from_ip_trail__no_forwarded_for() {
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		$ip_trail = '1.2.3.4, 5.6.7.8';
		$whitelist = [ '5.6.7.8' ];

		$result = fix_remote_address_from_ip_trail( $ip_trail, $whitelist );

		$this->assertFalse( $result );
		$this->assertEquals( self::DEFAULT_REMOTE_ADDR, $_SERVER['REMOTE_ADDR'] );
	}

	public function test__fix_remote_address_from_ip_trail__ip_trail_has_lt_2_ips() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail = '1.2.3.4';
		$whitelist = [ '5.6.7.8' ];

		$result = fix_remote_address_from_ip_trail( $ip_trail, $whitelist );

		$this->assertFalse( $result );
		$this->assertEquals( self::DEFAULT_REMOTE_ADDR, $_SERVER['REMOTE_ADDR'] );
	}

	public function test__fix_remote_address_from_ip_trail__ip_trail_has_gt_2_ips() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail = '1.2.3.4, 9.0.21.0, 5.6.7.8';
		$whitelist = [ '5.6.7.8' ];

		$result = fix_remote_address_from_ip_trail( $ip_trail, $whitelist );

		$this->assertFalse( $result );
		$this->assertEquals( self::DEFAULT_REMOTE_ADDR, $_SERVER['REMOTE_ADDR'] );
	}

	public function test__fix_remote_address_from_ip_trail__proxy_doesnt_match_forwarded_for() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.5.5.5';
		$ip_trail = '1.2.3.4, 5.6.7.8';
		$whitelist = [ '0.0.0.0' ];

		$result = fix_remote_address_from_ip_trail( $ip_trail, $whitelist );

		$this->assertFalse( $result );
		$this->assertEquals( self::DEFAULT_REMOTE_ADDR, $_SERVER['REMOTE_ADDR'] );
	}

	public function test__fix_remote_address_from_ip_trail__invalid_remote_ip() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail = '1.2.3.4, 123456789';
		$whitelist = [ '5.6.7.8' ];

		$result = fix_remote_address_from_ip_trail( $ip_trail, $whitelist );

		$this->assertFalse( $result );
		$this->assertEquals( self::DEFAULT_REMOTE_ADDR, $_SERVER['REMOTE_ADDR'] );
	}

	public function test__fix_remote_address_from_ip_trail__invalid_user_ip() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail = 'bad_ip, 5.6.7.8';
		$whitelist = [ '5.6.7.8' ];

		$result = fix_remote_address_from_ip_trail( $ip_trail, $whitelist );

		$this->assertFalse( $result );
		$this->assertEquals( self::DEFAULT_REMOTE_ADDR, $_SERVER['REMOTE_ADDR'] );
	}

	public function test__fix_remote_address_from_ip_trail__ip_not_in_whitelist() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail = '1.2.3.4, 5.6.7.8';
		$whitelist = [ '0.0.0.0' ];

		$result = fix_remote_address_from_ip_trail( $ip_trail, $whitelist );

		$this->assertFalse( $result );
		$this->assertEquals( self::DEFAULT_REMOTE_ADDR, $_SERVER['REMOTE_ADDR'] );
	}

	public function test__fix_remote_address_from_ip_trail__ip_in_whitelist_ipv4() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail = '1.2.3.4, 5.6.7.8';
		$whitelist = [ '5.6.7.8' ];

		$result = fix_remote_address_from_ip_trail( $ip_trail, $whitelist );

		$this->assertTrue( $result );
		$this->assertEquals( '1.2.3.4', $_SERVER['REMOTE_ADDR'] );
	}

	public function test__fix_remote_address_from_ip_trail__ip_in_whitelist_ipv6() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail = '2001:db8::1234:ace:6006:1e, 5.6.7.8';
		$whitelist = [ '5.6.7.8' ];

		$result = fix_remote_address_from_ip_trail( $ip_trail, $whitelist );

		$this->assertTrue( $result );
		$this->assertEquals( '2001:db8::1234:ace:6006:1e', $_SERVER['REMOTE_ADDR'] );
	}

	// fix_remote_address_with_verification_key
	// TODO: invalid IP
	// TODO: invalid key
	/*
	public function test__fix_with_verification_key__valid_ip4() {
		$key = 'valid-key';
		$user_ip = '5.6.7.8';

		$result = fix_remote_address_with_verification_key( $user_ip, $key );

		$this->assertTrue( $result );
		$this->assertEquals( '5.6.7.8', $_SERVER['REMOTE_ADDR'] );
	}
	*/
}
