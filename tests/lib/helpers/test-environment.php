<?php

namespace Automattic\VIP\Helpers;

require_once __DIR__ . '/../../../lib/helpers/environment.php';

use Automattic\Test\Constant_Mocker;
use PHPUnit\Framework\TestCase;
use Yoast\PHPUnitPolyfills\Polyfills\ExpectPHPException;

class Environment_Test extends TestCase {
	use ExpectPHPException;

	public function setUp(): void {
		parent::setUp();
		Constant_Mocker::clear();
	}

	public function get_var_standard_env() {
		Constant_Mocker::define( 'VIP_ENV_VAR_MY_VAR', 'FOO' );
	}

	public function get_var_legacy_env() {
		Constant_Mocker::define( 'MY_VAR', 'FOO' );
	}

	// tests the use-case that an environment has a defined env var
	public function test_has_var() {

		$this->get_var_standard_env();
		$val = vip_has_env_var( 'MY_VAR' );
		$this->assertEquals( true, $val );
	}

	// tests the use-case that an environment is missing a defined env var
	public function test_has_var_missing() {

		$val = vip_has_env_var( 'MISSING_ENV_VAR' );
		$this->assertEquals( false, $val );
	}

	// tests the use-case where $key parameter is not found
	public function test_get_default_var() {
		$this->expectNotice();

		$val = vip_get_env_var( 'MY_VAR', 'BAR' );
		$this->assertEquals( 'BAR', $val );
	}

	/**
	 * tests the use-case where $key parameter is ''
	 */
	public function test_get_var_empty_key() {
		$this->expectNotice();

		$this->get_var_standard_env();
		$val = vip_get_env_var( '', 'BAR' );
		$this->assertEquals( 'BAR', $val );
	}

	public function test_get_var() {
		$this->get_var_standard_env();
		$val = vip_get_env_var( 'MY_VAR', 'BAR' );
		$this->assertEquals( 'FOO', $val );
	}
}
