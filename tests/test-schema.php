<?php

use Yoast\WPTestUtils\WPIntegration\TestCase;

class VIP_Go_Schema_Test extends TestCase {
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
	}

	public function test__dbDelta__verify_blog_tables() {
		global $wpdb;
		$deltas = dbDelta( 'blog', false );
		
		$this->assertCount( 1, $deltas, 'More deltas than expected for blogs table' );
		$this->assertEquals( $deltas[0], 'Added index wptests_postmeta KEY `vip_meta_key_value` (`meta_key`(191),`meta_value`(100))', 'Delta did not find vip_meta_key_value index or assertion needs updating.' );
	}
}
