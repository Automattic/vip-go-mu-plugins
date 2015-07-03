<?php /*

**************************************************************************

Plugin Name:  New Device Notification
Description:  Uses a cookie to identify new devices that are used to log in. On new device detection, an e-mail is sent. This provides some basic improved security against compromised accounts.
Author:       Automattic VIP Team
Author URI:   http://vip.wordpress.com/

**************************************************************************/

class New_Device_Notification {

	// Notifications won't be sent for a certain period of time after the plugin is enabled.
	// This is to get all of the normal users into the logs and avoid spamming inboxes.
	public $grace_period = 604800; // 1 week

	public $cookie_name = 'deviceseenbefore';

	public $cookie_hash;

	function __construct() {
		// Log when this plugin was first activated
		add_option( 'newdevicenotification_installedtime', time() );

		// Wait until "admin_init" to do anything else
		if ( apply_filters( 'ndn_run_only_in_admin', true ) ) {
			add_action( 'admin_init', array( $this, 'start' ), 99 );
		} else {
			add_action( 'init', array( $this, 'start' ), 99 );
		}
	}

	public function start() {
		global $current_user;

		// Internal IP whitelist
		if ( in_array( $_SERVER['REMOTE_ADDR'], array( '72.233.96.227' ) ) )
			return;

		// User Agent whitelist
		if ( in_array( $_SERVER['HTTP_USER_AGENT'], array(
			'Shockwave Flash', // The uploader
		) ) ) {
			return;
		}

		get_currentuserinfo();

		// By default, users to skip:
		// * Super admins (Automattic employees visiting your site)
		// * Users who don't have /wp-admin/ access
		$is_privileged_user = ! is_super_admin() && current_user_can( 'edit_posts' );
		if ( false === apply_filters( 'ndn_run_for_current_user', $is_privileged_user ) )
			return;

		// Set up the per-blog salt
		$salt = get_option( 'newdevicenotification_salt' );
		if ( ! $salt ) {
			$salt = wp_generate_password( 64, true, true );
			add_option( 'newdevicenotification_salt', $salt );
		}

		$this->cookie_hash = hash_hmac( 'md5', $current_user->ID, $salt );

		// Seen this device before?
		if ( $this->verify_cookie() )
			return;

		// Attempt to mark this device as seen via a cookie
		$this->set_cookie();

		// Maybe we've seen this user+IP+agent before but they don't accept cookies?
		$memcached_key = 'lastseen_' . $current_user->ID . '_' . md5( $_SERVER['REMOTE_ADDR'] . '|' . $_SERVER['HTTP_USER_AGENT'] );
		if ( wp_cache_get( $memcached_key, 'newdevicenotification' ) )
			return;

		// As a backup to the cookie, record this IP address (only in memcached for now, proper logging will come later)
		wp_cache_set( $memcached_key, time(), 'newdevicenotification' );

		add_filter( 'ndn_send_email', array( $this, 'maybe_send_email' ), 10, 2 );

		$this->notify_of_new_device();

	}

	public function verify_cookie() {
		if ( ! empty( $_COOKIE[$this->cookie_name] ) && $_COOKIE[$this->cookie_name] === $this->cookie_hash )
			return true;

		return false;
	}

	public function set_cookie() {
		if ( headers_sent() )
			return false;

		$tenyrsfromnow = time() + 315569260;

		// Covers all subdomains of wordpress.com, which covers admin section as well
		setcookie( $this->cookie_name, $this->cookie_hash, $tenyrsfromnow, COOKIEPATH, 'wordpress.com', false, true );

		// If site is on a mapped domain
		if ( site_url() != home_url() ) {
			$parts = parse_url( home_url() );
			setcookie( $this->cookie_name, $this->cookie_hash, $tenyrsfromnow, COOKIEPATH, $parts['host'], false, true );
		}
	}

