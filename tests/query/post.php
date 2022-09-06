<?php

/**
 * @group meta
 */
class Tests_Query_Post extends WP_UnitTestCase {
	function setUp() {
		parent::setUp();
	}

	function test_meta_key_or_query() {
		$post_id = $this->factory->post->create();
		add_post_meta( $post_id, 'foo', rand_str() );
		add_post_meta( $post_id, 'foo', rand_str() );
		$post_id2 = $this->factory->post->create();
		add_post_meta( $post_id2, 'bar', 'val2' );
		$post_id3 = $this->factory->post->create();
		add_post_meta( $post_id3, 'baz', rand_str() );
		$post_id4 = $this->factory->post->create();
		add_post_meta( $post_id4, 'froo', rand_str() );
		$post_id5 = $this->factory->post->create();
		add_post_meta( $post_id5, 'tango', 'val2' );
		$post_id6 = $this->factory->post->create();
		add_post_meta( $post_id6, 'bar', 'val1' );

		es_wp_query_index_test_data();

		$query = new ES_WP_Query( array(
			'meta_query' => array(
				array(
					'key' => 'foo'
				),
				array(
					'key' => 'bar',
					'value' => 'val2'
				),
				array(
					'key' => 'baz'
				),
				array(
					'key' => 'froo'
				),
				'relation' => 'OR',
			),
		) );

		$posts = $query->get_posts();
		$this->assertEquals( 4, count( $posts ) );
		foreach ( $posts as $post ) {
			$this->assertInstanceOf( 'WP_Post', $post );
			$this->assertEquals( 'raw', $post->filter );
		}

		$post_ids = wp_list_pluck( $posts, 'ID' );
		$this->assertEqualSets( array( $post_id, $post_id2, $post_id3, $post_id4 ), $post_ids );
	}

	function test_meta_key_and_query() {
		$post_id = $this->factory->post->create();
		add_post_meta( $post_id, 'foo', rand_str() );
		add_post_meta( $post_id, 'foo', rand_str() );
		$post_id2 = $this->factory->post->create();
		add_post_meta( $post_id2, 'bar', 'val2' );
		add_post_meta( $post_id2, 'foo', rand_str() );
		$post_id3 = $this->factory->post->create();
		add_post_meta( $post_id3, 'baz', rand_str() );
		$post_id4 = $this->factory->post->create();
		add_post_meta( $post_id4, 'froo', rand_str() );
		$post_id5 = $this->factory->post->create();
		add_post_meta( $post_id5, 'tango', 'val2' );
		$post_id6 = $this->factory->post->create();
		add_post_meta( $post_id6, 'bar', 'val1' );
		add_post_meta( $post_id6, 'foo', rand_str() );
		$post_id7 = $this->factory->post->create();
		add_post_meta( $post_id7, 'foo', rand_str() );
		add_post_meta( $post_id7, 'froo', rand_str() );
		add_post_meta( $post_id7, 'baz', rand_str() );
		add_post_meta( $post_id7, 'bar', 'val2' );

		es_wp_query_index_test_data();

		$query = new ES_WP_Query( array(
			'meta_query' => array(
				array(
					'key' => 'foo'
				),
				array(
					'key' => 'bar',
					'value' => 'val2'
				),
				array(
					'key' => 'baz'
				),
				array(
					'key' => 'froo'
				),
				'relation' => 'AND',
			),
		) );

		$posts = $query->get_posts();
		$this->assertEquals( 1, count( $posts ) );
		foreach ( $posts as $post ) {
			$this->assertInstanceOf( 'WP_Post', $post );
			$this->assertEquals( 'raw', $post->filter );
		}

		$post_ids = wp_list_pluck( $posts, 'ID' );
		$this->assertEquals( array( $post_id7 ), $post_ids );

		$query = new ES_WP_Query( array(
			'meta_query' => array(
				array(
					'key' => 'foo'
				),
				array(
					'key' => 'bar',
				),
				'relation' => 'AND',
			),
		) );

		$posts = $query->get_posts();
		$this->assertEquals( 3, count( $posts ) );
		foreach ( $posts as $post ) {
			$this->assertInstanceOf( 'WP_Post', $post );
			$this->assertEquals( 'raw', $post->filter );
		}

		$post_ids = wp_list_pluck( $posts, 'ID' );
		$this->assertEqualSets( array( $post_id2, $post_id6, $post_id7 ), $post_ids );
	}

