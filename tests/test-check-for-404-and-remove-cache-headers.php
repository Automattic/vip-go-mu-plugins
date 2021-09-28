<?php

class VIP_Go_Test_Check_For_404_And_Remove_Cache_Headers extends WP_UnitTestCase {

	public static function set_up_before_class() {
		parent::set_up_before_class();
		require_once( __DIR__ . '/../misc.php' );
	}

	public function tear_down() {
		parent::tear_down();
		wp_reset_query();
	}

	/**
	 * Test cache headers removal if page is 404.
	 *
	 * @dataProvider data_provider
	 * @param bool $is_wp_query_set Is the global $wp_query object set for a particular test case.
	 * @param bool $is_404_set Is this page a 404.
	 */
	function test_check_for_404_and_remove_cache_headers( $is_wp_query_set, $is_404_set, $expected_headers ) {
		global $wp_query;
		$headers = array(
			'Expires'       => 1,
			'Cache-Control' => 1,
			'Pragma'        => 1,
		);

		if ( $is_404_set ) {
			$wp_query->set_404();
		}
		if ( ! $is_wp_query_set ) {
			$wp_query = null;
		}

		$result = wpcom_vip_check_for_404_and_remove_cache_headers( $headers );
		$this->assertEquals( $result, $expected_headers );

	}

	/**
	 * The data provider method
	 *
	 * @return array
	 */
	function data_provider() {
		return array(
			'wp_query-set-and-is_404'         => array(
				true,
				true,
				array(),
			),
			'wp_query-set-and-not-is_404'     => array(
				true,
				false,
				array(
					'Expires'       => 1,
					'Cache-Control' => 1,
					'Pragma'        => 1,
				),
			),
			'wp_query-not-set-and-not-is_404' => array(
				false,
				false,
				array(
					'Expires'       => 1,
					'Cache-Control' => 1,
					'Pragma'        => 1,
				),
			),
			'wp_query-not-set-and-is_404'     => array(
				false,
				true,
				array(
					'Expires'       => 1,
					'Cache-Control' => 1,
					'Pragma'        => 1,
				),
			),
		);
	}

}
