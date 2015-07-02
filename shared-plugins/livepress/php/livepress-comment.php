<?php
/**
 * Everything comment related
 *
 * @package Livepress
 */

require_once( LP_PLUGIN_PATH . 'php/livepress-config.php' );
require_once( LP_PLUGIN_PATH . 'php/livepress-javascript-config.php' );
require_once( LP_PLUGIN_PATH . 'php/livepress-communication.php' );
require_once( LP_PLUGIN_PATH . 'php/livepress-wp-utils.php' );

class LivePress_Comment {
	public static $comments_template_path;
	public static $comments_template_tag = array(
		'start' => '<div id="post_comments_livepress">',
		'end'   => '</div>',
	);

	private $overridden_comments_count;

	private static $ajax_response_codes = array(
		'error'         => 404,
		'closed'        => 403,
		'pending'       => 202,
		'not_allowed'   => 401,
		'missing_fields'    => 400,
		'approved'      => 200,
		'spam'          => 405,
		'unapproved'    => 406,
		'flood'         => 407,
		'duplicate'     => 409,
	);

	// Global LP configuration options
	private $options;
	private $lp_com;

	/**
	 * Constructor.
	 *
	 * @param $options
	 */
	function __construct( $options ) {
		$this->options = $options;
		$this->lp_com  = new LivePress_Communication( $this->options['api_key'] );
	}

	/**
	 * Attach comment related code to WP actions and filters.
	 *
	 * @param boolean $is_ajax_lp_comment_request
	 */
	public function do_wp_binds( $is_ajax_lp_comment_request ) {
		// Send to livepress for diff everytime something changes in the comments list.
		add_action( 'comment_post', array( &$this, 'send_to_livepress_new_comment' ) );

		// TODO: fix those operations with comments
		add_action( 'transition_comment_status', array( &$this, 'send_comment_if_approved' ), 10, 3 );

		add_action( 'wp_ajax_lp_post_comment',        array( &$this, 'lp_post_comment' ) );
		add_action( 'wp_ajax_nopriv_lp_post_comment', array( &$this, 'lp_post_comment' ) );
		add_action( 'wp_ajax_lp_dim_comment',         array( &$this, 'lp_dim_comment' ) );

		if ( $is_ajax_lp_comment_request ) {
			add_action( 'comment_flood_trigger',     array( &$this, 'received_a_flood_comment' ) );
		} else {
			add_filter( 'comments_template', array( &$this, 'enclose_comments_in_div' ) );
		}
	}

	// Verifies the nonce for the live edit screen, takes the comment id as $_POST['id'] and
	// returns the nonce required to take action on a comment
	public function lp_dim_comment() {

		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( $comment = get_comment( $id ) ) {
			check_ajax_referer( "post_comment" );
			$nonce = array(
				'approve_comment_nonce' => wp_create_nonce( 'approve-comment_' . $id ),
				'delete_comment_nonce'  => wp_create_nonce( 'delete-comment_' . $id )
				);
			wp_send_json_success( $nonce );
		} else {
			wp_send_json_error();
		}
	}



