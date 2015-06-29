<?php

/**
 *
 * Test the Shoehorn. This is when you use a normal WP_Query object, but set 'es' => true in the arguments.
 * We're testing against a known data set, so we can check that specific posts are included in the output.
 *
 * @group query
 */
class Tests_Query_Shoehorn extends WP_UnitTestCase {

	public $q;

	public function setUp() {
		parent::setUp();

		$cat_a = $this->factory->term->create( array( 'taxonomy' => 'category', 'name' => 'cat-a' ) );
		$cat_b = $this->factory->term->create( array( 'taxonomy' => 'category', 'name' => 'cat-b' ) );
		$cat_c = $this->factory->term->create( array( 'taxonomy' => 'category', 'name' => 'cat-c' ) );

		$this->factory->post->create( array( 'post_title' => 'tag-נ', 'tags_input' => array( 'tag-נ' ), 'post_date' => '2008-11-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'cats-a-b-c', 'post_date' => '2008-12-01 00:00:00', 'post_category' => array( $cat_a, $cat_b, $cat_c ) ) );
		$this->factory->post->create( array( 'post_title' => 'cats-a-and-b', 'post_date' => '2009-01-01 00:00:00', 'post_category' => array( $cat_a, $cat_b ) ) );
		$this->factory->post->create( array( 'post_title' => 'cats-b-and-c', 'post_date' => '2009-02-01 00:00:00', 'post_category' => array( $cat_b, $cat_c ) ) );
		$this->factory->post->create( array( 'post_title' => 'cats-a-and-c', 'post_date' => '2009-03-01 00:00:00', 'post_category' => array( $cat_a, $cat_c ) ) );
		$this->factory->post->create( array( 'post_title' => 'cat-a', 'post_date' => '2009-04-01 00:00:00', 'post_category' => array( $cat_a ) ) );
		$this->factory->post->create( array( 'post_title' => 'cat-b', 'post_date' => '2009-05-01 00:00:00', 'post_category' => array( $cat_b ) ) );
		$this->factory->post->create( array( 'post_title' => 'cat-c', 'post_date' => '2009-06-01 00:00:00', 'post_category' => array( $cat_c ) ) );
		$this->factory->post->create( array( 'post_title' => 'lorem-ipsum', 'post_date' => '2009-07-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'comment-test', 'post_date' => '2009-08-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'one-trackback', 'post_date' => '2009-09-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'many-trackbacks', 'post_date' => '2009-10-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'no-comments', 'post_date' => '2009-10-02 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'one-comment', 'post_date' => '2009-11-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'contributor-post-approved', 'post_date' => '2009-12-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'embedded-video', 'post_date' => '2010-01-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'simple-markup-test', 'post_date' => '2010-02-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'raw-html-code', 'post_date' => '2010-03-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'tags-a-b-c', 'tags_input' => array( 'tag-a', 'tag-b', 'tag-c' ), 'post_date' => '2010-04-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'tag-a', 'tags_input' => array( 'tag-a' ), 'post_date' => '2010-05-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'tag-b', 'tags_input' => array( 'tag-b' ), 'post_date' => '2010-06-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'tag-c', 'tags_input' => array( 'tag-c' ), 'post_date' => '2010-07-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'tags-a-and-b', 'tags_input' => array( 'tag-a', 'tag-b' ), 'post_date' => '2010-08-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'tags-b-and-c', 'tags_input' => array( 'tag-b', 'tag-c' ), 'post_date' => '2010-09-01 00:00:00' ) );
		$this->factory->post->create( array( 'post_title' => 'tags-a-and-c', 'tags_input' => array( 'tag-a', 'tag-c' ), 'post_date' => '2010-10-01 00:00:00' ) );

		$this->parent_one = $this->factory->post->create( array( 'post_title' => 'parent-one', 'post_date' => '2007-01-01 00:00:00' ) );
		$this->parent_two = $this->factory->post->create( array( 'post_title' => 'parent-two', 'post_date' => '2007-01-01 00:00:00' ) );
		$this->parent_three = $this->factory->post->create( array( 'post_title' => 'parent-three', 'post_date' => '2007-01-01 00:00:00' ) );
		$this->child_one = $this->factory->post->create( array( 'post_title' => 'child-one', 'post_parent' => $this->parent_one, 'post_date' => '2007-01-01 00:00:01' ) );
		$this->child_two = $this->factory->post->create( array( 'post_title' => 'child-two', 'post_parent' => $this->parent_one, 'post_date' => '2007-01-01 00:00:02' ) );
		$this->child_three = $this->factory->post->create( array( 'post_title' => 'child-three', 'post_parent' => $this->parent_two, 'post_date' => '2007-01-01 00:00:03' ) );
		$this->child_four = $this->factory->post->create( array( 'post_title' => 'child-four', 'post_parent' => $this->parent_two, 'post_date' => '2007-01-01 00:00:04' ) );

		es_wp_query_index_test_data();

		unset( $this->q );
		$this->q = new WP_Query();
	}

	function test_wp_query_default() {
		$posts = $this->q->query('');

		// the output should be the most recent 10 posts as listed here
		$expected = array(
			0 => 'tags-a-and-c',
			1 => 'tags-b-and-c',
			2 => 'tags-a-and-b',
			3 => 'tag-c',
			4 => 'tag-b',
			5 => 'tag-a',
			6 => 'tags-a-b-c',
			7 => 'raw-html-code',
			8 => 'simple-markup-test',
			9 => 'embedded-video',
		);

		$this->assertEquals( $expected, wp_list_pluck( $posts, 'post_name' ) );
		$this->assertEquals( 0, substr_count( $this->q->request, 'ES_WP_Query Shoehorn' ) );
	}

	function test_wp_query() {
		$posts = $this->q->query( 'es=true' );

		// the output should be the most recent 10 posts as listed here
		$expected = array(
			0 => 'tags-a-and-c',
			1 => 'tags-b-and-c',
			2 => 'tags-a-and-b',
			3 => 'tag-c',
			4 => 'tag-b',
			5 => 'tag-a',
			6 => 'tags-a-b-c',
			7 => 'raw-html-code',
			8 => 'simple-markup-test',
			9 => 'embedded-video',
		);

		$this->assertEquals( $expected, wp_list_pluck( $posts, 'post_name' ) );
		$this->assertEquals( 1, substr_count( $this->q->request, 'ES_WP_Query Shoehorn' ) );
	}

	function test_wp_query_posts_per_page() {
		$posts = $this->q->query('posts_per_page=5&es=true');

		$expected = array (
			0 => 'tags-a-and-c',
			1 => 'tags-b-and-c',
			2 => 'tags-a-and-b',
			3 => 'tag-c',
			4 => 'tag-b',
		);

		$this->assertCount( 5, $posts );
		$this->assertEquals( $expected, wp_list_pluck( $posts, 'post_name' ) );
		$this->assertEquals( 1, substr_count( $this->q->request, 'ES_WP_Query Shoehorn' ) );
	}

	function test_wp_query_offset() {
		$posts = $this->q->query('offset=2&es=true');

		$expected = array (
			0 => 'tags-a-and-b',
			1 => 'tag-c',
			2 => 'tag-b',
			3 => 'tag-a',
			4 => 'tags-a-b-c',
			5 => 'raw-html-code',
			6 => 'simple-markup-test',
			7 => 'embedded-video',
			8 => 'contributor-post-approved',
			9 => 'one-comment',
		);

		$this->assertCount( 10, $posts );
		$this->assertEquals( $expected, wp_list_pluck( $posts, 'post_name' ) );
		$this->assertEquals( 1, substr_count( $this->q->request, 'ES_WP_Query Shoehorn' ) );
	}

	function test_wp_query_paged() {
		$posts = $this->q->query('paged=2&es=true');

		$expected = array (
			0 => 'contributor-post-approved',
			1 => 'one-comment',
			2 => 'no-comments',
			3 => 'many-trackbacks',
			4 => 'one-trackback',
			5 => 'comment-test',
			6 => 'lorem-ipsum',
			7 => 'cat-c',
			8 => 'cat-b',
			9 => 'cat-a',
		);

		$this->assertCount( 10, $posts );
		$this->assertTrue( $this->q->is_paged() );
		$this->assertEquals( $expected, wp_list_pluck( $posts, 'post_name' ) );
		$this->assertEquals( 1, substr_count( $this->q->request, 'ES_WP_Query Shoehorn' ) );
	}

	function test_wp_query_paged_and_posts_per_page() {
		$posts = $this->q->query('paged=4&posts_per_page=4&es=true');

		$expected = array (
			0 => 'no-comments',
			1 => 'many-trackbacks',
			2 => 'one-trackback',
			3 => 'comment-test',
		);

		$this->assertCount( 4, $posts );
		$this->assertTrue( $this->q->is_paged() );
		$this->assertEquals( $expected, wp_list_pluck( $posts, 'post_name' ) );
		$this->assertEquals( 1, substr_count( $this->q->request, 'ES_WP_Query Shoehorn' ) );
	}

	/**
	 * @ticket 18897
	 */
	function test_wp_query_offset_and_paged() {
		$posts = $this->q->query('paged=2&offset=3&es=true');

		$expected = array (
			0 => 'many-trackbacks',
			1 => 'one-trackback',
			2 => 'comment-test',
			3 => 'lorem-ipsum',
			4 => 'cat-c',
			5 => 'cat-b',
			6 => 'cat-a',
			7 => 'cats-a-and-c',
			8 => 'cats-b-and-c',
			9 => 'cats-a-and-b',
		);

		$this->assertCount( 10, $posts );
		$this->assertTrue( $this->q->is_paged() );
		$this->assertEquals( $expected, wp_list_pluck( $posts, 'post_name' ) );
		$this->assertEquals( 1, substr_count( $this->q->request, 'ES_WP_Query Shoehorn' ) );
	}

	function test_wp_query_no_results() {
		$posts = $this->q->query( 'year=2000&es=true' );

		$this->assertEmpty( $posts );
		$this->assertEquals( 1, substr_count( $this->q->request, 'ES_WP_Query Shoehorn' ) );
	}

	function test_wp_query_rule_changes() {
		global $wp_post_types;
		foreach ( array_keys( $wp_post_types ) as $slug )
			$wp_post_types[$slug]->exclude_from_search = true;

		$posts = $this->q->query( array( 'post_type' => 'any', 'es' => true ) );

		$this->assertEmpty( $posts );
		$this->assertEquals( 1, substr_count( $this->q->request, 'ES_WP_Query Shoehorn' ) );

		foreach ( array_keys( $wp_post_types ) as $slug )
			$wp_post_types[$slug]->exclude_from_search = false;

		$posts2 = $this->q->query( array( 'post_type' => 'any', 'es' => true ) );

		$this->assertNotEmpty( $posts2 );
		$this->assertEquals( 1, substr_count( $this->q->request, 'ES_WP_Query Shoehorn' ) );
	}

	/**
	 * Same query is run multiple times via WP_Query::get_posts().
	 */
	function test_wp_query_multiple_get_posts() {
		$expected = array(
			'tags-a-and-c',
			'tags-a-and-b',
			'tag-a',
			'tags-a-b-c'
		);

		$posts = $this->q->query( 'tag=tag-a&es=true' );
		$this->assertCount( 4, $posts );
		$this->assertEquals( $expected, wp_list_pluck( $posts, 'post_name' ) );
		$this->assertEquals( 1, substr_count( $this->q->request, 'ES_WP_Query Shoehorn' ) );

		$posts = $this->q->get_posts();
		$this->assertCount( 4, $posts );
		$this->assertEquals( $expected, wp_list_pluck( $posts, 'post_name' ) );
		$this->assertEquals( 1, substr_count( $this->q->request, 'ES_WP_Query Shoehorn' ) );

		$posts = $this->q->get_posts();
		$this->assertCount( 4, $posts );
		$this->assertEquals( $expected, wp_list_pluck( $posts, 'post_name' ) );
		$this->assertEquals( 1, substr_count( $this->q->request, 'ES_WP_Query Shoehorn' ) );
	}

	/**
	 * Same query object used multiple times, but with different query vars.
	 */
	function test_wp_query_change_query_vars() {
		$posts = $this->q->query( 'tag=tag-b&es=true' );
		$expected = array(
			'tags-b-and-c',
			'tags-a-and-b',
			'tag-b',
			'tags-a-b-c'
		);
		$this->assertCount( 4, $posts );
		$this->assertEquals( $expected, wp_list_pluck( $posts, 'post_name' ) );
		$this->assertEquals( 1, substr_count( $this->q->request, 'ES_WP_Query Shoehorn' ) );

		$posts = $this->q->query( 'tag=tag-c&es=true' );
		$expected = array(
			'tags-a-and-c',
			'tags-b-and-c',
			'tag-c',
			'tags-a-b-c'
		);
		$this->assertCount( 4, $posts );
		$this->assertEquals( $expected, wp_list_pluck( $posts, 'post_name' ) );
		$this->assertEquals( 1, substr_count( $this->q->request, 'ES_WP_Query Shoehorn' ) );
	}

	function test_wp_query_return_ids() {
		$posts = $this->q->query( array(
			'm'       => '20070101000000',
			'fields'  => 'ids',
			'orderby' => 'ID',
			'order'   => 'ASC',
			'es'      => true
		) );
		$expected = array(
			$this->parent_one,
			$this->parent_two,
			$this->parent_three
		);

		$this->assertCount( 3, $posts );
		$this->assertEquals( $expected, $posts );
		$this->assertEquals( 1, substr_count( $this->q->request, 'ES_WP_Query Shoehorn' ) );
	}

	function test_wp_query_return_ids_parents() {
		$posts = $this->q->query( array(
			'post_parent__in' => array( $this->parent_one, $this->parent_two ),
			'fields'          => 'id=>parent',
			'orderby'         => 'date',
			'order'           => 'ASC',
			'es'              => true
		) );
		$expected = array(
			$this->child_one   => $this->parent_one,
			$this->child_two   => $this->parent_one,
			$this->child_three => $this->parent_two,
			$this->child_four  => $this->parent_two,
		);

		$this->assertCount( 4, $posts );
		$this->assertEquals( $expected, $posts );
		$this->assertEquals( 1, substr_count( $this->q->request, 'ES_WP_Query Shoehorn' ) );
	}

	function _run_another_basic_query( &$query ) {
		if ( 2 == $query->get( 'es' ) ) {
			$another_query = new WP_Query;
			$posts = $another_query->query( 'category_name=cat-a' );
			$expected = array(
				'cat-a',
				'cats-a-and-b',
				'cats-a-and-c',
				'cats-a-b-c'
			);
			$this->assertCount( 4, $posts );
			$this->assertEquals( $expected, wp_list_pluck( $posts, 'post_name' ) );
			$this->assertEquals( 0, substr_count( $another_query->request, 'ES_WP_Query Shoehorn' ) );
		}
	}

	function _run_another_es_query( &$query ) {
		if ( 2 == $query->get( 'es' ) ) {
			$another_query = new WP_Query;
			$posts = $another_query->query( 'category_name=cat-a&es=true' );
			$expected = array(
				'cat-a',
				'cats-a-and-b',
				'cats-a-and-c',
				'cats-a-b-c'
			);
			$this->assertCount( 4, $posts );
			$this->assertEquals( $expected, wp_list_pluck( $posts, 'post_name' ) );
			$this->assertEquals( 0, substr_count( $another_query->request, 'ES_WP_Query Shoehorn' ) );
		}
	}

	function test_wp_query_mixed_queries() {
		add_action( 'pre_get_posts', array( $this, '_run_another_basic_query' ), 1001 );
		add_action( 'pre_get_posts', array( $this, '_run_another_es_query' ), 1001 );

		$posts = $this->q->query( 'category_name=cat-b&es=2' );
		$expected = array(
			'cat-b',
			'cats-b-and-c',
			'cats-a-and-b',
			'cats-a-b-c'
		);

		$this->assertCount( 4, $posts );
		$this->assertEquals( $expected, wp_list_pluck( $posts, 'post_name' ) );
		$this->assertEquals( 1, substr_count( $this->q->request, 'ES_WP_Query Shoehorn' ) );

		remove_action( 'pre_get_posts', array( $this, '_run_another_basic_query' ), 1001 );
		remove_action( 'pre_get_posts', array( $this, '_run_another_es_query' ), 1001 );
	}

	function test_wp_query_data_changes_between_queries() {

		$posts = $this->q->query( 'tag=tag-a&es=true' );
		$expected = array(
			'tags-a-and-c',
			'tags-a-and-b',
			'tag-a',
			'tags-a-b-c'
		);
		$this->assertCount( 4, $posts );
		$this->assertEquals( $expected, wp_list_pluck( $posts, 'post_name' ) );
		$this->assertEquals( 1, substr_count( $this->q->request, 'ES_WP_Query Shoehorn' ) );

		$this->factory->post->create( array( 'post_title' => 'between_queries', 'tags_input' => array( 'tag-a' ), 'post_date' => '2010-11-01 00:00:00' ) );
		es_wp_query_index_test_data();

		$posts = $this->q->get_posts();
		$expected = array(
			'between_queries',
			'tags-a-and-c',
			'tags-a-and-b',
			'tag-a',
			'tags-a-b-c'
		);
		$this->assertCount( 5, $posts );
		$this->assertEquals( $expected, wp_list_pluck( $posts, 'post_name' ) );
		$this->assertEquals( 1, substr_count( $this->q->request, 'ES_WP_Query Shoehorn' ) );
	}

}