<?php
/**
 * Module to handle post-format updates to individual posts.
 *
 * @module LivePress
 * @since 0.7
 */

/**
 * Singleton class for managing post formats and updates.
 *
 * Covers inserting new updates into the database, overriding post displays to hide them from the normal display,
 * and pulling them back out in the appropriate loops for display on individual post pages.
 */
class LivePress_PF_Updates {
	/**
	 * Singleton instance
	 *
	 * @var bool|LivePress_PF_Updates
	 */
	protected static $instance = false;

	/**
	 * LivePress API communication instance.
	 * @var LivePress_Communication
	 */
	var $lp_comm;

	/**
	 * LivePress API key as stored in the database.
	 *
	 * @var string
	 */
	var $api_key;

	/**
	 * Order in which updates are displayed.
	 *
	 * @var string
	 */
	var $order;

	/**
	 * Array of region information for each update.
	 *
	 * Must be populated by calling assemble_pieces() and specifying the Post for which to parse regions.
	 *
	 * @var array[]
	 */
	var $pieces;

	/**
	 * Array of live tags.
	 *
	 * Must be populated by calling assemble_pieces() and specifying the Post for which to parse regions.
	 *
	 * @var array[]
	 */
	var $livetags;

	/**
	 * Collected post meta information, populated by assemble_pieces()
	 *
	 * post_modified_gmt = timestamp of most recent post modify
	 * near_uuid = uuid of post update, that was around 2 minutes ago (or last, if last older)
	 */
	var $post_modified_gmt;
	var $near_uuid;
	var $cache = false;

	/**
	 * Private constructor used to build the singleton instance.
	 * Registers all hooks and filters.
	 *
	 * @access protected
	 */
	protected function __construct() {
		$options = get_option( LivePress_Administration::$options_name );

		if ( false != $options && array_key_exists( 'api_key', $options ) ){
			$this->api_key = $options['api_key'];
			$this->lp_comm = new LivePress_Communication( $this->api_key );

			$this->order = $options['feed_order'];
		}

		// Wire actions
		add_action( 'wp_ajax_start_ee',              array( $this, 'start_editor' ) );
		add_action( 'wp_ajax_lp_append_post_update', array( $this, 'append_update' ) );
		add_action( 'wp_ajax_lp_change_post_update', array( $this, 'change_update' ) );

		add_action( 'wp_ajax_lp_append_post_draft', array( $this, 'append_draft' ) );
		add_action( 'wp_ajax_lp_change_post_draft', array( $this, 'change_draft' ) );

		add_action( 'wp_ajax_lp_delete_post_update', array( $this, 'delete_update' ) );
		add_action( 'before_delete_post',            array( $this, 'delete_children' ) );
		add_action( 'pre_get_posts',                 array( $this, 'filter_children_from_query' ) );

		// Wire filters
		add_filter( 'parse_query',                   array( $this, 'hierarchical_posts_filter' ) );
		add_filter( 'the_content',                   array( $this, 'process_oembeds' ), -10 );
		add_filter( 'the_content',                   array( $this, 'append_more_tag' ) );
		add_filter( 'the_content',                   array( $this, 'add_children_to_post' ) );
	}

	/**
	 * Static method used to retrieve the current class instance.
	 *
	 * @static
	 *
	 * @return LivePress_PF_Updates
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Posts cannot typically have parent-child relationships.
	 *
	 * Our updates, however, are all "owned" by a traditional
	 * post so we know how to lump things together on the front-end
	 * and in the post editor.
	 *
	 * @param WP_Query $query Current query.
	 *
	 * @return WP_Query
	 */
	public function hierarchical_posts_filter( $query ) {
		global $pagenow, $typenow;

		if ( is_admin()
			&& 'edit.php' == $pagenow
			&& in_array( $typenow, apply_filters( 'livepress_post_types', array( 'post' ) ) ) ) {
			$query->query_vars['post_parent'] = 0;
		}

		return $query;
	}

