<?php

require_once ABSPATH . '/wp-admin/includes/upgrade.php';

class VIP_Go_Schema_Test extends WP_UnitTestCase {
	public function test__dbDelta__verify_blog_tables() {
		/** @var wpdb $wpdb */
		global $wpdb;

		$deltas = dbDelta( 'blog', false ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.dbDelta_dbdelta

		$db_version     = $wpdb->db_version();
		$db_server_info = $wpdb->db_server_info();

		if ( version_compare( $db_version, '8.0.17', '>=' ) && false === strpos( $db_server_info, 'MariaDB' ) ) {
			$deltas = array_filter( $deltas, fn ( $delta ) => ! preg_match( '!Changed type of [^ ]+ from ([^ ]+)( unsigned)? to \1\(\d+\)\2?!', $delta ) );
		}
	
		if ( count( $deltas ) !== 1 ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			print_r( $deltas );
		}

		$this->assertCount( 1, $deltas, 'More deltas than expected' );
		$this->assertEquals( $deltas[0], 'Added index wptests_postmeta KEY `vip_meta_key_value` (`meta_key`(191),`meta_value`(100))', 'Delta did not find vip_meta_key_value index or assertion needs updating.' );
	}
}
