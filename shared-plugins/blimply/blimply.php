<?php
/*
Plugin Name: Blimply
Plugin URI: http://doejo.com
Description: Blimply allows you to send push notifications to your mobile users utilizing Urban Airship API. It sports a post meta box and a dashboard widgets. You have the ability to broadcast pushes, and to push to specific Urban Airship tags as well.
Author: Rinat Khaziev, doejo
Version: 0.5.1
Author URI: http://doejo.com

GNU General Public License, Free Software Foundation <http://creativecommons.org/licenses/GPL/2.0/>

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/

define( 'BLIMPLY_VERSION', '0.5.1' );
define( 'BLIMPLY_ROOT' , dirname( __FILE__ ) );
define( 'BLIMPLY_FILE_PATH' , BLIMPLY_ROOT . '/' . basename( __FILE__ ) );
define( 'BLIMPLY_URL' , plugins_url( '/', __FILE__ ) );
define( 'BLIMPLY_PREFIX' , 'blimply' );

// Bootstrap
require_once BLIMPLY_ROOT . '/lib/wp-urban-airship/urbanairship.php';
require_once BLIMPLY_ROOT . '/lib/settings-api-class/class.settings-api.php';
require_once BLIMPLY_ROOT . '/lib/blimply-settings.php';

class Blimply {

	public $options, $airships, $airship, $tags;
	/**
	 * Instantiate
	 */
	function __construct() {
		add_action( 'admin_init', array( $this, 'action_admin_init' ) );
		add_action( 'save_post', array( $this, 'action_save_post' ) );
		add_action( 'add_meta_boxes', array( $this, 'post_meta_boxes' ) );
		add_action( 'update_option_blimply_options', array( $this, 'sync_airship_tags' ), 5, 2 );
		add_action( 'register_taxonomy', array( $this, 'after_register_taxonomy' ), 5, 3 );
		add_action( 'create_term', array( $this, 'action_create_term' ), 5, 3 );
		add_action( 'init', array( $this, 'action_init' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'dashboard_setup' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts_and_styles' ) );
		add_action( 'wp_ajax_blimply-send-push', array( $this, 'handle_ajax_post' ) );
	}

	function dashboard_setup() {
		if ( is_blog_admin() && current_user_can( apply_filters( 'blimply_push_cap', 'edit_posts' ) ) )
			wp_add_dashboard_widget( 'dashboard_blimply', __( 'Send a Push Notification' ), array( $this, 'dashboard_widget' ) );
	}

	/**
	 *  Init hook
	 *
	 */
	function action_init() {
		register_taxonomy( 'blimply_tags', array( 'post' ), array(
				'public' => false,
				'labels' => array(
					'name' => __( 'Urban Airship Tags', 'blimply' ),
					'singular_name' => __( 'Urban Airship Tags', 'blimply' ),
				),
				'show_in_nav_menus' => false,
				'show_ui' => false
			) );
		load_plugin_textdomain( 'blimply', false, dirname( plugin_basename( __FILE__ ) ) . '/lib/languages/' );
	}
	/**
	 * Set basic app properties
	 *
	 */
	function action_admin_init() {
		global $pagenow;
		// Init the plugin only on proper pages and if doing ajax request
		if ( ! in_array( $pagenow, array( 'post-new.php', 'post.php', 'index.php', 'options.php' ) ) && ! defined( 'DOING_AJAX' ) )
			return;
		$defaults = array(
			BLIMPLY_PREFIX  . '_name' => '',
			BLIMPLY_PREFIX  . '_app_key' => '',
			BLIMPLY_PREFIX . '_app_secret' => '',
			BLIMPLY_PREFIX . '_character_limit' => 140,
			BLIMPLY_PREFIX . '_quiet_time_from' => '',
			BLIMPLY_PREFIX . '_quiet_time_to' => '',
			BLIMPLY_PREFIX . '_enable_quiet_time' => ''
		);
		// Try to set default options if option doesn't exist
		$this->options = get_option( 'urban_airship', $defaults );
		// Make sure that default options are set properly even if suboption key doesn't exist
		// e.g. new option was added in a new verion;
		$this->options = array_merge( $defaults, (array) $this->options );

		$this->sounds = get_option( 'blimply_sounds' );
		$this->airships[ $this->options['blimply_name'] ] = new Airship( $this->options['blimply_app_key'], $this->options['blimply_app_secret'] );
		// Pass the reference to convenience var
		// We don't use multiple Airships yet.
		// Although we can, there's no UI for switching Airships.
		$this->airship = &$this->airships[ $this->options['blimply_name'] ];
		// We don't use built-in WP UI, instead we choose tag in custom Blimply meta box
		$this->tags = get_terms( 'blimply_tags', array( 'hide_empty' => 0 ) );
	}

	/**
	 * Helper function to determine if current time should be quiet time (no push sounds)
	 * @return boolean [description]
	 */
	function _is_quiet_time() {
		$current_time = date( "G:i", current_time( 'timestamp' ) );
		$quiet_from = $this->options[BLIMPLY_PREFIX . '_quiet_time_from'];
		$quiet_to = $this->options[BLIMPLY_PREFIX . '_quiet_time_to'];
		$quiet_to_array = explode( ":", $quiet_to );
		$is_quiet_from = $quiet_from < $current_time;
		$is_quiet_to = ( $quiet_to >  $current_time && $quiet_to_array[0] > 12 ) || ( $quiet_to < $current_time && $quiet_to_array[0] < 12 );
		return $is_quiet_from && $is_quiet_to && 'on' === $this->options[BLIMPLY_PREFIX . '_enable_quiet_time'];
	}

	/**
	 * Register scripts and styles
	 *
	 */
	function register_scripts_and_styles() {
		global $pagenow;
		// Only load this on the proper page
		if ( ! in_array( $pagenow, array( 'post-new.php', 'post.php', 'index.php', 'options-general.php' ) ) )
			return;
		wp_enqueue_style( 'blimply-style', BLIMPLY_URL . '/lib/css/blimply.css' );
		wp_enqueue_script( 'timepicker', BLIMPLY_URL . '/lib/js/jquery.timePicker.min.js', array( 'jquery' ) );
		wp_enqueue_script( 'blimply-js', BLIMPLY_URL . '/lib/js/blimply.js', array( 'jquery', 'timepicker' ) );
		wp_localize_script( 'blimply-js', 'Blimply', array(
				'push_sent' => __( 'Push notification successfully sent', 'blimply' ),
				'push_error' => __( 'Sorry, there was some error while we were trying to send your push notification. Try again later!', 'blimply' ),
				'character_limit' => (int) $this->options[ BLIMPLY_PREFIX .'_character_limit' ]
			)
		);
	}

	/**
	 * Sync our newly created tag with Urban Airship
	 *
	 * @param int     $term_id  term_id
	 * @param int     $tt_id    term_taxonomy_id
	 * @param string  $taxonomy
	 */
	function action_create_term( $term_id, $tt_id, $taxonomy ) {
		if ( 'blimply_tags' != $taxonomy )
			return;
		$tag = get_term( $term_id, $taxonomy );
		// Let's sync
		if ( ! is_wp_error( $tag ) ) {
			try {
				$response = $this->airship->_request( BASE_URL . "/tags/{$tag->slug}", 'PUT', null );
			} catch ( Exception $e ) {
				// @todo do something with exception
			}
			if ( isset( $response[0] ) && $response[0] == 201 ) {
				// @todo process ok result
			}
		}
	}

	/**
	 * Send a push notification if checkbox is checked
	 *
	 * @param int     $post_id
	 */
	function action_save_post( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || wp_is_post_revision( $post_id ) )
			return;
		if ( isset( $_POST['blimply_nonce'] ) && !wp_verify_nonce( $_POST['blimply_nonce'], BLIMPLY_FILE_PATH ) )
			return;
		if ( !current_user_can( apply_filters( 'blimply_push_cap', 'edit_posts' ) ) )
			return;
		if ( 1 == get_post_meta( $post_id, 'blimply_push_sent', true ) )
			return;

		if ( isset( $_POST['blimply_push'] ) && 1 == $_POST['blimply_push'] ) {
			$alert = !empty( $_POST['blimply_push_alert'] ) ? sanitize_text_field( $_POST['blimply_push_alert'] ) : sanitize_text_field( $_POST['post_title'] );
			$this->_send_broadcast_or_push( $alert, $_POST['blimply_push_tag'], get_permalink( $post_id ), (bool) isset( $_POST['blimply_no_sound'] ) && $_POST['blimply_no_sound'] );
			update_post_meta( $post_id, 'blimply_push_sent', true );
		}
	}

	/**
	 * Method to handle AJAX request for Dashboard Widget
	 */
	function handle_ajax_post() {
		if ( !wp_verify_nonce( $_POST['_wpnonce'], 'blimply-send-push' ) )
			return;
		if ( !current_user_can( apply_filters( 'blimply_push_cap', 'edit_posts' ) ) )
			return;
		$response = false;
		// Truncate to whatever the limit is set (if not 0)
		$alert = sanitize_text_field( $_POST['blimply_push_alert'] );
		$limit = (int) $this->options[ BLIMPLY_PREFIX . '_character_limit' ];
		if ( $limit )
			$alert = substr( $alert, 0, $limit );
		// Determine if sounds are disabled for the push
		$no_sound = isset( $_POST['blimply_no_sound'] ) && $_POST['blimply_no_sound'];

		$this->_send_broadcast_or_push( $alert, $_POST['blimply_push_tag'], false, (bool) $no_sound );
		echo 'ok';
		exit;
	}

	/**
	 * Private method to send push or broadcast.
	 *
	 * @param string  $alert
	 * @param string  $tag
	 *
	 */
	function _send_broadcast_or_push( $alert, $tag, $url = false, $disable_sound = false ) {
		// Strip escape slashes, otherwise double escaping would happen
		$alert = html_entity_decode( stripcslashes( strip_tags( $alert ) ) );
		// Include Android and iOS payloads
		$payload = array(
			'aps'     => array( 'alert' => $alert, 'badge' => '+1' ),
			'android' => array( 'alert' => $alert ),
			'blackberry' => array( 'content-type' => 'text/plain', 'body' => $alert ),
		);

		// Add a URL if any, to be handled by apps
		if ( $url ) {
			$payload['aps']['url'] = $url;
			$payload['android']['extra']['url'] = $url;
		}

		if ( $tag === 'broadcast' ) {
			$response =  $this->request( $this->airship, 'broadcast', $payload );
		} else {
			// Set a sound for the specific tag
			if ( !$disable_sound && isset( $this->sounds["blimply_sound_{$tag}"] ) && !empty( $this->sounds["blimply_sound_{$tag}"] ) )
				$payload['aps']['sound'] = $this->sounds["blimply_sound_{$tag}"];
			// Or use the default sound
			elseif ( !$disable_sound )
				$payload['aps']['sound'] = 'default';

			$payload['tags'] = array( $tag );

			// Payload filter (allows to workaround quirks of UA API if any)
			$payload = apply_filters( 'blimply_payload_override', $payload );
			$response = $this->request( $this->airship, 'push', $payload );
		}
	}

	/**
	 * Register metabox for selected post types
	 *
	 * @todo implement ability to actually pick specific post types
	 */
	function post_meta_boxes() {
		// Enable meta box for all public post types by default but allow to override with filters
		$post_types = apply_filters( 'blimply_enabled_post_types', get_post_types( array( 'public' => true ), 'objects' ) );
		foreach ( $post_types as $post_type => $props )
			add_meta_box( BLIMPLY_PREFIX, __( 'Push Notification', 'blimply' ), array( $this, 'post_meta_box' ), $post_type, 'side' );
	}

	/**
	 * Render HTML
	 *
	 * @todo make HTML prettier with instead of peppering everythin with line breaks
	 */
	function post_meta_box( $post ) {
		$is_push_sent = get_post_meta( $post->ID, 'blimply_push_sent', true );
		if ( 1 != $is_push_sent ) {
			echo '<div class="blimply-wrapper">';
			wp_nonce_field( BLIMPLY_FILE_PATH, 'blimply_nonce' );
			echo '<label for="blimply_push_alert">';
			esc_html_e( 'Push message', 'blimply' );
			$nice_warning = __( 'Keep in mind that all HTML will be stripped out, and refrain from putting any links in the message.', 'blimply' );
			// 0 means no limit
			$limit = (int) apply_filters( BLIMPLY_PREFIX . '_character_limit', $this->options[BLIMPLY_PREFIX . '_character_limit'] );
			$limit_html = $limit ? sprintf( ' maxlength="%d" ', $limit ) :  '';
			echo '</label><br/><small>' . esc_html( $nice_warning ) . ' Character limit is: ' . (int) $limit . '</small><br/>';
			echo '<textarea id="blimply_push_alert" name="blimply_push_alert" class="bl_textarea"' . $limit_html . '>' . esc_textarea( $post->post_title ) . '</textarea><br/>';
			echo '<strong>' . esc_html__( 'Send Push to following Urban Airship tags', 'blimply' ) . '</strong>';
			foreach ( (array) $this->tags as $tag ) {
				echo '<input type="radio" name="blimply_push_tag" id="blimply_tag_' . esc_attr( $tag->term_id ) . '" value="' . esc_attr( $tag->slug ) . '"/>';
				echo '<label class="selectit" for="blimply_tag_' . esc_attr( $tag->term_id ) . '" style="margin-left: 4px">';
				echo esc_html( $tag->name );
				echo '</label><br/>';
			}

			if ( isset( $this->options['blimply_allow_broadcast'] ) && $this->options['blimply_allow_broadcast'] == 'on' ) {
				echo '<input type="radio" name="blimply_push_tag" id="blimply_tag_broadcast" value="broadcast"/>';
				echo '<label class="selectit" for="blimply_tag_broadcast" style="margin-left: 4px">';
				esc_html_e( 'Broadcast (send to all tags)', 'blimply' );
				echo '</label><br/>';
			}

			echo '<br/><label class="selectit" for="blimply_no_sound" style="margin-left: 4px">';
			echo '<input type="checkbox" style="float:left" name="blimply_no_sound" id="blimply_no_sound" value="1" '. checked( $this->_is_quiet_time(), true, false ) . ' />';
			esc_html_e( 'Turn the sound off', 'blimply' );
			echo '</label><br/>';

			echo '<br/><input type="hidden" id="" name="blimply_push" value="0" />';
			echo '<input type="checkbox" id="blimply_push" name="blimply_push" value="1" disabled="disabled" />';
			echo '<label for="blimply_push">';
			esc_html_e( 'Check to confirm sending', 'blimply' );
			echo '</label> ';
			echo '</div>';

		} else {
			esc_html_e( 'Push notification is already sent', 'blimply' );
		}
	}

	/**
	 * Wrapper to make a remote request to Urban Airship
	 *
	 * @param Airship $airship an instance of Airship passed by reference
	 * @param string  $method
	 * @param mixed   $args
	 * @param mixed   $tokens
	 * @return mixed response or Exception or error
	 */
	function request( Airship &$airship, $method = '', $args = array(), $tokens = array() ) {
		if ( in_array( $method, array( 'register', 'deregister', 'feedback', 'push', 'broadcast' ) ) ) {
			$args = apply_filters( "blimply_{$method}_args", $args, $airship, $tokens );
			try {
				$response = $airship->$method( $args, $tokens );
				return $response;
			} catch ( Exception $e ) {
				$exception_class = get_class( $e );
				if ( is_admin() ) {
					// @todo implement admin notification of misconfiguration
				}
			}
		} else {
			// @todo illegal request
		}
	}

	/**
	 * Dashboard widget
	 *
	 */
	function dashboard_widget() {
		$limit = (int) apply_filters( BLIMPLY_PREFIX . '_character_limit', $this->options[BLIMPLY_PREFIX . '_character_limit'] );
		$limit_html = $limit ? sprintf( ' maxlength="%d" ', $limit ) :  '';
?>
		<form name="post" action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>" method="post" id="blimply-dashboard-widget">
			<h4 id="content-label"><label for="content"><?php esc_html_e( 'Send Push Notification' ) ?></label></h4>
			<small><?php esc_html_e( 'Keep in mind that all HTML will be stripped out, and refrain from putting any links in the message.', 'blimply' ); ?></small><br/>
			<?php if ( $limit ): ?>
			<small><?php esc_html_e( 'Character limit is: ', 'blimply' ); ?><strong><?php echo (int) $limit ?></strong>.</small> <br/>
			<small><strong><?php esc_html_e( 'Characters left: ', 'blimply' ); ?><span class="limit"><?php echo (int) $limit ?></strong></span>.
			<?php esc_html_e( "You won't be able to type more than that.", 'blimply' ); ?>
			</small>
			<?php endif; ?>
			<div class="textarea-wrap">
				<textarea name="blimply_push_alert" id="content" rows="3" cols="15" tabindex="2" <?php echo $limit_html ?> placeholder="Your push message"></textarea>
			</div>
			<h4><label for="tags-input"><?php esc_html_e( 'Choose a tag' ) ?></label></h4>
<?php
		foreach ( (array) $this->tags as $tag ) {
			echo '<label class="float-left f-left selectit" for="blimply_tag_' . esc_attr( $tag->term_id ) . '" style="margin-left: 4px">';
			echo '<input type="radio" class="float-left f-left" style="float:left" name="blimply_push_tag" id="blimply_tag_' . esc_attr( $tag->term_id ) . '" value="' . esc_attr( $tag->slug ) . '"/>';
			echo $tag->name;
			echo '</label><br/>';
		}

		if ( isset( $this->options['blimply_allow_broadcast'] ) && $this->options['blimply_allow_broadcast'] == 'on' ) {
			echo '<label class="selectit" for="blimply_tag_broadcast" style="margin-left: 4px">';
			echo '<input type="radio" style="float:left" name="blimply_push_tag" id="blimply_tag_broadcast" value="broadcast"/>';
			esc_html_e( 'Broadcast (send to all tags)', 'blimply' );
			echo '</label><br/>';
		}
		?>
		<br/>
		<h4><label for="blimply_no_sound"><?php esc_html_e( 'Turn the sound off' ) ?></label></h4> <?php
		echo '<label class="selectit" for="blimply_no_sound" style="margin-left: 4px">';
		echo '<input type="checkbox" style="float:left" name="blimply_no_sound" id="blimply_no_sound" value="1" '. checked( $this->_is_quiet_time(), true, false ) . ' />';
		esc_html_e( 'Turn the sound off', 'blimply' );
		echo '</label><br/>';
?>
			<p class="submit">
				<input type="hidden" name="action" id="blimply-push-action" value="blimply-send-push" />
				<?php wp_nonce_field( 'blimply-send-push' ); ?>
				<input type="reset" value="<?php esc_attr_e( 'Reset' ); ?>" class="button" />
				<span id="publishing-action">
					<?php
		if ( current_user_can( apply_filters( 'blimply_push_cap', 'publish_posts' ) ) ):
?>
					<input type="submit" name="publish" disabled="disabled" id="blimply_push_send" accesskey="p" tabindex="5" class="button-primary" value="<?php  esc_attr_e( 'Send push notification' ) ?>" />
					<?php endif; ?>
					<img class="waiting" style="display:none;" src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" alt="" />
				</span>
				<br class="clear" />
			</p>
		</form>
<?php
	}
}

// define BLIMPLY_NOINIT constant somewhere in your theme to easily subclass Blimply
if ( ! defined( 'BLIMPLY_NOINIT' ) || defined( 'BLIMPLY_NOINIT' ) && BLIMPLY_NOINIT ) {
	global $blimply;
	$blimply = new Blimply;
}
