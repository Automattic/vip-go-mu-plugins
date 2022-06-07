<?php

namespace Automattic\VIP\Tests;

use WP_UnitTestCase;

class WPCOM_VIP_Load_Plugin_Test extends WP_UnitTestCase {
	public function test__warning_on_late_load() {
		$this->markTestSkipped( 'This test needs updated b/c now we load mu-plugins normally, and Akismet already gets loaded, causing a duplicate constant definition error when we load it below' );

		$this->setExpectedIncorrectUsage( 'wpcom_vip_load_plugin' );

		// This should have been fired already but just in case
		do_action( 'plugins_loaded' );

		wpcom_vip_load_plugin( 'akismet' );
	}
}
