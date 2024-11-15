<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use Automattic\Test\Constant_Mocker;
use WP_UnitTestCase;

use function Automattic\VIP\Telemetry\Tracks\get_hosting_provider;
use function Automattic\VIP\Telemetry\Tracks\is_wpvip_site;
use function Automattic\VIP\Telemetry\Tracks\get_tracks_core_properties;
use function Automattic\VIP\Telemetry\Tracks\is_wpvip_sandbox;

class Tracks_Utils_Test extends WP_UnitTestCase {
	public function tear_down() {
		parent::tear_down();
		Constant_Mocker::clear();
	}

	public function test_is_wpvip_site_returns_false_on_non_VIP_hosting(): void {
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', false );

		$this->assertEquals( false, is_wpvip_site() );
	}

	public function test_is_wpvip_site_returns_false_on_sandbox(): void {
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );
		Constant_Mocker::define( 'WPCOM_SANDBOXED', true );

		$this->assertEquals( false, is_wpvip_site() );
	}

	public function test_is_wpvip_site_returns_true(): void {
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );
		Constant_Mocker::define( 'WPCOM_SANDBOXED', false );

		$this->assertEquals( true, is_wpvip_site() );
	}

	public function test_is_wpvip_sandbox_returns_true(): void {
		Constant_Mocker::define( 'WPCOM_SANDBOXED', true );

		$this->assertEquals( true, is_wpvip_sandbox() );
	}

	public function test_is_wpvip_sandbox_returns_false(): void {
		Constant_Mocker::define( 'WPCOM_SANDBOXED', false );

		$this->assertEquals( false, is_wpvip_sandbox() );
	}

	public function test_get_hosting_provider_returns_wpvip_on_VIP_hosting(): void {
		Constant_Mocker::define( 'WPCOM_IS_VIP_ENV', true );
		Constant_Mocker::define( 'WPCOM_SANDBOXED', false );

		$this->assertEquals( 'wpvip', get_hosting_provider() );
	}

	public function test_get_hosting_provider_returns_wpvip_sandbox_on_sandbox(): void {
		Constant_Mocker::define( 'WPCOM_SANDBOXED', true );

		$this->assertEquals( 'wpvip_sandbox', get_hosting_provider() );
	}

	public function test_get_hosting_provider_returns_other_on_non_VIP_hosting(): void {
		$this->assertEquals( 'other', get_hosting_provider() );
	}

	public function test_track_core_properties(): void {
		wp_set_current_user( 1 );
		$output = get_tracks_core_properties();

		$props = [
			'hosting_provider' => 'other',
			'is_vip_user'      => false,
			'is_multisite'     => is_multisite(),
			'wp_version'       => get_bloginfo( 'version' ),
			'_ut'              => 'anon',
			'_ui'              => wp_hash( sprintf( '%s|%s', get_option( 'home' ), 1 ) ),
		];
		$this->assertEquals( $props, $output );
	}
}
