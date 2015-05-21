<?php

// Mock the Jetpack class and it's is_active method
class Jetpack {

	/**
	 * Is Jetpack active?
	 */
	public static function is_active() {
		return true;
	}

}

/**
 * @group vip_helpers
 */
class VIPHelpersStatsTest extends WP_UnitTestCase {

	function test_wpcom_vip_top_posts_array() {

		// ARRANGE

		// Mock the Jetpack stats function
		function stats_get_csv() {
			return array(
				array(
					'post_id'        => '0',
					'post_title'     => 'Home page',
					'post_permalink' => 'http://jetpack.example.invalid/"',
					'views'          => '17',
				),
				array(
					'post_id'        => '1',
					'post_title'     => 'Hello world!',
					'post_permalink' => 'http://jetpack.example.invalid/2015/05/hello-world/"',
					'views'          => '16',
				),
			);
		}

		// ACT

		$stats = wpcom_vip_top_posts_array();
		$first_post  = $stats[0];

		// ASSERT

		$this->assertTrue( true );
		$this->assertTrue( is_array( $stats ) );
		$this->assertEquals( 2, count( $stats ) );

		$this->assertArrayHasKey( 'post_id', $first_post );
		$this->assertArrayHasKey( 'post_title', $first_post );
		$this->assertArrayHasKey( 'post_permalink', $first_post );
		$this->assertArrayHasKey( 'views', $first_post );

		$this->assertInternalType('int', $first_post['post_id']);
		$this->assertInternalType('string', $first_post['post_title']);
		$this->assertInternalType('string', $first_post['post_permalink']);
		$this->assertInternalType('int', $first_post['views']);

		$this->assertGreaterThanOrEqual(0, $first_post['post_id']);
		$this->assertGreaterThanOrEqual(0, $first_post['views']);
	}
}
