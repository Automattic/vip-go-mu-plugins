<?php

namespace Automattic\VIP\Tests;

class WPCOM_VIP_Load_Plugin_Test extends \WP_UnitTestCase {
	function test__warning_on_late_load() {
		$this->setExpectedIncorrectUsage( 'wpcom_vip_load_plugin' );

		// This should have been fired already but just in case
		do_action( 'plugins_loaded' );

		wpcom_vip_load_plugin( 'akismet' );
	}
}
