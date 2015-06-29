<?php
/*
Plugin Name: Disable Comment Querying
Description: Disables comments queries for s performance boost when using an external comment system like Disqus or ID
Version: 0.1
License: GPL version 2 or any later version
Author: Mark Jaquith
Author URI: http://coveredwebservices.com/

Some modifications by the WordPress.com VIP team
*/

class Disable_Comments_Query_Plugin {
	static $instance;
	private $already_ran = false;

	public function __construct() {
		self::$instance = $this;
		add_action( 'template_redirect', array( $this, 'template_redirect' ) );
	}

	public function template_redirect() {
		add_filter( 'option_require_name_email', array( $this, 'require_name_email' ) );
	}

	/**
	 * This filter is fired in the comments_template() function,
	 * which lacks a suitable way of hooking in early and aborting the comments query
	 */
	public function require_name_email( $value ) {
		if ( !defined( 'DOING_AJAX' ) || !DOING_AJAX )
			add_filter( 'query', array( $this, 'comments_query_filter' ) );
		return $value;
	}

	/**
	 * Neuter the comments query, to prevent doing double work.
	 */
	public function comments_query_filter( $query ) {
		if ( $this->already_ran )
			return $query;
		
		global $wpdb;

		$pattern = '#^\s*SELECT\s*\*\s*FROM\s*' . preg_quote( $wpdb->comments, '#' ) .'\s*WHERE(?:.*)comment_post_ID\s*=\s*([0-9]+)\s*#i';
		if ( preg_match( $pattern, $query ) ) {
			// Neuter the query, while leaving a clue as to what happened
			$query = preg_replace( $pattern, 'SELECT * FROM ' . $wpdb->comments . ' WHERE 1=0 /* Query killed by disable-comments-query */ AND comment_post_ID = $1 ', $query );
			$this->already_ran = true;
		}

		return $query;
	}

}

new Disable_Comments_Query_Plugin;