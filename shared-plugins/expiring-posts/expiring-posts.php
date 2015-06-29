<?php

/*
Plugin Name: Expiring Posts
Plugin URI: http://www.10up.com
Description: Add new status for expired posts.
Author: Tanner Moushey, Ivan Kruchkoff (10up LLC)
Version: 1.1
Author URI: http://www.10up.com

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


define( 'EXPIRING_POSTS_URL' , plugins_url( '/', __FILE__ ) );

class EXP_Expiring_Posts {

	/**
	 * The only instance of the EXP_Expiring_Posts.
	 *
	 * @var  EXP_Expiring_Posts
	 */
	private static $instance;

	/**
	 * Returns the main instance.
	 *
	 * @return  EXP_Expiring_Posts
	 */
	public static function instance() {
		if ( is_null( self::$instance ) )
			self::$instance = new self();

		return self::$instance;
	}

	private function __construct() {

		// make sure expired post meta field follows directly after publish field
		add_action( 'post_submitbox_misc_actions', array( $this, 'post_meta_box' ), 5 );

		// enqueue admin JS and CSS for styling and DOM Voodoo
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );

		// Enqueue custom post status
		add_action( 'init', array( $this, 'expiring_post_status' ) );

		// Action from wp_transition_post_status
		add_action( 'expired_post', array( $this, 'expired_post_transition' ) );

		// Unschedule exp_expire_post_event for this post if it is deleted
		add_action( 'after_delete_post', array( $this, 'unschedule_expired_post' ) );

		// Event scheduled when an expiration date is set
		add_action( 'exp_expire_post_event', array( $this, 'check_and_expire_scheduled_post' ) );

		// Save expired posts meta
        add_action( 'save_post', array( $this, 'save_expiration_date' ) );

        // Update all posts view to show expired status
        add_action( 'display_post_states' , array( $this, 'add_expiry_post_states' ) );

    }

    /**
     * Display expired/expiring status in All Posts view
     *
     * @param $states
     */
    function add_expiry_post_states( $states ) {
        global $post;

        $is_expired = get_post_status( $post->ID ) === "expired";
        $is_expiring = get_post_meta( $post->ID, 'exp_pending_expiration', true );
        $expiry_time = implode( get_post_meta( $post->ID, 'exp_expiration_date' ) );
        // Check if expired or pending expiry
        // Post can have an expiry time, but not be expired (if they check the never box)
        if ( $is_expired || ( $is_expiring && strlen( $expiry_time ) ) ) {
            $expiry_message = $is_expired ? "Expired" : "Expiring: $expiry_time";
            $states[] = __( '<span class="expiry">' . $expiry_message . '</span>' );
        }

        return $states;
    }

	/**
	 * Save date for expiring posts
	 * Called from save_post
	 *
	 * @param $post_id
	 */
	function save_expiration_date( $post_id ) {

		// Check its not an auto save
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
			return;

		// make suer we have all of our values
		if ( !isset($_POST['expiring_posts_nonce']) || !isset($_POST['exp-aa']) || !isset($_POST['exp-mm']) || !isset($_POST['exp-jj']) || !isset($_POST['exp-hh']) || !isset($_POST['exp-mn']) || !isset($_POST['exp-ss']) )
			return;

		// Check permissions
		if ( !current_user_can( 'edit_post', $post_id ) )
			return;

		// Finally check the nonce
		check_admin_referer( 'save_expiration_post_meta', 'expiring_posts_nonce' );

		$prev_expiration_date = get_post_meta( $post_id, 'exp_expiration_date', true );
		$post_status = get_post_status( $post_id );

		// if post was manually set to expired, we want to record the current time with expired_post_transition()
		if ( 'expired' == $post_status && ( ! $prev_expiration_date || strtotime( $prev_expiration_date ) >= time() ) )
			return;

		$aa = $_POST['exp-aa'];
		$mm = $_POST['exp-mm'];
		$jj = $_POST['exp-jj'];
		$hh = $_POST['exp-hh'];
		$mn = $_POST['exp-mn'];
		$ss = $_POST['exp-ss'];
		$jj = ($jj > 31 ) ? 31 : $jj;
		$hh = ($hh > 23 ) ? $hh -24 : $hh;
		$mn = ($mn > 59 ) ? $mn -60 : $mn;
		$ss = ($ss > 59 ) ? $ss -60 : $ss;
		$expiration_date = "$aa-$mm-$jj $hh:$mn:$ss";
		$valid_date = wp_checkdate( $mm, $jj, $aa, $expiration_date );

		if ( ! $valid_date )
			return;

		update_post_meta( $post_id, 'exp_expiration_date', sanitize_text_field( $expiration_date ) );

		// Enabling the expiration feature is opt-in, where the checkbox is
		// checked by default

		// If post is already expired, make sure the this is visually represented
		if ( 'expired' == $post_status ) {
			update_post_meta( $post_id, 'exp_pending_expiration', true );

			// post is scheduled to expire, enable expiration and set hook. Exception is if the post has
			// just transitioned from expired to publish
		} elseif ( !isset( $_POST['exp-enable'] ) && !( 'expired' === $_POST['hidden_post_status'] && 'publish' === $post_status ) ) {
			$this->schedule_post_expiration( $post_id );

			// post expiration is not enabled. Clear any expiring hooks and disable expiration
		} else {
			$this->unschedule_expired_post( $post_id );
		}

	}

	/**
	 * Enqueue scripts for arranging elements
	 */
	function admin_scripts( $page ) {
		if ( 'post.php' != $page && 'post-new.php' != $page )
			return;

		wp_enqueue_script( 'admin-expiring-posts', EXPIRING_POSTS_URL . '/inc/js/expiring-posts.js', array( 'jquery' ) );

		wp_localize_script( 'admin-expiring-posts', 'AdminExpiringPosts', array(
			'expires_on' => __( 'Expires on:' ),
			'expires_never' => __( 'Expires: <b>never</b>' ),
			'expired_text' => __( 'Expired' ),
			'save_text' => __( 'Save Post' ),
			'post_status' => get_post_status(),
		) );

		wp_enqueue_style( 'expiring-posts-css', EXPIRING_POSTS_URL . '/inc/css/expiring-posts.css' );
	}

	/**
	 * Handle new Expired metabox
	 * Called from post_submitbox_misc_actions
	 */
	function post_meta_box() {
		global $post;

		if ( 0 == $post->ID )
			return;

		$post_type = $post->post_type;
		$post_type_object = get_post_type_object($post_type);
		$can_publish = current_user_can($post_type_object->cap->publish_posts);
		$expiration_date = strtotime( get_post_meta( $post->ID, 'exp_expiration_date', true ) );
		$expiration_enabled = get_post_meta( $post->ID, 'exp_pending_expiration', true );

		// set default expiration date if one is not present.
		// strtotime returns false if the string is not a valid time
		if ( ! $expiration_date )
			$expiration_date = time() + ( DAY_IN_SECONDS * 5 ); // add 5 days to current time

		$datef = __( 'M j, Y @ G:i' );
		if ( ! $expiration_enabled ) {
			$stamp = __( 'Expires: <b>never</b>' );
		} elseif ( 'expired' == $post->post_status ) { // Post has expired
			$stamp = __( 'Expired on: <b>%1$s</b>' );
		} else {
			$stamp = __( 'Expires on: <b>%1$s</b>' );
		}

		$date = date_i18n( $datef, $expiration_date );

		// added an expired status that is appended to the post status editor with js
		if ( $can_publish ) : // Contributors don't get to choose the date of publish ?>
			<select style="display:none;"><option <?php selected( $post->post_status, 'expired' ); ?> id='expired-status' value='expired'><?php _e( 'Expired' ) ?></option></select>
			<div class="misc-pub-section curtime">
				<span id="exp-timestamp" style="background-image: url(<?php echo admin_url(); ?>images/date-button.gif);"><?php printf( $stamp, $date ); ?></span>
				<a href="#" class="exp-edit-timestamp hide-if-no-js"><?php _e( 'Edit' ) ?></a>
				<div id="exp-timestampdiv" class="hide-if-js"><?php $this->select_time( $post->ID ); ?></div>
			</div>
			<?php
			wp_nonce_field( 'save_expiration_post_meta', 'expiring_posts_nonce' );
		endif;
	}

	/**
	 * Create the form elements for the time picker, reused code from touch_time
	 * so that we could edit the names for our use.
	 *
	 * @param int $post_id
	 * @param int $tab_index
	 * @param int $multi
	 */
	function select_time( $post_id, $tab_index = 0, $multi = 0 ) {
		global $wp_locale;

		$tab_index_attribute = '';
		if ( (int) $tab_index > 0 )
			$tab_index_attribute = sprintf( ' tabindex="%s"', intval( $tab_index ) );

		$expiration_date = strtotime( get_post_meta( $post_id, 'exp_expiration_date', true ) );

		// strtotime returns false if a valid string is not provided
		if ( ! $expiration_date )
			$expiration_date = time() + ( DAY_IN_SECONDS * 5 ); // add 5 days to current time

		// define date format
		$datef = __( 'Y-m-d H:i:s' );

		$expiration_date = date_i18n( $datef, $expiration_date );

		$jj = mysql2date( 'd', $expiration_date, false );
		$mm = mysql2date( 'm', $expiration_date, false );
		$aa = mysql2date( 'Y', $expiration_date, false );
		$hh = mysql2date( 'H', $expiration_date, false );
		$mn = mysql2date( 'i', $expiration_date, false );
		$ss = mysql2date( 's', $expiration_date, false );

		$month = "<select " . ( $multi ? '' : 'id="exp-mm" ' ) . "name=\"exp-mm\"$tab_index_attribute>\n";
		for ( $i = 1; $i < 13; $i = $i +1 ) {
			$monthnum = zeroise($i, 2);
			$month .= "\t\t\t" . '<option value="' . $monthnum . '"';
			if ( $i == $mm )
				$month .= ' selected="selected"';
			/* translators: 1: month number (01, 02, etc.), 2: month abbreviation */
			$month .= '>' . sprintf( __( '%1$s-%2$s' ), $monthnum, $wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) ) ) . "</option>\n";
		}
		$month .= '</select>';

		$day = '<input type="text" ' . ( $multi ? '' : 'id="exp-jj" ' ) . 'name="exp-jj" value="' . esc_attr( $jj ) . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" />';
		$year = '<input type="text" ' . ( $multi ? '' : 'id="exp-aa" ' ) . 'name="exp-aa" value="' . esc_attr( $aa ) . '" size="4" maxlength="4"' . $tab_index_attribute . ' autocomplete="off" />';
		$hour = '<input type="text" ' . ( $multi ? '' : 'id="exp-hh" ' ) . 'name="exp-hh" value="' . esc_attr( $hh ) . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" />';
		$minute = '<input type="text" ' . ( $multi ? '' : 'id="exp-mn" ' ) . 'name="exp-mn" value="' . esc_attr( $mn ) . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" />';

		echo '<div class="exp-timestamp-wrap">';
		/* translators: 1: month input, 2: day input, 3: year input, 4: hour input, 5: minute input */
		printf(__('%1$s%2$s, %3$s @ %4$s : %5$s'), $month, $day, $year, $hour, $minute);

		echo '</div><input type="hidden" id="exp-ss" name="exp-ss" value="' . $ss . '" />';

		if ( $multi ) return;

		echo "\n\n";
		foreach ( array('mm', 'jj', 'aa', 'hh', 'mn') as $timeunit ) {
			echo '<input type="hidden" id="hidden_exp-' . $timeunit . '" name="hidden_exp-' . $timeunit . '" value="' . esc_attr( $$timeunit ) . '" />' . "\n";
		}
		?>

		<input type="checkbox" name="exp-enable" <?php checked( get_post_meta( $post_id, 'exp_pending_expiration', true ) == false ) ?> id="exp-enable" value="never" />
		<label for="exp-enable"><?php _e( 'Never expire' ); ?></label>

		<p>
			<a href="#exp-edit_timestamp" class="exp-save-timestamp hide-if-no-js button"><?php _e( 'OK' ); ?></a>
			<a href="#exp-edit_timestamp" class="exp-cancel-timestamp hide-if-no-js"><?php _e( 'Cancel' ); ?></a>
		</p>
	<?php
	}

	/**
	 * Register custom status for expired posts
	 */
	function expiring_post_status(){
		$args =  array(
			'label'                     => _x( 'Expired', 'post' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>' ),
		);

		$args = apply_filters( 'exp_expired_post_status_args', $args );
		register_post_status( 'expired', $args );
	}

	/**
	 * Set the expiration date to the current date when
	 * a post is manually expired.
	 *
	 * Called from expired_post
	 *
	 * @param $post_id
	 */
	function expired_post_transition( $post_id ) {
		$expiration_date = get_post_meta( $post_id, 'exp_expiration_date', true );

		if ( strtotime( $expiration_date ) && strtotime( $expiration_date ) < time() )
			return;

		$datef = __( 'Y-m-d H:i:s' );
		$expiration_date = date_i18n( $datef, time() );
		update_post_meta( $post_id, 'exp_expiration_date', sanitize_text_field( $expiration_date ) );
		update_post_meta( $post_id, 'exp_pending_expiration', true );
	}

	/**
	 * Sets the given posts status to expired, reused code from wp_publish_post
	 *
	 * @param $post
	 */
	function expire_post( $post ) {
		global $wpdb;

		if ( ! $post = get_post( $post ) )
			return;

		$wpdb->update( $wpdb->posts, array( 'post_status' => 'expired' ), array( 'ID' => $post->ID ) );

		clean_post_cache( $post->ID );

		$old_status = $post->post_status;
		$post->post_status = 'expired';
		wp_transition_post_status( 'expired', $old_status, $post );

		do_action( 'edit_post', $post->ID, $post );
		do_action( 'save_post', $post->ID, $post );
		do_action( 'wp_insert_post', $post->ID, $post );

	}

	/**
	 * Schedule post to expire.
	 * option key.
	 *
	 * @param $post
	 * @return bool|WP_Error
	 */
	function schedule_post_expiration( $post ) {

		if ( ! $post = get_post( $post ) )
			return new WP_Error( 'exp_expiring_posts_error', __( 'This is not a valid post.' ) );

		update_post_meta( $post->ID, 'exp_pending_expiration', true );

		if ( 'expired' === get_post_status( $post->ID ) )
			return true;

		// Verify that post is set to expire
		if ( ! $expiring_time = strtotime( get_post_meta( $post->ID, 'exp_expiration_date', true ) ) )
			return new WP_Error( 'exp_expiring_posts_error', __( 'This post cannot be expired, the expiration date is invalid.' ) );

		$this->reset_expiration_event();
		return true;
	}

	/**
	 * Unschedule a post from expiring
	 *
	 * @param $post
	 *
	 * @return bool|WP_Error
	 */
	function unschedule_expired_post( $post ) {

		if ( ! $post = get_post( $post ) )
			return new WP_Error( 'exp_expiring_posts_error', __( 'The post provided is not a valid post.' ) );

		delete_post_meta( $post->ID, 'exp_pending_expiration' );

		do_action( 'unschedule_expiring_post', $post->ID );

		$this->reset_expiration_event();
		return true;
	}

	/**
	 * Set the next event for expiring posts.
	 */
	function reset_expiration_event() {

		$times = $this->get_expiring_posts();

		// something went wrong, bail early
		if ( ! is_array( $times ) || empty( $times ) )
			return;

		sort( $times, SORT_NUMERIC );

		$next_time = reset( $times );
		$next_scheduled = wp_next_scheduled( 'exp_expire_post_event');

		// if the schedule is already correct, exit
		if ( $next_scheduled && $next_scheduled === $next_time )
			return;
		elseif( $next_scheduled )
			wp_unschedule_event( $next_scheduled, 'exp_expire_post_event' );

		wp_schedule_single_event( $next_time, 'exp_expire_post_event' );

	}

	/**
	 * Check all posts that are scheduled to expire and expire the ones
	 * that are due.
	 *
	 * Called from exp_expire_post_event
	 */
	function check_and_expire_scheduled_post() {

		$curr_time = time();

		$next_scheduled = wp_next_scheduled( 'exp_expire_post_event');

		// make sure this is the right time to run, if not reset the time for the
		// next scheduled event
		if ( $next_scheduled > $curr_time )
			return $this->reset_expiration_event();

		$times = $this->get_expiring_posts();

		// something went wrong, bail early
		if ( ! is_array( $times ) || empty( $times ) )
			return;

		foreach( $times as $post_id => $time ) {

			if ( $time <= $curr_time )
				$this->expire_post( $post_id );

		}

		$this->reset_expiration_event();

	}

	/**
	 * Get all posts that are scheduled to expire and return them
	 * in an array where the value is the GMT timestamp and the key
	 * is the post ID
	 *
	 * @return array|bool
	 */
	function get_expiring_posts() {
		global $wpdb;

		$expiring_posts = array();

		$querystr = "
			SELECT $wpdb->posts.ID
			FROM $wpdb->posts, $wpdb->postmeta
			WHERE $wpdb->posts.ID = $wpdb->postmeta.post_id
			AND $wpdb->postmeta.meta_key = 'exp_pending_expiration'
			AND $wpdb->posts.post_status != 'expired'
			ORDER BY $wpdb->posts.post_date DESC
		 ";

		$post_ids = $wpdb->get_col( $querystr );

		if ( ! is_array( $post_ids ) )
			return false;

		foreach ( $post_ids as $post_id ) {
			$gmtime = get_gmt_from_date( get_post_meta( $post_id, 'exp_expiration_date', true ) );
			if ( $expiration_date = strtotime( $gmtime ) )
				$expiring_posts[$post_id] = $expiration_date;
		}

		return $expiring_posts;

	}

}

