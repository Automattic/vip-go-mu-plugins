<?php

namespace Automattic\VIP\Helpers;

require_once __DIR__ . '/../../../lib/helpers/environment.php';

use Automattic\Test\Constant_Mocker;
use PHPUnit\Framework\TestCase;

/**
 * The wrapped Environment methods are covered by Automattic\VIP\Environment_Test.
 */
class Environment_Test extends TestCase {
	public function test_wrappers_delegate_to_environment() {
		Constant_Mocker::define( 'VIP_ENV_VAR_MY_VAR', 'FOO' );

		$this->assertTrue( vip_has_env_var( 'MY_VAR' ) );
		$this->assertSame( 'FOO', vip_get_env_var( 'MY_VAR', 'BAR' ) );
		$this->assertFalse( vip_has_env_var( 'MISSING_ENV_VAR' ) );
		$this->assertSame( 'BAR', vip_get_env_var( 'MISSING_ENV_VAR', 'BAR' ) );
	}
}