	/**
	 * If a post has children (live updates) automatically append a read more link.
	 * Also, automatically pad the post's content with the first update if content
	 * is empty.
	 *
	 * @param string $content Post content
	 *
	 * @return string
	 */
	public function append_more_tag( $content ) {
		global $post;

		if ( ! is_object( $post ) ) {
			return $content;
		}

		if ( isset( $post->no_update_tag ) || is_single() || is_admin() ||
			(defined( 'XMLRPC_REQUEST' ) && constant( 'XMLRPC_REQUEST' )) ) {
			return $content;
		}

		// First, make sure the content is non-empty
		$content = $this->hide_empty_update( $content );

		$is_live = LivePress_Updater::instance()->blogging_tools->get_post_live_status( $post->ID );

		if ( $is_live ) {
			$more_link_text = esc_html__( '(see updates...)', 'livepress' );

			$pad = $this->pad_content( $post );

			$content .= apply_filters( 'livepress_pad_content', $pad, $post );
			$content .= apply_filters( 'the_content_more_link', ' <a href="' . get_permalink() . "#more-{$post->ID}\" class=\"more-link\">$more_link_text</a>", $more_link_text );
			$content  = force_balance_tags( $content );
		}

		return $content;
	}

	/**
	 * Don't display unnecessarily empty LivePress HTML tags.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	protected function hide_empty_update( $content ) {
		if ( $this->is_empty( $content ) ) {
			$content = '';
		}

		return $content;
	}

	/**
	 * Adjust the_content filter removing all but a handful of whitelisted filters,
	 * preventing plugins from adding content to the live update stream
	 *
	 * @since 1.0.9
	 */
	private function clear_most_the_content_filters() {
		global $wp_filter;
		//return;
		//
		if ( empty( $wp_filter['the_content'] ) ) {
			return;
		}

		// White list of the filters we want to preserve
		$whiltelisted_content_filters = array(
			'process_oembeds',
			'run_shortcode',
			'autoembed',
			'wptexturize',
			'convert_smilies',
			'convert_chars',
			'wpautop',
			'shortcode_unautop',
			'capital_P_dangit',
			'do_shortcode',
			'add_children_to_post',
			);

		// Iterate thru all existing the_content filters
		foreach ( $wp_filter['the_content'] as $filterkey => $filtervalue ) {
			// Filters are in arrays by priority, so iterate thru each of those
			foreach ( $filtervalue as $contentfilterkey => $contentfiltervalue ) {
				$found_in_whitelist = false;
				// Loop thru the whitelisted filters to see if this filter should be unset
				foreach ( $whiltelisted_content_filters as $white ) {
					if ( false !== strpos( $contentfilterkey, $white ) ) {
						$found_in_whitelist = true;
						break;
					}
				}

				// If the filter is not in our whitelist, remove it
				if ( ! $found_in_whitelist ){
					unset( $wp_filter['the_content'][ $filterkey ][ $contentfilterkey ] );
				}
			}
		}

		return;
	}

	/**
	 * Filter posts on the front end so that individual updates appear as separate elements.
	 *
	 * Filter automatically removes itself when called the first time.
	 *
	 * @param string $content Parent post content.
	 *
	 * @return string
	 */
	public function add_children_to_post( $content ) {
		global $post;

		if ( apply_filters( 'livepress_the_content_filter_disabled', false ) ) {
			return $content;
		}

		// Only filter on single post pages
		if ( ! is_singular( apply_filters( 'livepress_post_types', array( 'post' ) ) ) ) {
			return $content;
		}
		if ( ! LivePress_Updater::instance()->blogging_tools->get_post_live_status( get_the_ID() ) ) {
			return $content;
		}

		$this->assemble_pieces( $post );

		$response = array();
		foreach ( $this->pieces as $piece ) {
			$update_meta = $piece['meta'];
			if ( ! is_array( $update_meta ) || ! array_key_exists( 'draft', $update_meta ) || true !== $update_meta['draft'] ){
				$response[] = $piece['prefix'];
				$response[] = $piece['proceed'];
				$response[] = $piece['suffix'];
			}
		}

		// Clear the original content if we have live updates
		if ( 0 === count( $response ) ){
			$content = '';
		}
		$content = join( '', $response );
		$content = LivePress_Updater::instance()->add_global_post_content_tag( $content, $this->post_modified_gmt, $this->livetags );

		return $content;
	}

