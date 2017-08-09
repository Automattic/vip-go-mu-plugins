<?php

namespace Automattic\VIP\Tests;

/**
 * Tests for the wpcom_vip_get_client_mu_plugins()
 *
 * We're using test fixtures so we don't have to muck around with a hard-coded client-mu-plugins path.
 */
class Client_Mu_Plugins__Get__Tests extends \WP_UnitTestCase {
	public function test__invalid_dir() {
		$dir = __DIR__ . '/fixtures/client-mu-plugins/this-doesnt-exist/';

		$actual = wpcom_vip_get_client_mu_plugins( $dir );

		$this->assertEmpty( $actual );
	}

	public function test__empty() {
		$dir = __DIR__ . '/fixtures/client-mu-plugins/empty/';

		$actual = wpcom_vip_get_client_mu_plugins( $dir );

		$this->assertEmpty( $actual );
	}

	public function test__valid() {
		$dir = __DIR__ . '/fixtures/client-mu-plugins/valid';
		$expected = [
			$dir . '/0-first-plugin.php',
			$dir . '/index.php',
			// not-php.txt is stripped out
			$dir . '/with-headers.php',
			$dir . '/z-last-plugin.php',
		];

		$actual = wpcom_vip_get_client_mu_plugins( $dir );

		$this->assertEquals( $expected, $actual );
	}
}

/**
 * Tests for the wpcom_vip_get_client_mu_plugins_data()
 *
 * We're using test fixtures so we don't have to muck around with a hard-coded client-mu-plugins path.
 */
class Client_Mu_Plugins__Get_Data__Tests extends \WP_UnitTestCase {
	function test__empty() {
		$dir = __DIR__ . '/fixtures/client-mu-plugins/empty/';

		$actual = wpcom_vip_get_client_mu_plugins_data( $dir );

		$this->assertEmpty( $actual );
	}

	function test__valid() {
		$dir = __DIR__ . '/fixtures/client-mu-plugins/valid/';

		$actual = wpcom_vip_get_client_mu_plugins_data( $dir );

		$expected_keys = [
			'0-first-plugin.php',
			// index.php is stripped out
			// not-php.txt is stripped out
			'with-headers.php',
			'z-last-plugin.php'
		];
		$this->assertEquals( $expected_keys, array_keys( $actual ), 'Returned array keys for plugins don\'t match expected values' );

		// Plugin without headers
		$this->assertEquals( '0-first-plugin.php', $actual['0-first-plugin.php']['Name'], 'Name of plugin without headers was not the filename' );

		// Plugin with headers
		$this->assertEquals( 'The Plugin', $actual['with-headers.php']['Name'], 'Name of plugin without headers was not the filename' );
	}
}
