<?php

/**
 * Class New_Device_Notification_WPCom
 *
 * Customisations for the WordPress.com platform.
 */
class New_Device_Notification_WPCom {

	/**
	 * New_Device_Notification_WPCom constructor.
	 */
	public function __construct() {
		add_filter( 'ndn_location',       array( $this, 'filter_ndn_location' ) );
		add_filter( 'ndn_message',        array( $this, 'filter_ndn_message' ), 10, 3 );
		add_filter( 'ndn_headers',        array( $this, 'filter_ndn_headers' ), 10, 1 );
		add_filter( 'ndn_cookie_domains', array( $this, 'filter_ndn_cookie_domains' ) );
	}

	/**
	 * Get the singleton instance of this class
	 *
	 * @return New_Device_Notification_VIP_Go
	 */
	static public function instance() {
		static $instance = false;
		if ( ! $instance ) {
			$instance = new New_Device_Notification_WPCom();
		}
		return $instance;
	}

	/**
	 * Hooks the `ndn_location` filter to supply the location
	 * of the current remote address.
	 *
	 * @param object $location A stdClass object, detailing a location (possibly unknown)
	 *
	 * @return object A stdClass object, detailing the location of the remote address
	 */
	public function filter_ndn_location( $location ) {
		$ip = $_SERVER['REMOTE_ADDR'];

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

			$location->human  = implode( ', ', $human );
			$location->human .= ' (likely completely wrong for mobile devices)';
		} else {
			$location->human = 'Unknown';
		}

		return $location;
	}

	public function filter_ndn_headers( $headers ) {
		// Emails sent from WordPress.com will need to have the FROM header set, otherwise it will show up as "(unknown sender)"
		$headers .= 'From: "WordPress.com VIP Support" <vip-support@wordpress.com>' . "\r\n";
		return $headers;
	}

	public function filter_ndn_message( $message, $user_obj, $args ) {
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
		return $message;
	}

	/**
	 * Hooks the `ndn_cookie_domains` filter to add
	 * the WordPress.com domain
	 *
	 * @param $cookie_domains
	 *
	 * @return array An array of domains to set cookies on
	 */
	public function filter_ndn_cookie_domains( $cookie_domains ) {
		$cookie_domains[] = 'wordpress.com';
		return $cookie_domains;
	}

}

New_Device_Notification_WPCom::instance();
