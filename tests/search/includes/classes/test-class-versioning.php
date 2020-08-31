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
	public static $search;

	public static function setUpBeforeClass() {
		define( 'VIP_ELASTICSEARCH_ENDPOINTS', array(
			'https://es-endpoint1',
			'https://es-endpoint2',
		) );

		require_once __DIR__ . '/../../../../search/search.php';

		self::$search = \Automattic\VIP\Search\Search::instance();

		self::$search->queue->schema->prepare_table();

		// Required so that EP registers the Indexables
		do_action( 'plugins_loaded' );

		self::$version_instance = self::$search->versioning;
	}

	public function get_next_version_number_data() {
		return array(
			array(
				// Input array of versions
				array(
					1 => array(
						'number' => 1,
					),
					2 => array(
						'number' => 2,
					),
				),
				// Expected next version
				3,
			),
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

	public function get_inactive_versions_data() {
		return array(
			// No index marked active
			array(
				// Input array of versions
				array(
					2 => array(
						'number' => 2,
						'active' => false,
						'created_time' => 1,
						'activated_time' => null,
					),
					3 => array(
						'number' => 3,
						'active' => false,
						'created_time' => 2,
						'activated_time' => null,
					),
				),
				// Indexable slug
				'post',
				// Expected inactive versions
				array(
					2 => array(
						'number' => 2,
						'active' => false,
						'created_time' => 1,
						'activated_time' => null,
					),
					3 => array(
						'number' => 3,
						'active' => false,
						'created_time' => 2,
						'activated_time' => null,
					),
				),
			),

			// No versions tracked
			array(
				// Input array of versions
				array(),
				// Indexable slug
				'post',
				// Expected active version
				array(),
			),

			// 1 version active, with another
			array(
				// Input array of versions
				array(
					2 => array(
						'number' => 2,
						'active' => true,
						'created_time' => 1,
						'activated_time' => 1,
					),
					3 => array(
						'number' => 3,
						'active' => false,
						'created_time' => null,
						'activated_time' => null,
					),
				),
				// Indexable slug
				'post',
				// Expected inactive versions
				array(
					3 => array(
						'number' => 3,
						'active' => false,
						'created_time' => null,
						'activated_time' => null,
					),
				),
			),
		);
	}

	/**
	 * @dataProvider get_inactive_versions_data
	 */
	public function test_get_inactive_versions( $versions, $indexable_slug, $expected_inactive_versions ) {
		$indexable = \ElasticPress\Indexables::factory()->get( $indexable_slug );

		self::$version_instance->update_versions( $indexable, $versions );

		$inactive_versions = self::$version_instance->get_inactive_versions( $indexable );

		$this->assertEquals( $expected_inactive_versions, $inactive_versions );
	}

	public function normalize_version_number_data() {
		return array(
			// Regular, 'next'
			array(
				// Input array of versions
				array(
					1 => array( 
						'number' => 1,
						'active' => false,
					),
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
				// Version string to be normalized
				'next',
				// Expected normalized version number
				3,
			),

			// Regular, 'previous'
			array(
				// Input array of versions
				array(
					1 => array( 
						'number' => 1,
						'active' => false,
					),
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
				// Version string to be normalized
				'previous',
				// Expected normalized version number
				1,
			),

			// Regular, 'active'
			array(
				// Input array of versions
				array(
					1 => array( 
						'number' => 1,
						'active' => false,
					),
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
				// Version string to be normalized
				'active',
				// Expected normalized version number
				2,
			),

			// No previous
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
				// Version string to be normalized
				'previous',
				// Expected normalized version number
				null,
			),

			// No next
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
				// Version string to be normalized
				'next',
				// Expected normalized version number
				null,
			),

			// No active
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
				// Version string to be normalized
				'active',
				// Expected active version
				null,
			),

			// No active, trying to get next
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
				// Version string to be normalized
				'next',
				// Expected active version
				null,
			),
		);
	}

	/**
	 * @dataProvider normalize_version_number_data
	 */
	public function test_normalize_version_number( $versions, $indexable_slug, $version_string, $expected_version_number ) {
		$indexable = \ElasticPress\Indexables::factory()->get( $indexable_slug );

		self::$version_instance->update_versions( $indexable, $versions );

		$normalized_version_number = self::$version_instance->normalize_version_number( $indexable, $version_string );

		$this->assertEquals( $expected_version_number, $normalized_version_number );
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

			// Current version is 2
			array(
				// Input array of versions
				array(
					1 => array(
						'number' => 1,
						'active' => false,
					),
					2 => array(
						'number' => 2,
						'active' => true,
					),
				),
				// Indexable slug
				'post',
				// Expected new versions
				array(
					1 => array(
						'number' => 1,
						'active' => false,
					),
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

			// No versions tracked
			array(
				// Input array of versions
				array(),
				// Indexable slug
				'post',
				// Expected new versions
				array(
					// Should have added the default version 1 data
					1 => array(
						'number' => 1,
						'active' => true, // Defaults to active when no other index version is known
					),
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
		$new_version = self::$version_instance->add_version( $indexable );

		$expected_new_version = end( $expected_new_versions );

		$this->assertEquals( $expected_new_version['number'], $new_version['number'], 'The returned new version does not match the expected new version' );

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

			// With an index already marked active
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

			// Target index is already marked active
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
				3,
				// Expected new versions
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
			),

			// Target index is already marked active, and is index 1
			array(
				// Input array of versions
				array(),
				// Indexable slug
				'post',
				// Version to activate
				1,
				// Expected new versions
				array(
					1 => array(
						'number' => 1,
						'active' => true,
					),
				),
			),

			// Switching back to 1, which may not exist in the option
			array(
				// Input array of versions
				array(),
				// Indexable slug
				'post',
				// Version to activate
				1,
				// Expected new versions
				array(
					1 => array(
						'number' => 1,
						'active' => true,
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

			// Switching back to 1, when it's not registered (has been deleted over time)
			array(
				// Input array of versions
				array(
					200 => array(
						'number' => 200,
						'active' => false,
					),
					201 => array(
						'number' => 201,
						'active' => true,
					),
				),
				// Indexable slug
				'post',
				// Version to activate
				1,
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

	public function delete_version_data() {
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
				// Version to delete
				2,
				// Expected new versions
				array(
					3 => array(
						'number' => 3,
						'active' => false,
					),
				),
			),

			// Current version is 2
			array(
				// Input array of versions
				array(
					1 => array(
						'number' => 1,
						'active' => false,
					),
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
				// Version to delete
				1,
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
	 * @dataProvider delete_version_data
	 */
	public function test_delete_version( $start_versions, $indexable_slug, $version_number, $expected_versions ) {
		$indexable = \ElasticPress\Indexables::factory()->get( $indexable_slug );

		$indexable_mock = $this->getMockBuilder( get_class( $indexable ) )
			->setMethods( [ 'delete_index' ] )
			->getMock();

		$indexable_mock->expects( $this->once() )
			->method( 'delete_index' )
			->willReturn( true );

		self::$version_instance->update_versions( $indexable, $start_versions );

		// Delete the version
		$delete_result = self::$version_instance->delete_version( $indexable_mock, $version_number );

		$this->assertEquals( true, $delete_result, 'The index version was not deleted' );

		$versions = self::$version_instance->get_versions( $indexable );

		// Can only compare the deterministic parts of the version info (not created_time, for example)
		$this->assertEquals( wp_list_pluck( $expected_versions, 'number' ), wp_list_pluck( $versions, 'number' ), 'New version numbers do not match expected values' );
		$this->assertEquals( wp_list_pluck( $expected_versions, 'active' ), wp_list_pluck( $versions, 'active' ), 'New versions "active" statuses do not match expected values' );
	}

	public function test_delete_version_invalid() {
		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		$result = self::$version_instance->delete_version( $indexable, 99999 );

		$this->assertEquals( true, is_wp_error( $result ) );
		$this->assertEquals( 'invalid-version-number', $result->get_error_code() );
	}

	public function test_delete_version_while_active() {
		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		$active_version_number = self::$version_instance->get_active_version_number( $indexable );

		$result = self::$version_instance->delete_version( $indexable, $active_version_number );

		$this->assertEquals( true, is_wp_error( $result ) );
		$this->assertEquals( 'cannot-delete-active-index-version', $result->get_error_code() );
	}

	public function test_current_version_number_overrides() {
		delete_option( Versioning::INDEX_VERSIONS_OPTION );

		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		$result = self::$version_instance->add_version( $indexable );

		$this->assertNotFalse( $result, 'Failed to add new version of index' );
		$this->assertNotInstanceOf( \WP_Error::class, $result, 'Got WP_Error when adding new index version' );

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

	public function test_action__vip_search_indexing_object_queued() {
		self::$version_instance->action__vip_search_indexing_object_queued( 1, 'post', array( 'foo' => 'bar' ), 1 );
		self::$version_instance->action__vip_search_indexing_object_queued( 1, 'post', array( 'foo' => 'bar' ), 2 );

		$expected_queued_objects_by_type_and_version = array(
			'post' => array(
				1 => array(
					array(
						'object_id' => 1,
						'options' => array( 'foo' => 'bar' ),
					),
				),
				2 => array(
					array(
						'object_id' => 1,
						'options' => array( 'foo' => 'bar' ),
					),
				),
			),
		);

		$current_queued_objects = $this->get_property( 'queued_objects_by_type_and_version' )->getValue( self::$version_instance );

		$this->assertEquals( $expected_queued_objects_by_type_and_version, $current_queued_objects );
	}

	/**
	 * Tests that queue jobs get properly replicated to the queue for other index versions
	 */
	public function test_queue_job_replication() {
		global $wpdb;

		self::$search->queue->empty_queue();

		// For these tests, we're just using the post type and index versions 1, 2, and 3, for simplicity
		self::$version_instance->update_versions( \ElasticPress\Indexables::factory()->get( 'post' ), array() ); // Reset them
		self::$version_instance->add_version( \ElasticPress\Indexables::factory()->get( 'post' ) );
		self::$version_instance->add_version( \ElasticPress\Indexables::factory()->get( 'post' ) );

		do_action( 'vip_search_indexing_object_queued', 1, 'post', array( 'foo' => 'bar' ), 1 );
		do_action( 'vip_search_indexing_object_queued', 2, 'post', array( 'foo' => 'bar' ), 1 );
		do_action( 'vip_search_indexing_object_queued', 1, 'post', array( 'foo' => 'bar' ), 2 ); // Non-active version, should have no effect

		// Rather than run shutdown, which has side effects, ensure that we are hooked, then run just the shutdown callback
		// NOTE - has_action() returns the priority if hooked, or false if not
		$this->assertEquals( 100, has_action( 'shutdown', array( self::$version_instance, 'action__shutdown' ) ) );

		self::$version_instance->action__shutdown();

		$expected_jobs = array(
			array(
				'object_id' => 1,
				'object_type' => 'post',
				'index_version' => 2,
			),
			array(
				'object_id' => 1,
				'object_type' => 'post',
				'index_version' => 3,
			),
			array(
				'object_id' => 2,
				'object_type' => 'post',
				'index_version' => 2,
			),
			array(
				'object_id' => 2,
				'object_type' => 'post',
				'index_version' => 3,
			),
		);

		$queue_table_name = self::$search->queue->schema->get_table_name();

		$jobs = $wpdb->get_results(
			"SELECT * FROM {$queue_table_name}", // Cannot prepare table name. @codingStandardsIgnoreLine
			ARRAY_A
		);

		$this->assertEquals( count( $expected_jobs ), count( $jobs ) );

		// Only comparing certain fields (the ones passed through to $expected_jobs), since some are generated at insert time
		foreach ( $expected_jobs as $index => $job ) {
			$keys = array_keys( $job );

			foreach ( $keys as $key ) {
				$this->assertEquals( $expected_jobs[ $index ][ $key ], $job[ $key ], "The job at index {$index} has the wrong value for key {$key}" );
			}
		}
	}

	public function replicate_queued_objects_to_other_versions_data() {
		return array(
			// Replicates queued items on the active index to a single non-active indexe
			array(
				// Input
				array(
					'post' => array(
						// Active version
						1 => array(
							array(
								'object_id' => 1, // Object id
								'options' => array(), // Additional options it was originally queued with
							),
							array(
								'object_id' => 9000, // Object id
								'options' => array(), // Additional options it was originally queued with
							),
						),
						// Some other random version, should have no effect on replicated jobs
						9999 => array(
							array(
								'object_id' => 1, // Object id
								'options' => array(), // Additional options it was originally queued with
							),
							array(
								'object_id' => 9000, // Object id
								'options' => array(), // Additional options it was originally queued with
							),
						),
					),
				),

				// Expected queued jobs
				array(
					array(
						'object_id' => 1,
						'object_type' => 'post',
						'index_version' => 2,
					),
					array(
						'object_id' => 9000,
						'object_type' => 'post',
						'index_version' => 2,
					),
					array(
						'object_id' => 1,
						'object_type' => 'post',
						'index_version' => 3,
					),
					array(
						'object_id' => 9000,
						'object_type' => 'post',
						'index_version' => 3,
					),
				),
			),

			// Does not replicate queued items on non-active indexes
			array(
				// Input
				array(
					'post' => array(
						// Inactive version
						2 => array(
							array(
								'object_id' => 1, // Object id
								'options' => array(), // Additional options it was originally queued with
							),
							array(
								'object_id' => 9000, // Object id
								'options' => array(), // Additional options it was originally queued with
							),
						),
						// Some other random version, should have no effect on replicated jobs
						9999 => array(
							array(
								'object_id' => 1, // Object id
								'options' => array(), // Additional options it was originally queued with
							),
							array(
								'object_id' => 9000, // Object id
								'options' => array(), // Additional options it was originally queued with
							),
						),
					),
				),

				// Expected queued jobs
				array(),
			),
		);
	}

	/**
	 * @dataProvider replicate_queued_objects_to_other_versions_data
	 */
	public function test_replicate_queued_objects_to_other_versions( $input, $expected_jobs ) {
		global $wpdb;

		self::$search->queue->empty_queue();

		// For these tests, we're just using the post type and index versions 1, 2, and 3, for simplicity
		self::$version_instance->update_versions( \ElasticPress\Indexables::factory()->get( 'post' ), array() ); // Reset them
		self::$version_instance->add_version( \ElasticPress\Indexables::factory()->get( 'post' ) );
		self::$version_instance->add_version( \ElasticPress\Indexables::factory()->get( 'post' ) );

		$queue_table_name = self::$search->queue->schema->get_table_name();

		self::$version_instance->replicate_queued_objects_to_other_versions( $input );

		$jobs = $wpdb->get_results(
			"SELECT * FROM {$queue_table_name}", // Cannot prepare table name. @codingStandardsIgnoreLine
			ARRAY_A
		);

		$this->assertEquals( count( $expected_jobs ), count( $jobs ) );

		// Only comparing certain fields (the ones passed through to $expected_jobs), since some are generated at insert time
		foreach ( $expected_jobs as $index => $job ) {
			$keys = array_keys( $job );

			foreach ( $keys as $key ) {
				$this->assertEquals( $expected_jobs[ $index ][ $key ], $job[ $key ], "The job at index {$index} has the wrong value for key {$key}" );
			}
		}
	}

	public function test_replicate_indexed_objects_to_other_versions() {
		global $wpdb;

		self::$search->queue->empty_queue();

		// For these tests, we're just using the post type and index versions 1, 2, and 3, for simplicity
		self::$version_instance->update_versions( \ElasticPress\Indexables::factory()->get( 'post' ), array() ); // Reset them
		self::$version_instance->add_version( \ElasticPress\Indexables::factory()->get( 'post' ) );
		self::$version_instance->add_version( \ElasticPress\Indexables::factory()->get( 'post' ) );

		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		$sync_manager = $indexable->sync_manager;

		// Fake some changed posts
		$sync_manager->sync_queue = array(
			1 => true,
			2 => true,
			3 => true,
		);

		// Then fire pre_ep_index_sync_queue to simulate EP performing indexing
		$result = apply_filters( 'pre_ep_index_sync_queue', false, $sync_manager, 'post' );

		// Should not be bailing (the $result)
		$this->assertFalse( $result );

		// And check what's in the queue table - should be jobs for all the edited posts, on the non-active versions

		$queue_table_name = self::$search->queue->schema->get_table_name();

		$jobs = $wpdb->get_results(
			"SELECT * FROM {$queue_table_name}", // Cannot prepare table name. @codingStandardsIgnoreLine
			ARRAY_A
		);

		$expected_jobs = array(
			array(
				'object_id' => 1,
				'object_type' => 'post',
				'index_version' => 2,
			),
			array(
				'object_id' => 2,
				'object_type' => 'post',
				'index_version' => 2,
			),
			array(
				'object_id' => 3,
				'object_type' => 'post',
				'index_version' => 2,
			),
			array(
				'object_id' => 1,
				'object_type' => 'post',
				'index_version' => 3,
			),
			array(
				'object_id' => 2,
				'object_type' => 'post',
				'index_version' => 3,
			),
			array(
				'object_id' => 3,
				'object_type' => 'post',
				'index_version' => 3,
			),
		);

		// Only comparing certain fields (the ones passed through to $expected_jobs), since some are generated at insert time
		foreach ( $expected_jobs as $index => $job ) {
			$keys = array_keys( $job );

			foreach ( $keys as $key ) {
				$this->assertEquals( $expected_jobs[ $index ][ $key ], $jobs[ $index ][ $key ], "The job at index {$index} has the wrong value for key {$key}" );
			}
		}
	}

	public function test_replicate_deletes_to_other_index_versions() {
		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		// For these tests, we're just using the post type and index versions 1, 2, and 3, for simplicity
		self::$version_instance->update_versions( $indexable, array() ); // Reset them
		self::$version_instance->add_version( $indexable );
		self::$version_instance->add_version( $indexable );

		// Add a filter that we can use to count how many deletes are actually sent to ES
		$delete_count = 0;

		add_filter( 'ep_do_intercept_request', function( $request, $query, $args, $failures ) use ( &$delete_count ) {
			if ( 'DELETE' === $args['method'] ) {
				$delete_count++;
			}

			// For linting, always have to return something
			return null;
		}, 10, 4 );

		$indexable->delete( 1 );

		$this->assertEquals( $delete_count, 3 );
	}

	public function normalize_version_data() {
		return array(
			// No data at all
			array(
				// Input version
				array(),
				// Expected (normalized) version
				array(
					'number' => null,
					'active' => null,
					'activated_time' => null,
					'created_time' => null,
				),
			),

			// Partial data
			array(
				// Input version
				array(
					'number' => 2,
					'active' => false,
				),
				// Expected (normalized) version
				array(
					'number' => 2,
					'active' => false,
					'activated_time' => null,
					'created_time' => null,
				),
			),
		);
	}

	/**
	 * @dataProvider normalize_version_data
	 */
	public function test_normalize_version( $input, $expected ) {
		$normalized = self::$version_instance->normalize_version( $input );

		$this->assertEquals( $expected, $normalized );
	}

	public function test_get_version() {
		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );

		$one = self::$version_instance->get_version( $indexable, 1 );

		$this->assertEquals( 1, $one['number'], 'Wrong version number for returned version (expected 1)' );

		$new = self::$version_instance->add_version( $indexable );

		$new_retrieved = self::$version_instance->get_version( $indexable, $new['number'] );

		$this->assertEquals( $new['number'], $new_retrieved['number'], 'Wrong version number for returned version on newly created version' );
	}

	/**
	 * Helper function for accessing protected properties.
	 */
	protected static function get_property( $name ) {
		$class = new \ReflectionClass( __NAMESPACE__ . '\Versioning' );

		$property = $class->getProperty( $name );
		$property->setAccessible( true );

		return $property;
	}
}
