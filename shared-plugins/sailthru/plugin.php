<?php
/*
Plugin Name: Sailthru for WordPress
Plugin URI: http://sailthru.com/
Description: Add the power of Sailthru to your Wordpress set up. Deliver individualized experiences to your users in real-time by configuring Concierge and Scout. Comes bundled with a handy widget for allowing your visitors to subscribe to your lists right from your website. <strong>To get started</strong>: 1) Click the "Activate" link to the left of this description, 2) Locate your <a href="https://my.sailthru.com/login" target="_blank">Sailthru API key</a>, and 3) Go to the Sailthru configuration page, and save your API key.
Version: 1.1
Author: Sailthru
Author URI: http://sailthru.com
Author Email: nick@sailthru.com
License:

  Copyright 2013 (Sailthru)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/


if( ! defined('SAILTHRU_PLUGIN_PATH') )
	define( 'SAILTHRU_PLUGIN_PATH', plugin_dir_path(__FILE__) );

if( ! defined('SAILTHRU_PLUGIN_URL') )
	define( 'SAILTHRU_PLUGIN_URL', plugin_dir_url(__FILE__) );


/*
 * Sailthru PHP5 Developer Library
 * Source: http://getstarted.sailthru.com/developers/client-libraries/set-config-file/php5
 */
require_once( SAILTHRU_PLUGIN_PATH . 'lib/Sailthru_Util.php' );
require_once( SAILTHRU_PLUGIN_PATH . 'lib/Sailthru_Client.php' );
require_once( SAILTHRU_PLUGIN_PATH . 'lib/Sailthru_Client_Exception.php' );
require_once( SAILTHRU_PLUGIN_PATH . 'classes/class-wp-sailthru-client.php');

/*
 * Get Sailthru for Wordpress plugin classes
 */
require_once( SAILTHRU_PLUGIN_PATH . 'classes/class-sailthru-horizon.php' );
require_once( SAILTHRU_PLUGIN_PATH . 'classes/class-sailthru-concierge.php' );
require_once( SAILTHRU_PLUGIN_PATH . 'classes/class-sailthru-scout.php' );



/*
 * Sailthru for Wordpress admin view settings and registrations.
 */
require_once( SAILTHRU_PLUGIN_PATH . 'views/admin.functions.php' );

/*
 * Grab and activate the Sailthru Subscribe widget.
 */
require_once( SAILTHRU_PLUGIN_PATH . 'widget.subscribe.php' );


/*
 * Horizon handles the foundational actions like adding menus, meta tags,
 * and javascript files.
 */
if( class_exists( 'Sailthru_Horizon' ) ) {

	$sailthru_horizon = new Sailthru_Horizon();

	//if( class_exists( 'Sailthru_Concierge' ) ) {
	//	$sailthru_concierge = new Sailthru_Concierge();
	//}

	if( class_exists( 'Sailthru_Scout' ) ) {
		$sailthru_scout = new Sailthru_Scout();
	}
}


/**
 * Register hooks that are fired when the plugin is activated,
 * deactivated, and uninstalled, respectively.
 */
register_activation_hook( __FILE__, array( 'Sailthru_Horizon', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Sailthru_Horizon', 'deactivate' ) );
register_uninstall_hook(  __FILE__, array( 'Sailthru_Horizon', 'uninstall' ) );

// Add and action to handle when a user logs in
add_action('wp_login', 'sailthru_user_login', 10, 2);


function sailthru_user_login($user_login, $user) {

	if (get_option('sailthru_setup_complete')) {

		$sailthru = get_option('sailthru_setup_options');
		$api_key = $sailthru['sailthru_api_key'];
		$api_secret = $sailthru['sailthru_api_secret'];

		//$client = new Sailthru_Client( $api_key, $api_secret );
		$client = new WP_Sailthru_Client( $api_key, $api_secret);

		$id = $user->user_email;
		$options = array(
				'login' => array(
				'user_agent' => $_SERVER['HTTP_USER_AGENT'],
				'key' => 'email',
				'ip' => $_SERVER['SERVER_ADDR'],
				'site' => $_SERVER['HTTP_HOST'],
			),
			'fields' => array('keys' => 1),
		);

		try {
			if ($client) {
				$st = $client->saveUser($id, $options);
			}
		}
		catch (Sailthru_Client_Exception $e) {
			//silently fail
			return;
		}

	}

}


/*
 * If this plugin is active, override native WP email functions
 */
if( get_option('sailthru_override_wp_mail')
	  && get_option('sailthru_setup_complete')
		&& !function_exists('wp_mail') ) {

	function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {

	  // we'll be going through Sailthru so we'll handle text/html emails there already
	  // replace the <> in the reset password message link to allow the link to display.
	  // in HTML emails
	  $message = preg_replace( '#<(https?://[^*]+)>#', '$1', $message );

	  extract( apply_filters( 'wp_mail', compact( $to, $subject, $message, $headers = '', $attachments = array() ) ) );

		// recipients
		$recipients = is_array($to) ? implode(',', $to) : $to;

		// as the client library accepts these...
		$vars = array(
			'subject' => $subject,
			'body' => $message
		);

		// template
		$sailthru_configs = get_option('sailthru_setup_options');
		  $template = $sailthru_configs['sailthru_setup_email_template'];


		// SEND
		$sailthru = get_option('sailthru_setup_options');
			$api_key = $sailthru['sailthru_api_key'];
			$api_secret = $sailthru['sailthru_api_secret'];
		$client = new WP_Sailthru_Client( $api_key, $api_secret);
		$r = $client->send($template, $recipients, $vars, array());

		return true;

	}

}