	/**
	 * Add comments needed by JS on frontend
	 *
	 * @param LivePress_JavaScript_Config $ljsc
	 * @param Post    $post                 current post
	 * @param integer $page_active          the page of the pagination
	 * @param integer $comments_per_page
	 */
	public function js_config( $ljsc, $post, $page_active, $comments_per_page ) {
		$config = LivePress_Config::get_instance();

		if ( isset( $post->comment_count ) ) {
			$ljsc->new_value( 'comment_count', $post->comment_count, Livepress_Configuration_Item::$LITERAL );
		} else {
			$ljsc->new_value( 'comment_count', 0, Livepress_Configuration_Item::$LITERAL );
		}

		$pagination_on = ( $config->get_host_option( "page_comments" ) == "1" );
		$ljsc->new_value( 'comment_pagination_is_on', $pagination_on, Livepress_Configuration_Item::$BOOLEAN );
		$ljsc->new_value( 'comment_page_number', $page_active, Livepress_Configuration_Item::$LITERAL );
		$ljsc->new_value( 'comment_pages_count', $comments_per_page, Livepress_Configuration_Item::$LITERAL );

		$comment_order = $config->get_host_option( 'comment_order' );
		$ljsc->new_value( 'comment_order', $comment_order, Livepress_Configuration_Item::$STRING );

		$ljsc->new_value( 'disable_comments',
				$this->options['disable_comments'], Livepress_Configuration_Item::$BOOLEAN );
		$ljsc->new_value( 'comment_live_updates_default',
				$this->options['comment_live_updates_default'], Livepress_Configuration_Item::$BOOLEAN );

		if ( isset( $post->ID ) && $post->ID ) {
			$comment_msg_id = LivePress_WP_Utils::get_from_post( $post->ID, "comment_update", true );
			$ljsc->new_value( 'comment_msg_id', $comment_msg_id );
			$ljsc->new_value( 'can_edit_comments', current_user_can( 'edit_post', $post->ID ),
							Livepress_Configuration_Item::$BOOLEAN );
		}
	}

	// ============== Those methods are only public to be called from WP actions and filters

	/**
	 * Change the comments template path to a special one from the plugin to enclose
	 * the original one in HTML tags
	 *
	 * @param   string  Original comments template path
	 *
	 * @return  string  Special comments template path. See description.
	 */
	public function enclose_comments_in_div( $comments_template_path ) {
		self::$comments_template_path = $comments_template_path;
		return LP_PLUGIN_PATH . 'php/special_comments.php';
	}

	/**
	 * Send to the livepress webservice a new message with the comment updates
	 * if it was approved. This method will be called anytime a comment suffer
	 * a status transition
	 */
	public function send_comment_if_approved( $new_status, $old_status, $comment ) {
		if ( $new_status == 'approved' ) {
			$this->send_to_livepress_new_comment( $comment, $new_status );
		}
	}

	/**
	 * Send to the livepress webservice a new message with the comment updates
	 */
	public function send_to_livepress_new_comment( $comment_id, $comment_status = '' ) {
		if ( is_int( $comment_id ) ) {
			$comment = get_comment( $comment_id );
		} else {
			$comment    = $comment_id;
			$comment_id = $comment->comment_ID;
		}
		if ( !$comment_status ) {
			$comment_status = wp_get_comment_status( $comment_id );
		}

		$post = get_post( $comment->comment_post_ID );

		$params = array(
			'content'     => $comment->comment_content,
			'comment_id'  => $comment_id,
			'comment_url' => get_comment_link( $comment ),
			'comment_gmt' => $comment->comment_date_gmt . 'Z',
			'post_id'     => $comment->comment_post_ID,
			'post_title'  => $post->post_title,
			'post_link'   => get_permalink( $post->ID ),
			'author'      => $comment->comment_author,
			'author_url'  => $comment->comment_author_url,
			'avatar_url'  => get_avatar( $comment->comment_author_email, 30 ),
		);
		if ( $comment_status != 'approved' ) {
			try {
				$params = array_merge( $params, array(
					'status'       => $comment_status,
					'author_email' => $comment->comment_author_email,
				) );
				$this->lp_com->send_to_livepress_new_created_comment( $params );
			} catch ( LivePress_Communication_Exception $e ) {
				$e->log( "new comment" );
			}
		} else {
			$old_uuid = LivePress_WP_Utils::get_from_post( $comment->comment_post_ID, 'comment_update', TRUE );
			$new_uuid = $this->lp_com->new_uuid();
			LivePress_WP_Utils::save_on_post( $comment->comment_post_ID, 'comment_update', $new_uuid );

			// Used to fake if the user is logged or not
			global $user_ID;
			$global_user_ID = $user_ID;
			$user_ID = NULL;
			if (current_user_can( 'edit_post', $post->ID ) ) {
				wp_set_current_user( NULL );
			}

			$comment_template_non_logged       = $this->get_comment_list_templated( $comment );
			$added_comment_template_non_logged = $this->get_comment_templated( $comment, $post );
			$updated_counter_only_template     = $this->get_comments_counter_templated( $comment_id, $post );

			global $wp_query;
			$wp_query->rewind_comments();

			$user_ID = $post->post_author;
			if ( !current_user_can( 'edit_post', $post->ID ) ) {
				wp_set_current_user( $post->post_author);
			}

			$comment_template = $this->get_comment_list_templated( $comment );
			$added_comment_template = $this->get_comment_templated( $comment, $post );
			$updated_counter_only_template_logged = $this->get_comments_counter_templated( $comment_id, $post );

			try {
				$params = array_merge( $params, array(
					'post_author'                  => get_the_author_meta( 'login', $post->post_author),
					'old_template'                 => $comment_template_non_logged['old'],
					'new_template'                 => $comment_template_non_logged['new'],
					'comment_parent'               => $comment->comment_parent,
					'comment_html'                 => $added_comment_template_non_logged,
					'_ajax_nonce'                  => $this->options['ajax_comment_nonce'],
					'previous_uuid'                => $old_uuid,
					'uuid'                         => $new_uuid,
					'old_template_logged'          => $comment_template['old'],
					'new_template_logged'          => $comment_template['new'],
					'comments_counter_only'        => $updated_counter_only_template,
					'comments_counter_only_logged' => $updated_counter_only_template_logged,
					'comment_html_logged'          => $added_comment_template,
				) );
				$this->lp_com->send_to_livepress_approved_comment( $params );
			} catch ( LivePress_Communication_Exception $e ) {
				$e->log( 'approved comment' );
			}

			$user_ID = $global_user_ID; // Restore changed global $user_ID
		}
	}