EXP_Expiring_Posts::instance();

/**
 * Schedule a post to be expired. If the expiration date is not given,
 * the post will expire immediately.
 *
 * @param $post
 * @param $expiration_date, GMT Timestamp
 *
 * @return bool|WP_Error
 */
function exp_expire_post( $post, $expiration_date = false ) {

	if ( ! $expiration_date )
		$expiration_date = time();

	if ( ! is_int( $expiration_date ) )
		$expiration_date = strtotime( $expiration_date );

	if ( ! ( $post = get_post( $post ) ) || $expiration_date === false )
		return new WP_Error( 'exp_expiring_posts_error', __( 'Either the post or expiration date provided are not valid.' ) );

	$datef = __( 'Y-m-d H:i:s' );
	$expiration_datef = date_i18n( $datef, $expiration_date );

	update_post_meta( $post->ID, 'exp_expiration_date', sanitize_text_field( $expiration_datef ) );
	update_post_meta( $post->ID, 'exp_pending_expiration', true );

	do_action( 'exp_expire_post', $post->ID, $expiration_date );

	$expiring_posts = EXP_Expiring_Posts::instance();

	if ( $expiration_date <= time() )
		$expiring_posts->expire_post( $post );

	$expiring_posts->schedule_post_expiration( $post );

	return true;

}

/**
 * Unschedule a post that is scheduled to expire
 *
 * @param $post
 *
 * @return bool|WP_Error
 */
function exp_unschedule_expiring_post( $post ) {

	if ( ! $post = get_post( $post ) )
		return new WP_Error( 'exp_expiring_posts_error', __( 'The post provided is not valid.' ) );

	$expiring_posts = EXP_Expiring_Posts::instance();
	$expiring_posts->unschedule_expired_post( $post );

	return true;

}
