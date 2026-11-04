<?php

declare(strict_types=1);

namespace Automattic\VIP\Telemetry;

use Automattic\Test\Constant_Mocker;
use WP_UnitTestCase;

use function Automattic\VIP\Telemetry\Tracks\get_hosting_provider;
use function Automattic\VIP\Telemetry\Tracks\is_wpvip_site;
use function Automattic\VIP\Telemetry\Tracks\get_base_properties_of_track_event;
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

	public function test_base_properties_of_track_event(): void {
		$mu_plugins_dir = get_temp_dir() . 'vip-mu-plugins-version-test';
		wp_mkdir_p( $mu_plugins_dir );
		file_put_contents( $mu_plugins_dir . '/.version', "20261104.0\n" ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents -- Local fixture.
		Constant_Mocker::define( 'WPVIP_MU_PLUGIN_DIR', $mu_plugins_dir );
		Constant_Mocker::define( 'WP_ENVIRONMENT_TYPE', 'staging' );
		Constant_Mocker::define( 'VIP_GO_APP_ENVIRONMENT', 'preprod' );
		Constant_Mocker::define( 'VIP_ORG_ID', 17 );
		wp_set_current_user( 1 );

		try {
			$this->assertEquals( [
				'environment_type'   => 'staging',
				'hosting_provider'   => 'other',
				'is_vip_user'        => false,
				'is_multisite'       => is_multisite(),
				'mu_plugins_version' => '20261104.0',
				'wp_version'         => get_bloginfo( 'version' ),
				'vip_env'            => 'preprod',
				'vip_org'            => 17,
			], get_base_properties_of_track_event() );
		} finally {
			unlink( $mu_plugins_dir . '/.version' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink -- Local fixture.
			rmdir( $mu_plugins_dir ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir -- Local fixture.
		}
	}
}
