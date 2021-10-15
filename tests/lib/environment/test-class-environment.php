<?php

namespace Automattic\VIP;

class Environment_Test extends \PHPUnit_Framework_TestCase {
	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once __DIR__ . '/../../../lib/environment/class-environment.php';
	}

	public function get_var_standard_env() {
		define( 'VIP_ENV_VAR_MY_VAR', 'VIP_ENV_VAR_MY_VAR' );
	}

	public function get_var_legacy_env() {
		define( 'MY_VAR', 'MY_VAR' );
	}

	// tests the use-case where $key parameter is not found
	public function test_get_default_var() {
		\PHPUnit_Framework_Error_Notice::$enabled = false;

		$val = Environment::get_var( 'MY_VAR', 'default_value' );
		$this->assertEquals( $val, 'default_value' );
	}

	// tests the use-case where $key parameter does not have the prefix
	public function test_get_var_legacy_key() {
		\PHPUnit_Framework_Error_Notice::$enabled = false;

		$this->get_var_legacy_env();
		$val = Environment::get_var( 'MY_VAR', 'default_value' );
		$this->assertEquals( $val, 'MY_VAR' );
	}

	// tests the use-case where $key parameter is lower case
	public function test_get_var_lower_key() {
		\PHPUnit_Framework_Error_Notice::$enabled = false;

		$this->get_var_standard_env();
		$val = Environment::get_var( 'vip_env_var_my_var', 'default_value' );
		$this->assertEquals( $val, 'VIP_ENV_VAR_MY_VAR' );
	}

	// tests the use-case where $key parameter is ''
	public function test_get_var_empty_key() {
		\PHPUnit_Framework_Error_Notice::$enabled = false;

		$this->get_var_standard_env();
		$val = Environment::get_var( '', 'default_value' );
		$this->assertEquals( $val, 'default_value' );
	}

	public function test_get_var() {
		\PHPUnit_Framework_Error_Notice::$enabled = false;

		$this->get_var_standard_env();
		$val = Environment::get_var( 'MY_VAR', 'default_value' );
		$this->assertEquals( $val, 'VIP_ENV_VAR_MY_VAR' );
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
