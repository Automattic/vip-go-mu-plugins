<?php

class Lift_Post_Update_Watcher {

	public static function get_watched_post_types() {
		return apply_filters( 'lift_watched_post_types', Lift_Search::get_indexed_post_types() );
	}

	public static function get_watched_post_fields( $post_type ) {
		return apply_filters( 'lift_watch_post_fields', Lift_Search::get_indexed_post_fields( $post_type ), $post_type);
	}

	public static function check_post_validity( $post_status, $post_type ) {
		return (!in_array( $post_status, array( 'open', 'auto-draft' ) ) && in_array( $post_type, Lift_Post_Update_Watcher::get_watched_post_types() ) );
	}

	public static function init() {

		//catch new posts
		add_filter( 'wp_insert_post_data', function($data, $postarr) {
				if ( Lift_Post_Update_Watcher::check_post_validity( $data['post_status'], $data['post_type'] ) ) {

					if ( empty( $postarr['ID'] ) ) {
						//new post, so lets queue the entire post to be saved, since there is no
						//specific hook in wp to know it is an insert, we have to do some trickery
						add_action( 'save_post', function($post_id, $post) {
								//check to make sure this isn't a revision insert
								$_post = $post;
								if ( in_array( get_post_type( $post_id ), Lift_Post_Update_Watcher::get_watched_post_types() ) ) {
									Lift_Post_Update_Watcher::queue_entire_post( $post_id );
									remove_action( 'save_post', __FUNCTION__ );
								}
							}, 1, 2 );
					}
				}
				return $data;
			}, 10, 2 );


		//catch updates
		add_action( 'post_updated', function( $post_id, $post_after, $post_before ) {
				$post_status = get_post_status( $post_id );
				$post_type = get_post_type( $post_id );
				if ( Lift_Post_Update_Watcher::check_post_validity( $post_status, $post_type ) ) {
					if ( $post_before->post_status == 'auto-draft' ) {
						Lift_Post_Update_Watcher::queue_entire_post( $post_id );
					} else {
						foreach ( Lift_Post_Update_Watcher::get_watched_post_fields( $post_type ) as $field_name ) {
							if ( ( isset( $post_after->$field_name ) && isset( $post_before->$field_name ) && $post_after->$field_name != $post_before->$field_name )
								|| ( empty( $post_after->$field_name ) xor empty( $post_before->$field_name ) )
							) {
								lift_queue_field_update( $post_id, $field_name, 'post' );
							}
						}
					}
				}
			}, 10, 3 );

		add_action( 'delete_post', function($post_id) {
				$post_status = get_post_status( $post_id );
				$post_type = get_post_type( $post_id );
				if ( Lift_Post_Update_Watcher::check_post_validity( $post_status, $post_type ) ) {
					lift_queue_deletion( $post_id, 'post' );
				}
			}, 10, 2 );
	}

	public static function queue_entire_post( $post_id ) {
		$post_type = get_post_type( $post_id );

		foreach ( self::get_watched_post_fields( $post_type ) as $field_name ) {
			lift_queue_field_update( $post_id, $field_name, 'post' );
		}

		do_action( 'lift_queue_entire_post', $post_id );
	}

}

class Lift_Post_Meta_Update_Watcher {

	public static function get_watched_meta_keys( $post_type ) {
		return apply_filters( 'lift_watched_meta_keys', array( ), $post_type );
	}

	public static function init() {

		//enqueue all watched meta fields if the entire post is enqueued
		add_action( 'lift_queue_entire_post', function($post_id) {
				$post_type = get_post_type( $post_id );
				$meta_keys = Lift_Post_Meta_Update_Watcher::get_watched_meta_keys( $post_type );
				foreach ( $meta_keys as $meta_key ) {
					lift_queue_field_update( $document_id, $meta_key, 'post' );
				}
			} );

		//handle meta updates
		add_action( "update_post_meta", function($meta_id, $post_id, $meta_key, $_meta_value) {
				$post_type = get_post_type( $post_id );
				if ( in_array( $meta_key, Lift_Post_Meta_Update_Watcher::get_watched_meta_keys( $post_type ) ) ) {
					lift_queue_field_update( $post_id, $meta_key );
				}
			}, 10, 4 );

		//handle meta adds
		add_action( "add_post_meta", function($post_id, $meta_key, $_meta_value) {
				$post_type = get_post_type( $post_id );
				if ( in_array( $meta_key, Lift_Post_Meta_Update_Watcher::get_watched_meta_keys( $post_type ) ) ) {
					lift_queue_field_update( $post_id, 'post_meta_' . $meta_key, 'post' );
				}
			}, 10, 3 );

		add_action( "delete_post_meta", function($meta_ids, $post_id, $meta_key, $_meta_value) {
				$post_type = get_post_type( $post_id );
				if ( in_array( $meta_key, Lift_Post_Meta_Update_Watcher::get_watched_meta_keys( $post_type ) ) ) {
					lift_queue_field_update( $post_id, 'post_meta_' . $meta_key, 'post' );
				}
			}, 10, 4 );
	}

}

class Lift_Taxonomy_Update_Watcher {

	public static function get_watched_taxonomies( $post_type ) {

		$default_taxonomies = array(
			'post' => array( 'category', 'post_tag' ),
		);
		return apply_filters( 'lift_watched_taxonomies', isset( $default_taxonomies[$post_type] ) ? $default_taxonomies[$post_type] : array( ), $post_type );
	}

	public static function init() {
		add_action( 'set_object_terms', function($post_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids) {
				$post_type = get_post_type( $post_id );
				if ( in_array( $taxonomy, Lift_Taxonomy_Update_Watcher::get_watched_taxonomies( $post_type ) ) ) {
					if ( $append || array_diff( $old_tt_ids, $tt_ids ) || array_diff( $tt_ids, $old_tt_ids ) ) {
						lift_queue_field_update( $post_id, 'taxonomy_' . $taxonomy, 'post' );
					}
				}
			}, 10, 6 );

		add_action( 'edited_term', function($term_id, $tt_id, $taxonomy) {

				$watched_post_types = Lift_Post_Update_Watcher::get_watched_post_types();
				$tax_obj = get_taxonomy( $taxonomy );
				$post_types = array_intersect( $watched_post_types, $tax_obj->object_type );
				foreach ( $post_types as $post_type ) {
					if ( in_array( $taxonomy, Lift_Taxonomy_Update_Watcher::get_watched_taxonomies( $post_type ) ) ) {
						//we now have to update every post that has this term...dude, this sucks
						$post_ids = get_posts( array(
							'fields' => 'ids',
							'posts_per_page' => 999,
							'post_type' => $post_type,
							'tax_query' => array(
								array(
									'taxonomy' => $taxonomy,
									'field' => 'id',
									'terms' => $term_id,
								)
							)
							) );

						foreach ( $post_id as $post_id ) {
							lift_queue_field_update( $post_id, 'taxonomy_' . $taxonomy, 'post' );
						}
					}
				}
			}, 10, 3 );
	}

}
