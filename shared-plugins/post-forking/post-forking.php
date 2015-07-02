<?php
/**
Plugin Name: Post Forking
Description: WordPress Post Forking allows users to "fork" or create an alternate version of content to foster a more collaborative approach to WordPress content curation.
Author:      Benjamin J. Balter, Daniel Bachhuber, Aaron Jorbin
Version:     0.3.0-alpha
Plugin URI:  http://postforking.wordpress.com
License:     GPLv2 or Later
Domain Path: /languages
Text Domain: post-forking
 */

/* Post Forking
 *
 * Provides users that would not normally be able to edit a post with the ability to submit revisions.
 * This can be users on a site without the `edit_post` or `edit_published_post` capabilities,
 * or can be members of the general public. Also  allows post authors to edit published posts
 * without their changes going appearing publicly until published.
 *
 * Copyright (C) 2012 Benjamin J. Balter ( Ben@Balter.com | http://ben.balter.com )
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @copyright 2012-2013
 * @license GPL v2
 * @version 0.2
 * @package post_forking
 * @author Benjamin J. Balter <ben@balter.com>
 */

require_once dirname( __FILE__ ) . '/includes/capabilities.php';
require_once dirname( __FILE__ ) . '/includes/options.php';
require_once dirname( __FILE__ ) . '/includes/admin.php';
require_once dirname( __FILE__ ) . '/includes/merge.php';
require_once dirname( __FILE__ ) . '/includes/revisions.php';
require_once dirname( __FILE__ ) . '/includes/branches.php';
require_once dirname( __FILE__ ) . '/includes/diff.php';
require_once dirname( __FILE__ ) . '/includes/preview.php';

class Fork {

	const post_type = 'fork';
	public $post_type_support = 'fork'; //key to register when adding post type support
	public $fields = array( 'post_title', 'post_content' ); //post fields to map from post to fork
	public $version = '0.2';
	/**
	 * Register initial hooks with WordPress core
	 */
	function __construct() {

		$this->capabilities = new Fork_Capabilities( $this );
		$this->options = new Fork_Options( $this );
		$this->branches = new Fork_Branches( $this );
		$this->preview = new Fork_Preview( $this );

		add_action( 'init', array( $this, 'register_cpt' ) );
		add_action( 'init', array( $this, 'action_init' ) );
		add_action( 'init', array( $this, 'add_post_type_support'), 999  );
		add_action( 'init', array( $this, 'l10n'), 5  );

		add_filter( 'the_title', array( $this, 'title_filter'), 10, 3 );
		add_action( 'delete_post', array( $this, 'delete_post' ) );

	}

