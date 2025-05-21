<?php

use PHPUnit\Framework\TestCase;

class NinjaFormsPatchTest extends TestCase {

	public function test_value_type_juggling_patch() {
		// Simulate WordPress calling the filter before updating the option
		$filtered_value = vip_ninja_forms_update_option( 0, '0' );
		$this->assertSame( '0', $filtered_value, 'Expected the filtered value to be string "0".' );

		$filtered_value = vip_ninja_forms_update_option( 1, '0' );
		$this->assertSame( 1, $filtered_value, 'Expected unpatched value to pass through.' );
	}
}
