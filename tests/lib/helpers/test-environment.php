<?php

namespace Automattic\VIP\Helpers;

require_once __DIR__ . '/../../../lib/helpers/environment.php';

use PHPUnit\Framework\TestCase;
use Yoast\PHPUnitPolyfills\Polyfills\ExpectPHPException;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class Environment_Test extends TestCase {
	use ExpectPHPException;

	public function get_var_standard_env() {
		define( 'VIP_ENV_VAR_MY_VAR', 'FOO' );
	}

	public function get_var_legacy_env() {
		define( 'MY_VAR', 'FOO' );
	}

	// tests the use-case where $key parameter is not found
	public function test_get_default_var() {
		$this->expectNotice();

		$val = vip_get_env_var( 'MY_VAR', 'BAR' );
		$this->assertEquals( 'BAR', $val );
	}

	/**
	 * tests the use-case where $key parameter does not have the prefix
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_var_legacy_key() {

		$this->get_var_legacy_env();
		$val = vip_get_env_var( 'MY_VAR', 'BAR' );
		$this->assertEquals( 'FOO', $val );
	}

	/**
	 * tests the use-case where $key parameter is lower case
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_var_lower_key() {

		$this->get_var_standard_env();
		$val = vip_get_env_var( 'vip_env_var_my_var', 'BAR' );
		$this->assertEquals( 'FOO', $val );
	}

	/**
	 * tests the use-case where $key parameter is ''
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_var_empty_key() {
		$this->expectNotice();

		$this->get_var_standard_env();
		$val = vip_get_env_var( '', 'BAR' );
		$this->assertEquals( 'BAR', $val );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_var() {

		$this->get_var_standard_env();
		$val = vip_get_env_var( 'MY_VAR', 'BAR' );
		$this->assertEquals( 'FOO', $val );
	}
}
