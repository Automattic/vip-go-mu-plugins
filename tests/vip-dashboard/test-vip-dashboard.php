<?php

require_once __DIR__ . '/../../vip-dashboard.php';

class Test_VIP_Dashboard extends WP_UnitTestCase {
	public function test_vip_echo_mailto_vip_hosting(): void {
		wp_set_current_user( 1 );
		$actual = vip_echo_mailto_vip_hosting( 'Link', false );
		self::assertStringNotContainsString( '&body=', $actual );
		self::assertStringNotContainsString( '\n', $actual );

		$matches = [];
		preg_match( '/href="([^"]+)"/', $actual, $matches );
		self::assertIsArray( $matches );
		self::assertArrayHasKey( 1, $matches );
		$href = $matches[1];

		self::assertStringNotContainsString( ' ', $href );
	}
}
