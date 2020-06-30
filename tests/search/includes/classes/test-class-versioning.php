<?php

namespace Automattic\VIP\Search;

class Versioning_Test extends \WP_UnitTestCase {
	/**
	* Make tests run in separate processes since we're testing state
	* related to plugin init, including various constants.
	*/
	protected $preserveGlobalState = false; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
	protected $runTestInSeparateProcess = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

	public static $version_instance;

	public static function setUpBeforeClass() {
		require_once __DIR__ . '/../../../../search/search.php';

		$search = \Automattic\VIP\Search\Search::instance();

		// Required so that EP registers the Indexables
		do_action( 'plugins_loaded' );

		self::$version_instance = $search->versioning;
	}

	public function get_next_version_number_data() {
		return array(
			array(
				// Input array of versions
				array(
					2 => array(
						'number' => 2,
					),
					3 => array(
						'number' => 3,
					),
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
					-1 => array(
						'number' => -1,
					),
					-2 => array(
						'number' => -2,
					),
					-3 => array(
						'number' => -3,
					),
				),
				// Expected next version
				2,
			),
			array(
				// Input array of versions
				array(
					2000000 => array(
						'number' => 2000000,
					),
					1000000 => array(
						'number' => 1000000,
					),
				),
				// Expected next version
				2000001,
			),
			array(
				// Input array of versions
				array(
					'one' => array(
						'number' => 'one',
					),
					'two' => array(
						'number' => 'two',
					),
					3 => array(
						'number' => 3,
					),
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
		$next_version = self::$version_instance->get_next_version_number( $versions );

		$this->assertEquals( $expected_next_version, $next_version );
	}

	public function get_active_version_data() {
		return array(
			// No index marked active
			array(
				// Input array of versions
				array(
					2 => array(
						'number' => 2,
						'active' => false,
					),
					3 => array(
						'number' => 3,
						'active' => false,
					),
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
					2 => array(
						'number' => 2,
						'active' => true,
					),
					3 => array(
						'number' => 3,
						'active' => false,
					),
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

		self::$version_instance->update_versions( $indexable, $versions );

		$active_version = self::$version_instance->get_active_version_number( $indexable, $versions );

		$this->assertEquals( $expected_active_version, $active_version );
	}

	public function add_version_data() {
		return array(
			// No index marked active
			array(
				// Input array of versions
				array(
					2 => array(
						'number' => 2,
						'active' => false,
					),
					3 => array(
						'number' => 3,
						'active' => false,
					),
				),
				// Indexable slug
				'post',
				// Expected new versions
				array(
					2 => array(
						'number' => 2, 
						'active' => false,
					),
					3 => array(
						'number' => 3,
						'active' => false,
					),
					4 => array(
						'number' => 4,
						'active' => false,
					),
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
					2 => array(
						'number' => 2,
						'active' => false,
					),
				),
			),
		);
	}

	/**
	 * @dataProvider add_version_data
	 */
	public function test_add_version( $versions, $indexable_slug, $expected_new_versions ) {
		$indexable = \ElasticPress\Indexables::factory()->get( $indexable_slug );

		self::$version_instance->update_versions( $indexable, $versions );

		// Add the new version
		$succeeded = self::$version_instance->add_version( $indexable );

		$this->assertTrue( $succeeded, 'Adding a new version failed, but it should have succeeded' );

		$new_versions = self::$version_instance->get_versions( $indexable );

		// Can only compare the deterministic parts of the version info (not created_time, for example)
		$this->assertEquals( wp_list_pluck( $expected_new_versions, 'number' ), wp_list_pluck( $new_versions, 'number' ), 'New version numbers do not match expected values' );
		$this->assertEquals( wp_list_pluck( $expected_new_versions, 'active' ), wp_list_pluck( $new_versions, 'active' ), 'New versions "active" statuses do not match expected values' );
	}

	public function activate_version_data() {
		return array(
			// No index marked active
			array(
				// Input array of versions
				array(
					2 => array(
						'number' => 2,
						'active' => false,
					),
					3 => array(
						'number' => 3,
						'active' => false,
					),
				),
				// Indexable slug
				'post',
				// Version to activate
				2,
				// Expected new versions
				array(
					2 => array(
						'number' => 2,
						'active' => true,
					),
					3 => array(
						'number' => 3,
						'active' => false,
					),
				),
			),

			// Index already marked active
			array(
				// Input array of versions
				array(
					2 => array(
						'number' => 2,
						'active' => false,
					),
					3 => array(
						'number' => 3,
						'active' => true,
					),
				),
				// Indexable slug
				'post',
				// Version to activate
				2,
				// Expected new versions
				array(
					2 => array(
						'number' => 2,
						'active' => true,
					),
					3 => array(
						'number' => 3,
						'active' => false,
					),
				),
			),
		);
	}

	/**
	 * @dataProvider activate_version_data
	 */
	public function test_activate_version( $versions, $indexable_slug, $version_to_activate, $expected_new_versions ) {
		$indexable = \ElasticPress\Indexables::factory()->get( $indexable_slug );

		self::$version_instance->update_versions( $indexable, $versions );

		$now = time();

		// Add the new version
		$succeeded = self::$version_instance->activate_version( $indexable, $version_to_activate );

		$this->assertTrue( $succeeded, 'Activating version failed, but it should have succeeded' );

		$new_versions = self::$version_instance->get_versions( $indexable );

		// Can only compare the deterministic parts of the version info (not activated_time, for example)
		$this->assertEquals( wp_list_pluck( $expected_new_versions, 'number' ), wp_list_pluck( $new_versions, 'number' ), 'New version numbers do not match expected values' );
		$this->assertEquals( wp_list_pluck( $expected_new_versions, 'active' ), wp_list_pluck( $new_versions, 'active' ), 'New versions "active" statuses do not match expected values' );

		// And make sure the now active version recorded when it was activated
		$active_version = self::$version_instance->get_active_version( $indexable );

		$this->assertEquals( $version_to_activate, $active_version['number'], 'Currently active version does not match expected active version' );
		$this->assertEquals( $now, $active_version['activated_time'], '"activated_time" property of currently active version does not match expected value' );
	}

	public function activate_version_invalid_data() {
		return array(
			// No index marked active
			array(
				// Input array of versions
				array(
					2 => array(
						'number' => 2,
						'active' => false,
					),
					3 => array(
						'number' => 3,
						'active' => false,
					),
				),
				// Indexable slug
				'post',
				// Version to activate
				4,
			),
		);
	}

	/**
	 * @dataProvider activate_version_invalid_data
	 */
	public function test_activate_version_invalid( $versions, $indexable_slug, $version_to_activate ) {
		$indexable = \ElasticPress\Indexables::factory()->get( $indexable_slug );

		self::$version_instance->update_versions( $indexable, $versions );

		$now = time();

		// Add the new version
		$result = self::$version_instance->activate_version( $indexable, $version_to_activate );

		$this->assertTrue( is_wp_error( $result ), 'Expected WP_Error instance' );
		$this->assertEquals( 'invalid-index-version', $result->get_error_code() );

		$new_versions = self::$version_instance->get_versions( $indexable );

		// Can only compare the deterministic parts of the version info (not activated_time, for example), but should be unchanged
		$this->assertEquals( wp_list_pluck( $versions, 'number' ), wp_list_pluck( $new_versions, 'number' ), 'New version numbers do not match expected values' );
		$this->assertEquals( wp_list_pluck( $versions, 'active' ), wp_list_pluck( $new_versions, 'active' ), 'New versions "active" statuses do not match expected values' );
	}

	public function test_current_version_number_overrides() {
		delete_option( Versioning::INDEX_VERSIONS_OPTION );

		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		$result = self::$version_instance->add_version( $indexable );

		$this->assertTrue( $result, 'Failed to add new version of index' );

		// Defaults to active index (1 in our case)
		$this->assertEquals( 1, self::$version_instance->get_current_version_number( $indexable ), 'Default (non-overridden) version number is wrong' );

		// Now we override so we can work on other indexes
		$result = self::$version_instance->set_current_version_number( $indexable, 2 );

		$this->assertTrue( $result, 'Failed to set current version number' );

		// Current index should now be 2
		$this->assertEquals( 2, self::$version_instance->get_current_version_number( $indexable ), 'Overridden version number is wrong' );

		// And reset it back to default
		$result = self::$version_instance->reset_current_version_number( $indexable );

		$this->assertTrue( $result, 'Failed to reset current version number' );

		// Back to the active index
		$this->assertEquals( 1, self::$version_instance->get_current_version_number( $indexable ), 'Version number is wrong after resetting to default' );
	}
}
