<?php

namespace Automattic\VIP\Files;

require_once __DIR__ . '/../../files/class-path-utils.php';

class Path_Utils_Test extends \PHPUnit_Framework_TestCase {
	public function get_test_data__is_subdirectory_multisite_path__nope() {
		return [
			'missing_leading_slash' => [
				'subsite1/wp-content/uploads/sites/1/file.jpg',
			],
			'no_subdirectory'       => [
				'/wp-content/uploads/sites/1/file.jpg',
			],
			'invalid_subdirectory'  => [
				'/subsité1/wp-content/uploads/sites/1/file.jpg',
			],
			'empty_subdirectory'    => [
				'//wp-content/uploads/sites/1/file.jpg',
			],
			'no_wp-content-uploads' => [
				'/subsite1/sites/1/file.jpg',
			],
		];
	}

	/**
	 * @dataProvider get_test_data__is_subdirectory_multisite_path__nope
	 */
	public function test__is_subdirectory_multisite_path__nope( $test_path ) {
		$actual_return = Path_Utils::is_subdirectory_multisite_path( $test_path, 'wp-content/uploads' );

		$this->assertEquals( 0, $actual_return );
	}

	public function get_test_data__is_subdirectory_multisite_path__yep() {
		return [
			'valid_path'             => [
				'/subsite1/wp-content/uploads/sites/1/file.jpg',
			],
			'all_allowed_characters' => [
				'/1s_u-bSIT2e/wp-content/uploads/sites/4567/file.jpg',
			],
		];
	}

	/**
	 * @dataProvider get_test_data__is_subdirectory_multisite_path__yep
	 */
	public function test__is_subdirectory_multisite_path__yep( $test_path ) {
		$actual_return = Path_Utils::is_subdirectory_multisite_path( $test_path, 'wp-content/uploads' );

		$this->assertEquals( 1, $actual_return );
	}

	public function get_test_data__is_sub_subdirectory_multisite_path__nope() {
		return [
			'missing_leading_slash'    => [
				'subsite1/subsite2/wp-content/uploads/sites/1/file.jpg',
			],
			// Can't test for no_subdirectory :)
			'no_sub_subdirectory'      => [
				'/subsite1/wp-content/uploads/sites/1/file.jpg',
			],
			'invalid_subdirectory'     => [
				'/subsité1/wp-content/uploads/sites/1/file.jpg',
			],
			'invalid_sub_subdirectory' => [
				'/subsite1/subsité2/wp-content/uploads/sites/1/file.jpg',
			],
			'empty_subdirectory'       => [
				'//subsite2/wp-content/uploads/sites/1/file.jpg',
			],
			'empty_sub_subdirectory'   => [
				'/subsite1//wp-content/uploads/sites/1/file.jpg',
			],
			'no_wp-content-uploads'    => [
				'/subsite1/subsite2/sites/1/file.jpg',
			],
		];
	}

	/**
	 * @dataProvider get_test_data__is_sub_subdirectory_multisite_path__nope
	 */
	public function test__is_sub_subdirectory_multisite_path__nope( $test_path ) {
		$actual_return = Path_Utils::is_sub_subdirectory_multisite_path( $test_path, 'wp-content/uploads' );

		$this->assertEquals( 0, $actual_return );
	}

	public function get_test_data__is_sub_subdirectory_multisite_path__yep() {
		return [
			'valid_path'             => [
				'/subsite1/subsite2/wp-content/uploads/sites/1/file.jpg',
			],
			'all_allowed_characters' => [
				'/1s_u-bSIT2e/bSIT2e_1s_u-/wp-content/uploads/sites/4567/file.jpg',
			],
		];
	}

	/**
	 * @dataProvider get_test_data__is_sub_subdirectory_multisite_path__yep
	 */
	public function test__is_sub_subdirectory_multisite_path__yep( $test_path ) {
		$actual_return = Path_Utils::is_sub_subdirectory_multisite_path( $test_path, 'wp-content/uploads' );

		$this->assertEquals( 1, $actual_return );
	}

	public function get_test_data__trim_leading_multisite_directory() {
		return [
			'sub_subdirectory'         => [
				'/subsite1/subsite2/wp-content/uploads/sites/10/file.jpg',
				'/wp-content/uploads/sites/10/file.jpg',
			],
			'subdirectory'             => [
				'/subsite1/wp-content/uploads/sites/2/file.jpg',
				'/wp-content/uploads/sites/2/file.jpg',
			],
			'sub_subdirectory_no_site' => [
				'/subsite1/subsite2/wp-content/uploads/file.jpg',
				'/wp-content/uploads/file.jpg',
			],
			'subdirectory_no_site'     => [
				'/subsite1/wp-content/uploads/file.jpg',
				'/wp-content/uploads/file.jpg',
			],
			'other'                    => [
				'',
				false,
			],
		];
	}

	/**
	 * @dataProvider get_test_data__trim_leading_multisite_directory
	 */
	public function test__trim_leading_multisite_directory( $test_path, $expected_result ) {
		$actual_result = Path_Utils::trim_leading_multisite_directory( $test_path, 'wp-content/uploads' );

		$this->assertEquals( $expected_result, $actual_result );
	}
}
