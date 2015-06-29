<?php
/**
 * Main class for mering a fork back into its parent
 * @package fork
 */

class Fork_Merge {

	public $ttl = 1; //super-short TTL means we cache within page load, but don't ever hit persistant cache
	public $conflict_meta = '_conflict_marked';

	function __construct( &$parent ) {

		$this->parent = &$parent;

		//fire up the native WordPress diff engine
		$engine = extension_loaded( 'xdiff' ) ? 'xdiff' : 'native';
		require_once ABSPATH . WPINC . '/wp-diff.php';
		require_once ABSPATH . WPINC . '/Text/Diff/Engine/' . $engine . '.php';

		//init our three-way diff library which extends WordPress's native diff library
		if ( !class_exists( 'Text_Diff3' ) )
			require_once dirname( __FILE__ ) . '/diff3.php';

		add_filter( 'wp_insert_post_data', array( $this, 'check_merge_conflict' ), 10, 2 );
		add_action( 'admin_notices', array( $this, 'conflict_warning' ) );
		add_action( 'transition_post_status', array( $this, 'intercept_publish' ), 0, 3 );

	}


	/**
	 * Merges a fork's content back into its parent post
	 * @param int $fork_id the ID of the fork to merge
	 */
	function merge( $fork ) {
		$user = wp_get_current_user();

		if ( !is_object( $fork ) )
			$fork = get_post( $fork );

		$fork_content_pre_merge = $fork->post_content;

		if ( $this->has_conflict_markup( $fork ) ) {
			update_post_meta( $fork->ID, 'fork-conflict-raw', $fork_content_pre_merge );
			return false;
		}

		if ( !current_user_can( 'publish_fork', $fork->ID ) ) {
			wp_die( __( 'You are not authorized to merge forks', 'post-forking' ) );
		}

		$update = array(
			'ID' => $fork->post_parent,
			'post_content' => $this->get_merged( $fork ),
		);

		// reload $fork to check for conflict markup, and save the original in postmeta if so
		$fork = get_post( $fork->ID );

		// Note: $merge_author = id of user who's doing the merge
	  $merge_author = wp_get_current_user()->ID;
    do_action( 'merge', $fork, $merge_author );

		return wp_update_post( $update );

	}


	/**
	 * Returns the merged, possibly conflicted post_content
	 * @param int|object $fork_id the ID of the fork, or the fork itself
	 * @return string the post content
	 * NOTE: may be conflicted. use `is_conflicted()` to check
	 */
	function get_merged( $fork ) {

		$diff = $this->get_diff( $fork );
		$merged = $diff->mergedOutput( __( 'Fork', 'post-forking' ), __( 'Current Version', 'post-forking' ) );
		return implode( "\n", $merged );

	}


	/**
	 * Determines if a given fork can merge cleanly into the current post
	 * @param int|object $fork_id the ID for the fork to merge or the fork itself
	 * @return bool true if conflicted, otherwise false
	 */
	function is_conflicted( $fork ) {

		$diff = $this->get_diff( $fork );

		foreach ( $diff->_edits as $edit )
			if ( $edit->isConflict() )
				return true;

		return false;

	}


	/**
	 * Performs a three-way merge with a fork, it's parent revision, and the current version of the post
	 * Caches so that multiple calls to get_diff within the same page load will not re-run the diff each time
	 * Passing an object (rather than a fork id) will bypass the cache (to allow for on-the-fly diffing on save
	 */
	function get_diff( $fork ) {

		if ( !is_object( $fork ) && $diff = wp_cache_get( $fork, 'Fork_Diff' ) )
			return $diff;

		if ( !is_object( $fork ) )
			$fork = get_post( $fork );

		$fork_id = $fork->ID;

		//grab the three elments
		$parent = $this->parent->revisions->get_parent_revision( $fork );
		$current = $fork->post_parent;

		//remove trailing whitespace  and convert string -> array
		foreach ( array( 'fork', 'parent', 'current' ) as $string ) {
			if ( is_object( $$string ) )
				$$string = $$string->post_content;
			else
				$$string = get_post( $$string )->post_content;
			$$string = rtrim( $$string );
			$$string = explode( "\n", $$string );
		}

		//diff, cache, return
		$diff = new Text_Diff3( $parent, $fork, $current );
		wp_cache_set( $fork_id, $diff, 'Fork_Diff', $this->ttl );
		return $diff;

	}


	/**
	 * Prior to publishing, check if the post fork is conflicted
	 * If so, intercept the publish, mark the conflict, and revert to draft
	 */
	function check_merge_conflict( $post, $postarr ) {

		//verify post type
		if ( $post['post_type'] != 'fork' )
			return $post;

		//not an update
		if ( !isset( $postarr['ID'] ) )
			return $post;

		//not publishing
		if ( $post['post_status'] != 'publish' )
			return $post;

		//never let a user publish conflict markup
		if ( $this->has_conflict_markup( $post ) ) {
			$post['post_status'] = 'draft';
			add_filter( 'redirect_post_location', array( &$this, 'redirect_message_filter' ) );
			return $post;
		}

		//not conflicted, no need to do anything here, let the merge go through
		if ( !$this->is_conflicted( (object) $postarr ) )
			return $post;

		//we've previously flagged the conflict, they've resolved it, let it go through
		if ( (bool) get_post_meta( $postarr['ID'], $this->conflict_meta, true ) ) {
			delete_post_meta( $postarr['ID'], $this->conflict_meta );
			return $post;
		}

		$post['post_content'] = $this->get_merged( (object) $postarr );
		$post['post_status'] = 'draft';

		//mark that it's conflicted, so on subsequent publish (resolved) we can force
		add_post_meta( $postarr['ID'], $this->conflict_meta, true );

		add_filter( 'redirect_post_location', array( &$this, 'redirect_message_filter' ) );
		return $post;

	}


	function conflict_warning() {
		global $post;

		if ( get_current_screen()->post_type != 'fork' )
			return;

		if ( get_current_screen()->base != 'post' )
			return;

		if ( !$post )
			return;

		if ( get_post_type( $post ) != 'fork' )
			return;

		if ( !$this->has_conflict_markup( $post ) )
			return;

		$this->parent->template( 'conflict-warning' );

	}


	function redirect_message_filter( $url ) {

		$args = wp_parse_args( $url );

		if ( !isset( $args['message'] ) )
			return $url;

		return remove_query_arg( 'message', $url );

	}


	/**
	 * Intercept the publish action and merge forks into their parent posts
	 */
	function intercept_publish( $new, $old, $post ) {

		if ( wp_is_post_revision( $post ) )
			return;

		if ( $post->post_type != 'fork' )
			return;

		if ( $new != 'publish' )
			return;

		$post = $this->merge( $post->ID );

		wp_safe_redirect( admin_url( "post.php?action=edit&post={$post}&message=6" ) );
		exit();

	}


	/**
	 * Check if the fork has conflict markup
	 */
	function has_conflict_markup( $fork ) {

		//postarr is being passed on publish
		//convert to object for consistency
		if ( is_array( $fork ) )
			$fork = (object) $fork;

		if ( !is_object( $fork ) )
			$fork = get_post( $fork );

		$pattern = sprintf( '#\<\<\<\<\<\<\<|\>\>\>\>\>\>\>|&lt;&lt;&lt;&lt;&lt;&lt;&lt;|&gt;&gt;&gt;&gt;&gt;&gt;&gt;#s', __( 'Fork', 'post-forking' ), __( 'Current Version', 'post-forking' ) );

		return (bool) preg_match( $pattern, $fork->post_content );

	}


}
