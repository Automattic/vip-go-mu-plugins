<?php

namespace Automattic\VIP\Tests;

use function Automattic\VIP\Proxy\is_valid_ip;
use function Automattic\VIP\Proxy\set_remote_address;
use function Automattic\VIP\Proxy\fix_remote_address;
use function Automattic\VIP\Proxy\fix_remote_address_from_ip_trail;
use function Automattic\VIP\Proxy\fix_remote_address_with_verification_key;
use function Automattic\VIP\Proxy\get_proxy_verification_key;
use function Automattic\VIP\Proxy\get_ip_addresses_from_ip_trail;
use function Automattic\VIP\Proxy\is_valid_proxy_verification_key;
use function Automattic\VIP\Proxy\fix_remote_address_from_ip_trail_with_verification_key;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
// phpcs:disable PEAR.NamingConventions.ValidClassName.Invalid

abstract class IP_Forward_Test_Base extends TestCase {
	const DEFAULT_REMOTE_ADDR = '1.0.1.0';

	public function setUp(): void {
		$this->original_remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : null;
		$this->original_x_forwarded_for = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : null;

		$_SERVER['REMOTE_ADDR'] = self::DEFAULT_REMOTE_ADDR;
	}

	public function tearDown(): void {
		if ( $this->original_remote_addr ) {
			$_SERVER['REMOTE_ADDR'] = $this->original_remote_addr;
		}

		if ( $this->original_x_forwarded_for ) {
			$_SERVER['HTTP_X_FORWARDED_FOR'] = $this->original_x_forwarded_for;
		}
	}
}

class IP_Forward__Get_Ip_Addresses_From_Ip_Trail__Tests extends IP_Forward_Test_Base {

	// fix_remote_address_from_ip_trail
	public function test__get_ip_addresses_from_ip_trail__no_forwarded_for() {
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		$ip_trail = '1.2.3.4, 5.6.7.8';

		$result = get_ip_addresses_from_ip_trail( $ip_trail );

		$this->assertFalse( $result );
	}

	public function test__get_ip_addresses_from_ip_trail__ip_trail_has_lt_2_ips() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail = '1.2.3.4';

		$result = get_ip_addresses_from_ip_trail( $ip_trail );

		$this->assertFalse( $result );
	}

	public function test__get_ip_addresses_from_ip_trail__ip_trail_has_gt_2_ips() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail = '1.2.3.4, 9.0.21.0, 5.6.7.8';

		$result = get_ip_addresses_from_ip_trail( $ip_trail );

		$this->assertFalse( $result );
	}

	public function test__get_ip_addresses_from_ip_trail__proxy_doesnt_match_forwarded_for() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.5.5.5';
		$ip_trail = '1.2.3.4, 5.6.7.8';

		$result = get_ip_addresses_from_ip_trail( $ip_trail );

		$this->assertFalse( $result );
	}

	public function test__fix_remote_address_from_ip_trail__invalid_remote_ip() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail = '1.2.3.4, 123456789';

		$result = get_ip_addresses_from_ip_trail( $ip_trail );

		$this->assertFalse( $result );
	}

	public function test__fix_remote_address_from_ip_trail__invalid_user_ip() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail = 'bad_ip, 5.6.7.8';

		$result = get_ip_addresses_from_ip_trail( $ip_trail );

		$this->assertFalse( $result );
	}

	public function test__fix_remote_address_from_ip_trail__valid_ip_trail_ipv4() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail = '1.2.3.4, 5.6.7.8';
		$expected_ip_addresses = [ '1.2.3.4', '5.6.7.8' ];

		$result = get_ip_addresses_from_ip_trail( $ip_trail );

		$this->assertEquals( $expected_ip_addresses, $result );
	}

	public function test__fix_remote_address_from_ip_trail__valid_ip_trail_ipv6() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail = '2001:db8::1234:ace:6006:1e, 5.6.7.8';
		$expected_ip_addresses = [ '2001:db8::1234:ace:6006:1e', '5.6.7.8' ];

		$result = get_ip_addresses_from_ip_trail( $ip_trail );

		$this->assertEquals( $expected_ip_addresses, $result );
	}
}


class IP_Forward_Tests extends IP_Forward_Test_Base {

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

	// set_remote_address
	public function test__set_remote_address() {
		$user_ip = '5.6.7.8';

		set_remote_address( $user_ip );

		$this->assertEquals( $user_ip, $_SERVER['REMOTE_ADDR'] );
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

	public function test__fix_remote_address_from_ip_trail__ip_not_in_whitelist() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail = '1.2.3.4, 5.6.7.8';
		$whitelist = [ '0.0.0.0' ];

		$result = fix_remote_address_from_ip_trail( $ip_trail, $whitelist );

		$this->assertFalse( $result );
		$this->assertEquals( self::DEFAULT_REMOTE_ADDR, $_SERVER['REMOTE_ADDR'] );
	}

	public function test__fix_remote_address_from_ip_trail__ip_in_whitelist() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail = '1.2.3.4, 5.6.7.8';
		$whitelist = [ '5.6.7.8' ];

		$result = fix_remote_address_from_ip_trail( $ip_trail, $whitelist );

		$this->assertTrue( $result );
		$this->assertEquals( '1.2.3.4', $_SERVER['REMOTE_ADDR'] );
	}
}

