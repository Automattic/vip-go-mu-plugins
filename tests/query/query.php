<?php

class Tests_Post_Query extends WP_UnitTestCase {

	/**
	 *
	 * @ticket 17065
	 */
	function test_orderby_array() {
		global $wpdb;

		$q1 = new ES_WP_Query( array(
			'orderby' => array(
				'type' => 'DESC',
				'name' => 'ASC'
			)
		) );
		$this->assertEquals( 'desc', $q1->es_args['sort'][0]['post_type'] );
		$this->assertEquals( 'asc', $q1->es_args['sort'][1]['post_name'] );

		$q2 = new ES_WP_Query( array( 'orderby' => array() ) );
		$this->assertFalse( isset( $q2->es_args['sort'] ) );

		$q3 = new ES_WP_Query( array( 'post_type' => 'post' ) );
		$this->assertEquals( 'desc', $q3->es_args['sort'][0]['post_date.date'] );
	}

	/**
	 *
	 * @ticket 17065
	 */
	function test_order() {
		global $wpdb;

		$q1 = new ES_WP_Query( array(
			'orderby' => array(
				'post_type' => 'foo'
			)
		) );
		$this->assertEquals( 'desc', $q1->es_args['sort'][0]['post_type'] );

		$q2 = new ES_WP_Query( array(
			'orderby' => 'title',
			'order'   => 'foo'
		) );
		$this->assertEquals( 'desc', $q2->es_args['sort'][0]['post_title'] );

		$q3 = new ES_WP_Query( array(
			'order' => 'asc'
		) );
		$this->assertEquals( 'asc', $q3->es_args['sort'][0]['post_date.date'] );
	}

	/**
	 * @ticket 29629
	 */
	function test_orderby() {
		// 'none' is a valid value
		$q3 = new ES_WP_Query( array( 'orderby' => 'none' ) );
		$this->assertFalse( isset( $q3->es_args['sort'] ) );

		// false is a valid value
		$q4 = new ES_WP_Query( array( 'orderby' => false ) );
		$this->assertFalse( isset( $q4->es_args['sort'] ) );

		// empty array() is a valid value
		$q5 = new ES_WP_Query( array( 'orderby' => array() ) );
		$this->assertFalse( isset( $q5->es_args['sort'] ) );
	}
}
