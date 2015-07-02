<?php
/**
 * Collaboration.
 *
 * @todo Remove/deprecate where necessary.
 */

if ( ! defined( 'LP_PLUGIN_PATH' ) ){
	define( 'LP_PLUGIN_PATH' , ( plugin_dir_path( dirname( __FILE__ ) ) ) );
}

require_once ( LP_PLUGIN_PATH . 'php/livepress-config.php' );

class Collaboration {
	static function tablename() {
	}

	static function install() {
	}

	// Remove legacy caching table if present
	static function uninstall() {
	}

	/**
	 * Clear out the tranient cache for a post
	 *
	 * @param  int $post_id Post id
	 *
	 * @since  1.0.7
	 */
	static function clear_terms_cache( $post_id ){
		$cache_key = 'livepress_terms_' . $post_id;
		delete_transient( $cache_key );
	}

	/**
	 * Get a list of terms for this user
	 *
	 * @param  int   $post_id Post id
	 * @param  int   $user_id User id
	 * @return array Array of terms
	 */
	static function termlist($post_id, $user_id) {

		$cached_terms = array();
		$cache_key = 'livepress_terms_' . $post_id;
		if ( is_array( get_transient( $cache_key ) ) ) {
			$cached_terms = get_transient( $cache_key );
		}

		return $cached_terms;

	}

	/**
	 * Get twitter searches for post/user, optionally matching a list of terms
	 *
	 * @param  int $post_id Post id
	 * @param  int $user_id User id
	 * @param  array $terms Optional array of terms to match
	 * @return array        An array of matching/found terms, or an empty array if none found
	 */
	static function get_searches($post_id, $user_id, $terms) {
		$dbterms = self::termlist( $post_id, $user_id );

		// Grab the results from the cache if availavle
		$result = ( isset( $terms ) && isset( $dbterms[ $user_id ] ) ) ? $dbterms[ $user_id ] : array();

		// If prefer to return subset of terms that present on LP side
		if ( isset( $terms ) && isset( $result ) ){
			$result = array_intersect( $result, $terms );
		}
			return isset( $result ) ? $result : array();

	}

	/**
	 * Add a search term
	 *
	 * @param int $post_id Post id
	 * @param int $user_id User id
	 * @param string $term The term to add
	 */
	static function add_search($post_id, $user_id, $term) {
		// Get the exiting list of terms from the cache
		$cache_key = 'livepress_terms_' . $post_id;
		$cached_terms = is_array( get_transient( $cache_key ) ) ? get_transient( $cache_key ) : array();
		if ( false === array_search( $term, $cached_terms ) ) {
			// Append the new term and save back to the cache
			$cached_terms[ $user_id ][] = $term;
			set_transient( $cache_key, $cached_terms );

			// Add the term to the LivePress service
			$options = get_option( LivePress_Administration::$options_name );
			$livepress_com = new LivePress_Communication( $options['api_key'] );
			$res = json_decode( $livepress_com->send_to_livepress_handle_twitter_search( 'add', $term ) );
			return $res != null && ($res->success || $res->errors == '');
		} else {
			return false;
		}

	}

	/**
	 * Delete a search term
	 *
	 * @param  int $post_id Post id
	 * @param  int $user_id User id
	 * @param  String $term Term to remove
	 * @return bool         Removal status
	 */
	static function del_search($post_id, $user_id, $term) {
		// Check to see if the term is in the cache and remove
		$cache_key = 'livepress_terms_' . $post_id;
		$cached_terms = is_array( get_transient( $cache_key ) ) ? get_transient( $cache_key ) : array();

		if ( isset( $cached_terms[ $user_id ] ) && false !== $key = array_search( $term, $cached_terms[ $user_id ] ) ) {
			// Remove the term and re-save to the cache
			unset( $cached_terms[ $user_id ][ $key ] );
			set_transient( $cache_key, $cached_terms );
			// Remove the term from theLivePress service
			$options = get_option( LivePress_Administration::$options_name );
			$livepress_com = new LivePress_Communication( $options['api_key'] );
			$livepress_com->send_to_livepress_handle_twitter_search( 'remove', $term );// TODO: handle error somehow
		}

		return true;
	}

