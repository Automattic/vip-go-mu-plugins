<?php

namespace Automattic\VIP\Admin_Notice;

class Admin_Notice_Test extends \WP_UnitTestCase {

	public function test__init__should_attach_filter() {
		remove_all_filters( 'admin_notices' );

		require_once __DIR__ . '/../../admin-notice/admin-notice.php';

		$this->assertTrue( has_filter( 'admin_notices' ) );
	}

}
