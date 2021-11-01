<?php

namespace Automattic\VIP\Helpers;

require_once __DIR__ . '/../../../lib/helpers/environment.php';

use PHPUnit\Framework\TestCase;
use Yoast\PHPUnitPolyfills\Polyfills\ExpectPHPException;

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class Environment_Test extends TestCase {
	public function setUp(): void {
		$this->error_reporting = error_reporting();
	}

	public function tearDown(): void {
		error_reporting( $this->error_reporting );
		parent::tearDown();
	}

	public function get_var_standard_env() {
		define( 'VIP_ENV_VAR_MY_VAR', 'FOO' );
	}

	public function get_var_legacy_env() {
		define( 'MY_VAR', 'FOO' );
	}

	// tests the use-case where $key parameter is not found
	public function test_get_default_var() {
		error_reporting( $this->error_reporting & ~E_USER_NOTICE );

		$val = vip_get_env_var( 'MY_VAR', 'BAR' );
		$this->assertEquals( 'BAR', $val );
	}

	/**
	 * tests the use-case where $key parameter does not have the prefix
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_var_legacy_key() {
		error_reporting( $this->error_reporting & ~E_USER_NOTICE );

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
		error_reporting( $this->error_reporting & ~E_USER_NOTICE );

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
		error_reporting( $this->error_reporting & ~E_USER_NOTICE );

		$this->get_var_standard_env();
		$val = vip_get_env_var( '', 'BAR' );
		$this->assertEquals( 'BAR', $val );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_get_var() {
		error_reporting( $this->error_reporting & ~E_USER_NOTICE );

		$this->get_var_standard_env();
		$val = vip_get_env_var( 'MY_VAR', 'BAR' );
		$this->assertEquals( 'FOO', $val );
	}
}
