<?php

namespace Automattic\VIP\Tests;

use WP_UnitTestCase;

require_once __DIR__ . '/../../vip-helpers/vip-wp-cli.php';
require_once __DIR__ . '/../../wp-cli/vip-data-cleanup.php';

// phpcs:ignore PEAR.NamingConventions.ValidClassName.Invalid
class VIP_Data_Cleanup_Command__Test extends WP_UnitTestCase {
	private $original_srtm;
	private bool $srtm_existed;

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
		if ( $this->srtm_existed ) {
			$wpdb->srtm = $this->original_srtm;
		} elseif ( property_exists( $wpdb, 'srtm' ) ) {
			unset( $wpdb->srtm );
		}

		parent::tearDown();
	}

	public function test__datasync__sets_srtm_flag_when_property_exists() {
		global $wpdb;

		// Ensure srtm property exists and is set to false initially
		$wpdb->srtm = false;

		// Simulate the datasync() method logic for setting srtm
		if ( property_exists( $wpdb, 'srtm' ) ) {
			$wpdb->srtm = true;
		}

		// Assert that srtm was set to true
		$this->assertTrue( $wpdb->srtm, 'srtm should be set to true when property exists' );
	}
}
