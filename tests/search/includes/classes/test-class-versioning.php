<?php

namespace Automattic\VIP\Search;

use \WP_Error as WP_Error;

class Versioning_Test extends \WP_UnitTestCase {
	/**
	* Make tests run in separate processes since we're testing state
	* related to plugin init, including various constants.
	*/
	protected $preserveGlobalState      = false; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
	protected $runTestInSeparateProcess = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

	public static $version_instance;
	public static $search;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		if ( ! defined( 'VIP_ELASTICSEARCH_ENDPOINTS' ) ) {
			define( 'VIP_ELASTICSEARCH_ENDPOINTS', array(
				'https://es-endpoint1',
				'https://es-endpoint2',
			) );
		}

		require_once __DIR__ . '/../../../../search/search.php';

		self::$search = \Automattic\VIP\Search\Search::instance();

		self::$search->queue->schema->prepare_table();

		// Required so that EP registers the Indexables
		do_action( 'plugins_loaded' );
		do_action( 'init' );

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
					3     => array(
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
						'number'         => 2,
						'active'         => false,
						'created_time'   => 1,
						'activated_time' => null,
					),
					3 => array(
						'number'         => 3,
						'active'         => false,
						'created_time'   => 2,
						'activated_time' => null,
					),
				),
				// Indexable slug
				'post',
				// Expected inactive versions
				array(
					2 => array(
						'number'         => 2,
						'active'         => false,
						'created_time'   => 1,
						'activated_time' => null,
					),
					3 => array(
						'number'         => 3,
						'active'         => false,
						'created_time'   => 2,
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
						'number'         => 2,
						'active'         => true,
						'created_time'   => 1,
						'activated_time' => 1,
					),
					3 => array(
						'number'         => 3,
						'active'         => false,
						'created_time'   => null,
						'activated_time' => null,
					),
				),
				// Indexable slug
				'post',
				// Expected inactive versions
				array(
					3 => array(
						'number'         => 3,
						'active'         => false,
						'created_time'   => null,
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
			// Regular, normalizes string representation of a version into an int
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
				'2',
				// Expected normalized version number
				2,
			),

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
				new \WP_Error( 'no-previous-version' ),
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
				new \WP_Error( 'no-next-version' ),
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
				1, // NOTE - expect 1 because get_active_version_number() returns 1 by default. This behavior is likely to change
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
				new \WP_Error( 'active-index-not-found-in-versions-list' ), // NOTE - like above, this is because the default active version is 1, even if it doesn't exist in the list. Likely to change
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

		if ( is_wp_error( $expected_version_number ) ) {
			// Just validate the code on WP_Errors
			$this->assertTrue( is_wp_error( $normalized_version_number ), 'Expected normalized version to be a WP_Error' );
			$this->assertEquals( $expected_version_number->get_error_code(), $normalized_version_number->get_error_code(), 'Unexpected WP_Error code' );
		} else {
			$this->assertEquals( $expected_version_number, $normalized_version_number );
		}
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
				),
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
				// WP_Error due to too many index versions
				new WP_Error( 'too-many-index-versions', 'Currently, 2 versions exist for indexable post. Only 2 versions allowed per indexable.' ),
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

		if ( is_wp_error( $new_version ) ) {
			$this->assertEquals( $expected_new_versions, $new_version, 'The WP_Error thrown should match the expected WP_Error' );
		} else {
			$expected_new_version = end( $expected_new_versions );

			$this->assertEquals( $expected_new_version['number'], $new_version['number'], 'The returned new version does not match the expected new version' );

			$new_versions = self::$version_instance->get_versions( $indexable );

			// Can only compare the deterministic parts of the version info (not created_time, for example)
			$this->assertEquals( wp_list_pluck( $expected_new_versions, 'number' ), wp_list_pluck( $new_versions, 'number' ), 'New version numbers do not match expected values' );
			$this->assertEquals( wp_list_pluck( $expected_new_versions, 'active' ), wp_list_pluck( $new_versions, 'active' ), 'New versions "active" statuses do not match expected values' );
		}
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
						'options'   => array( 'foo' => 'bar' ),
					),
				),
				2 => array(
					array(
						'object_id' => 1,
						'options'   => array( 'foo' => 'bar' ),
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

		do_action( 'vip_search_indexing_object_queued', 1, 'post', array( 'foo' => 'bar' ), 1 );
		do_action( 'vip_search_indexing_object_queued', 2, 'post', array( 'foo' => 'bar' ), 1 );
		do_action( 'vip_search_indexing_object_queued', 1, 'post', array( 'foo' => 'bar' ), 2 ); // Non-active version, should have no effect

		// Rather than run shutdown, which has side effects, ensure that we are hooked, then run just the shutdown callback
		// NOTE - has_action() returns the priority if hooked, or false if not
		$this->assertEquals( 100, has_action( 'shutdown', array( self::$version_instance, 'action__shutdown' ) ) );

		self::$version_instance->action__shutdown();

		$expected_jobs = array(
			array(
				'object_id'     => 1,
				'object_type'   => 'post',
				'index_version' => 2,
			),
			array(
				'object_id'     => 2,
				'object_type'   => 'post',
				'index_version' => 2,
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
						1    => array(
							array(
								'object_id' => 1, // Object id
								'options'   => array(), // Additional options it was originally queued with
							),
							array(
								'object_id' => 9000, // Object id
								'options'   => array(), // Additional options it was originally queued with
							),
						),
						// Some other random version, should have no effect on replicated jobs
						9999 => array(
							array(
								'object_id' => 1, // Object id
								'options'   => array(), // Additional options it was originally queued with
							),
							array(
								'object_id' => 9000, // Object id
								'options'   => array(), // Additional options it was originally queued with
							),
						),
					),
				),

				// Expected queued jobs
				array(
					array(
						'object_id'     => 1,
						'object_type'   => 'post',
						'index_version' => 2,
					),
					array(
						'object_id'     => 9000,
						'object_type'   => 'post',
						'index_version' => 2,
					),
				),
			),

			// Does not replicate queued items on non-active indexes
			array(
				// Input
				array(
					'post' => array(
						// Inactive version
						2    => array(
							array(
								'object_id' => 1, // Object id
								'options'   => array(), // Additional options it was originally queued with
							),
							array(
								'object_id' => 9000, // Object id
								'options'   => array(), // Additional options it was originally queued with
							),
						),
						// Some other random version, should have no effect on replicated jobs
						9999 => array(
							array(
								'object_id' => 1, // Object id
								'options'   => array(), // Additional options it was originally queued with
							),
							array(
								'object_id' => 9000, // Object id
								'options'   => array(), // Additional options it was originally queued with
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
				'object_id'     => 1,
				'object_type'   => 'post',
				'index_version' => 2,
			),
			array(
				'object_id'     => 2,
				'object_type'   => 'post',
				'index_version' => 2,
			),
			array(
				'object_id'     => 3,
				'object_type'   => 'post',
				'index_version' => 2,
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

		$this->assertEquals( $delete_count, 2 );
	}

	public function normalize_version_data() {
		return array(
			// No data at all
			array(
				// Input version
				array(),
				// Expected (normalized) version
				array(
					'number'         => null,
					'active'         => null,
					'activated_time' => null,
					'created_time'   => null,
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
					'number'         => 2,
					'active'         => false,
					'activated_time' => null,
					'created_time'   => null,
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

	private $default_versions = [
		1 => [
			'number' => 1,
			'active' => true,
			'created_time' => null,
			'activated_time' => null,
		],
	];

	public function get_versions_default_data() {
		return [
			[
				null,
				$this->default_versions,
			],
			[
				'some string',
				$this->default_versions,
			],
			[
				[],
				$this->default_versions,
			],
			[
				[
					'post' => [
						2 => [
							'number' => 2,
							'active' => true,
						],
					],
				],
				[
					2 => [
						'number' => 2,
						'active' => true,
						'created_time' => null,
						'activated_time' => null,
					],
				],
			],
			[
				// No valid versions
				[
					'post' => 'invalid versions value',
				],
				$this->default_versions,
			],
		];
	}

	/**
	 * @dataProvider get_versions_default_data
	 */
	public function test__get_versions_default( $versioning, $expected ) {
		update_option( Versioning::INDEX_VERSIONS_OPTION, $versioning );
		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );


		$result = self::$version_instance->get_versions( $indexable );

		$this->assertEquals( $expected, $result );
	}

	public function get_versions_data() {
		return [
			[
				null,
				[],
			],
			[
				'some string',
				[],
			],
			[
				[],
				[],
			],
			[
				[
					'post' => [
						2 => [
							'number' => 2,
							'active' => true,
						],
					],
				],
				[
					2 => [
						'number' => 2,
						'active' => true,
						'created_time' => null,
						'activated_time' => null,
					],
				],
			],
			[
				// No valid versions
				[
					'post' => 'invalid versions value',
				],
				[],
			],
		];
	}

	/**
	 * @dataProvider get_versions_data
	 */
	public function test__get_versions( $versioning, $expected ) {
		update_option( Versioning::INDEX_VERSIONS_OPTION, $versioning );
		$indexable = \ElasticPress\Indexables::factory()->get( 'post' );


		$result = self::$version_instance->get_versions( $indexable, false );

		$this->assertEquals( $expected, $result );
	}

	private $get_versions__combine_globals_local = [
		'foo' => [
			1 => [
				'number' => 1,
				'active' => true,
				'created_time' => null,
				'activated_time' => null,
			],
		],
	];
	private $get_versions__combine_globals_global = [
		'foo' => [
			2 => [
				'number' => 2,
				'active' => true,
				'created_time' => null,
				'activated_time' => null,
			],
		],
		'bar' => [
			1 => [
				'number' => 1,
				'active' => true,
				'created_time' => null,
				'activated_time' => null,
			],
		],
	];
	public function get_versions__combine_globals_data() {
		return [
			[
				// foo in globals
				'foo',
				true,
				$this->get_versions__combine_globals_global['foo'],
			],
			[
				// foo in locals
				'foo',
				false,
				$this->get_versions__combine_globals_local['foo'],
			],
			[
				// bar comes from globals
				'bar',
				true,
				$this->get_versions__combine_globals_global['bar'],
			],
		];
	}

	/**
	 * @dataProvider get_versions__combine_globals_data
	 */
	public function test__get_versions__combine_globals( $slug, $global, $expected ) {
		update_option( Versioning::INDEX_VERSIONS_OPTION, $this->get_versions__combine_globals_local );
		update_site_option( Versioning::INDEX_VERSIONS_OPTION_GLOBAL, $this->get_versions__combine_globals_global );

		$indexable_mock = $this->getMockBuilder( \ElasticPress\Indexable::class )->getMock();
		$indexable_mock->slug = $slug;
		$indexable_mock->global = $global;

		$result = self::$version_instance->get_versions( $indexable_mock );

		$this->assertEquals( $expected, $result );
	}

	public function maybe_self_heal_reconstruct_data() {
		return [
			[
				[],
				[],
				[],
			],
			[
				[ 'post', 'user' ],
				[
					'post' => [
						1 => [],
					],
					'user' => [
						1 => [],
					],
				],
				[],
			],
			[
				// empty user versions
				[ 'post', 'user' ],
				[
					'post' => [
						1 => [],
					],
					'user' => [],
				],
				[ 'user' ],
			],
		];
	}

	/**
	 * @dataProvider maybe_self_heal_reconstruct_data
	 */
	public function test__maybe_self_heal_reconstruct( $indexables, $versioning, $expected_reconstructions ) {
		$indexables_mocks = array_map( function( $slug ) {
			$indexable_mock = $this->getMockBuilder( \ElasticPress\Indexable::class )->getMock();
			$indexable_mock->slug = $slug;
			return $indexable_mock;
		}, $indexables);

		$indexables_mock = $this->getMockBuilder( \ElasticPress\Indexables::class )
			->setMethods( [ 'get_all' ] )
			->getMock();
		$indexables_mock->method( 'get_all' )->willReturn( $indexables_mocks );

		$partially_mocked_versioning = $this->getMockBuilder( \Automattic\VIP\Search\Versioning::class )
			->setMethods( [ 'get_versions', 'reconstruct_versions_for_indexable', 'get_all_accesible_indicies' ] )
			->getMock();

		$partially_mocked_versioning
			->method( 'get_versions' )
			->will( $this->returnCallback( function( $indexable ) use ( $versioning ) {
					return $versioning[ $indexable->slug ];
			} ) );

		$partially_mocked_versioning->expects( $this->exactly( count( $expected_reconstructions ) ) )
			->method( 'reconstruct_versions_for_indexable' );

		$partially_mocked_versioning->elastic_search_indexables = $indexables_mock;
		$partially_mocked_versioning->alerts = $this->createMock( \Automattic\VIP\Utils\Alerts::class );

		$partially_mocked_versioning->maybe_self_heal();
	}

	public function get_all_accesible_indicies_data() {
		return [
			[
				new WP_Error( 'Test Error' ),
				new WP_Error( 'Test Error' ),
			],
			[
				[ 'response' => [ 'code' => 500 ] ],
				new WP_Error( 'failed-to-fetch-indicies', 'Request failed to fetch indicies with status 500' ),
			],
			[
				[
					'response' => [ 'code' => 200 ],
					'body' => 'malformed_body',
				],
				[],
			],
			[
				[
					'response' => [ 'code' => 200 ],
					'body' => '{"valid_json": "with_invalid_structure"}',
				],
				[],
			],
			[
				[
					'response' => [ 'code' => 200 ],
					'body' => '[{"index": "ix1"}, {"index": "ix2"}]',
				],
				[ 'ix1', 'ix2' ],
			],
		];
	}

	/**
	 * @dataProvider get_all_accesible_indicies_data
	 */
	public function test__get_all_accesible_indicies( $response, $expected ) {
		$es_mock = $this->getMockBuilder( \ElasticPress\Elasticsearch::class )
			->setMethods( [ 'remote_request' ] )
			->getMock();
		$es_mock->expects( $this->once() )
			->method( 'remote_request' )
			->willReturn( $response );


		$instance = new Versioning();
		$instance->elastic_search_instance = $es_mock;


		$result = $instance->get_all_accesible_indicies();

		$this->assertEquals( $expected, $result );
	}

	public function reconstruct_versions_for_indexable_data() {
		return [
			[
				[],
				[
					'slug' => 'post',
					'global' => false,
				],
				[],
			],
			[
				'invalid_input',
				[
					'slug' => 'post',
					'global' => false,
				],
				[],
			],
			[
				[ 'invalid_ix_format' ],
				[
					'slug' => 'post',
					'global' => false,
				],
				[],
			],
			[
				// correctly parse and pick the lowest
				[ 'vip-200508-post-1-v3', 'vip-200508-post-1', 'vip-200508-post-1-v2' ],
				[
					'slug' => 'post',
					'global' => false,
				],
				[
					1 => [
						'number' => 1,
						'active' => true,
						'created_time' => null,
						'activated_time' => null,
					],
					2 => [
						'number' => 2,
						'active' => false,
						'created_time' => null,
						'activated_time' => null,
					],
					3 => [
						'number' => 3,
						'active' => false,
						'created_time' => null,
						'activated_time' => null,
					],
				],
			],
			[
				//  ignore other indexables
				[ 'vip-200508-post-1-v2', 'vip-200508-user-v3', 'vip-200508-post2-1-v5', 'vip-200508-post-1-v3' ],
				[
					'slug' => 'post',
					'global' => false,
				],
				[
					2 => [
						'number' => 2,
						'active' => true,
						'created_time' => null,
						'activated_time' => null,
					],
					3 => [
						'number' => 3,
						'active' => false,
						'created_time' => null,
						'activated_time' => null,
					],
				],
			],
			[
				//  ignore different blog id
				[ 'vip-200508-post-1-v2', 'vip-200508-post-2-v3' ],
				[
					'slug' => 'post',
					'global' => false,
				],
				[
					2 => [
						'number' => 2,
						'active' => true,
						'created_time' => null,
						'activated_time' => null,
					],
				],
			],
			[
				//  handle globals correctly (ignores the ones with blog_id)
				[ 'vip-200508-user', 'vip-200508-user-1-v2', 'vip-200508-user-v3' ],
				[
					'slug' => 'user',
					'global' => true,
				],
				[
					1 => [
						'number' => 1,
						'active' => true,
						'created_time' => null,
						'activated_time' => null,
					],
					3 => [
						'number' => 3,
						'active' => false,
						'created_time' => null,
						'activated_time' => null,
					],
				],
			],
		];
	}

	/**
	 * @dataProvider reconstruct_versions_for_indexable_data
	 */
	public function test__reconstruct_versions_for_indexable( $indicies, $indexable_data, $expected ) {
		$indexable_mock = $this->getMockBuilder( \ElasticPress\Indexable::class )->getMock();
		$indexable_mock->slug = $indexable_data['slug'];
		$indexable_mock->global = $indexable_data['global'];

		$result = self::$version_instance->reconstruct_versions_for_indexable( $indicies, $indexable_mock );

		$this->assertEquals( $expected, $result );
	}

	public function parse_index_name_data() {
		return [
			[
				'vip-200-post-1-v3',
				[
					'environment_id' => 200,
					'slug' => 'post',
					'blog_id' => 1,
					'version' => 3,
				],
			],
			[
				'vip-200-post-v3',
				[
					'environment_id' => 200,
					'slug' => 'post',
					'version' => 3,
				],
			],
			[
				'vip-200-post',
				[
					'environment_id' => 200,
					'slug' => 'post',
					'version' => 1,
				],
			],
			[
				'vip-200',
				new \WP_Error( 'index-name-not-valid', 'Index name "vip-200" is not valid' ),
			],

		];
	}

	/**
	 * @dataProvider parse_index_name_data
	 */
	public function test__parse_index_name( $index, $expected ) {
		$result = self::$version_instance->parse_index_name( $index );

		$this->assertEquals( $expected, $result );
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
