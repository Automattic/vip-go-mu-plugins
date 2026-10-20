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
	public function data_hosting(): array {
		return [
			'no constants'           => [ [], false, false, 'other' ],
			'non-VIP hosting'        => [ [ 'WPCOM_IS_VIP_ENV' => false ], false, false, 'other' ],
			'not sandboxed'          => [ [ 'WPCOM_SANDBOXED' => false ], false, false, 'other' ],
			'VIP hosting'            => [
				[
					'WPCOM_IS_VIP_ENV' => true,
					'WPCOM_SANDBOXED'  => false,
				],
				true,
				false,
				'wpvip',
			],
			'sandbox'                => [ [ 'WPCOM_SANDBOXED' => true ], false, true, 'wpvip_sandbox' ],
			'VIP hosting, sandboxed' => [
				[
					'WPCOM_IS_VIP_ENV' => true,
					'WPCOM_SANDBOXED'  => true,
				],
				false,
				true,
				'wpvip_sandbox',
			],
		];
	}

	/**
	 * @dataProvider data_hosting
	 */
	public function test_hosting_detection( array $constants, bool $is_site, bool $is_sandbox, string $hosting_provider ): void {
		foreach ( $constants as $name => $value ) {
			Constant_Mocker::define( $name, $value );
		}

		$this->assertSame( $is_site, is_wpvip_site() );
		$this->assertSame( $is_sandbox, is_wpvip_sandbox() );
		$this->assertSame( $hosting_provider, get_hosting_provider() );
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