	/**
	 * Retrieves template of a single comment.
	 *
	 * @access private
	 *
	 * @param WP_Comment $comment
	 * @param WP_Post $post
	 * @return String added comment template
	 */
	private function get_comment_templated( $comment, $post ) {
		$curr_comment = $comment;
		$comments     = LivePress_Post::all_approved_comments( $comment->comment_post_ID );

		// get template of added comment only
		$selected = array( $comment );
		while ( $parent = $this->retrieve_parent_comment( $curr_comment->comment_parent, $comments ) ) {
			$selected[]   = $parent;
			$curr_comment = $parent;
		}

		$selected     = array_reverse( $selected );
		$view         = $this->get_comments_list_template( $selected, $post );
		$comment_node = $this->extract_comment_by_element_id( $view, "comment-" . $comment->comment_ID );

		return $comment_node;
	}

	/**
	 * Retrieve parent comment.
	 *
	 * @access private
	 *
	 * @param $parent_id
	 * @param $comments
	 * @return mixed
	 */
	private function retrieve_parent_comment( $parent_id, $comments ) {
		foreach ( $comments as $c) {
			if ( $c->comment_ID == $parent_id ) {
				return $c;
			}
		}
	}

	/**
	 * Retrives html of comments count
	 *
	 * @access private
	 *
	 * @param int     $comment_id
	 * @param WP_Post $post
	 * @return String updated counter html with amount of comments for given post
	 */
	private function get_comments_counter_templated( $comment_id, $post ) {
		$comments = LivePress_Post::all_approved_comments( get_comment( $comment_id )->comment_post_ID );

		// we want to pass this result to the diff server and get only counter update as a result
		// remove parsed comment from comments table
		for ( $i = 0 ; $i < count( $comments ) ; $i++ ) {
			if ( $comments[$i]->comment_ID == $comment_id ) {
				unset( $comments[$i] );
			}
		}
		$comments_with_updated_counter = $this->get_comments_list_template( $comments, $post, count( $comments ) + 1 );
		return $comments_with_updated_counter;
	}

	/**
	 * Returns comment element node html
	 *
	 * @access private
	 *
	 * @param String $view HTML containing comments.
	 * @param String $element_id ID of comment.
	 * @return String html of comment
	 */
	private function get_element_by_id( $dom, $id ) {
		$xpath = new DOMXPath( $dom );
		return $xpath->query( "//*[@id='$id']" )->item( 0 );
	}

