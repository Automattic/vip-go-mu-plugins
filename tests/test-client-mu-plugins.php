<?php

namespace Automattic\VIP\Tests;

use WP_UnitTestCase;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
// phpcs:disable PEAR.NamingConventions.ValidClassName.Invalid

/**
 * Tests for the wpcom_vip_get_client_mu_plugins()
 *
 * We're using test fixtures so we don't have to muck around with a hard-coded client-mu-plugins path.
 */
class Client_Mu_Plugins__Get__Tests extends WP_UnitTestCase {
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
		$dir      = __DIR__ . '/fixtures/client-mu-plugins/valid';
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
class Client_Mu_Plugins__Get_Data__Tests extends WP_UnitTestCase {
	public function test__empty() {
		$dir = __DIR__ . '/fixtures/client-mu-plugins/empty/';

		$actual = wpcom_vip_get_client_mu_plugins_data( $dir );

		$this->assertEmpty( $actual );
	}

	public function test__valid() {
		$dir = __DIR__ . '/fixtures/client-mu-plugins/valid/';

		$actual = wpcom_vip_get_client_mu_plugins_data( $dir );

		$expected_keys = [
			'0-first-plugin.php',
			// index.php is stripped out
			// not-php.txt is stripped out
			'with-headers.php',
			'z-last-plugin.php',
		];
		$this->assertEquals( $expected_keys, array_keys( $actual ), 'Returned array keys for plugins don\'t match expected values' );

		// Plugin without headers
		$this->assertEquals( '0-first-plugin.php', $actual['0-first-plugin.php']['Name'], 'Name of plugin without headers was not the filename' );

		// Plugin with headers
		$this->assertEquals( 'The Plugin', $actual['with-headers.php']['Name'], 'Name of plugin without headers was not the filename' );
	}
}

/**
 * Tests for `plugins_url` override
 */
class Client_Mu_Plugins__Plugins_Url__Tests extends WP_UnitTestCase {
	public function get_test_data() {
		return [
			'not-client-mu-plugins-path'     => [
				'script.js',
				WP_PLUGIN_DIR . '/file.php',
				WP_CONTENT_URL . '/plugins/script.js',
			],

			'client-mu-plugins-path_root'    => [
				'script.js',
				WPCOM_VIP_CLIENT_MU_PLUGIN_DIR . '/file.php',
				WP_CONTENT_URL . '/client-mu-plugins/script.js',
			],

			'client-mu-plugins-path_subpath' => [
				'script.js',
				WPCOM_VIP_CLIENT_MU_PLUGIN_DIR . '/plugin/file.php',
				WP_CONTENT_URL . '/client-mu-plugins/plugin/script.js',
			],

			// No double-slash for url-path
			'client-mu-plugins-path_with_leading-slash-in-url' => [
				'/script.js',
				WPCOM_VIP_CLIENT_MU_PLUGIN_DIR . '/plugin/file.php',
				WP_CONTENT_URL . '/client-mu-plugins/plugin/script.js',
			],
		];
	}

	/**
	 * @dataProvider get_test_data
	 */
	public function test__client_mu_plugins_url( $url_path, $plugin_path, $expected_url ) {
		$actual_url = plugins_url( $url_path, $plugin_path );

		$this->assertEquals( $expected_url, $actual_url );
	}
}