	/**
	 * Clear all searches for a post/user
	 *
	 * @param  int $post_id Post id
	 * @param  int $user_id User id
	 * @return boolean      True if any terms were cleared, otherwise false
	 */
	static function clear_searches($post_id, $user_id) {

		$cache_key = 'livepress_terms_' . $post_id;
		$cached_terms = array();
		if ( is_array( get_transient( $cache_key ) ) ) {
			$cached_terms = get_transient( $cache_key );
		}

		if ( isset( $cached_terms[ $user_id ] ) ) {
			foreach ( $cached_terms as $term ){
				$options = get_option( LivePress_Administration::$options_name );
				$livepress_com = new LivePress_Communication( $options['api_key'] );
				$livepress_com->send_to_livepress_handle_twitter_search( 'remove', $term );// TODO: handle errors
			}

			// Remove this user from the cache
			unset( $cached_terms[ $user_id ] );
			set_transient( $cache_key, $cached_terms );
			return true;
		}
		// Nothing to clear
		return false;
	}

	static function twitter_search($action, $post_id, $user_id, $term) {
		switch ( $action ) {
			case 'add':
			return self::add_search( $post_id, $user_id, $term );
			case 'remove':
			return self::del_search( $post_id, $user_id, $term );
			case 'clear':
			return self::clear_searches( $post_id, $user_id );
		}
		return false;
	}

	static function comments_number() {
		check_ajax_referer( 'lp_collaboration_comments_nonce' );
		$post = get_post( intval( $_POST['post_id'] ) );
		if ( ! current_user_can( 'edit_post', $post_id ) ){
			die;
		}
		echo esc_html( $post->comment_count );
		die;
	}

	static function return_live_edition_data() {
		$response = array();
		$response['comments'] = self::comments_of_post();
		$response['guest_bloggers'] = array();

		$taf = self::tracked_and_followed();
		if ( isset( $taf['guest_bloggers'] ) ) {
			$response['guest_bloggers'] = $taf['guest_bloggers']; }
		$response['terms'] = $taf['terms'];
		return $response;
	}
	static function get_live_edition_data() {
		$response = self::return_live_edition_data();
		echo json_encode( $response );
		die;
	}

	// Ajax version of get_live_edition_data checks nonce and user capabilities
	static function get_live_edition_data_ajax() {
		check_ajax_referer( 'get_live_edition_data_nonce' );
		$post_id = (int) $_POST['post_id'];
		if ( ! current_user_can( 'edit_post', $post_id ) ){
			die;
		}
		$this->get_live_edition_data();
	}

	private static function tracked_and_followed() {
		global $current_user;
		$options = get_option( LivePress_Administration::$options_name );
		$communication = new LivePress_Communication( $options['api_key'] );

		$params = array( 'post_id' => $_REQUEST['post_id'], 'format' => 'json' );
		$result = json_decode( $communication->followed_tracked_twitter_accounts( $params ), true ); // TODO: Handle errors
		$result['terms'] = self::get_searches( $_REQUEST['post_id'], $current_user->ID, $result['terms'] );
		return $result;
	}

	private static function comments_of_post() {
		$post_id = absint( $_REQUEST['post_id'] );

		$args = 'post_id=' . $post_id;
		$post_comments = get_comments( $args );

		$comments = array();
		$comment_msg_id = get_post_meta( $post_id, '_'.LP_PLUGIN_NAME.'_comment_update', true );

		foreach ( $post_comments as $c ) {
			$avatar = get_avatar( $c->comment_author_email, 30 );
			$commentId = $c->comment_ID;
			$comment = array(
				'_ajax_nonce' => wp_create_nonce( 'approve-comment_' . $commentId ),
				'comment_id'  => $commentId,
				'avatar_url'  => $avatar,
				'author_url'  => $c->comment_author_url,
				'author'      => $c->comment_author,
				'status'      => wp_get_comment_status( $commentId ),
				'content'     => $c->comment_content,
				'comment_gmt' => $c->comment_date_gmt,
				'comment_url' => get_comment_link( $c )
			);
			$comments[] = $comment;
		}
		$comments = array_reverse( $comments );
		$env = array( 'comment_msg_id' => $comment_msg_id, 'comments' => $comments );
		return $env;
	}
}
