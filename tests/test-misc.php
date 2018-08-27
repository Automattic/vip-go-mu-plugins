<?php

class VIP_Go_Misc_Test extends WP_UnitTestCase {

	public static function setUpBeforeClass() {
		parent::setUpBeforeClass();
		require_once( __DIR__ . '/../misc.php' );
	}

	/**
	 * Test cache headers removal if page is 404.
	 *
	 * @dataProvider data_provider
	 * @param bool $is_wp_query_set Is the global $wp_query object set for a particular test case.
	 * @param bool $is_404_set Is this page a 404.
	 */
	function test_check_for_404_and_remove_cache_headers( $is_wp_query_set, $is_404_set ) {
		global $wp_query;
		$headers = array(
			'Expires'       => 1,
			'Cache-Control' => 1,
			'Pragma'        => 1,
		);

		$temp = null;
		if ( $is_404_set ) {
			$wp_query->set_404();
		}
		if ( ! $is_wp_query_set ) {
			$temp     = $wp_query;
			$wp_query = null;
		}

		$result = wpcom_vip_check_for_404_and_remove_cache_headers( $headers );
		$this->assertEquals( empty( $result ), ( $is_404_set && $is_wp_query_set ) );
		if ( ! isset( $wp_query ) ) {
			$wp_query = $temp;
		}
	}

	/**
	 * The data provider method
	 *
	 * @return array
	 */
	function data_provider() {
		return array(
			array(
				true,
				true,
			),
			array(
				true,
				false,
			),
			array(
				false,
				false,
			),
			array(
				false,
				true,
			),
		);
	}

}
