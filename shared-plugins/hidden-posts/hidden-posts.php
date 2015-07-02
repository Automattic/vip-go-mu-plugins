<?php
/**
 * Plugin Name: Hidden Posts
 * Description: Hide posts on the home page.
 * Version:     0.1
 * Author:      Automattic
 * Author URI:  http://automattic.com
 * License:     GPLv2 or later
 */

/**
 * Hidden Posts
 *
 * Hide a limited number of specified posts from the hompage.
 *
 * We keep a list of post ID's in an option and use
 * a NOT IN query with those post ID's. We limit
 * the number of posts so that the query doesn't
 * get too slow.
 */
class Hidden_Posts {

    const META_KEY = 'hidden-posts';
    const NONCE_KEY = 'hidden-posts-nonce';

    /**
     * Maximum number of posts to store in the hidden array
     */
    const LIMIT = 100;

    function __construct() {
        add_action( 'post_submitbox_misc_actions', array( $this, 'hidden_checkbox' ) );
        add_action( 'save_post', array( $this, 'save_meta' ) );
        add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
    }

    /**
     * Hide the posts in the hidden array on the homepage
     */
    function pre_get_posts( $query ) {
        if ( is_admin() || ! $query->is_main_query() )
            return;

        if ( apply_filters( 'hidden_posts_show_posts', is_single() ) )
            return;

		$hidden_posts = self::get_posts();
		$post_not_in = $query->get( 'post__not_in' );
		if ( is_array( $post_not_in ) ) {
			$post_not_in = array_unique( array_merge( $post_not_in, $hidden_posts ) );
		} else {
			$post_not_in = $hidden_posts;
		}
		$query->set( 'post__not_in', $post_not_in );
    }

    /**
     * Show the checkbox in the admin
     */
    function hidden_checkbox() {
        global $post;

        $checked = in_array( $post->ID, self::get_posts() );

        wp_nonce_field( self::NONCE_KEY, self::NONCE_KEY );
        printf( '<div id="superawesome-box" class="misc-pub-section"><label><input type="checkbox" name="%s" %s> %s</label></div>', self::META_KEY, checked( $checked, true, false ), esc_html( apply_filters( 'hidden_posts_checkbox_text', 'Hide Post' ) ) );
    }

    /**
     * Update the post array
     */
    function save_meta( $post ) {
        // Verify the nonce
        if ( ! isset( $_POST[ self::NONCE_KEY ] ) || ! wp_verify_nonce( $_POST[ self::NONCE_KEY ], self::NONCE_KEY ) )
            return;

        // Update the post array if necessary
        if ( isset( $_POST[ self::META_KEY ] ) )
            self::add_post( $post );
        else
            self::remove_post( $post );
    }

    /**
     * Get the array of posts
     */
    static function get_posts() {
        return array_filter( array_map( 'absint', get_option( self::META_KEY, array() ) ) );
    }

    /**
     * Add the post to the hidden array
     *
     * If the post is already in the hidden array,
     * just bail. Otherwise, add it. Also,
     * make sure we don't go over the specified limit.
     */
    static function add_post( $id ) {
        $posts = self::get_posts();

        if ( in_array( $id, $posts ) )
            return;

        // Add the post to the array
        $posts[] = $id;

        // Make sure there are only LIMIT posts in the array
        while ( count( $posts ) > self::LIMIT )
            array_shift( $posts );

        update_option( self::META_KEY, array_map( 'intval', $posts ) );
    }

    /**
     * Remove the post from the hidden array
     *
     * If the post doesn't exist in the hidden array,
     * just bail. Otherwise, splice it out.
     */
    static function remove_post( $id ) {
        $posts = self::get_posts();

        if ( ! in_array( $id, $posts ) )
            return;

        array_splice( $posts, array_search( $id, $posts ), 1 );

        update_option( self::META_KEY, array_map( 'intval', $posts ) );
    }

}

new Hidden_Posts;
