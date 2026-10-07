<?php

use SebastianBergmann\RecursionContext\InvalidArgumentException;
use PHPUnit\Framework\ExpectationFailedException;

class VIP_Go_Jetpack_Test extends WP_UnitTestCase {
	public function get_jp_sync_settings_data() {
		// In-range values are numeric strings, as they are when read back from the database.
		return [
			'queue size below min'   => [ 'jetpack_sync_settings_max_queue_size', 1, 10000 ],
			'queue size in range'    => [ 'jetpack_sync_settings_max_queue_size', '30000', 30000 ],
			'queue size above max'   => [ 'jetpack_sync_settings_max_queue_size', 10000000, 100000 ],
			'queue size non-numeric' => [ 'jetpack_sync_settings_max_queue_size', 'apples', 10000 ],
			'queue lag below min'    => [ 'jetpack_sync_settings_max_queue_lag', 1, 7200 ],
			'queue lag in range'     => [ 'jetpack_sync_settings_max_queue_lag', '15000', 15000 ],
			'queue lag above max'    => [ 'jetpack_sync_settings_max_queue_lag', 10000000, 86400 ],
			'queue lag non-numeric'  => [ 'jetpack_sync_settings_max_queue_lag', 'apples', 7200 ],
		];
	}

	/**
	 * @dataProvider get_jp_sync_settings_data
	 */
	public function test__jp_queue_settings_filters( $option, $value, $expected ) {
		update_option( $option, $value );

		$result = get_option( $option );

		$this->assertSame( $expected, $result );
	}

	public function get_jetpack_sync_modules_data() {
		return [
			'enabled-no-matching-modules'   => [
				[
					'sync' => 'Other_Sync_Class',
				],
				[
					'sync' => 'Other_Sync_Class',
				],
			],

			'enabled-with-matching-modules' => [
				[
					'sync'      => 'Jetpack_Sync_Modules_Full_Sync',
					'also-sync' => 'Automattic\\Jetpack\\Sync\\Modules\\Full_Sync',
					'not-sync'  => 'Not_Sync_Class',
				],
				[
					'sync'      => 'Automattic\\Jetpack\\Sync\\Modules\\Full_Sync_Immediately',
					'also-sync' => 'Automattic\\Jetpack\\Sync\\Modules\\Full_Sync_Immediately',
					'not-sync'  => 'Not_Sync_Class',
				],
			],
		];
	}

	/**
	 * @dataProvider get_jetpack_sync_modules_data
	 */
	public function test__jetpack_sync_modules__class_exists( $modules, $expected_modules ) {
		require_once __DIR__ . '/fixtures/jetpack/class-jetpack-sync-immediately.php';

		$actual_modules = apply_filters( 'jetpack_sync_modules', $modules );

		$this->assertEquals( $expected_modules, $actual_modules );
	}

	public function test__jetpack_https_test__transient_filter() {
		$https_test         = apply_filters( 'pre_transient_jetpack_https_test', null );
		$https_test_message = apply_filters( 'pre_transient_jetpack_https_test_message', null );

		$this->assertEquals( 1, $https_test, 'Value of the jetpack_https_test pre-transient filter is incorrect' );
		$this->assertEquals( '', $https_test_message, 'Value of the jetpack_https_test_message pre-transient filter is incorrect' );
	}

	public function test__jetpack_options_fallback_no_verify_ssl_certs__filter() {
		if ( ! class_exists( 'Jetpack' ) ) {
			return self::markTestSkipped( 'Jetpack is required to run this test' );
		}

		// Make sure it doesn't already exist as 0
		\Jetpack_Options::delete_option( 'fallback_no_verify_ssl_certs' );

		$value = \Jetpack_Options::get_option( 'fallback_no_verify_ssl_certs' );

		$this->assertEquals( 0, $value, 'The fallback_no_verify_ssl_certs Jetpack option value is incorrect' );
	}

	/**
	 * @dataProvider data_vip_jetpack_is_mobile
	 */
	public function test_vip_jetpack_is_mobile( ?string $x_mobile_class, string $kind, bool $return_matched_agent, $matches, $expected ): void {
		if ( null !== $x_mobile_class ) {
			$_SERVER['HTTP_X_MOBILE_CLASS'] = $x_mobile_class;
		}

		try {
			$actual = vip_jetpack_is_mobile( $matches, $kind, $return_matched_agent );
			self::assertSame( $expected, $actual );
		} finally {
			unset( $_SERVER['HTTP_X_MOBILE_CLASS'] );
		}
	}

	public function data_vip_jetpack_is_mobile(): iterable {
		// [ X-Mobile-Class header, kind, return matched agent, incoming matches, expected ]
		return [
			'no header keeps matches'          => [ null, 'any', false, false, false ],
			'returning matched agent keeps it' => [ 'tablet', 'tablet', true, 'xxx', 'xxx' ],
			'desktop never matches'            => [ 'desktop', 'smart', false, false, false ],
			'unhandled kind keeps matches'     => [ 'smart', 'desktop', false, false, false ],
			'any matches tablet'               => [ 'tablet', 'any', false, false, true ],
			'smart matches smart'              => [ 'smart', 'smart', false, false, true ],
			'dumb matches dumb'                => [ 'dumb', 'dumb', false, false, true ],
			'smart does not match dumb'        => [ 'smart', 'dumb', false, false, false ],
		];
	}

	/**
	 *
	 * @dataProvider get_vip_jetpack_offline_mode_data
	 */
	public function test__jetpack_vip_filter_jetpack_offline_mode_on_site_launch( $is_launching, $offline_modes, $expected_results ) {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Not relevant on single-site' );
			return;
		}

		if ( $is_launching ) {
			wp_cache_set( 'launching', 'true', 'vip_launch_tools', 0 );
		} else {
			wp_cache_delete( 'launching', 'vip_launch_tools' );
		}
		$offline_modes_count = count( $offline_modes );
		for ( $i = 0; $i < $offline_modes_count; $i++ ) {
			$offline_mode = $offline_modes[ $i ];
			$expected     = $expected_results[ $i ];
			$actual       = apply_filters( 'jetpack_offline_mode', $offline_mode );
			$this->assertEquals( $expected, $actual, 'Value of the jetpack_offline_mode filter is incorrect' );
		}
		// clearing the cache for the next run.
		wp_cache_delete( 'launching', 'vip_launch_tools' );
	}

	public function get_vip_jetpack_offline_mode_data(): iterable {
		return array(
			'not-launching-offline-mode-is-unchanged' =>
			array(
				false, // is_launching.
				array( null, true, false ), // offline_modes.
				array( null, true, false ), // expected_results.
			),
			'launching-offline-mode-is-true'          => array(
				true, // is_launching.
				array( null, true, false ), // offline_modes.
				array( true, true, true ), // expected_results.
			),
		);
	}
}
