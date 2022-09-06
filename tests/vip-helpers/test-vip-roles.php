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

	/**
	 * @ticket CANTINA-920
	 */
	public function test_wpcom_vip_add_role_yoast_style(): void {
		// See https://github.com/Yoast/wordpress-seo/blob/0742e9b6ba4c0d6ae9d65223267a106b92a6a4a1/admin/roles/class-role-manager-vip.php#L22-L37
		$capabilities = [
			'moderate_comments' => true,
			'manage_categories' => true,
			'manage_links'      => true,
		];

		$enabled_capabilities = [];
		foreach ( $capabilities as $capability => $_ ) {
			$enabled_capabilities[] = $capability;
		}

		$role = 'test_role';
		wpcom_vip_add_role( $role, 'Test Role', $enabled_capabilities );

		$actual   = wpcom_vip_get_role_caps( $role );
		$expected = $capabilities;

		self::assertEquals( $expected, $actual );
	}
}