class IP_Forward__Get_Proxy_Verification_Key__Test extends \PHPUnit_Framework_TestCase {
	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__not_defined() {
		// not defining the key

		$actual_key = get_proxy_verification_key();

		$this->assertNotEmpty( $actual_key, 'The Proxy Verification Key is empty' );
		$this->assertTrue( is_string( $actual_key ), 'The Proxy Verification Key is not a string' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__defined_but_empty() {
		define( 'WPCOM_VIP_PROXY_VERIFICATION', '' );

		$actual_key = get_proxy_verification_key();

		$this->assertNotEmpty( $actual_key, 'The Proxy Verification Key is empty' );
		$this->assertTrue( is_string( $actual_key ), 'The Proxy Verification Key is not a string' );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__defined_but_integer() {
		define( 'WPCOM_VIP_PROXY_VERIFICATION', 1234 );

		$actual_key = get_proxy_verification_key();

		$this->assertTrue( is_string( $actual_key ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__defined() {
		$expected_key = 'secretkey';
		define( 'WPCOM_VIP_PROXY_VERIFICATION', $expected_key );

		$actual_key = get_proxy_verification_key();

		$this->assertEquals( $expected_key, $actual_key );
	}
}

class IP_Forward__Is_Valid_Proxy_Verification_Key__Test extends \PHPUnit_Framework_TestCase {
	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__invalid_key() {
		define( 'WPCOM_VIP_PROXY_VERIFICATION', 'valid-key' );
		$key = 'not-a-valid-key';

		$result = is_valid_proxy_verification_key( $key );

		$this->assertFalse( $result );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__valid_key() {
		define( 'WPCOM_VIP_PROXY_VERIFICATION', 'valid-key' );
		$key = 'valid-key';

		$result = is_valid_proxy_verification_key( $key );

		$this->assertTrue( $result );
	}
}

class IP_Forward__Fix_Remote_Address_With_Verification_Key__Test extends TestCase {
	const DEFAULT_REMOTE_ADDR = '1.0.1.0';

	public function setUp(): void {
		$this->original_remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : null;
		$this->original_x_forwarded_for = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : null;

		$_SERVER['REMOTE_ADDR'] = self::DEFAULT_REMOTE_ADDR;
	}

	public function tearDown(): void {
		if ( $this->original_remote_addr ) {
			$_SERVER['REMOTE_ADDR'] = $this->original_remote_addr;
		}

		if ( $this->original_x_forwarded_for ) {
			$_SERVER['HTTP_X_FORWARDED_FOR'] = $this->original_x_forwarded_for;
		}
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__invalid_ip() {
		define( 'WPCOM_VIP_PROXY_VERIFICATION', 'valid-key' );
		$key = 'valid-key';
		$user_ip = 'bad_ip';

		$result = fix_remote_address_with_verification_key( $user_ip, $key );

		$this->assertFalse( $result );
		$this->assertEquals( self::DEFAULT_REMOTE_ADDR, $_SERVER['REMOTE_ADDR'] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__invalid_key() {
		define( 'WPCOM_VIP_PROXY_VERIFICATION', 'valid-key' );
		$key = 'not-a-valid-key';
		$user_ip = '5.6.7.8';

		$result = fix_remote_address_with_verification_key( $user_ip, $key );

		$this->assertFalse( $result );
		$this->assertEquals( self::DEFAULT_REMOTE_ADDR, $_SERVER['REMOTE_ADDR'] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__all_valid() {
		define( 'WPCOM_VIP_PROXY_VERIFICATION', 'valid-key' );
		$key = 'valid-key';
		$user_ip = '5.6.7.8';

		$result = fix_remote_address_with_verification_key( $user_ip, $key );

		$this->assertTrue( $result );
		$this->assertEquals( '5.6.7.8', $_SERVER['REMOTE_ADDR'] );
	}
}

class IP_Forward__Fix_Remote_Address_From_Ip_Trail_With_Verification_Key__Test extends IP_Forward_Test_Base {
	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__all_valid() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail = '1.2.3.4, 5.6.7.8';
		define( 'WPCOM_VIP_PROXY_VERIFICATION', 'valid-key' );
		$key = 'valid-key';

		$result = fix_remote_address_from_ip_trail_with_verification_key( $ip_trail, $key );

		$this->assertTrue( $result );
		$this->assertEquals( '1.2.3.4', $_SERVER['REMOTE_ADDR'] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__invalid_key() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail = '1.2.3.4, 5.6.7.8';
		define( 'WPCOM_VIP_PROXY_VERIFICATION', 'valid-key' );
		$key = 'invalid-key';

		$result = fix_remote_address_from_ip_trail_with_verification_key( $ip_trail, $key );

		$this->assertFalse( $result );
		$this->assertEquals( self::DEFAULT_REMOTE_ADDR, $_SERVER['REMOTE_ADDR'] );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__invalid_ip_trail() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '5.6.7.8';
		$ip_trail = '1.2.3.4, 5.6.7.eight';
		define( 'WPCOM_VIP_PROXY_VERIFICATION', 'valid-key' );
		$key = 'valid-key';

		$result = fix_remote_address_from_ip_trail_with_verification_key( $ip_trail, $key );

		$this->assertFalse( $result );
		$this->assertEquals( self::DEFAULT_REMOTE_ADDR, $_SERVER['REMOTE_ADDR'] );
	}
}

