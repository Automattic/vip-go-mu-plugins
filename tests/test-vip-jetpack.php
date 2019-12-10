<?php

class VIP_Go_Jetpack_Test extends WP_UnitTestCase {
	function get_jp_sync_settings_data() {
		return [
			// Too small
			[
				'jetpack_sync_settings_max_queue_size',
				1,
				10000,
			],

			// Too big
			[
				'jetpack_sync_settings_max_queue_size',
				10000000,
				100000,
			],

			// Just right
			[
				'jetpack_sync_settings_max_queue_size',
				10000,
				10000,
			],

			// Within the range - not modified
			[
				'jetpack_sync_settings_max_queue_size',
				20000,
				20000,
			],

			// Not set
			[
				'jetpack_sync_settings_max_queue_size',
				null,
				10000,
			],

			// A string
			[
				'jetpack_sync_settings_max_queue_size',
				'apples',
				10000,
			],

			// Integer as a string (parses as int and returns it if within range)
			[
				'jetpack_sync_settings_max_queue_size',
				'30000',
				30000,
			],

			// Too small
			[
				'jetpack_sync_settings_max_queue_lag',
				1,
				7200,
			],

			// Too big
			[
				'jetpack_sync_settings_max_queue_lag',
				10000000,
				86400,
			],

			// Just right
			[
				'jetpack_sync_settings_max_queue_lag',
				7200,
				7200,
			],

			// Within the range - not modified
			[
				'jetpack_sync_settings_max_queue_lag',
				10000,
				10000,
			],

			// Not set
			[
				'jetpack_sync_settings_max_queue_lag',
				null,
				7200,
			],

			// A string
			[
				'jetpack_sync_settings_max_queue_lag',
				'apples',
				7200,
			],

			// Integer as a string (parses as int and returns it if within range)
			[
				'jetpack_sync_settings_max_queue_lag',
				'15000',
				15000,
			],
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

	public function test__jp_sync_settings_constants_defined() {
		$this->assertTrue( defined( 'VIP_GO_JETPACK_SYNC_MAX_QUEUE_SIZE_LOWER_LIMIT' ) );
		$this->assertTrue( defined( 'VIP_GO_JETPACK_SYNC_MAX_QUEUE_SIZE_UPPER_LIMIT' ) );

		$this->assertTrue( defined( 'VIP_GO_JETPACK_SYNC_MAX_QUEUE_LAG_LOWER_LIMIT' ) );
		$this->assertTrue( defined( 'VIP_GO_JETPACK_SYNC_MAX_QUEUE_LAG_UPPER_LIMIT' ) );
	}

	function get_jetpack_sync_modules_data() {
		return [
			'not-enabled' => [
				false, // sync immediately constant
				// modules input
				[
					'sync' => 'Jetpack_Sync_Modules_Full_Sync',
					'also-sync' => 'Automattic\\Jetpack\\Sync\\Modules\\Full_Sync',
					'not-sync' => 'Not_Sync_Class',
				],
				// modules output
				[
					'sync' => 'Jetpack_Sync_Modules_Full_Sync',
					'also-sync' => 'Automattic\\Jetpack\\Sync\\Modules\\Full_Sync',
					'not-sync' => 'Not_Sync_Class',
				],
			],

			'enabled-no-matching-modules' => [
				true,
				[
					'sync' => 'Other_Sync_Class',
				],
				[
					'sync' => 'Other_Sync_Class',
				],
			],

			'enabled-with-matching-modules' => [
				true,
				[
					'sync' => 'Jetpack_Sync_Modules_Full_Sync',
					'also-sync' => 'Automattic\\Jetpack\\Sync\\Modules\\Full_Sync',
					'not-sync' => 'Not_Sync_Class',
				],
				[
					'sync' => 'Automattic\\Jetpack\\Sync\\Modules\\Full_Sync_Immediately',
					'also-sync' => 'Automattic\\Jetpack\\Sync\\Modules\\Full_Sync_Immediately',
					'not-sync' => 'Not_Sync_Class',
				],
			],
		];
	}

	/**
	 * When the class is not defined, modules are not modified.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test__jetpack_sync_modules__without_class() {
		$modules = [
			'sync' => 'Jetpack_Sync_Modules_Full_Sync',
			'also-sync' => 'Automattic\\Jetpack\\Sync\\Modules\\Full_Sync',
			'not-sync' => 'Not_Sync_Class',
		];
		$expected_modules = [
			'sync' => 'Jetpack_Sync_Modules_Full_Sync',
			'also-sync' => 'Automattic\\Jetpack\\Sync\\Modules\\Full_Sync',
			'not-sync' => 'Not_Sync_Class',
		];

		$actual_modules = apply_filters( 'jetpack_sync_modules', $modules );

		$this->assertEquals( $expected_modules, $actual_modules );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 * @dataProvider get_jetpack_sync_modules_data
	 */
	public function test__jetpack_sync_modules__class_exists( $full_sync_enabled, $modules, $expected_modules ) {
		require_once( __DIR__ . '/fixtures/jetpack/class-jetpack-sync-immediately.php' );

		define( 'VIP_JETPACK_FULL_SYNC_IMMEDIATELY', $full_sync_enabled );

		$actual_modules = apply_filters( 'jetpack_sync_modules', $modules );

		$this->assertEquals( $expected_modules, $actual_modules );
	}
}