	public function notify_of_new_device() {
		global $current_user;

		get_currentuserinfo();

		$location = $this->ip_to_city( $_SERVER['REMOTE_ADDR'] );
		$blogname = html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$hostname = gethostbyaddr( $_SERVER['REMOTE_ADDR'] );

		// If we're still in the grace period, don't send an e-mail
		$installed_time = get_option( 'newdevicenotification_installedtime' );
		$send_email  = ( time() - $installed_time < (int) apply_filters( 'ndn_grace_period', $this->grace_period ) ) ? false : true;

		$send_email = apply_filters( 'ndn_send_email', $send_email, array( 'user' => $current_user, 'location' => $location, 'ip' => $_SERVER['REMOTE_ADDR'], 'hostname' => $hostname ) );

		do_action( 'ndn_notify_of_new_device', $current_user, array(
			'location' => $location,
			'send_email' => $send_email,
			'hostname' => $hostname,
		) );

		if ( ! $send_email )
			return false;

		$subject = sprintf( apply_filters( 'ndn_subject', '[%1$s] Automated security advisory: %2$s has logged in from an unknown device' ), $blogname, $current_user->display_name );

		$message = $this->get_standard_message( $current_user, array(
				'blogname'       => $blogname,
				'hostname'       => $hostname,
				'location'       => $location->human,
				'installed_time' => date( 'F jS, Y', $installed_time ),
			)
		);

		// "admin_email" plus any e-mails passed to the vip_multiple_moderators() function
		$emails = array_unique( (array) apply_filters( 'wpcom_vip_multiple_moderators', array( get_option( 'admin_email' ) ) ) );

		$emails = apply_filters( 'ndn_send_email_to', $emails );

		$headers  = 'From: "WordPress.com VIP Support" <vip-support@wordpress.com>' . "\r\n";

		// Filtering the email address instead of a boolean so we can change it if needed
		$cc_user = apply_filters( 'ndn_cc_current_user', $current_user->user_email, $current_user );
		if ( is_email( $cc_user ) )
			$headers .= 'CC: ' . $cc_user . "\r\n";

		wp_mail( $emails, $subject, $message, $headers );

		return true;
	}

	public function ip_to_city( $ip ) {
		$location = ip2location( $ip );

		$human = array();

		if ( ! empty( $location->city ) && '-' != $location->city )
			$human[] = $location->city;

		if ( ! empty( $location->region ) && '-' != $location->region && ( empty( $location->city ) || $location->region != $location->city ) )
			$human[] = $location->region;

		if ( ! empty( $location->country_long ) && '-' != $location->country_long )
			$human[] = $location->country_long;

		if ( ! empty( $human ) ) {
			$human = array_map( 'trim',       $human );
			$human = array_map( 'strtolower', $human );
			$human = array_map( 'ucwords',    $human );

			$location->human = implode( ', ', $human );
		} else {
			$location->human = 'Unknown';
		}

		return $location;
	}

	function maybe_send_email( $send_email, $user_info ) {
		if ( $this->is_user_from_valid_ip( $user_info['ip'] ) )
			$send_email = false;

		return $send_email;
	}

	function is_user_from_valid_ip( $ip ) {
		$whitelisted_ips = apply_filters( 'ndn_ip_whitelist', array() );
		if ( ! empty( $whitelisted_ips ) && in_array( $ip, $whitelisted_ips ) )
			return true;

		return false; // covers two scenarios: invalid ip or no ip whitelist
	}

	function get_standard_message( $user_obj, $args ) {
		if ( ! isset( $args['blogname'], $args['hostname'], $args['location'], $args['installed_time'] ) ) {
			return false;
		}

		$message = sprintf(
			'Hello,

This is an automated email to all %2$s site moderators to inform you that %1$s has logged into %3$s from a device that we don\'t recognize or that had last been used before %9$s when this monitoring was first enabled.

It\'s likely that %1$s simply logged in from a new web browser or computer (in which case this email can be safely ignored), but there is also a chance that their account has been compromised and someone else has logged into their account.

Here are some details about the login to help verify if it was legitimate:

WP.com Username: %8$s
IP Address: %4$s
Hostname: %5$s
Guessed Location: %6$s  (likely completely wrong for mobile devices)
Browser User Agent: %7$s

If you believe that this log in was unauthorized, please immediately reply to this e-mail and our VIP team will work with you to remove %1$s\'s access.

You should also advise %1$s to change their password immediately if you feel this log in was unauthorized:

http://support.wordpress.com/passwords/

Feel free to also reply to this e-mail if you have any questions whatsoever.

- WordPress.com VIP',
			$user_obj->display_name,                   // 1
			$args['blogname'],                         // 2
			trailingslashit( home_url() ),             // 3
			$_SERVER['REMOTE_ADDR'],                   // 4
			$args['hostname'],                         // 5
			$args['location'],                         // 6
			strip_tags( $_SERVER['HTTP_USER_AGENT'] ), // 7, strip_tags() is better than nothing
			$user_obj->user_login,                     // 8
			$args['installed_time']                    // 9, Not adjusted for timezone but close enough
		);

		return apply_filters( 'ndn_message', $message, $user_obj, $args );
	}

}

$new_device_notification = new New_Device_Notification();