	/**
	 * Extract comment by element ID.
	 *
	 * @access private
	 *
	 * @param $view
	 * @param $element_id
	 * @return string
	 */
	private function extract_comment_by_element_id( $view, $element_id ) {
		$dom = new DOMDocument( '1.0', 'UTF-8' );
		$dom->validateOnParse = true;
		$dom->formatOutput    = true;
		$parse_success       = @$dom->loadHTML( '<?xml encoding="UTF-8"?>' . $view );

		if ( !$parse_success ) {
			// fix for encoding detection bug, as "UTF-8" passed to constructor is not enough. *sigh*.
			$parse_success = $dom->loadHTML( '<?xml encoding="UTF-8"?>' . $view );
		}

		$response = new DOMDocument( '1.0', 'UTF-8' );
		$response->formatOutput = true;
		$search = $this->get_element_by_id( $dom, $element_id );
		$searched = $search->parentNode;

		$imported = $response->importNode( $searched, true );
		$response->appendChild( $imported );

		return $response->saveHTML();
	}

	/**
	 * Retrieves the comment template with and without $commen_id
	 *
	 * @access private
	 *
	 * @param WP_Comment $comment The comment to be parsed.
	 * @return  array   'new' => has the comment template with the $comment_id
	 *                  'old' => comment template without it
	 */
	private function get_comment_list_templated( $local_comment ) {
		// $comments - Fake comment list generation on old comments template
		global $post, $comments;

		$comments = LivePress_Post::all_approved_comments( $local_comment->comment_post_ID );

		// When comment by XMLRPC the $post is empty, so get from the comment.
		if ( !isset( $post ) ) {
			$post = get_post( $local_comment->comment_post_ID );
		}

		if ( $local_comment->comment_approved == '0' ) {
			array_push( $comments, $local_comment );
		}
		$comment_full_out = $this->get_comments_list_template( $comments, $post );

		// remove parsed comment from comments table
		for ( $i = 0 ; $i < count( $comments ) ; $i++ ) {
			if ( $comments[$i]->comment_ID == $local_comment->comment_ID ) {
				unset( $comments[$i] );
			}
		}
		$comment_partial_out = $this->get_comments_list_template( $comments, $post );

		return array( 'old' => $comment_partial_out, 'new' => $comment_full_out );
	}

	// FIXME: global functions used below
	private function get_comments_list_template( $comments, $post, $comments_count = null ) {
		global $wp_query;
		// we store unmodified wp_query as the same object is used again later
		$original = clone $wp_query;

		// Fakes have_comments() that uses $wp_query->current_comment + 1 < $wp_query->comment_count
		if ( !$comments_count ) {
			$comments_count = count( $comments );
		}

		if ( $comments_count == 0 ) {
			$wp_query->current_comment = $comments_count;
		}
		$wp_query->comment_count = $comments_count;
		// Fakes comments_number()
		$post->comment_count = $comments_count;
		$this->add_overridden_comments_count_filter( $comments_count );
		// Fakes wp_list_comments()
		$wp_query->comments = $comments;

		// Fakes $comments_by_type
		$c_by_type =  separate_comments( $comments );
		$wp_query->comments_by_type = $c_by_type;
		$comments_by_type = $wp_query->comments_by_type;
		// Hack for bad-written themes, which rely on globals instead of functions
		$GLOBALS['comments'] = &$comments;
		$GLOBALS['comment_count'] = &$comments_count;
		// Prints the template buffered
		ob_start();
		include(TEMPLATEPATH.'/comments.php' );
		$comments_template = ob_get_clean();
		unset( $GLOBALS['comments'] );
		unset( $GLOBALS['comment_count'] );
		remove_filter( 'get_comments_number', 'overload_comments_number' );
		$wp_query = clone $original;
		return $comments_template;
	}

	private function add_overridden_comments_count_filter( $count ) {
		$this->overridden_comments_count = $count;
		add_filter( 'get_comments_number', array( &$this, 'overload_comments_number' ) );
	}

