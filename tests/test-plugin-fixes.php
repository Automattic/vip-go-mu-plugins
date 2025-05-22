<?php

use PHPUnit\Framework\TestCase;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class NinjaFormsPatchTest extends TestCase {

	protected function setUp(): void {
		remove_all_filters( 'pre_update_option_ninja_forms_needs_updates' );
	}

	public function test_update_option_patch_applies(): void {
		$this->assertSame( '0', vip_ninja_forms_update_option( 0, '0' ) );
	}

	public function test_update_option_patch_does_not_apply(): void {
		$this->assertSame( 1, vip_ninja_forms_update_option( 1, '0' ) );
		$this->assertSame( '0', vip_ninja_forms_update_option( '0', '0' ) );
	}

	public function test_filter_is_not_added_when_ninja_forms_class_is_missing(): void {
		vip_ninja_forms_setup();
		$this->assertEmpty(
			has_filter( 'pre_update_option_ninja_forms_needs_updates', 'vip_ninja_forms_update_option' ),
			'Filter should not be added when Ninja_Forms class is missing.'
		);
	}
}
