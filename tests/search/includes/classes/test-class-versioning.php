<?php

namespace Automattic\VIP\Search;

class Versioning_Test extends \WP_UnitTestCase {
	public function setUp() {
		require_once __DIR__ . '/../../../../search/search.php';

		$search = \Automattic\VIP\Search\Search::instance();

		// Required so that EP registers the Indexables
		do_action( 'plugins_loaded' );

		$this->version_instance = $search->versioning;
	}

	public function get_next_version_number_data() {
		return array(
			array(
				// Input array of versions
				array(
					array( 'number' => 2 ),
					array( 'number' => 3 ),
				),
				// Expected next version
				4,
			),
			array(
				// Input array of versions
				array(),
				// Expected next version
				2,
			),
			array(
				// Input array of versions
				array(
					array( 'number' => -1 ),
					array( 'number' => -2 ),
					array( 'number' => -3 ),
				),
				// Expected next version
				2,
			),
			array(
				// Input array of versions
				array(
					array( 'number' => 2000000 ),
					array( 'number' => 1000000 ),
				),
				// Expected next version
				2000001,
			),
			array(
				// Input array of versions
				array(
					array( 'number' => 'one' ),
					array( 'number' => 'two' ),
					array( 'number' => 3 ),
				),
				// Expected next version
				4,
			),
		);
	}

	/**
	 * @dataProvider get_next_version_number_data
	 */
	public function test_get_next_version_number( $versions, $expected_next_version ) {
		$next_version = $this->version_instance->get_next_version_number( $versions );

		$this->assertEquals( $expected_next_version, $next_version );
	}

	public function get_active_version_data() {
		return array(
			// No index marked active
			array(
				// Input array of versions
				array(
					array( 'number' => 2, 'active' => false ),
					array( 'number' => 3, 'active' => false ),
				),
				// Indexable slug
				'post',
				// Expected active version
				1,
			),

			// No versions tracked
			array(
				// Input array of versions
				array(),
				// Indexable slug
				'post',
				// Expected active version
				1,
			),

			array(
				// Input array of versions
				array(
					array( 'number' => 2, 'active' => true ),
					array( 'number' => 3, 'active' => false ),
				),
				// Indexable slug
				'post',
				// Expected active version
				2,
			),
		);
	}

	/**
	 * @dataProvider get_active_version_data
	 */
	public function test_get_active_version_number( $versions, $indexable_slug, $expected_active_version ) {
		$indexable = \ElasticPress\Indexables::factory()->get( $indexable_slug );

		$this->version_instance->update_versions( $indexable, $versions );

		$active_version = $this->version_instance->get_active_version_number( $indexable, $versions );

		$this->assertEquals( $expected_active_version, $active_version );
	}

	public function add_version_data() {
		return array(
			// No index marked active
			array(
				// Input array of versions
				array(
					array( 'number' => 2, 'active' => false ),
					array( 'number' => 3, 'active' => false ),
				),
				// Indexable slug
				'post',
				// Expected new versions
				array(
					array( 'number' => 2, 'active' => false ),
					array( 'number' => 3, 'active' => false ),
					array( 'number' => 4, 'active' => false ),
				),
			),

			// No versions tracked
			array(
				// Input array of versions
				array(),
				// Indexable slug
				'post',
				// Expected new versions
				array(
					array( 'number' => 2, 'active' => false ),
				),
			),
		);
	}

	/**
	 * @dataProvider add_version_data
	 */
	public function test_add_version( $versions, $indexable_slug, $expected_new_versions ) {
		$indexable = \ElasticPress\Indexables::factory()->get( $indexable_slug );

		$this->version_instance->update_versions( $indexable, $versions );

		// Add the new version
		$succeeded = $this->version_instance->add_version( $indexable );

		$this->assertTrue( $succeeded, 'Adding a new version failed, but it should have succeeded' );

		$new_versions = $this->version_instance->get_versions( $indexable );

		// Can only compare the deterministic parts of the version info (not created_time, for example)
		$this->assertEquals( wp_list_pluck( $expected_new_versions, 'number' ), wp_list_pluck( $new_versions, 'number' ), 'New version numbers do not match expected values' );
		$this->assertEquals( wp_list_pluck( $expected_new_versions, 'active' ), wp_list_pluck( $new_versions, 'active' ), 'New versions "active" statuses do not match expected values' );
	}	
}
