<?php

namespace Automattic\VIP\Helpers;

require_once __DIR__ . '/../../../lib/helpers/app.php';

use Automattic\Test\Constant_Mocker;
use PHPUnit\Framework\TestCase;

class App_Test extends TestCase {
	public function setUp(): void {
		parent::setUp();
		Constant_Mocker::clear();
	}

	public function tearDown(): void {
		Constant_Mocker::clear();
		parent::tearDown();
	}

	public function test__wpvip_get_app_name__not_defined() {
		$actual_result = wpvip_get_app_name();

		$this->assertSame( '', $actual_result );
	}

	public function test__wpvip_get_app_name__returns_value() {
		Constant_Mocker::define( 'VIP_GO_APP_SLUG', 'example-app' );

		$actual_result = wpvip_get_app_name();

		$this->assertSame( 'example-app', $actual_result );
	}

	public function test__wpvip_get_app_environment__not_defined() {
		$actual_result = wpvip_get_app_environment();

		$this->assertSame( '', $actual_result );
	}

	public function test__wpvip_get_app_environment__returns_value() {
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );

		$actual_result = wpvip_get_app_environment();

		$this->assertSame( 'production', $actual_result );
	}

	public function test__wpvip_get_app_alias__slug_not_defined() {
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'develop' );

		$actual_result = wpvip_get_app_alias();

		$this->assertSame( '', $actual_result );
	}

	public function test__wpvip_get_app_alias__environment_not_defined() {
		Constant_Mocker::define( 'VIP_GO_APP_SLUG', 'example-app' );

		$actual_result = wpvip_get_app_alias();

		$this->assertSame( '', $actual_result );
	}

	public function test__wpvip_get_app_alias__both_not_defined() {
		$actual_result = wpvip_get_app_alias();

		$this->assertSame( '', $actual_result );
	}

	public function test__wpvip_get_app_alias__returns_value() {
		Constant_Mocker::define( 'VIP_GO_APP_SLUG', 'example-app' );
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'develop' );

		$actual_result = wpvip_get_app_alias();

		$this->assertSame( 'example-app.develop', $actual_result );
	}

	public function test__wpvip_get_app_alias__production_environment() {
		Constant_Mocker::define( 'VIP_GO_APP_SLUG', 'my-production-site' );
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );

		$actual_result = wpvip_get_app_alias();

		$this->assertSame( 'my-production-site.production', $actual_result );
	}
}
