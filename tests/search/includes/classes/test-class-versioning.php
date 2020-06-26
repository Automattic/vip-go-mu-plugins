<?php

namespace Automattic\VIP\Search;

class Versioning_Test extends \WP_UnitTestCase {
	public function setUp() {
		require_once __DIR__ . '/../../../../search/search.php';

		$search = \Automattic\VIP\Search\Search::instance();

		$this->version_instance = $search->versioning;
	}

	public function get_get_next_version_from_versions_data() {
		return array(
			array(
				// Input array of versions
				array(
					2 => array(),
					3 => array(),
				),
				// Expected next version
				4,
			),
			array(
				// Input array of versions
				array(
					0 => array(),
				),
				// Expected next version
				2,
			),
			array(
				// Input array of versions
				array(
					-1 => array(),
					-2 => array(),
					-3 => array(),
				),
				// Expected next version
				2,
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
					1000000 => array(),
					2000000 => array(),
				),
				// Expected next version
				2000001,
			),
			array(
				// Input array of versions
				array(
					'one' => array(),
					'two' => array(),
					3 => array(),
				),
				// Expected next version
				4,
			),
			array(
				// Input array of versions (flat array, invalid structure)
				array(
					1,
					2,
					3,
				),
				// Expected next version
				2,
			),
		);
	}

	/**
	 * @dataProvider get_get_next_version_from_versions_data
	 */
	public function test_get_next_version_from_versions( $versions, $expected_next_version ) {
		$next_version = $this->version_instance->get_next_version_from_versions( $versions );

		$this->assertEquals( $expected_next_version, $next_version );
	}	
}