	/**
	 * @ticket 18158
	 */
	function test_meta_key_not_exists() {
		$post_id = $this->factory->post->create();
		add_post_meta( $post_id, 'foo', rand_str() );
		$post_id2 = $this->factory->post->create();
		add_post_meta( $post_id2, 'bar', rand_str() );
		$post_id3 = $this->factory->post->create();
		add_post_meta( $post_id3, 'bar', rand_str() );
		$post_id4 = $this->factory->post->create();
		add_post_meta( $post_id4, 'baz', rand_str() );
		$post_id5 = $this->factory->post->create();
		add_post_meta( $post_id5, 'foo', rand_str() );

		es_wp_query_index_test_data();

		$query = new ES_WP_Query( array(
			'meta_query' => array(
			array(
				'key' => 'foo',
				'compare' => 'NOT EXISTS',
			),
			),
		) );

		$posts = $query->get_posts();
		$this->assertEquals( 3, count( $posts ) );
		foreach ( $posts as $post ) {
			$this->assertInstanceOf( 'WP_Post', $post );
			$this->assertEquals( 'raw', $post->filter );
		}

		$query = new ES_WP_Query( array(
			'meta_query' => array(
			array(
				'key' => 'foo',
				'compare' => 'NOT EXISTS',
			),
				array(
				'key' => 'bar',
				'compare' => 'NOT EXISTS',
			),
			),
		) );

		$posts = $query->get_posts();
		$this->assertEquals( 1, count( $posts ) );
		foreach ( $posts as $post ) {
			$this->assertInstanceOf( 'WP_Post', $post );
			$this->assertEquals( 'raw', $post->filter );
		}

		$query = new ES_WP_Query( array(
			'meta_query' => array(
			array(
				'key' => 'foo',
				'compare' => 'NOT EXISTS',
			),
				array(
				'key' => 'bar',
				'compare' => 'NOT EXISTS',
			),
				array(
				'key' => 'baz',
				'compare' => 'NOT EXISTS',
			),
			)
		) );

		$posts = $query->get_posts();
		$this->assertEquals( 0, count( $posts ) );
	}


	function test_meta_query_decimal_ordering() {
		$post_1 = $this->factory->post->create();
		$post_2 = $this->factory->post->create();
		$post_3 = $this->factory->post->create();
		$post_4 = $this->factory->post->create();
		$post_5 = $this->factory->post->create();

		update_post_meta( $post_1, 'numeric_value', '1' );
		update_post_meta( $post_2, 'numeric_value', '200' );
		update_post_meta( $post_3, 'numeric_value', '30' );
		update_post_meta( $post_4, 'numeric_value', '400.5' );
		update_post_meta( $post_5, 'numeric_value', '400.499' );

		es_wp_query_index_test_data();

		$query = new ES_WP_Query( array(
			'orderby'   => 'meta_value',
			'order'     => 'DESC',
			'meta_key'  => 'numeric_value',
			'meta_type' => 'DECIMAL'
		) );
		$this->assertEquals( array( $post_4, $post_5, $post_2, $post_3, $post_1 ), wp_list_pluck( $query->posts, 'ID' ) );
	}

	/**
	 * @ticket 20604
	 */
	function test_taxonomy_empty_or() {
		// An empty tax query should return an empty array, not all posts.

		$this->factory->post->create_many( 10 );

		es_wp_query_index_test_data();

		$query = new ES_WP_Query( array(
			'fields'	=> 'ids',
			'tax_query' => array(
			'relation' => 'OR',
			array(
				'taxonomy' => 'post_tag',
				'field' => 'id',
				'terms' => false,
				'operator' => 'IN'
			),
			array(
				'taxonomy' => 'category',
				'field' => 'id',
				'terms' => false,
				'operator' => 'IN'
			)
			)
		) );

		$posts = $query->get_posts();
		$this->assertEquals( 0 , count( $posts ) );
	}
}
