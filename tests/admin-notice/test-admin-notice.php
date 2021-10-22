<?php

namespace Automattic\VIP\Admin_Notice;

use WP_UnitTestCase;

class Admin_Notice_Test extends WP_UnitTestCase {

	public function test__init__should_attach_filter() {
		$this->markTestSkipped( 'This test needs updated b/c now we load mu-plugins normally, so the require_once here doesn\'t happen to re-register the filter' );

		remove_all_filters( 'admin_notices' );

		require_once __DIR__ . '/../../admin-notice/admin-notice.php';

		$this->assertTrue( has_filter( 'admin_notices' ) );
	}

}
