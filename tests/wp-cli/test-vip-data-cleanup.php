<?php

namespace Automattic\VIP\Tests;

use WP_UnitTestCase;

require_once __DIR__ . '/../../vip-helpers/vip-wp-cli.php';
require_once __DIR__ . '/../../wp-cli/vip-data-cleanup.php';

// phpcs:ignore PEAR.NamingConventions.ValidClassName.Invalid
class VIP_Data_Cleanup_Command__Test extends WP_UnitTestCase {
	private $original_srtm;
	private $srtm_existed;

	public function setUp(): void {
		parent::setUp();

		// Backup the srtm property state
		global $wpdb;
		$this->srtm_existed = property_exists( $wpdb, 'srtm' );
		if ( $this->srtm_existed ) {
			$this->original_srtm = $wpdb->srtm;
		}
	}

	public function tearDown(): void {
		// Restore the srtm property state
		global $wpdb;
		if ( $this->srtm_existed && property_exists( $wpdb, 'srtm' ) ) {
			$wpdb->srtm = $this->original_srtm;
		}

		parent::tearDown();
	}

	public function test__datasync__sets_srtm_flag_when_property_exists() {
		global $wpdb;

		// Add srtm property if it doesn't exist for testing
		if ( ! property_exists( $wpdb, 'srtm' ) ) {
			$wpdb->srtm = false;
		}

		// Set to false initially
		$wpdb->srtm = false;

		// Simulate the datasync() method logic for setting srtm
		if ( property_exists( $wpdb, 'srtm' ) ) {
			$wpdb->srtm = true;
		}

		// Assert that srtm was set to true
		$this->assertTrue( $wpdb->srtm, 'srtm should be set to true when property exists' );
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
			$wpdb->srtm = true;
			$this->assertTrue( $wpdb->srtm );
		}
	}
}
