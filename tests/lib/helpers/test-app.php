<?php

namespace Automattic\VIP\Helpers;

require_once __DIR__ . '/../../../lib/helpers/app.php';

use Automattic\Test\Constant_Mocker;
use PHPUnit\Framework\TestCase;

/**
 * The wrapped Context getters are covered by Automattic\VIP\Utils\Context_Test.
 */
class App_Test extends TestCase {
	public function test__wpvip_get_app_name_and_environment__delegate_to_context() {
		Constant_Mocker::define( 'VIP_GO_APP_SLUG', 'example-app' );
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'production' );

		$this->assertSame( 'example-app', wpvip_get_app_name() );
		$this->assertSame( 'production', wpvip_get_app_environment() );
	}

	public function get_test_data__wpvip_get_app_alias__incomplete() {
		return [
			'slug not defined'        => [ [ 'VIP_GO_APP_ENVIRONMENT' => 'develop' ] ],
			'environment not defined' => [ [ 'VIP_GO_APP_SLUG' => 'example-app' ] ],
			'both not defined'        => [ [] ],
		];
	}

	/**
	 * @dataProvider get_test_data__wpvip_get_app_alias__incomplete
	 */
	public function test__wpvip_get_app_alias__incomplete( array $constants ) {
		foreach ( $constants as $name => $value ) {
			Constant_Mocker::define( $name, $value );
		}

		$this->assertSame( '', wpvip_get_app_alias() );
	}

	public function test__wpvip_get_app_alias__returns_value() {
		Constant_Mocker::define( 'VIP_GO_APP_SLUG', 'example-app' );
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'develop' );

		$actual_result = wpvip_get_app_alias();

		$this->assertSame( 'example-app.develop', $actual_result );
	}
}
