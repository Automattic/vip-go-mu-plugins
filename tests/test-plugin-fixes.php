<?php

use PHPUnit\Framework\TestCase;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class PluginFixesPatchTest extends TestCase {

	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'Ninja_Forms', false ) ) {
			require_once __DIR__ . '/fixtures/plugin-fixes/mock-ninja-forms-class.php';
		}
	}

	public function test_patch_applies_with_supported_version(): void {
		$this->assertSame( '0', vip_ninja_forms_update_option( 0, '0' ) );
		$this->assertSame( 1, vip_ninja_forms_update_option( 1, '0' ) );
		$this->assertSame( '0', vip_ninja_forms_update_option( '0', '0' ) );
	}
}
