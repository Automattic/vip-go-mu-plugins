<?php

namespace Automattic\VIP\Helpers;

use WP_UnitTestCase;

class Test_VIP_Roles extends WP_UnitTestCase {
	public function test_override_role_caps(): void {
		wpcom_vip_duplicate_role( 'contributor', 'writer', 'Writer', [] );
		wpcom_vip_override_role_caps( 'writer', [ 'read' => false ] );
		wp_roles()->for_site();
		
		$actual   = wpcom_vip_get_role_caps( 'writer' );
		$expected = [ 'read' => false ];

		self::assertEquals( $expected, $actual );
	}
}
