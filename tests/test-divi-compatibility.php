<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../plugin-fixes.php';

class VIP_Go_Test_Check_Divi_Setup extends TestCase {
	/**
	 * Test the vip_divi_compatibility function.
	 *
	 * @dataProvider data_provider
	 * @param bool $is_divi_active Is the Divi theme active.
	 * @param array $expected_constants Expected constants to be defined.
	 * @param bool $expected_filter_exists Expected existence of the 'et_cache_wpfs_credentials' filter.
	 */
	public function test_vip_divi_compatibility( $is_divi_active, $expected_constants, $expected_filter_exists ) {
		if ( $is_divi_active ) {
			if ( ! defined( 'ET_CORE_VERSION' ) ) {
				define( 'ET_CORE_VERSION', '4.9.3' );
			}
		}

		vip_divi_setup();

		foreach ( $expected_constants as $constant => $expected_value ) {
			$this->assertTrue( defined( $constant ) );
			$this->assertEquals( $expected_value, constant( $constant ) );
		}

		$this->assertEquals( $expected_filter_exists, has_filter( 'et_cache_wpfs_credentials' ) );
	}

	/**
	 * The data provider method
	 *
	 * @return array
	 */
	public function data_provider() {
		return array(
			'divi-not-active' => array(
				false,
				array(),
				false,
			),
			'divi-active'     => array(
				true,
				array(
					'ET_DISABLE_FILE_BASED_CACHE' => true,
					'ET_BUILDER_CACHE_ASSETS'     => false,
					'ET_BUILDER_CACHE_MODULES'    => false,
				),
				true,
			),
		);
	}
}
