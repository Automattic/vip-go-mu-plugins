<?php

namespace Automattic\VIP\Admin_Notice;

require_once __DIR__ . '/../../admin-notice/class-admin-notice-controller.php';
require_once __DIR__ . '/../../admin-notice/class-admin-notice.php';

class Admin_Notice_Controller_Test extends \WP_UnitTestCase {

	public function test__init__should_attach_filter() {
		$controller = new Admin_Notice_Controller();

		remove_all_filters( 'admin_notices' );

		$controller->init();

		$this->assertTrue( has_filter( 'admin_notices' ) );
	}

}
