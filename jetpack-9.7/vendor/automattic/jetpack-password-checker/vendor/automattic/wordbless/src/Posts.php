<?php

namespace WorDBless;

class Posts {

	use Singleton, ClearCacheGroup;

	public $posts       = array();
	public $cache_group = 'posts';

	private function __construct() {
		add_filter( 'wp_insert_post_data', array( $this, 'insert_post' ), 10, 3 );
		add_filter( 'wp_insert_attachment_data', array( $this, 'insert_post' ), 10, 3 );

		add_action( 'delete_post', array( $this, 'delete_post' ) );

		add_filter( 'wordbless_wpdb_query_results', array( $this, 'filter_query' ), 10, 2 );

		wp_cache_flush();
	}

	public function filter_query( $query_results, $query ) {
		global $wpdb;
		// this pattern is used in get_post() and in wp_delete_post().
		$pattern = '/^SELECT \* FROM ' . preg_quote( $wpdb->posts ) . ' WHERE ID = (\d+)( LIMIT 1)?$/';
		if ( 1 === preg_match( $pattern, $query, $matches ) ) {
			$post_id = (int) $matches[1];
			if ( isset( $this->posts[ $post_id ] ) ) {
				return array( $this->posts[ $post_id ] );
			}
		}
		return $query_results;
	}

	public function insert_post( $data, $postarr, $unsanitized_postarr = array() ) {

		if ( ! isset( $postarr['ID'] ) || empty( $postarr['ID'] ) || 0 === $postarr['ID'] ) {
			$post_ID = InsertId::bump_and_get();
		} else {
			$post_ID = $postarr['ID'];
		}

		$data['ID'] = $post_ID;

		$_post = (object) sanitize_post( $data, 'raw' );
		wp_cache_add( $post_ID, $_post, 'posts' );
		$this->posts[ $post_ID ] = $_post;
		return $data;
	}

	public function delete_post( $post_ID ) {
		unset( $this->posts[ $post_ID ] );
		wp_cache_delete( $post_ID, 'posts' );
	}

	public function clear_all_posts() {
		$this->posts = array();
	}

	public function clear_all_posts_from_author( $author_id ) {
		$this->posts = array_filter(
			$this->posts,
			function( $post ) use ( $author_id ) {
				return $post->post_author !== $author_id;
			}
		);
		$this->clear_cache_group();
	}

	public function transfer_posts_authorship( $author_id_from, $author_id_to ) {
		$this->posts = array_map(
			function( $post ) use ( $author_id_from, $author_id_to ) {
				if ( $post->post_author === $author_id_from ) {
					$post->post_author = $author_id_to;
				}
				return $post;
			},
			$this->posts
		);
		$this->clear_cache_group();
	}

}