	/**
	 * If the post's content is below a certain threshhold, pad it with updates until it's reasonable.
	 *
	 * @param WP_Post $post
	 *
	 * @return string
	 */
	public function pad_content( $post ) {
		// Temporarily unhook this filter
		remove_filter( 'the_content', array( $this, 'append_more_tag' ) );

		$extras = '';

		$content = trim( $post->post_content );
		$excerpt = trim( $post->post_excerpt );

		if ( $this->is_empty( $content ) && $this->is_empty( $excerpt ) ) {

			// Use transient cache to ensure child query only runs once/minute
			if ( false === ( $extras = get_transient( 'lp_first_child_extra_' . $post->ID ) ) ) {
				// We have no content to display. Grab the post's first update and return it instead.
				$children = get_children(
					array(
						'post_type'        => 'post',
						'post_parent'      => $post->ID,
						'numberposts'      => 1,
						'suppress_filters' => false,
					)
				);

				if ( count( $children ) > 0 ) {
					reset( $children );
					$child    = $children[key( $children )];
					$piece_id = get_post_meta( $child->ID, '_livepress_update_id', true );

					$extras = apply_filters( 'the_content', $child->post_content );
				}
				set_transient( 'lp_first_child_extra_' . $post->ID, $extras, MINUTE_IN_SECONDS );
			}
		}

		// Re-add filters
		add_filter( 'the_content', array( $this, 'append_more_tag' ) );

		return $extras;
	}

	/**
	 * Don't show child posts on the front page of the site, they'll be pulled in separately as updates to a live post.
	 *
	 * @param WP_Query $query The current query
	 *
	 * @return WP_Query
	 */
	public function filter_children_from_query( WP_Query $query ) {

		$post_type = $query->get( 'post_type' );

		// only applies to indexes and post format
		if ( ( $query->is_home() || $query->is_archive() ) &&  ( empty( $post_type ) || in_array( $post_type, apply_filters( 'livepress_post_types', array( 'post' ) ) ) ) ) {
			$parent = $query->get( 'post_parent' );
			if ( empty( $parent ) ) {
				$query->set( 'post_parent', 0 );
			}
		}
	}

	/**
	 * Prepend a region identifier to a post update so we can check it later.
	 *
	 * @param int     $post_ID
	 * @param WP_Post $post
	 */
	public function prepend_lp_comment( $post_ID, $post ) {
		if ( ! in_array( $post->post_type, apply_filters( 'livepress_post_types', array( 'post' ) ) ) ) {
			return;
		}

		// If the content already has the LivePress comment field, remove it and re-add it
		if ( 1 === preg_match( '/\<\!--livepress(.+)--\>/', $post->post_content ) ) {
			$post->post_content = preg_replace( '/\<\!--livepress(.+)--\>/', '', $post->post_content );
		}

		if ( '' === $post->post_content ) {
			return;
		}

		$md5 = md5( $post->post_content );

		$post->post_content = "<!--livepress md5={$md5} id={$post_ID}-->" . $post->post_content;

		// Remove the action before updating
		remove_action( 'wp_insert_post', array( $this, 'prepend_lp_comment' ) );
		wp_update_post( $post );
		add_action( 'wp_insert_post', array( $this, 'prepend_lp_comment' ), 10, 2 );
	}

	/*****************************************************************/
	/*                         AJAX Functions                        */
	/*****************************************************************/

	/**
	 * Enable the Real-Time Editor for LivePress.
	 *
	 * Fetch the content of the user's current textarea and return:
	 * - Original post content, split into regions with distinct IDs
	 * - Processed content, again split into regions
	 * - User's POSTed content, split into regions with same IDs as original post
	 * - Processed POSTed content, split into regions
	 */
	public function start_editor() {
		// Globalize $post so we can modify it a bit before using it
		global $post;

		// Set up the $post object
		$post_id = (int)$_POST['post_id'];
		$post = get_post( $post_id );
		$post->no_update_tag = true;

		if ( isset( $_POST['content'] ) ) {
			$user_content = wp_kses_post( stripslashes( $_POST['content'] ) );
		} else {
			$user_content = '';
		}

		$this->assemble_pieces( $post );

		// If the post content is not empty, and there are no child posts, the post has
		// just been made live.  Insert the content as a live update
		if ( 0 == count( $this->pieces ) ) {
			if ( '' !== $user_content ) {
				// Add a live update with the current content
				$this->add_update( $post, $user_content, '' );
				$this->assemble_pieces( $post );
			}
		}

		$original = $this->pieces;

		if ( $post->post_content == $user_content ) {
			$user = null;
		} else {
			// Proceed user-supplied post content.
			$user = $this->pieces;
		}

		$ans = array(
			'orig'        => $original,
			'user'        => $user,
			'edit_uuid'   => $this->near_uuid,
			'editStartup' => Collaboration::return_live_edition_data()
		);

		header( 'Content-type: application/javascript' );
		echo json_encode( $ans );
		die;
	}

