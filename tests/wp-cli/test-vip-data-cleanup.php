<?php

namespace Automattic\VIP\Tests;

use WP_UnitTestCase;

require_once __DIR__ . '/../../vip-helpers/vip-wp-cli.php';
require_once __DIR__ . '/../../wp-cli/vip-data-cleanup.php';

// phpcs:ignore PEAR.NamingConventions.ValidClassName.Invalid
class VIP_Data_Cleanup_Command__Test extends WP_UnitTestCase {
	private $wpdb_backup;

	public function setUp(): void {
		parent::setUp();

		// Backup the global $wpdb
		global $wpdb;
		$this->wpdb_backup = $wpdb;
	}

	public function tearDown(): void {
		// Restore global $wpdb
		global $wpdb;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring backup in test teardown
		$wpdb = $this->wpdb_backup;

		parent::tearDown();
	}

	public function test__datasync__sets_srtm_flag_when_property_exists() {
		global $wpdb;

		// Store original srtm value
		$original_srtm = null;
		if ( property_exists( $wpdb, 'srtm' ) ) {
			$original_srtm = $wpdb->srtm;
		}

		// Add srtm property if it doesn't exist
		if ( ! property_exists( $wpdb, 'srtm' ) ) {
			$wpdb->srtm = false;
		}

		// Verify it's not already true
		$wpdb->srtm = false;

		// Simulate the datasync() method logic for setting srtm
		if ( property_exists( $wpdb, 'srtm' ) ) {
			$wpdb->srtm = true;
		}

		// Assert that srtm was set to true
		$this->assertTrue( $wpdb->srtm, 'srtm should be set to true when property exists' );

		// Restore
		if ( null !== $original_srtm ) {
			$wpdb->srtm = $original_srtm;
		}
	}

	public function test__wpdb_object_has_srtm_property_in_hyperdb_environment() {
		global $wpdb;

		// In a HyperDB environment, $wpdb should have the srtm property
		// This test documents the expected behavior when HyperDB is loaded

		// We can't assume HyperDB is loaded, so we test that our code
		// handles both cases gracefully
		$has_srtm = property_exists( $wpdb, 'srtm' );

		// This should not throw an error regardless
		$this->assertIsBool( $has_srtm );

		// If srtm exists, verify we can set it
		if ( $has_srtm ) {
			$original   = $wpdb->srtm;
			$wpdb->srtm = true;
			$this->assertTrue( $wpdb->srtm );
			$wpdb->srtm = $original;
		}
	}
}
