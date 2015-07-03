<?php
/* Plugin Name: Comment Probation
 * Plugin URI: http://wordpress.org/extend/plugins/comment-probation/
 * Description: There is a setting in WordPress to allow comments to appear automatically for comment authors <a href="options-discussion.php">who have previously approved comments</a>. This plugin allows you to put a comment author "on probation," approving that comment, but not automatically approving future comments until one of their comments is approved without probation.
 * Author: Andrew Nacin
 * Author URI: http://nacin.com/
 * Version: 0.1
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

class Plugin_Comment_Probation {

	const meta_key = '_comment_probation';
	static $instance;

	function __construct() {
		self::$instance = $this;
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
	}

	function plugins_loaded() {
		if ( ! get_option( 'comment_whitelist' ) )
			return;

		add_action( 'load-options-discussion.php', array( $this, 'load_discussion_page' ) );
		add_action( 'admin_head-edit-comments.php', array( $this, 'admin_head' ) );
		add_filter( 'pre_comment_approved', array( $this, 'pre_comment_approved' ), 10, 2 );
		add_filter( 'comment_row_actions', array( $this, 'comment_row_actions' ), 10, 2 );
		add_action( 'wp_set_comment_status', array( $this, 'wp_set_comment_status' ), 10, 2 );
	}

	function load_discussion_page() {
		add_action( 'gettext', array( $this, 'gettext' ), 10, 3 );	
	}

	function gettext( $translated, $original, $domain ) {
		if ( 'Comment author must have a previously approved comment' != $original )
			return $translated;
	
		return sprintf( __( 'Comment author must have a previously approved comment (<a href="%s">and not be on probation</a>)', 'comment-probation' ), network_admin_url( 'plugins.php' ) . '#comment-probation' );
	}

	function wp_set_comment_status( $comment_id, $status ) {
		global $wpdb;
		if ( $status != '1' && $status != 'approve' )
			return;

		if ( ! empty( $_POST['probation'] ) ) {
			update_comment_meta( $comment_id, self::meta_key, '1' );
		} else {
			$commentdata = get_comment( $comment_id );
			$comment_ids = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM $wpdb->comments WHERE comment_author = %s AND comment_author_email = %s AND comment_approved = '1'",
                        $commentdata->comment_author, $commentdata->comment_author_email ) );
			foreach ( $comment_ids as $comment_id )
				delete_comment_meta( $comment_id, self::meta_key );
		}
	}

	function admin_head() {
		echo '<style>.comment-probation { display: none; } tr.unapproved .comment-probation { display: inline }</style>' . "\n";
	}

	function pre_comment_approved( $approved, $commentdata ) {
		global $wpdb;

		// If we're not approving the comment, keep going.
		if ( 1 != $approved )
			return $approved;

		// WPCOM: is_user_logged_in() exception removed as open registration is allowed.

		// The only other situation is check_comment() returning true.
		$comment_ids = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM $wpdb->comments
			WHERE comment_author = %s AND comment_author_email = %s AND comment_approved = '1'",
			$commentdata['comment_author'], $commentdata['comment_author_email'] ) );

		// This shouldn't happen...
		if ( ! $comment_ids )
			return 0; // or return $approved?

		$comment_ids = implode( ', ', $comment_ids );
		$commentmeta = $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM $wpdb->commentmeta
			WHERE comment_id IN ($comment_ids) AND meta_key = %s LIMIT 1", self::meta_key ) );

		// This user is on probation. Tsk tsk.
		if ( $commentmeta ) {
			add_action( 'comment_post', array( $this, 'comment_post' ) );
			return 0;
		} else {
			return 1;
		}
	}

	function comment_post( $comment_id ) {
		update_comment_meta( $comment_id, self::meta_key, '1' );
	}

	function comment_row_actions( $actions, $comment ) {
		if ( ! isset( $actions['approve'] ) )
			return $actions;

		$probation = str_replace( 'action=approvecomment', 'action=approvecomment&amp;probation=1', $actions['approve'] );
		preg_match( '/^(.*?>)/', $probation, $matches );
		$probation = str_replace( array( ':new=approved', ' vim-a' ), array( ':new=approved&probation=1', '' ), $matches[1] );
		$probation .= __( 'Approve with Probation', 'comment-probation' ) . '</a>';

		$actions['approve'] .= '<span class="comment-probation"> | ' . $probation . '</span>';

		return $actions;
	}

}
new Plugin_Comment_Probation;