	/**
	 * Insert a new child post to the current post via AJAX.
	 *
	 * @uses LivePress_PF_Updates::add_update
	 */
	public function append_update( $is_draft = false ) {
		global $post;
		check_ajax_referer( 'livepress-append_post_update-' . intval( $_POST['post_id'] ) );

		$post = get_post( intval( $_POST['post_id'] ) );
		$user_content = wp_kses_post( wp_unslash( trim( $_POST['content'] ) ) );
		// grab and escape the live update tags
		$livetags = isset( $_POST['liveTags'] ) ? array_map( 'esc_attr', $_POST['liveTags'] ) : array();

		if ( array_key_exists( 'update_meta', $_POST ) ){
			$update_meta = $_POST['update_meta'];
		}
		$update_meta['draft'] = ( $is_draft ) ? true : false;
		// $response = $this::add_update($post, $user_content);
		// PHP 5.2 compat static call
		$response = call_user_func_array( array( $this, 'add_update'), array( $post, $user_content, $livetags , $update_meta ) );

		header( 'Content-type: application/javascript' );
		echo json_encode( $response );
		die;
	}


	/**
	 * Insert a new child post to the current post via AJAX.
	 *
	 * @uses LivePress_PF_Updates::add_update
	 */
	public function append_draft() {
		$this->append_update( true );
	}
	/**
	 * Modify an existing update. Basically, replace the content of a child post with some other content.
	 *
	 * @uses wp_update_post() Uses the WordPress API to update post content.
	 */
	public function change_update( $is_draft = false ) {
		global $post;
		check_ajax_referer( 'livepress-change_post_update-' . intval( $_POST['post_id'] ) );

		$post = get_post( intval( $_POST['post_id'] ) );

		if ( array_key_exists( 'update_meta', $_POST ) ){
			$update_meta = $_POST['update_meta'];
		}
		$update_meta['draft'] = $is_draft;

		// 	TODO: allow contrib / authors to save drafts controlled from options
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			die;
		}
		$region = false;
		$update_id = intval( $_POST['update_id'] );
		$update = $this->get_update( $post->ID, $update_id );

		// set the custom timestanp to current publish date
		$lp_updater = new LivePress_Updater();
		$lp_updater->set_custom_timestamp( $update->post_date );

		if ( null == $update ) {
			// Todo: notify about error: post get deleted by another editor
			$region = false;
		} else {
			//	need to double unslash here to normalise content
			$user_content = wp_kses_post( stripslashes( stripslashes( $_POST['content'] ) ) );

			if ( empty($user_content) ) {
				$region = false;
			} else {
				// Save updated post content to DB
				list($_, $piece_id, $piece_gen) = explode( '__', $update->post_title, 3 );
				$piece_gen++;
				$update->post_title = 'livepress_update__'.$piece_id.'__'.$piece_gen;
				$update->post_content = $user_content;

				wp_update_post( $update );
	            $region = $this::send_to_livepress_incremental_post_update( 'replace', $post, $update, $update_meta );

			}
		}