	public function overload_comments_number() {
		$count = $this->overridden_comments_count;
		return $count;
	}

	/**
	 * Receives an ajax request to post a comment, returns comment's state
	 * Uses a lot of GLOBAL variables and functions
	 */
	public function lp_post_comment() {
		global $wpdb, $post;
		$comment_post_ID = ( int ) $_POST['comment_post_ID'];

		$post = get_post( $comment_post_ID );

		if ( empty( $post->comment_status ) ) {
			do_action( 'comment_id_not_found', $comment_post_ID );
			$this->die_post_status_to_json( 'error' );
		} elseif ( !comments_open( $comment_post_ID ) ) {
			do_action( 'comment_closed', $comment_post_ID );
			$this->die_post_status_to_json( 'closed' );
		} elseif ( in_array( $post->post_status, array( 'draft', 'pending' ) ) ) {
			$this->die_post_status_to_json( 'pending' );
		}

		$comment_author       = ( isset( $_POST['author'] ) )  ? trim( strip_tags( $_POST['author'] ) ) : null;
		$comment_author_email = ( isset( $_POST['email'] ) )   ? trim( $_POST['email'] ) : null;
		$comment_author_url   = ( isset( $_POST['url'] ) )     ? trim( $_POST['url'] ) : null;
		$comment_content      = ( isset( $_POST['comment'] ) ) ? trim( $_POST['comment'] ) : null;

		// If the user is logged in
		$user = wp_get_current_user();
		if ( $user->ID ) {
			if ( empty( $user->display_name ) )
						$user->display_name = $user->user_login;
			$comment_author        = esc_sql( $user->display_name );
			$comment_author_email  = esc_sql( $user->user_email );
			$comment_author_url    = esc_sql( $user->user_url );
			if ( current_user_can( 'unfiltered_html' ) ) {
				if ( wp_create_nonce( 'unfiltered-html-comment_' . $comment_post_ID ) != $_POST['_wp_unfiltered_html_comment'] ) {
					kses_remove_filters(); // start with a clean slate
					kses_init_filters(); // set up the filters
				}
			}
		} else {
		if ( get_option( 'comment_registration' ) )
			$this->die_post_status_to_json( 'not_allowed' );
		}

		$comment_type = '';

		if ( get_option( 'require_name_email' ) && !$user->ID ) {
			if ( 6 > strlen( $comment_author_email ) || '' == $comment_author )
				$this->die_post_status_to_json( 'missing_fields' );
			elseif ( !is_email( $comment_author_email ) )
				$this->die_post_status_to_json( 'missing_fields' );
		}

		if ( '' == $comment_content )
			$this->die_post_status_to_json( 'missing_fields' );

		$comment_parent = isset( $_POST['comment_parent'] ) ? absint( $_POST['comment_parent'] ) : 0;

		$commentdata = compact(
			'comment_post_ID', 'comment_author', 'comment_author_email',
			'comment_author_url', 'comment_content', 'comment_type', 'comment_parent', 'user_ID'
		);

		$comment_id = wp_new_comment( $commentdata );

		$comment = get_comment( $comment_id );

		wp_set_comment_cookies( $comment, $user );

		$this->die_post_status_to_json( wp_get_comment_status( $comment_id ) );
	}



	/**
	 * Comment marked as flood, gives an appropriate response if is an ajax request.
	 */
	public function received_a_flood_comment() {
		$this->die_post_status_to_json( 'flood' );
	}

	/**
	 * Dies with translated post status to JSON. Needs PHP 5.2 >=
	 *
	 * @param   string  $status A comment post status
	 * @uses json_encode
	 */
	private function die_post_status_to_json( $status ) {
		// Default code
		$code = 999;
		foreach ( self::$ajax_response_codes as $status_name => $status_code ) {
			if ( $status == $status_name ) {
				$code = $status_code;
			}
		}

		header( 'HTTP/1.0 200 OK' );
		die( json_encode( array( 'msg' => $status, 'code' => $code ) ) );
	}

}
