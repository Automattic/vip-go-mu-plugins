<?php
/*
Plugin Name: Safe Report Comments
Plugin Script: safe-report-comments.php
Plugin URI: http://wordpress.org/extend/plugins/safe-report-comments/
Description: This script gives visitors the possibility to flag/report a comment as inapproriate. 
After reaching a threshold the comment is moved to moderation. If a comment is approved once by a moderator future reports will be ignored.
Version: 0.4
Author: Thorsten Ott, Daniel Bachhuber, Automattic
Author URI: http://automattic.com
*/

if ( !class_exists( "Safe_Report_Comments" ) ) {

	class Safe_Report_Comments {

		private $_plugin_prefix = 'srcmnt';
		private $_admin_notices = array();
		private $_nonce_key = 'flag_comment_nonce';
		private $_auto_init = true;
		private $_storagecookie = 'sfrc_flags';
		
		public $plugin_url = false;
		
		public $thank_you_message = 'Thank you for your feedback. We will look into it.';
		public $invalid_nonce_message = 'It seems you already reported this comment. <!-- nonce invalid -->';
		public $invalid_values_message = 'Cheating huh? <!-- invalid values -->';
		public $already_flagged_message = 'It seems you already reported this comment. <!-- already flagged -->';
		public $already_flagged_note = '<!-- already flagged -->'; // displayed instead of the report link when a comment was flagged. 
		
		public $filter_vars = array( 'thank_you_message', 'invalid_nonce_message', 'invalid_values_message', 'already_flagged_message', 'already_flagged_note' );
		
		// amount of possible attempts transient hits per comment before a COOKIE enabled negative check is considered invalid
		// transient hits will be counted up per ip any time a user flags a comment
		// this number should be always lower than your threshold to avoid manipulation
		public $no_cookie_grace = 3; 
		public $cookie_lifetime = 604800; // lifetime of the cookie ( 1 week ). After this duration a user can report a comment again
		public $transient_lifetime = 86400; // lifetime of fallback transients. lower to keep things usable and c
		
		public function __construct( $auto_init=true ) {

			$this->_admin_notices = get_transient( $this->_plugin_prefix . '_notices' );
			if ( !is_array( $this->_admin_notices ) ) 
				$this->_admin_notices = array();
			$this->_admin_notices = array_unique( $this->_admin_notices );
			$this->_auto_init = $auto_init;
			
			if ( !is_admin() || ( defined( 'DOING_AJAX' ) && true === DOING_AJAX ) ) {
				add_action( 'init', array( $this, 'frontend_init' ) );
			} else if ( is_admin() ) {
				add_action( 'admin_init', array( $this, 'backend_init' ) );
			}
			add_action( 'comment_unapproved_to_approved', array( $this, 'mark_comment_moderated' ), 10, 1 );
			
			// apply some filters to easily alter the frontend messages 
			// add_filter( 'safe_report_comments_thank_you_message', 'alter_message' ); // this or similar will do the job
			foreach( $this->filter_vars as $var )
				$this->{$var} = apply_filters( 'safe_report_comments_' . $var , $this->{$var} );
		}

		public function __destruct() {

		}

		/* 
		 * Initialize backend functions
		 * - register_admin_panel
		 * - admin_header
		 */
		public function backend_init() {
			do_action( 'safe_report_comments_backend_init' );

			add_settings_field( $this->_plugin_prefix . '_enabled', __( 'Allow comment flagging' ), array( $this, 'comment_flag_enable' ), 'discussion', 'default' );
			register_setting( 'discussion', $this->_plugin_prefix . '_enabled' );

			if ( ! $this->is_enabled() )
				return;

			add_settings_field( $this->_plugin_prefix . '_threshold', __( 'Flagging threshold' ), array( $this, 'comment_flag_threshold' ), 'discussion', 'default' );
			register_setting( 'discussion', $this->_plugin_prefix . '_threshold', array( $this, 'check_threshold' ) );
			add_filter('manage_edit-comments_columns', array( $this, 'add_comment_reported_column' ) );
			add_action('manage_comments_custom_column', array( $this, 'manage_comment_reported_column' ), 10, 2);
				
			add_action( 'admin_menu', array( $this, 'register_admin_panel' ) );
			add_action( 'admin_head', array( $this, 'admin_header' ) );
		}

		/*
		 * Initialize frontend functions
		 */
		public function frontend_init() {
			
			if ( ! $this->is_enabled() )
				return;

			if ( ! $this->plugin_url )
				$this->plugin_url = plugins_url( false, __FILE__ );

			do_action( 'safe_report_comments_frontend_init' );
			
			add_action( 'wp_ajax_safe_report_comments_flag_comment', array( $this, 'flag_comment' ) );
			add_action( 'wp_ajax_nopriv_safe_report_comments_flag_comment', array( $this, 'flag_comment' ) );
			
			add_action( 'wp_enqueue_scripts', array( $this, 'action_enqueue_scripts' ) );

			if ( $this->_auto_init ) 
				add_filter( 'comment_reply_link', array( $this, 'add_flagging_link' ) );
			add_action( 'comment_report_abuse_link', array( $this, 'print_flagging_link' ) );
				
			add_action( 'template_redirect', array( $this, 'add_test_cookie' ) ); // need to do this at template_redirect because is_feed isn't available yet
		}

		public function action_enqueue_scripts() {

			// Use home_url() if domain mapped to avoid cross-domain issues
			if ( home_url() != site_url() )
				$ajaxurl = home_url( '/wp-admin/admin-ajax.php' );
			else
				$ajaxurl = admin_url( 'admin-ajax.php' );

			$ajaxurl = apply_filters( 'safe_report_comments_ajax_url', $ajaxurl );

			wp_enqueue_script( $this->_plugin_prefix . '-ajax-request', $this->plugin_url . '/js/ajax.js', array( 'jquery' ) );
			wp_localize_script( $this->_plugin_prefix . '-ajax-request', 'SafeCommentsAjax', array( 'ajaxurl' => $ajaxurl ) ); // slightly dirty but needed due to possible problems with mapped domains
		}

		public function add_test_cookie() {
			//Set a cookie now to see if they are supported by the browser.
			// Don't add cookie if it's already set; and don't do it for feeds
			if( ! is_feed() && ! isset( $_COOKIE[ TEST_COOKIE ] ) ) {
				@setcookie(TEST_COOKIE, 'WP Cookie check', 0, COOKIEPATH, COOKIE_DOMAIN);
				if ( SITECOOKIEPATH != COOKIEPATH )
					@setcookie(TEST_COOKIE, 'WP Cookie check', 0, SITECOOKIEPATH, COOKIE_DOMAIN);
			}
		}

		/*
		 * Add necessary header scripts 
		 * Currently only used for admin notices
		 */
		public function admin_header() {
			// print admin notice in case of notice strings given
			if ( !empty( $this->_admin_notices ) ) {
					add_action('admin_notices' , array( $this, 'print_admin_notice' ) );
			}
?>
<style type="text/css">
.column-comment_reported {
	width: 8em;
}
</style>
<?php
			
		}
		
		/* 
		 * Add admin error messages
		 */
		protected function add_admin_notice( $message ) {
			$this->_admin_notices[] = $message;
			set_transient( $this->_plugin_prefix . '_notices', $this->_admin_notices, 3600 );
		}

		/*
		 * Print a notification / error msg
		 */
		public function print_admin_notice() {
			?><div id="message" class="updated fade"><h3>Safe Comments:</h3><?php

			foreach( (array) $this->_admin_notices as $notice ) {
				?>
					<p><?php echo $notice ?></p>
				<?php
			}
			?></div><?php
			$this->_admin_notices = array();
			delete_transient( $this->_plugin_prefix . '_notices' );
		}
		
		/*
		 * Callback for settings field
		 */
		public function comment_flag_enable() {
			$enabled = $this->is_enabled();
			?>
			<label for="<?php echo $this->_plugin_prefix; ?>_enabled">
				<input name="<?php echo $this->_plugin_prefix; ?>_enabled" id="<?php echo $this->_plugin_prefix; ?>_enabled" type="checkbox" value="1" <?php if ( $enabled === true ) echo ' checked="checked"'; ?> />   
				<?php _e( "Allow your visitors to flag a comment as inappropriate." ); ?>
			</label>
			<?php
		}
		
		/*
		 * Callback for settings field
		 */		
		public function comment_flag_threshold() {
			$threshold = (int) get_option( $this->_plugin_prefix . '_threshold' );
			?>
			<label for="<?php echo $this->_plugin_prefix; ?>_threshold">
				<input size="2" name="<?php echo $this->_plugin_prefix; ?>_threshold" id="<?php echo $this->_plugin_prefix; ?>_threshold" type="text" value="<?php echo $threshold; ?>" />   
				<?php _e( "Amount of user reports needed to send a comment to moderation?" ); ?>
			</label>
			<?php
		}
		
		/* 
		 * Check if the functionality is enabled or not
		 */
		public function is_enabled() {
			$enabled = get_option( $this->_plugin_prefix . '_enabled' );
			if ( $enabled == 1 )
				$enabled = true;
			else 
				$enabled = false;
			return $enabled;
		}
		
		/* 
		 * Validate threshold, callback for settings field
		 */
		public function check_threshold( $value ) {
			if ( (int) $value <= 0 || (int) $value > 100 )
				$this->add_admin_notice( __('Please revise your flagging threshold and enter a number between 1 and 100') );
			return (int) $value;
		}
		
		/*
		 * Helper functions to (un)/serialize cookie values
		 */
		private function serialize_cookie( $value ) {
			$value = $this->clean_cookie_data( $value );
			return base64_encode( json_encode( $value ) );
		}
		private function unserialize_cookie( $value ) {
			$data = json_decode( base64_decode( $value ) );
			return $this->clean_cookie_data( $data );
		}

		private function clean_cookie_data( $data ) {
			$clean_data = array();

			if ( ! is_array( $data ) ) {
				$data = array();
			}

			foreach ( $data as $comment_id => $count ) {
				if ( is_numeric( $comment_id ) && is_numeric( $count ) ) {
					$clean_data[ $comment_id ] = $count;
				}
			}

			return $clean_data;
		}
		
		/*
		 * Mark a comment as being moderated so it will not be autoflagged again
		 * called via comment transient from unapproved to approved
		 */
		public function mark_comment_moderated( $comment ) {
			if ( isset( $comment->comment_ID ) ) {
				update_comment_meta( $comment->comment_ID, $this->_plugin_prefix . '_moderated', true );
			}
		}
		
		/*
		 * Check if this comment was flagged by the user before
		 */
		public function already_flagged( $comment_id ) {		

			// check if cookies are enabled and use cookie store
			if( isset( $_COOKIE[ TEST_COOKIE ] ) ) {
				if ( isset( $_COOKIE[ $this->_storagecookie ] ) ) {
					$data = $this->unserialize_cookie( $_COOKIE[ $this->_storagecookie ] );
					if ( is_array( $data ) && isset( $data[ $comment_id ] ) ) {
						return true;
					}
				}
			} 
			
			
			// in case we don't have cookies. fall back to transients, block based on IP/User Agent
			if ( $transient = get_transient( md5( $this->_storagecookie . $_SERVER['REMOTE_ADDR'] ) ) ) {
				if 	( 
					// check if no cookie and transient is set
					 ( !isset( $_COOKIE[ TEST_COOKIE ] ) && isset( $transient[ $comment_id ] ) ) ||
					// or check if cookies are enabled and comment is not flagged but transients show a relatively high number and assume fraud 
					 ( isset( $_COOKIE[ TEST_COOKIE ] )  && isset( $transient[ $comment_id ] ) && $transient[ $comment_id ] >= $this->no_cookie_grace )
					) {
						return true;
				}
			}
			return false;
		}
		
		/*
		 * Report a comment and send it to moderation if threshold is reached
		 */
		public function mark_flagged( $comment_id ) {
			$data = array();
			if( isset( $_COOKIE[ TEST_COOKIE ] ) ) {
				if ( isset( $_COOKIE[ $this->_storagecookie ] ) ) {
					$data = $this->unserialize_cookie( $_COOKIE[ $this->_storagecookie ] );
					if ( ! isset( $data[ $comment_id ] ) )
						$data[ $comment_id ] = 0;
					$data[ $comment_id ]++;
					$cookie = $this->serialize_cookie( $data );
					@setcookie( $this->_storagecookie, $cookie, time()+$this->cookie_lifetime, COOKIEPATH, COOKIE_DOMAIN );
					if ( SITECOOKIEPATH != COOKIEPATH )
						@setcookie( $this->_storagecookie, $cookie, time()+$this->cookie_lifetime, SITECOOKIEPATH, COOKIE_DOMAIN);
				} else {
					if ( ! isset( $data[ $comment_id ] ) )
						$data[ $comment_id ] = 0;
					$data[ $comment_id ]++;
					$cookie = $this->serialize_cookie( $data );
					@setcookie( $this->_storagecookie, $cookie, time()+$this->cookie_lifetime, COOKIEPATH, COOKIE_DOMAIN );
					if ( SITECOOKIEPATH != COOKIEPATH )
						@setcookie( $this->_storagecookie, $cookie, time()+$this->cookie_lifetime, SITECOOKIEPATH, COOKIE_DOMAIN);
				}
			}
			// in case we don't have cookies. fall back to transients, block based on IP, shorter timeout to keep mem usage low and don't lock out whole companies
			$transient = get_transient( md5( $this->_storagecookie . $_SERVER['REMOTE_ADDR'] ) );
			if ( !$transient ) {
				set_transient( md5( $this->_storagecookie . $_SERVER['REMOTE_ADDR'] ), array( $comment_id => 1), $this->transient_lifetime );
			} else {
				$transient[ $comment_id ]++;
				set_transient( md5( $this->_storagecookie . $_SERVER['REMOTE_ADDR'] ), $transient, $this->transient_lifetime );
			}

				
			$threshold = (int) get_option( $this->_plugin_prefix . '_threshold' );
			$current_reports = get_comment_meta( $comment_id, $this->_plugin_prefix . '_reported', true );
			$current_reports++;
			update_comment_meta( $comment_id, $this->_plugin_prefix . '_reported', $current_reports );
			
			
			// we will not flag a comment twice. the moderator is the boss here.
			$already_reported = get_comment_meta( $comment_id, $this->_plugin_prefix . '_reported', true );
			$already_moderated = get_comment_meta( $comment_id, $this->_plugin_prefix . '_moderated', true );
			if ( true == $already_reported && true == $already_moderated ) {
				// But maybe the boss wants to allow comments to be reflagged
				if ( ! apply_filters( 'safe_report_comments_allow_moderated_to_be_reflagged', false ) ) 
					return;
			}

			if ( $current_reports >= $threshold ) {
				do_action( 'safe_report_comments_mark_flagged', $comment_id );
				wp_set_comment_status( $comment_id, 'hold' );
			}
		}
		
		/*
		 * Die() with or without screen based on JS availability
		 */
		private function cond_die( $message ) {
			if ( isset( $_REQUEST['no_js'] ) && true == (boolean) $_REQUEST['no_js'] )
				wp_die( __( $message ), "Safe Report Comments Notice", array('response' => 200 ) );
			else
				die( __( $message ) );
		}
		
		/* 
		 * Ajax callback to flag/report a comment
		 */
		public function flag_comment() {		
			if ( (int) $_REQUEST[ 'comment_id' ] != $_REQUEST[ 'comment_id' ] || empty( $_REQUEST[ 'comment_id' ] ) )
				$this->cond_die( __( $this->invalid_values_message ) );
			
			$comment_id = (int) $_REQUEST[ 'comment_id' ];
			if ( $this->already_flagged( $comment_id ) )
				$this->cond_die( __( $this->already_flagged_message ) );
				
			$nonce = $_REQUEST[ 'sc_nonce' ];
			// checking if nonces help
			if ( ! wp_verify_nonce( $nonce, $this->_plugin_prefix . '_' . $this->_nonce_key ) ) 
				$this->cond_die( __( $this->invalid_nonce_message ) );
			else {
				$this->mark_flagged( $comment_id );
				$this->cond_die( __( $this->thank_you_message ) );
			}
			
		}
		
		public function print_flagging_link( $comment_id='', $result_id='', $text='Report comment' ) {
			echo $this->get_flagging_link( $comment_id='', $result_id='', $text='Report comment' );
		}
		
		/* 
		 * Output Link to report a comment
		 */
		public function get_flagging_link( $comment_id='', $result_id='', $text='Report comment' ) {
			global $in_comment_loop;
			if ( empty( $comment_id ) && !$in_comment_loop ) {
				return __( 'Wrong usage of print_flagging_link().' );
			}
			if ( empty( $comment_id ) ) {
				$comment_id = get_comment_ID();
			}
			else {
				$comment_id = (int) $comment_id;
				if ( !get_comment( $comment_id ) ) {
					return __( 'This comment does not exist.' );
				}
			}
			if ( empty( $result_id ) )
				$result_id = 'safe-comments-result-' . $comment_id;
				
			$result_id = apply_filters( 'safe_report_comments_result_id', $result_id );
			$text = apply_filters( 'safe_report_comments_flagging_link_text', $text );
			
			$nonce = wp_create_nonce( $this->_plugin_prefix . '_' . $this->_nonce_key );
			$params = array( 
							'action' => 'safe_report_comments_flag_comment', 
							'sc_nonce' => $nonce, 
							'comment_id' => $comment_id, 
							'result_id' => $result_id,
							'no_js' => true, 
			);
			
			if ( $this->already_flagged( $comment_id ) )
				return __( $this->already_flagged_note );
			
			return apply_filters( 'safe_report_comments_flagging_link', '
			<span id="' . $result_id . '"><a class="hide-if-no-js" href="javascript:void(0);" onclick="safe_report_comments_flag_comment( \'' . $comment_id . '\', \'' . $nonce . '\', \'' . $result_id . '\');">' . __( $text ) . '</a></span>' );
			
			
		}
		
		/*
		 * Callback function to automatically hook in the report link after the comment reply link. 
		 * If you want to control the placement on your own define no_autostart_safe_report_comments in your functions.php file and initialize the class
		 * with $safe_report_comments = new Safe_Report_Comments( $auto_init = false );
		 */
		public function add_flagging_link( $comment_reply_link ) {
			if ( !preg_match_all( '#^(.*)(<a.+class=["|\']comment-(reply|login)-link["|\'][^>]+>)(.+)(</a>)(.*)$#msiU', $comment_reply_link, $matches ) ) 
				return '<!-- safe-comments add_flagging_link not matching -->' . $comment_reply_link;
		
			$comment_reply_link =  $matches[1][0] . $matches[2][0] . $matches[4][0] . $matches[5][0] . '<span class="safe-comments-report-link">' . $this->get_flagging_link() . '</span>' . $matches[6][0];
			return apply_filters( 'safe_report_comments_comment_reply_link', $comment_reply_link );
		}
		
		/*
		 * Callback function to add the report counter to comments screen. Remove action manage_edit-comments_columns if not desired
		 */
		public function add_comment_reported_column( $comment_columns ) {
			$comment_columns['comment_reported'] = _x('Reported', 'column name');
			return $comment_columns;
		}
		
		/* 
		 * Callback function to handle custom column. remove action manage_comments_custom_column if not desired
		 */
		public function manage_comment_reported_column( $column_name, $comment_id ) { 
			switch($column_name) {
			case 'comment_reported':
				$reports = 0;
				$already_reported = get_comment_meta( $comment_id, $this->_plugin_prefix . '_reported', true );
				if ( $already_reported > 0 )
					$reports = (int) $already_reported;
				echo $reports;
				break;
			default:
				break;
			}
		}
		
	}
}

if ( !defined( 'no_autostart_safe_report_comments' ) )
	$safe_report_comments = new Safe_Report_Comments;

?>
