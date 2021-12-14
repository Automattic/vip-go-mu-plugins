<?php

namespace Automattic\VIP;

use Automattic\Test\Constant_Mocker;
use PHPUnit\Framework\TestCase;
use Yoast\PHPUnitPolyfills\Polyfills\ExpectPHPException;

require_once __DIR__ . '/../../../lib/environment/class-environment.php';

// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting

class Environment_Test extends TestCase {
	use ExpectPHPException;

	private $error_reporting;

	protected function setUp(): void {
		parent::setUp();
		$this->error_reporting = error_reporting();
		Constant_Mocker::clear();
	}

	protected function tearDown(): void {
		error_reporting( $this->error_reporting );
		parent::tearDown();
	}

	public function get_var_standard_env() {
		Constant_Mocker::define( 'VIP_ENV_VAR_MY_VAR', 'VIP_ENV_VAR_MY_VAR' );
	}

	public function get_var_legacy_env() {
		Constant_Mocker::define( 'MY_VAR', 'MY_VAR' );
	}

	// tests the use-case where $key parameter is not found
	public function test_get_default_var() {
		error_reporting( $this->error_reporting & ~E_USER_NOTICE );

		$val = Environment::get_var( 'MY_VAR', 'default_value' );
		$this->assertEquals( 'default_value', $val );
	}

	// tests the use-case where $key parameter does not have the prefix
	public function test_get_var_legacy_key() {
		error_reporting( $this->error_reporting & ~E_USER_NOTICE );

		$this->get_var_legacy_env();
		$val = Environment::get_var( 'MY_VAR', 'default_value' );
		$this->assertEquals( 'MY_VAR', $val );
	}

	// tests the use-case where $key parameter is lower case
	public function test_get_var_lower_key() {
		error_reporting( $this->error_reporting & ~E_USER_NOTICE );

		$this->get_var_standard_env();
		$val = Environment::get_var( 'vip_env_var_my_var', 'default_value' );
		$this->assertEquals( 'VIP_ENV_VAR_MY_VAR', $val );
	}

	// tests the use-case where $key parameter is ''
	public function test_get_var_empty_key() {
		error_reporting( $this->error_reporting & ~E_USER_NOTICE );

		$this->get_var_standard_env();
		$val = Environment::get_var( '', 'default_value' );
		$this->assertEquals( 'default_value', $val );
	}

	public function test_get_var() {
		error_reporting( $this->error_reporting & ~E_USER_NOTICE );

		$this->get_var_standard_env();
		$val = Environment::get_var( 'MY_VAR', 'default_value' );
		$this->assertEquals( 'VIP_ENV_VAR_MY_VAR', $val );
	}

	public function is_sandbox_container_data() {
		return array(
			// Non-sandbox hostname, no env vars
			array(
				// Hostname
				'foo',
				// Env vars
				array(),
				// Expected result
				false,
			),
			// Sandbox hostname, no env vars
			array(
				// Hostname
				'foo_web_dev_0001',
				// Env vars
				array(),
				// Expected result
				true,
			),
			// Non-sandbox hostname, has env var
			array(
				// Hostname
				'foo',
				// Env vars
				array(
					'IS_VIP_SANDBOX_CONTAINER' => 'true',
				),
				// Expected result
				true,
			),
			// Non-sandbox hostname, has env var with wrong value
			array(
				// Hostname
				'foo',
				// Env vars
				array(
					'IS_VIP_SANDBOX_CONTAINER' => 'not true',
				),
				// Expected result
				false,
			),
			// Sandbox hostname, has env var
			array(
				// Hostname
				'foo_web_dev_0001',
				// Env vars
				array(
					'IS_VIP_SANDBOX_CONTAINER' => 'true',
				),
				// Expected result
				true,
			),
		);
	}

	/**
	 * @dataProvider is_sandbox_container_data
	 */
	public function test_is_sandbox_container( $hostname, $env, $expected ) {
		$result = Environment::is_sandbox_container( $hostname, $env );

		$this->assertEquals( $expected, $result );
	}

	public function is_batch_container_data() {
		return array(
			// Non-batch hostname, no env vars
			array(
				// Hostname
				'foo',
				// Env vars
				array(),
				// Expected result
				false,
			),
			// Batch hostname, no env vars
			array(
				// Hostname
				'foo_wpcli_0001',
				// Env vars
				array(),
				// Expected result
				true,
			),
			// Batch hostname alternate, no env vars
			array(
				// Hostname
				'foo_wp_cli_0001',
				// Env vars
				array(),
				// Expected result
				true,
			),
			// Non-batch hostname, has env var
			array(
				// Hostname
				'foo',
				// Env vars
				array(
					'IS_VIP_BATCH_CONTAINER' => 'true',
				),
				// Expected result
				true,
			),
			// Non-batch hostname, has env var with wrong value
			array(
				// Hostname
				'foo',
				// Env vars
				array(
					'IS_VIP_BATCH_CONTAINER' => 'not true',
				),
				// Expected result
				false,
			),
			// Batch hostname, has env var
			array(
				// Hostname
				'foo_wpcli_0001',
				// Env vars
				array(
					'IS_VIP_BATCH_CONTAINER' => 'true',
				),
				// Expected result
				true,
			),
		);
	}

	/**
	 * @dataProvider is_batch_container_data
	 */
	public function test_is_batch_container( $hostname, $env, $expected ) {
		$result = Environment::is_batch_container( $hostname, $env );

		$this->assertEquals( $expected, $result );
	}
}
