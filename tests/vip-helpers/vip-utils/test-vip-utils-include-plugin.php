<?php

const TEST_GLOBAL_VARIABLE = 'include_plugin_test_variable';

class WPCOM_VIP_Utils_Include_Plugin_Test extends WP_UnitTestCase {

	public function test__wpcom_vip_include_plugin__will_move_variables_to_global() {

		$this->assertFalse( isset( $GLOBALS[ TEST_GLOBAL_VARIABLE ] ) );

		_wpcom_vip_include_plugin( __DIR__ . '/include-vip-utils-include-plugin.php' );

		$this->assertTrue( $GLOBALS[ TEST_GLOBAL_VARIABLE ] );

	}
}
