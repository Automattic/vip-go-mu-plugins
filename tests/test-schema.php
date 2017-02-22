<?php

class VIP_Go_Schema_Test extends WP_UnitTestCase {
	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();

		require_once( ABSPATH . '/wp-admin/includes/upgrade.php' );
	}

	public function test__dbDelta__verify_blog_tables() {
		global $wpdb;
		$deltas = dbDelta( 'blog', false );
		
		$this->assertCount( 1, $deltas, 'More deltas than expected for blogs table' );
		$this->assertEquals( $deltas[0], 'Added index wptests_postmeta KEY `vip_meta_key_value` (`meta_key`(191),`meta_value`(20))', 'Delta did not find vip_meta_key_value index or assertion needs updating.' );
	}
}