		header( 'Content-type: application/javascript' );
		echo json_encode( $region );
		die;
	}

	/**
	 * Modify an existing update. Basically, replace the content of a child post with some other content.
	 *
	 * @uses wp_update_post() Uses the WordPress API to update post content.
	 */
	public function change_draft() {
		$this->change_update( true );
	}

	/**
	 * Removes an update from the database entirely.
	 *
	 * @uses wp_delete_post() Uses the WordPress API to delete a post.
	 */
	public function delete_update() {
		global $post;
		check_ajax_referer( 'livepress-delete_post_update-' . intval( $_POST['post_id'] ) );

		$post = get_post( intval( $_POST['post_id'] ) );

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			die();
		}

		$update_id = intval( $_POST['update_id'] );
		$update = $this->get_update( $post->ID, $update_id );
		if ( null == $update ) {
			$region = false;
		} else {
			list($_, $piece_id, $piece_gen) = explode( '__', $update->post_title, 3 );
			$piece_gen++; // Deleted is new generation
			$update->post_title = 'livepress_update__'.$piece_id.'__'.$piece_gen;
			wp_delete_post( $update->ID, true );
			$region = $this::send_to_livepress_incremental_post_update( 'delete', $post, $update );
		}

		header( 'Content-type: application/javascript' );
		echo json_encode( $region );
		die;
	}

	/*****************************************************************/
	/*                         Helper Functions                      */
	/*****************************************************************/

	/**
	 * Add an update to an existing post.
	 *
	 * @param int|WP_Post $parent   Either the ID or object for the post which you are updating.
	 * @param string      $content  Post content.
	 * @param string      @livetags Live update tags for this update.
	 *
	 * @return int|WP_Error
	 *
	 * @uses wp_insert_post() Uses the WordPress API to create a new child post.
	 */
	public function add_update( $parent, $content, $livetags, $update_meta ) {
		global $current_user, $post;
		get_currentuserinfo();

		if ( ! is_object( $parent ) ) {
			$parent = get_post( $parent );
		}
		$save_post = $post;
		$post = $parent;

		if ( empty( $content ) ) {
			$response = false;
		} else {
			$plugin_options = get_option( livepress_administration::$options_name );
			if ( $plugin_options['feed_order'] == 'top' ) {
				$append = 'prepend';
			} else {
				$append = 'append';
			}

			$piece_id = mt_rand( 10000000, 26242537 );
			$piece_gen = 0;
			$update = wp_insert_post(
				array(
					 'post_author' => $current_user->ID,
					 'post_content' => $content,
					 'post_parent' => $post->ID,
					 'post_title' => 'livepress_update__'.$piece_id.'__'.$piece_gen,
					 'post_name' => 'livepress_update__'.$piece_id,
					 'post_type' => 'post',
					 'post_status' => 'inherit'
				),
				true
			);

			if ( is_wp_error( $update ) ) {
				$response = false;
			} else {
			    set_post_format( $update, 'aside' );
			    // Associate any livetags with this update
			    if ( ! empty( $livetags ) ) {
			    	wp_add_object_terms( $update, $livetags, 'livetags' );
			    }
				$response = $this::send_to_livepress_incremental_post_update( $append, $post, $update, $update_meta );
			}
		}

		$post = $save_post;
		return $response;
	}

	/**
	 * Merge nested child posts into a parent post.
	 *
	 * @param  int  $post_id ID of the parent post
	 * @return $post post object
	 */
	public function merge_children( $post_id ) {
		global $post;

		$post_id = (int) $post_id; // Force a cast as an integer.

		$post = get_post( $post_id );

		// If post has no children bail
		if ( 0 == count( get_children(
			array(
					'post_type'        => 'post',
					'post_parent'      => $post_id,
					'numberposts'      => 1,
					'suppress_filters' => false,
				) ) ) ) {
					return $post;
		}

		// Sanity check: only merge children of top-level posts.
		if ( 0 !== $post->post_parent ) {
			return $post;
		}
		remove_filter( 'parse_query', array( $this, 'hierarchical_posts_filter' ) );

		$post_content = $post->post_content;

		// Remove all the_content filters for merge
		global $wp_filter;
		$stored_wp_filter_the_content = $wp_filter['the_content'];
		$wp_filter['the_content'] = array();
		// Assemble all the children for merging
		$this->assemble_pieces( $post );
		// Restore the_content filters
		$wp_filter['the_content'] = $stored_wp_filter_the_content;

		// we want to render the HTML that the 'livepress_metainfo' shortcode will output into the Post content inorder fix the post
		global $shortcode_tags; // all the shortcode
		$golbal_shortcodes_tags = $shortcode_tags; // save so re-added them
		remove_all_shortcodes();
		$shortcode_tags['livepress_metainfo'] = $golbal_shortcodes_tags['livepress_metainfo']; // just back the shortcode need

		$response = array();
		// Wrap each child for display
		foreach ( $this->pieces as $piece ) {
			// skip if draft
			// this content will only have the current author's draft but we need to skip that
			$update_meta = $piece['meta'];
			if (
				false !== $update_meta &&
				is_array( $update_meta ) &&
				array_key_exists( 'draft', $update_meta ) &&
				true == $update_meta['draft']
			) {
				continue;
			}

			$prefix = sprintf( '<div id="livepress-old-update-%s" class="livepress-old-update">', $piece[ 'id' ] );
			$response[] = $prefix;
			$response[] = do_shortcode( $piece[ 'proceed' ] );
			$response[] = PHP_EOL.'</div>';
		}

		$shortcode_tags = $golbal_shortcodes_tags; // reset the short codes

		// Append the children to the parent post content
		$post->post_content = join( '', $response );

		// Update the post
		wp_update_post( $post );

		// Delete the merged children
		$this->delete_children( $post_id );

		add_filter( 'parse_query',        array( $this, 'hierarchical_posts_filter' ) );

		return $post;
	}

	/**
	 * Remove nested child posts when a parent is removed.
	 *
	 * @param int $parent ID of the parent post being deleted
	 */
	public function delete_children( $parent ) {

		// Remove the query filter.
		remove_filter( 'parse_query', array( $this, 'hierarchical_posts_filter' ) );

		$parent = (int) $parent; // Force a cast as an integer.

		$post = get_post( $parent );
		// Only delete children of top-level posts.
		if ( 0 !== $post->post_parent ) {
			return;
		}

		// Get all children
		$children = get_children(
			array(
				'post_type'        => 'post',
				'post_parent'      => $parent,
				'suppress_filters' => false,
			)
		);

		// Remove the action so it doesn't fire again
		remove_action( 'before_delete_post', array( $this, 'delete_children' ) );

		foreach ( $children as $child ) {
			// Never delete top level posts!
			if ( 0 === (int) $child->post_parent ) {
				continue;
			}
			// Note: before_delete_post filter will also fire remove_related_followers which will call
			// the LivePress server API clear_guest_blogger
			wp_delete_post( $child->ID, true );
		}
		add_action( 'before_delete_post', array( $this, 'delete_children' ) );
		add_filter( 'parse_query',        array( $this, 'hierarchical_posts_filter' ) );

	}

	/**
	 * Get the full content of a given parent post.
	 *
	 * @param object $parent
	 *
	 * @return string
	 */
	public function get_content( $parent ) {

		$this->assemble_pieces( $parent );

		$pieces = array();
		foreach ( $this->pieces as $piece ) {
			$pieces[] = $piece['content'];
		}

		return join( '', $pieces );
	}

	/**
	 * Send an update (add/change/delete) to LivePress' API
	 *
	 * @param string  $op     Operation (append/prepend/replace/delete)
	 * @param WP_Post $post   Parent post object
	 * @param WP_Post $update Update piece object
	 *
	 * @return array[] $region Object to send to editor
	 */
	protected function send_to_livepress_incremental_post_update( $op, $post, $update, $update_meta ) {
		if ( ! is_object( $post ) ) {
			$post = get_post( $post );
		}
		if ( ! is_object( $update ) ) {
			$update = get_post( $update );
		}

		// FIXME: may be better use $update->post_author there ?
		$user = wp_get_current_user();
		if ( $user->ID ) {
			if ( empty( $user->display_name ) ) {
				$update_author = addslashes( $user->user_login );
			}
			$update_author = addslashes( $user->display_name );
		} else {
			$update_author = '';
		}

		list($_, $piece_id, $piece_gen) = explode( '__', $update->post_title, 3 );
		global $wp_filter;
		// Remove all the_content filters so child posts are not filtered
		// removing share, vote and other per-post items from the live update stream.
		// Store the filters first for later restoration so filters still fire outside the update stream
		$stored_wp_filter_the_content = $wp_filter;
		$this->clear_most_the_content_filters();

		$region = array(
			'id'      => $piece_id,
			'lpg'     => $piece_gen,
			'op'      => $op,
			'content' => $update->post_content,
			'proceed' => do_shortcode( apply_filters( 'the_content', $update->post_content ) ),
			'prefix'  => sprintf( '<div id="livepress-update-%s" data-lpg="%d" class="livepress-update">', $piece_id, $piece_gen ),
			'suffix'  => '</div>'
		);

		// Restore the_content filters and carry on
		$wp_filter = $stored_wp_filter_the_content;
		$message = array(
			'op' => $op,
			'post_id' => $post->ID,
			'post_title' => $post->post_title,
			'post_link' => get_permalink( $post->ID ),
			'post_author' => $update_author,
			'update_id' => 'livepress-update-'.$piece_id,
			// todo: get updated from post update?
			'updated_at' => get_gmt_from_date( current_time( 'mysql' ) ) . 'Z',
			'uuid' => $piece_id.':'.$piece_gen,
			'edit' => json_encode( $region ),
		);

		if ( $op == 'replace' ) {
			$message['new_data'] = $region['prefix'].$region['proceed'].$region['suffix'];
		}
		elseif ( $op == 'delete' ) {
			$region['content'] = ''; // remove content from update for delete
			$region['proceed'] = '';
		}
		else {
			$message['data'] = $region['prefix'].$region['proceed'].$region['suffix'];
		}

		if ( true !== $update_meta['draft'] ){
			try {
				$job_uuid = $this->lp_comm->send_to_livepress_incremental_post_update( $op, $message );
				LivePress_WP_Utils::save_on_post( $post->ID, 'status_uuid', $job_uuid );
			} catch( livepress_communication_exception $e ) {
				$e->log( 'incremental post update' );
			}
		}

		// Set the parent post as having been updated
		$status = array( 'automatic' => 1, 'live' => 1 );
		update_post_meta( $post->ID, '_livepress_live_status', $status );
		$region['status'] = $status;

		// add meta to the child update
		update_post_meta( $update->ID, '_livepress_update_meta', $update_meta );
		$region['update_meta'] = $update_meta;

		return $region;
	}

	/**
	 * Cache pieces, so info get populated, but next call will not reevaluate
	 *
	 * @param object $parent Parent post for which we're assembling pieces
	 */
	function cache_pieces( $parent ) {
		remove_filter( 'the_content', array( $this, 'add_children_to_post' ) );
		$this->assemble_pieces( $parent, true );
		add_filter( 'the_content', array( $this, 'add_children_to_post' ) );
	}

	/**
	 * Populate the pieces array based on a given parent post.
	 *
	 * @param object $parent Parent post for which we're assembling pieces
	 */
	protected function assemble_pieces( $parent, $cache = false ) {
		global $wp_filter;
		// Remove all the_content filters so child posts are not filtered
		// removing share, vote and other per-post items from the live update stream.
		// Store the filters first for later restoration so filters still fire outside the update stream
		$stored_wp_filter_the_content = $wp_filter;
		$this->clear_most_the_content_filters();

		if ( ! is_object( $parent ) ) {
			$parent = get_post( $parent );
		}
		if ( $cache ) {
			$this->cache = $parent->ID;
		}
		elseif ( $this->cache == $parent->ID ) {
			return;
		}

		$this->post_modified_gmt = $parent->post_modified_gmt;
		$this->near_uuid = '';
		$min_uuid_ts = 2 * 60; // not earlier, than 2 minutes
		$near_uuid_ts = 0;
		$now = new DateTime();

		$pieces = array();

		// Set up child posts
		$children = get_children(
			array(
				'post_type'        => 'post',
				'post_parent'      => $parent->ID,
				'suppress_filters' => false,
			)
		);
		$child_pieces = array();
		$live_tags    = array();
		$update_count = 0;
		$child_count  = count( $children );
		$user_id = get_current_user_id();
		if ( $child_count > 0 ) {
			foreach ( $children as $child ) {

				$update_meta = get_post_meta( $child->ID, '_livepress_update_meta', true );
				$is_draft = (
					false !== $update_meta &&
					is_array( $update_meta ) &&
					array_key_exists( 'draft', $update_meta ) &&
					true == $update_meta['draft']
				) ? true : false;

				// if this is a draft only include the current authors posts
				if ( $is_draft && $user_id !== absint( $child->post_author ) ){
					continue;
				}

				$update_count++;
				$post = $child;
				list($_, $piece_id, $piece_gen) = explode( '__', $child->post_title, 3 );
				$this->post_modified_gmt = max( $this->post_modified_gmt, $child->post_modified_gmt );

				$modified = new DateTime( $child->post_modified_gmt, new DateTimeZone( 'UTC' ) );;
				$since    = $now->format( 'U' ) - $modified->format( 'U' );
				if ( $since > $min_uuid_ts && ($since < $near_uuid_ts || $near_uuid_ts == 0) ) {
					$near_uuid_ts = $since;
					$this->near_uuid = $piece_id.':'.$piece_gen;
				}
				// Grab and integrate any live update tags
				$update_tags = get_the_terms( $child->ID, 'livetags' );
				$update_tag_classes = ( $is_draft ) ? ' livepress-draft ' : '';
				if ( ! empty( $update_tags ) ) {
					foreach ( $update_tags as $a_tag ) {
						$live_tag_name = $a_tag->name;
						$update_tag_classes .= ' live-update-livetag-' . sanitize_title_with_dashes( $live_tag_name );
						if ( ! in_array( $live_tag_name, $live_tags ) ) {
							array_push( $live_tags, $live_tag_name );
						}
					}
					$update_tag_classes .= ' livepress-has-tags ';
				}
				$pin_header = LivePress_Updater::instance()->blogging_tools->is_post_header_enabled( $parent->ID );
				$piece = array(
					'id'      => $piece_id,
					'lpg'     => $piece_gen,
					'content' => $child->post_content,
					'proceed' => apply_filters( 'the_content', $child->post_content ),
					'prefix'  => sprintf(
						'<div id="livepress-update-%s" data-lpg="%d" class="livepress-update%s %s">',
						$piece_id,
						$piece_gen,
						$update_tag_classes,
					( $child_count == $update_count && $pin_header ) ? 'pinned-first-live-update' : '' ),
							'suffix'  => '</div>',
					'meta'	=> $update_meta
				);

				$child_pieces[] = $piece;
			}
		}
		// Display posts oldest-newest by default
		$child_pieces = array_reverse( $child_pieces );
		$pieces = array_merge( $pieces, $child_pieces );
		if ( 0 !== count( $pieces ) ) {
			if ( 'top' == $this->order ) {
				// If the header is pinned and the order reversed, ensure the first post remains first
				$pin_header = LivePress_Updater::instance()->blogging_tools->is_post_header_enabled( $parent->ID );
				if ( $pin_header ){
					$first  = array_shift( $pieces );
					$pieces = array_reverse( $pieces );
					array_unshift( $pieces, $first );
				} else {
					$pieces = array_reverse( $pieces );
				}
			}
		}
		$this->pieces = $pieces;
		$this->livetags = $live_tags;

			// Restore the_content filters and carry on
		$wp_filter = $stored_wp_filter_the_content;

	}

	public function process_oembeds( $content ) {
		return preg_replace( '&((?:<!--livepress.*?-->|\[livepress[^]]*\])\s*)(https?://[^\s"]+)&', '$1[embed]$2[/embed]', $content );
	}

	/**
	 * Check if a post update is empty (blank or only an HTML comment).
	 *
	 * @access protected
	 *
	 * @param string $post_content
	 *
	 * @return boolean
	 */
	protected function is_empty( $post_content ) {
		$empty_tag_position = strpos( $post_content, '<!--livepress md5=d41d8cd98f00b204e9800998ecf8427e' );
		return empty( $post_content ) || false !== $empty_tag_position || ( is_int( $empty_tag_position ) && $empty_tag_position >= 0 );
	}

	/**
	 * Check if an update is new or if it has been previously saved
	 *
	 * @access protected
	 *
	 * @param int $post_id
	 *
	 * @return boolean true if post has just been created.
	 */
	protected function is_new( $post_id ) {
		$options = array(
			'post_parent'      => $post_id,
			'post_type'        => 'revision',
			'numberposts'      => 2,
			'post_status'      => 'any',
			'suppress_filters' => false,
		);

		$updates = get_children( $options );
		if ( count( $updates ) == 0 ) {
			return true;
		} elseif ( count( $updates ) >= 2 ) {
			$first = array_shift( $updates );
			$last  = array_pop( $updates );
			return $last->post_modified_gmt == $first->post_modified_gmt;
		} else {
			return true;
		}
	}

	/**
	 * Get an update to a post from the database.
	 *
	 * @access protected
	 *
	 * @param int $parent_id            Parent post from which to retrieve an update.
	 * @param int $livepress_update_id  ID of the update to retrieve.
	 *
	 * @return null|object Returns null if a post doesn't exist.
	 */
	protected function get_update( $parent_id, $livepress_update_id ) {
		global $wpdb;

		$query = $wpdb->prepare( '
			SELECT      *
			FROM        ' . $wpdb->posts . '
			WHERE       post_name   = %s
			AND         post_type   = "post"
			AND         post_parent = %s
			LIMIT 1',
			'livepress_update__' . $livepress_update_id,
			$parent_id
		);
		$wpdb->get_results( $query );

		if ( 0 === $wpdb->num_rows ) {
			return null;
		}

		return $wpdb->last_result[0];
	}
}

LivePress_PF_Updates::get_instance();
