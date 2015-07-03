<?php
/**
 * Forking administrative functions
 * @package fork
 */

class Fork_Admin {

	/**
	 * Hook into WordPress API on init
	 */
	function __construct( &$parent ) {

		$this->parent = &$parent;
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'admin_init', array( $this, 'fork_callback' ) );
		add_action( 'admin_init', array( $this, 'merge_callback' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_action( 'admin_ajax_fork', array( $this, 'ajax' ) );
		add_action( 'admin_ajax_fork_merge', array( $this, 'ajax' ) );
		add_action( 'do_meta_boxes', array( $this, 'remove_add_new_button' ), 10, 1 );
		add_action( 'admin_menu', array( $this, 'remove_add_new_menu_item' ) );
		add_filter( 'map_meta_cap', array( $this, 'post_new_lockdown' ), 10, 2 );
		add_filter( 'admin_body_class', array( $this, 'remove_add_new_list_table' ), 10, 1 );

	}


	/**
	 * Add metaboxes to post edit pages
	 */
	function add_meta_boxes() {
		global $post;

		if ( $post->post_status == 'auto-draft' )
			return;

		if ( post_type_supports( $post->post_type, $this->parent->post_type_support ) )
			add_meta_box( 'fork', 'Fork', array( $this, 'post_meta_box' ), $post->post_type, 'side', 'high' );
		elseif ( Fork::post_type == $post->post_type ) {
			remove_meta_box( 'submitdiv', Fork::post_type, 'side' );
			add_meta_box( 'fork', 'Fork', array( $this, 'fork_meta_box' ), Fork::post_type, 'side', 'high' );
		}

	}


	/**
	 * Callback to listen for the primary fork action
	 */
	function fork_callback() {

		if ( !isset( $_GET['fork'] ) )
			return;

		check_admin_referer( 'post-forking-fork_' . intval( $_GET['fork'] ) );

		$fork = $this->parent->fork( (int) $_GET['fork'] );

		if ( !$fork )
			return;

		wp_safe_redirect( admin_url( "post.php?post=$fork&action=edit" ) );
		exit();

	}


	/**
	 * Callback to listen for the primary merge action
	 */
	function merge_callback() {

		if ( !isset( $_GET['merge'] ) )
			return;

		check_admin_referer( 'post-forking-merge_' . intval( $_GET['merge'] ) );

		$this->parent->merge->merge( (int) $_GET['merge'] );

		exit();

	}


	/**
	 * Callback to render post meta box
	 */
	function post_meta_box( $post ) {

		$this->parent->branches->branches_dropwdown( $post );

		if ( $this->parent->branches->can_branch( $post ) )
			$this->parent->template( 'author-post-meta-box', compact( 'post' ) );

		else
			$this->parent->template( 'post-meta-box', compact( 'post' ) );

	}


	/**
	 * Callback to render fork meta box
	 */
	function fork_meta_box( $post ) {

		$parent = $this->parent->revisions->get_previous_revision( $post );

		$this->parent->template( 'fork-meta-box', compact( 'post', 'parent' ) );
	}


	/**
	 * Registers update messages
	 * @param array $messages messages array
	 * @returns array messages array with fork messages
	 */
	function update_messages( $messages ) {
		global $post, $post_ID;

		$messages['fork'] = array(
			1 => __( 'Fork updated.', 'post-forking' ),
			2 => __( 'Custom field updated.', 'post-forking' ),
			3 => __( 'Custom field deleted.', 'post-forking' ),
			4 => __( 'Fork updated.', 'post-forking' ),
			5 => isset($_GET['revision']) ? sprintf( __( 'Fork restored to revision from %s', 'post-forking' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6 => __( 'Fork published. <a href="%s">Download Fork</a>', 'post-forking' ),
			7 => __( 'Fork saved.', 'post-forking' ),
			8 => __( 'Fork submitted.', 'post-forking' ),
			9 => __( 'Fork scheduled for:', 'post-forking' ),
			10 => __( 'Fork draft updated.', 'post-forking' ),
		);

		return $messages;
	}


	/**
	 * Enqueue javascript and css assets on backend
	 */
	function enqueue() {

		$post_types = $this->parent->get_post_types( true );
		$post_types[] = Fork::post_type;

		if ( !in_array( get_current_screen()->post_type, $post_types ) )
			return;

        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min'; 

		//js
		wp_enqueue_script( 'post-forking', plugins_url( "/js/admin{$suffix}.js", dirname( __FILE__ ) ), 'jquery', $this->parent->version, true );

		//css
		wp_enqueue_style( 'post-forking', plugins_url( "/css/admin{$suffix}.css", dirname( __FILE__ ) ), null, $this->parent->version );

	}


	/**
	 * Add additional actions to the post row view
	 */
	function row_actions( $actions, $post ) {

		if ( post_type_supports( get_post_type( $post ), $this->parent->post_type_support ) ) {
			$label = ( $this->parent->branches->can_branch ( $post ) ) ? __( 'Create branch', 'post-forking' ) : __( 'Fork', 'post-forking' );
			$actions[] = '<a href="' . wp_nonce_url( admin_url( "?fork={$post->ID}" ), 'post-forking-fork_' . $post->ID ) . '">' . $label . '</a>';
		}

		if ( Fork::post_type == get_post_type( $post ) ) {
			$parent = $this->parent->revisions->get_previous_revision( $post );
			$actions[] = '<a href="' . admin_url( "revision.php?page=fork-diff&right={$post->ID}" ) . '">' . __( 'Compare', 'post-forking' ) . '</a>';
		}

		return $actions;

	}


	/**
	 * Callback to handle ajax forks
	 * Note: Will output 0 on failure,
	 */
	function ajax() {

		foreach ( array( 'post', 'author', 'action' ) as $var )
			$$var = ( isset( $_GET[$var] ) ) ? $_GET[$var] : null;

		check_ajax_referer( 'post-forking-' . $action . '_' . $post );

		if ( $action == 'merge' )
			$result = $this->parent->merge->merge( $post, $author );
		else
			$result = $this->parent->fork( $post, $author );

		if ( $result == false )
			$result = -1;

		die( $result );

	}


	/**
	 * Remvoe the "Add New" button from the edit fork screen by nulling out the callback URL
	 * @uses add_meta_boxes
	 * @param string $post_type the post type of the current page
	 */
	function remove_add_new_button( $post_type ) {

		if ( $post_type != 'fork' )
			return;

		global $post_new_file;
		$post_new_file = null;

	}


	/**
	 * Remove "Add New" button from the admin menu
	 */
	function remove_add_new_menu_item() {

		global $submenu;

		if ( ! isset( $submenu['edit.php?post_type=fork'] ) )
			return;

		foreach ( $submenu['edit.php?post_type=fork'] as $ID => $item )
			if ( $item[0] === false )
				unset( $submenu['edit.php?post_type=fork'][$ID] );

	}

	/**
	 * Add admin body class for the forks list table view
	 */
	function remove_add_new_list_table( $classes ) {
		if ( 'edit-fork' == get_current_screen()->id )
			return $classes .= ' fork-list';
	}

	/**
	 * Lock down the post-new page for forks to prevent strange situations
	 * with parentless forks. Uses Map Meta Cap to add a new cap no one will ever have.
	 */
	function post_new_lockdown( $caps, $cap ) {

		if ( $cap != 'edit_forks' )
			return $caps;

		if ( !get_current_screen() )
			return $caps;

		if ( get_current_screen()->action != 'add' )
			return $caps;

		if ( get_current_screen()->post_type != 'fork' )
			return $caps;

		$caps[] = 'create_new_fork_without_parent';

		return $caps;

	}



}
