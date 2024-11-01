<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use Automattic\Test\Constant_Mocker;
use WP_UnitTestCase;

use function Automattic\VIP\Telemetry\Tracks\is_wpvip_site;

class Tracks_Utils_Test extends WP_UnitTestCase {
	public function tear_down() {
		parent::tear_down();
		Constant_Mocker::clear();
	}

	public function test_is_wip_returns_false_on_non_VIP_hosting(): void {
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', false );

		$this->assertEquals( false, is_wpvip_site() );
	}

	public function test_is_wip_returns_false_on_sandbox(): void {
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );
		Constant_Mocker::define( 'WPCOM_SANDBOXED', true );

		$this->assertEquals( false, is_wpvip_site() );
	}

	public function test_is_wip_returns_true(): void {
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );
		Constant_Mocker::define( 'WPCOM_SANDBOXED', false );

		$this->assertEquals( true, is_wpvip_site() );
	}
}
