<?php

namespace Automattic\VIP;

class Environment_Test extends \PHPUnit_Framework_TestCase {
	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once( __DIR__ . '/../../../lib/environment/class-environment.php' );
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
