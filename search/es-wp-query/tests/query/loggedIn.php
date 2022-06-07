<?php

/**
 * Test various queries when the user is logged in. This mainly affects post_status.
 *
 * @group user
 */
class Tests_Query_LoggedIn extends WP_UnitTestCase {
	protected $q;

	function setUp() {
		parent::setUp();

		$i = 0;
		$start_date = strtotime( '2014-01-01' );
		foreach ( get_post_stati() as $status ) {
			$year = 'future' == $status ? 2029 : 2014;
			$date = $start_date + ( DAY_IN_SECONDS * $i++ );

			$this->factory->post->create( array( 'post_status' => $status, 'post_date' => date( $year . '-m-d 00:00:00', $date ) ) );
		}

		es_wp_query_index_test_data();

		unset( $this->q );
		$this->q = new ES_WP_Query();
	}

	function test_query_not_logged_in_default() {
		$posts = $this->q->query( 'posts_per_page=100' );

		// the output should be the only published post
		$expected = array_values( get_post_stati( array( 'public' => true ) ) );
		sort( $expected );

		$actual = wp_list_pluck( $posts, 'post_status' );
		sort( $actual );

		$this->assertEquals( $expected, wp_list_pluck( $posts, 'post_status' ) );
	}

	function test_query_not_logged_in_any_status() {
		$posts = $this->q->query( 'post_status=any&posts_per_page=100' );

		// the output should be the only post statuses not set to exclude from search
		$expected = array_values( get_post_stati( array( 'exclude_from_search' => false ) ) );
		sort( $expected );

		$actual = wp_list_pluck( $posts, 'post_status' );
		sort( $actual );

		$this->assertEquals( $expected, $actual );
	}

	function test_query_not_logged_in_all_statuses() {
		$posts = $this->q->query( array(
			'post_status' => array_values( get_post_stati() ),
			'posts_per_page' => 100,
		) );

		// the output should be the only post statuses not set to exclude from search
		$expected = array_values( get_post_stati() );
		sort( $expected );

		$actual = wp_list_pluck( $posts, 'post_status' );
		sort( $actual );
		$this->assertEquals( $expected, $actual );
	}

	function test_query_admin_logged_in_default() {
		$current_user = get_current_user_id();
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$posts = $this->q->query( 'posts_per_page=100' );

		// the output should be the private and published posts
		$public = array_values( get_post_stati( array( 'public' => true ) ) );
		$private = array_values( get_post_stati( array( 'private' => true ) ) );
		$expected = array_unique( array_merge( $public, $private ) );
		sort( $expected );

		$actual = wp_list_pluck( $posts, 'post_status' );
		sort( $actual );

		$this->assertEquals( $expected, wp_list_pluck( $posts, 'post_status' ) );

		wp_set_current_user( $current_user );
	}

	function test_query_admin_logged_in_any_status() {
		$current_user = get_current_user_id();
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$posts = $this->q->query( 'post_status=any&posts_per_page=100' );

		// the output should be the only post statuses not set to exclude from search
		$expected = array_values( get_post_stati( array( 'exclude_from_search' => false ) ) );
		sort( $expected );

		$actual = wp_list_pluck( $posts, 'post_status' );
		sort( $actual );

		$this->assertEquals( $expected, $actual );

		wp_set_current_user( $current_user );
	}

	function test_query_admin_logged_in_all_statuses() {
		$current_user = get_current_user_id();
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );

		$posts = $this->q->query( array(
			'post_status' => array_values( get_post_stati() ),
			'posts_per_page' => 100,
		) );

		// the output should be the only post statuses not set to exclude from search
		$expected = array_values( get_post_stati() );
		sort( $expected );

		$actual = wp_list_pluck( $posts, 'post_status' );
		sort( $actual );
		$this->assertEquals( $expected, $actual );

		wp_set_current_user( $current_user );
	}

	function test_query_subscriber_logged_in_default() {
		$current_user = get_current_user_id();
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );

		$posts = $this->q->query( 'posts_per_page=100' );

		// the output should be the only published post
		$expected = array_values( get_post_stati( array( 'public' => true ) ) );
		sort( $expected );

		$actual = wp_list_pluck( $posts, 'post_status' );
		sort( $actual );

		$this->assertEquals( $expected, wp_list_pluck( $posts, 'post_status' ) );

		wp_set_current_user( $current_user );
	}

	function test_query_subscriber_logged_in_any_status() {
		$current_user = get_current_user_id();
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );

		$posts = $this->q->query( 'post_status=any&posts_per_page=100' );

		// the output should be the only post statuses not set to exclude from search
		$expected = array_values( get_post_stati( array( 'exclude_from_search' => false ) ) );
		sort( $expected );

		$actual = wp_list_pluck( $posts, 'post_status' );
		sort( $actual );

		$this->assertEquals( $expected, $actual );

		wp_set_current_user( $current_user );
	}

	function test_query_subscriber_logged_in_all_statuses() {
		$current_user = get_current_user_id();
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'subscriber' ) ) );

		$posts = $this->q->query( array(
			'post_status' => array_values( get_post_stati() ),
			'posts_per_page' => 100,
		) );

		// the output should be the only post statuses not set to exclude from search
		$expected = array_values( get_post_stati() );
		sort( $expected );

		$actual = wp_list_pluck( $posts, 'post_status' );
		sort( $actual );
		$this->assertEquals( $expected, $actual );

		wp_set_current_user( $current_user );
	}

}
