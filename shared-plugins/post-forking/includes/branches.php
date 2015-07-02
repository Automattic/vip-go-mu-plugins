<?php
/**
 * Branching functionality
 *
 * Any fork owned by the author of a post is considered a branch.
 *
 * @package fork
 */

class Fork_Branches {

	/**
	 * Hook into WP API
	 */
	function __construct( &$parent ) {
		$this->parent = &$parent;
	}


	/**
	 * Check whether a given fork is a branch
	 */
	function is_branch( $fork ) {

		if ( !is_object( $fork ) )
			$fork = get_post( $fork );

		if ( !$fork )
			return false;

		$parent = get_post( $fork->post_parent );
		
		if ( !$parent )
			return false;

		return $fork->post_author == $parent->post_author;

	}


	/**
	 * Check whether a given user can branch a given post
	 */
	function can_branch( $p = null, $user = null ) {

		global $post;

		if ( $p == null )
			$p = $post;

		if ( $user == null )
			$user = wp_get_current_user();

		if ( is_integer( $user ) )
			$user = get_user_by( 'id', $user );

		if ( !$p || !$user )
			return false;

		if ( !user_can( $user, 'branch_post', $p ) )
			return false;

		return true;

	}


	/**
	 * Filterable way to check if a user can branch a given post
	 * By default, just checks to see if a post is the user's post
	 * but capability plugins can hook in here, e.g., make multiple authors for a post
	 * post editors, etc.
	 */
	function fork_post_filter( $caps, $cap, $user_id, $p ) {

		if ( $cap != 'branch_posts' )
			return;

		if ( $p->post_author == $user_id )
			$cap[] = 'branch_others_posts';

		return $caps;

	}


	/**
	 * Get an array of branch objects for a given post
	 */
	function get_branches( $p = null , $args = array() ) {
		global $post;

		if ( $p == null )
			$p = $post;

		if ( !is_object( $p ) )
			$p = get_post( $p );

		if ( $p->post_type == 'fork' )
			$p = get_post( $p->post_parent );

		$args = array( 'post_author' => $p->post_author, 'post_parent' => $p->ID, 'post_status' => array( 'draft', 'pending' ) );
		return $this->parent->get_forks( $args );

	}


	/**
	 * Callback to Render branch dropdown
	 * @param object the post object
	 */
	function branches_dropwdown( $post ) {

		$branches = $this->get_branches( $post );
		$this->parent->template( 'branches-dropdown', compact( 'post', 'branches' ) );

	}


}