	/**
	 * Init i18n files
	 * Must be done early on init because they need to be in place when register_cpt is called
	 */
	function l10n() {
		load_plugin_textdomain( 'post-forking', false, plugin_basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	function get_post_type(){
		return self::post_type;
	}

	/**
	 * Pseudo-lazy loading of back-end functionality
	 */
	function action_init() {

		if ( !is_admin() )
			return;

		$this->admin = new Fork_Admin( $this );
		$this->revisions = new Fork_Revisions( $this );
		$this->merge = new Fork_Merge( $this );
		$this->diff = new Fork_Diff( $this );

	}

	/**
	 * Register "fork" custom post type
	 */
	function register_cpt() {

		$labels = array(
			'name'               => _x( 'Forks', 'post-forking' ),
			'singular_name'      => _x( 'Fork', 'post-forking' ),
			'add_new'            => false,
			'add_new_item'       => false,
			'edit_item'          => _x( 'Edit Fork', 'post-forking' ),
			'new_item'           => _x( 'New Fork', 'post-forking' ),
			'view_item'          => _x( 'View Fork', 'post-forking' ),
			'search_items'       => _x( 'Search Forks', 'post-forking' ),
			'not_found'          => _x( 'No forks found', 'post-forking' ),
			'not_found_in_trash' => _x( 'No forks found in Trash', 'post-forking' ),
			'parent_item_colon'  => _x( 'Parent Fork:', 'post-forking' ),
			'menu_name'          => _x( 'Forks', 'post-forking' ),
		);

		$args = array(
			'labels'              => $labels,
			'hierarchical'        => true,
			'supports'            => array( 'title', 'editor', 'author', 'revisions' ),
			'public'              => true,
			'show_ui'             => true,
			'show_in_nav_menus'   => false,
			'publicly_queryable'  => true,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'query_var'           => true,
			'can_export'          => true,
			'rewrite'             => true,
			'map_meta_cap'        => true,
			'capability_type'     => array( 'fork', 'forks' ),
			'menu_icon'           => plugins_url( '/img/menu-icon.png', __FILE__ ),
		);

		register_post_type( self::post_type, $args );

		$status_args = array(
			'label' => _x( 'Merged', 'post-forking' ),
			'public' => true,
			'exclude_from_search' => true,
			'label_count' => _n_noop( 'Merged <span class="count">(%s)</span>', 'Merged <span class="count">(%s)</span>' ),
		);

	}

	/**
	 * Load a template. MVC FTW!
	 * @param string $template the template to load, without extension (assumes .php). File should be in templates/ folder
	 * @param args array of args to be run through extract and passed to tempalate
	 */
	function template( $template, $args = array() ) {
		extract( $args );

		if ( !$template )
			return false;

		$path = dirname( __FILE__ ) . "/templates/{$template}.php";
		$path = apply_filters( 'fork_template', $path, $template );

		include $path;

	}

	/**
	 * Returns an array of post type => bool to indicate whether the post type(s) supports forking
	 * All post types will be included
	 * @param bool $filter whether to return all post types (false) or just the ones toggled (true)
	 * @return array an array of post types and their forkability
	 */
	function get_post_types( $filter = false ) {

		$active_post_types = $this->options->post_types;
		$post_types = array();

		foreach ( $this->get_potential_post_types() as $pt )
			$post_types[ $pt->name ] = ( array_key_exists( $pt->name, (array) $active_post_types ) && $active_post_types[ $pt->name ] );

		if ( $filter )
			$post_types = array_keys( array_filter( $post_types ) );

		$post_types = apply_filters( 'fork_post_types', $post_types, $filter );

		return  $post_types;

	}

	/**
	 * Returns an array of post type objects for all registered post types that support 'revisions' other than fork
	 * @param return array array of post type objects
	 */
	function get_potential_post_types() {

		$post_types = get_post_types( array( 'show_ui' => true ), 'objects' );
		unset( $post_types['fork'] );
		foreach($post_types as $post_type) {
			if (!post_type_supports( $post_type->name, 'revisions')) {
				unset($post_types[$post_type->name]); 
			}
		}
		return $post_types;

	}

	/**
	 * Registers post_type_support for forking with all active post types on load
	 */
	function add_post_type_support() {

		foreach ( $this->get_post_types() as $post_type => $status )
			if ( $status == true )
				add_post_type_support( $post_type, $this->post_type_support );

	}

	/**
	 * Checks if a user has a fork for a given post, or optionally for any post
	 * @param int $parent_id the post_id of the parent post to check
	 * @param int|string the author to check
	 * @return int|bool the fork id or false if no fork exists
	 */
	function user_has_fork( $parent_id = null, $author = null ) {

		if ( $author == null )
			$author = wp_get_current_user()->nicename;

		$args = array(
			'post_type' => 'fork',
			'author' => $author,
			'post_status' => array( 'draft', 'pending' ),
		);

		if ( $parent_id != null )
			$args[ 'post_parent' ] = (int) $parent_id;

		$posts = get_posts( $args );

		if ( empty( $posts ) )
			return false;

		return reset( $posts )->ID;

	}

	/**
	 * Returns an filterable list of fields to copy from original post to the fork
	 */
	function get_fork_fields() {

		return apply_filters( 'fork_fields', $this->fields );

	}

	/**
	 * Main forking function
	 * @param int|object $p the post_id or post object to fork
	 * @param string the nicename of author to fork post as
	 * @return int the ID of the fork
	 */
	function fork( $p = null, $author = null ) {
		global $post;

		if ( $p == null )
			$p = $post;

		if ( !is_object( $p ) )
			$p = get_post( $p );

		if ( !$p )
			return false;

		if ( $author == null )
			$author = wp_get_current_user()->ID;

		//bad post type, enable via forks->options
		if ( !post_type_supports( $p->post_type, $this->post_type_support ) )
			wp_die( __( 'That post type does not support forking', 'post-forking' ) );

		//hook into this cap check via map_meta cap
		// for custom capabilities
		if ( !user_can( $author, 'fork_post', $p->ID ) )
			wp_die( __( 'You are not authorized to fork that post', 'post-forking' ) );

		//user already has a fork, just return the existing ID
		if ( $fork = $this->user_has_fork( $p->ID, $author ) )
			return $fork;

		//set up base fork data array
		$fork = array(
			'post_type' => 'fork',
			'post_author' => $author,
			'post_status' => 'draft',
			'post_parent' => $p->ID,
		);

		//copy necessary post fields over to fork data array
		foreach ( $this->get_fork_fields() as $field )
			$fork[ $field ] = $p->$field;

		$fork_id = wp_insert_post( $fork );

		//something went wrong
		if ( !$fork_id )
			return false;

		//note: $p = parent post object
		do_action( 'fork', $fork_id, $p, $author );

		return $fork_id;

	}

	/**
	 * Wrapper for get_post that auto-adds the argument post_type => 'fork'
	 * Also applies a filter, which is used consistently internally, to batch filter plugin behavior
	 * @param array $args the arguments you'd normally pass to get_posts
	 * @param array array of post objects
	 */
	function get_forks( $args = array() ) {

		$args['post_type'] = 'fork';

		$forks = get_posts( $args );
		$forks = apply_filters( 'forks_query', $forks );

		return $forks;

	}

	/**
	 * Given a fork, gets the name of the parent post
	 * @param int|object $fork the fork ID or or object (optional, falls back to global $post)
	 * @return string the name of the parent post
	 */
	function get_parent_name( $fork = null ) {
		global $post;

		if ( $fork == null )
			$fork = $post;

		if ( !is_object( $fork ) )
			$fork = get_post( $fork );

		if ( !$fork )
			return;

		$parent = get_post( $fork->post_parent );

		$author = get_user_by( 'id', $parent->post_author );

		$name =  $author->user_nicename . ' &#187; ';
		$name .= get_the_title( $parent );

		return $name;

	}

	/**
	 * Given a fork, returns the true name of the fork, filterless
	 * @param int|object $fork the fork ID or or object (optional, falls back to global $post)
	 * @return string the name of the fork
	 */
	function get_fork_name( $fork = null ) {
		global $post;

		if ( $fork == null )
			$fork = $post;

		if ( !is_object( $fork ) )
			$fork = get_post( $fork );

		if ( !$fork )
			return;

		$author = new WP_User( $fork->post_author );
		$parent = get_post( $fork->post_parent );

		$name = $author->user_nicename . ' &#187; ';
		remove_filter( 'the_title', array( $this, 'title_filter'), 10, 3 );
		$name .= get_the_title( $parent->ID );
		add_filter( 'the_title', array( $this, 'title_filter'), 10, 3 );

		return $name;

	}

 	/**
 	 * Filter fork titles
 	 * @param string $title the post title
 	 * @param int $id the post ID
 	 * @return string the modified post title
 	 */
 	function title_filter( $title, $id = 0 ) {

 		if ( get_post_type( $id ) != 'fork' )
 			return $title;

 		return $this->get_fork_name( $id );


 	}

 	/**
 	 * When post is deleted, delete posts
 	 * @param int $post_id the parent post
 	 */
 	function delete_post( $post_id ) {

	 	//post delete
	 	if ( !get_post( $post_id ) )
	 		return;

	 	foreach( $this->get_forks( array( 'post_parent' => $post_id ) ) as $fork )
	 		wp_delete_post( $fork->ID );

 	}

}

$fork = new Fork();
