<?php
/**
 * Main class for storing and retrieving the parent revision ID's used for the three-way merge
 * @package fork
 *
 * WordPress handles revisions in a funny way. The post ID always refers to the most recent version
 * When the post is updated, the existing version is moved to a revision with a new ID.
 * Thus, at the time of forking, we cannot get the ID of the base revision, because the ID doesn't exist yet
 * So we stor the ID of the previous revision (immediatly prior to the one forked) in the fork's post_meta.
 * When we go to merge, we know we just have to get the revision that came after this one,
 * or if none exists, we just use the post itself.
 *
 */

class Fork_Revisions {

	public $previous_revision_key = '_previous_revision'; //post_meta field to hold previous revision of fork's parent

	function __construct() {

		add_action( 'fork', array( &$this, 'store_previous_revision' ), 10, 2 );

	}


	/**
	 * When post is forked, store previous revision as post meta
	 * @param int $fork_id the ID of the new fork
	 * @param object $parent the parent post object
	 * @return bool|int the result of the post meta update
	 */
	function store_previous_revision( $fork_id, $parent ) {

		return update_post_meta( $fork_id, $this->previous_revision_key, $this->get_previous_post_revision( $parent ) );

	}


	/**
	 * Returns the most recent revision of a given post
	 *
	 * Note: For forks, use `get_previous_revision( $fork )`
	 *
	 * @param int|object $p the post ID or post object
	 * @return int|bool the revision ID or false if no revisions exist
	 */
	function get_previous_post_revision( $p = null ) {

		global $post;

		if ( $p == null )
			$p = $post;

		if ( !is_object( $p ) )
			$p = get_post( $p );

		if ( !$p )
			return false;

		//we were passed a fork... try to grab from post meta
		if ( $p->post_type == 'fork' )
			return _doing_it_wrong( 'get_previous_post_revision', 'Function applies to posts, not forks', null );

		$revs = wp_get_post_revisions( $p );

		//the post has no revisions
		if ( empty( $revs ) )
			return false;

		//got revisions, return the previous revision's ID
		return reset( $revs )->ID;

	}


	/**
	 * Returns the revision ID of the revision immediatly previous to the revision our fork is based on
	 * @see `get_previous_post_revision()` for more information on how WordPress handles revisioning in this context
	 *
	 * @param int|object $p the post ID or post object
	 * @return int the revision ID or the postID if no revision exists
	 */
	function get_previous_revision( $p = null ) {

		global $post;

		if ( $p == null )
			$p = $post;

		if ( !is_object( $p ) )
			$p = get_post( $p );

		if ( !$p )
			return false;


		if ( $p->post_type != 'fork' )
			return _doing_it_wrong( 'get_previous_revision', 'Function only applies to forks, not posts', null );

		//we have a post meta, just return
		if ( $meta = get_post_meta( $p->ID, $this->previous_revision_key, true ) )
			return $meta;

		//we can't pass arguments to the filter, but the filter needs to know what date to limit results to
		//so store as property of class temporarily and then unset when done
		$this->modified_date = $p->post_modified;

		//immediately add and remove our filter so it doesn't affect other queries
		add_filter( 'posts_where', array( $this, 'revision_date_filter' ) );
		$revisions = wp_get_post_revisions( $p->post_parent );
		remove_filter( 'posts_where', array( $this, 'revision_date_filter' ) );

		unset( $this->modified_date );

		//post has no revisions (e.g., never edited), so just use the post
		if ( empty( $revisions ) )
			return $p->post_parent;

		//return the ID of the our best-guess revision
		return reset( $revisions )->ID;

	}


	/**
	 * Limit revision queries to revisions created before a certain date/time
	 *
	 * If we don't have a stored post revision, which most likely means that when the fork was made,
	 * there were no revisions, only the post. Try our best to figure out where that revision lies
	 *
	 * @param string $where the where clause of the SQL statement
	 * @return string the modified where clause
	 */
	function revision_date_filter( $where ) {
		global $wpdb;

		//if for some reason we didn't get a fork_modified date, sabotage the query
		// so that we always get false, and are forced to use the post itself
		if ( empty( $this->modified_date ) )
			return ' AND WHERE 0 = 1';

		return $where . $wpdb->prepare( " AND post_date < %s", $this->fork_modified );

	}


	/**
	 * Returns the exact revision a post is bassed off of
	 * @uses `get_previous_revision()`
	 */
	function get_parent_revision( $p = null ) {
		global $post;

		if ( $p == null )
			$p = $post;

		if ( !is_object( $p ) )
			$p = get_post( $p );

		if ( !$p )
			return false;

		if ( $p->post_type != 'fork' )
			return _doing_it_wrong( 'get_parent_revision', 'Function only applies to forks, not posts', null );

		//grab the previous revision ID
		$previous = $this->get_previous_revision( $p );

		//no previous revisions exist, just use the actual post
		if ( $previous == $p->post_parent )
			return $p->post_parent;

		//get an array of revision objects, in order ASC so we can find the next sequential revision
		$revisions = wp_get_post_revisions( $p->post_parent, array( 'order' => 'ASC' ) );

		//build an array of Index => revision_id to make it easier to increment
		$indicies = array_keys( $revisions );

		//index of our actual parent revision
		$actual = array_search( $previous, $indicies );

		//our revision is the latest revision, so we actually want to look at the post itself
		if ( !isset( $indicies[ $actual ] ) )
			return $p->post_parent;

		//return parent revision ID
		$parent = $indicies[ $actual ];
		return $parent;

	}


}