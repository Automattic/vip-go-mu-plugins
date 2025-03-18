<?php

use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../plugin-fixes.php';

class VIP_Go_Test_Check_Divi_Compatibility extends TestCase {
	public function test_divi_active() {
		if ( ! defined( 'ET_CORE_VERSION' ) ) {
			define( 'ET_CORE_VERSION', '4.9.3' );
		}

		vip_divi_setup();

		// Check if constants are defined
		$this->assertTrue( defined( 'ET_DISABLE_FILE_BASED_CACHE' ) );
		$this->assertTrue( constant( 'ET_DISABLE_FILE_BASED_CACHE' ) === true );

		$this->assertTrue( defined( 'ET_BUILDER_CACHE_ASSETS' ) );
		$this->assertTrue( constant( 'ET_BUILDER_CACHE_ASSETS' ) === false );

		$this->assertTrue( defined( 'ET_BUILDER_CACHE_MODULES' ) );
		$this->assertTrue( constant( 'ET_BUILDER_CACHE_MODULES' ) === false );

		$this->assertTrue( defined( 'ET_CORE_CACHE_DIR' ) );
		$this->assertTrue( defined( 'ET_CORE_CACHE_DIR_URL' ) );

		// Check if the filter is added
		$this->assertTrue( has_filter( 'et_cache_wpfs_credentials' ) !== false );
	}
}
