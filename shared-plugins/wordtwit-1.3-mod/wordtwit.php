<?php
/*
Plugin Name: WordTwit
Plugin URI: http://www.bravenewcode.com/wordtwit
Description: Generates Twitter Updates from Blog Postings
Author: <a href="http://www.bravenewcode.com">Duane Storey and Dale Mugford, BraveNewCode Inc.</a>, Modified and extended for WordPress.com by Thorsten Ott
Version: 1.3-mod
*/
/*
Reason for Mod:

As newer versions of WordTwit introduce database alterations this version was created to implement various feature enhancements and modifications.

Changes to original WordTwit version 1.3:

Added two new checkmarks in plugin configuration
- user_override enables User/Blog based inputs which are stored in the blog options
- user_preference setting controls if the plugin should fall back to the general options in case the user did not provide any settings. 
The user preferences can be done in the Profile page of the user. 
Implemented functionality to encrypt twitter access data
Added bit.ly and wp.me url shorteners
Added some optimizations to ensure that posts are not tweeted twice
Added threshold option to avoid tweeting out old posts

Usage:

This plugin can all enabled in your WordPress theme by adding this line to your theme's functions.php

require_once( WP_CONTENT_DIR . '/themes/vip/plugins/wordtwit-1.3-mod/wordtwit.php' );

Advanced usage:

You can use the following filters to change the twitter message to your needs.

$message = apply_filters( 'wordtwit_pre_proc_message', $message, $post->ID );

this filter is executed before the message string is parsed and replacements for [title] and [link] are done

$message = apply_filters( 'wordtwit_post_proc_message', $message, $post->ID );

is applied after the replacements for [title] and [link] are processed.

The example use case for this filters shown below adds a twitter username based on a mapping array.

add_filter( 'wordtwit_pre_proc_message', 'adjust_twit_msg', 10, 2 );

$twitter_usernames = array( 1234567 => 'twitteruser' ); // wordpress.com userid => twitter user

function adjust_twit_msg( $message, $post_id ) {
	global $twitter_usernames;
	$short_url = get_post_meta($post_id, 'short_url', true);
	if ( !empty( $short_url ) )
		$message = str_replace( '[link]', $short_url, $message );
	$post = get_post( $post_id );
	if ( isset( $twitter_usernames[ $post->post_author ] ) )
		$message = str_replace( '[twitname]', '@' . $twitter_usernames[ $post->post_author ], $message );
	else
		$message = str_replace( '[twitname]', '', $message );
	return $message;
}

If you want to limit the posts for which updates are sent you can do this by attaching a function to the following filter:

$post_update = apply_filters( 'wordtwit_post_update', true, $post_id );

In this way you could simply limit your updates on posts with a certain category, tag or author by attaching a function that returns false if the posting condition is not meet.
*/


// Some ideas taken from http://twitter.slawcup.com/twitter.class.phps

require_once( 'xml.php' );
require_once( 'twitter_oauth/twitteroauth.php' );

$twit_plugin_name = 'WordTwit';
$twit_plugin_prefix = 'wordtwit_';
$wordtwit_version = '1.3-mod';

define( 'WORDTWIT_CONSUMER_KEY', '10vBDgzOLPzKxk8lKFNtyA' );
define( 'WORDTWIT_CONSUMER_SECRET', 'u7cZaOr69ox8AioB09mLJUeeEWO5w6FcrcW8wOmTTM' );

// set up hooks for WordPress
add_action( 'publish_post', 'post_now_published' );
add_action( 'admin_head', 'wordtwit_admin_css' );

if ( true == get_option( $twit_plugin_prefix . 'user_override' ) ) {
	add_action( 'show_user_profile', 'twit_show_user_profile', 10, 1 );
	add_action( 'edit_user_profile', 'twit_edit_user_profile', 10, 1 );
	add_action( 'personal_options_update', 'twit_personal_options_update', 10, 1);
	add_action( 'edit_user_profile_update', 'twit_edit_user_profile_update', 10, 1);
}


/*
 * make sure to delete passwords
 */
add_action( 'init', 'twit_delete_passwords' );
function twit_delete_passwords() {
	global $wpdb, $twit_plugin_prefix;
	if ( empty( $wpdb->blogid ) )
		return;
	
	$encryption_status = get_option( $twit_plugin_prefix . 'encryption_status' );
	if ( 'delete' == $encryption_status )
		return;
	else if ( 'intermediate' != $encryption_status ) {
		update_option( $twit_plugin_prefix . 'encryption_status', 'intermediate' );
		
		$user_options = get_option($twit_plugin_prefix . 'user_options');
		if( is_array( $user_options) && !empty( $user_options) ) {
			foreach( $user_options as $key => $values ) {
				if ( isset( $values['twitter_password'] ) ) 
					unset( $user_options[$key]['twitter_password'] );
				if ( isset( $values['twitter_username'] ) ) 
					unset( $user_options[$key]['twitter_username'] );
			}
			update_option( $twit_plugin_prefix . 'user_options', $user_options);
		}
		
		delete_option( $twit_plugin_prefix . 'password', 0 );
		
		update_option( $twit_plugin_prefix . 'encryption_status', 'delete' );
	}
}


function twit_show_user_profile( $user ) {
	global $wpdb, $twit_plugin_prefix;
	if ( empty( $wpdb->blogid ) )
		return;

	$user_options = get_option($twit_plugin_prefix . 'user_options');
   
	if ( isset( $user_options[ $user->ID ] ) ) {
		$twit_options = $user_options[ $user->ID ];
		$twitter_message = $twit_options[ 'twitter_message' ];
		$oauth_token = $twit_options[ 'oauth_token' ];
		$oauth_token_secret = $twit_options[ 'oauth_token_secret' ];
	} else {
		$twitter_message = $oauth_token = $oauth_token_secret = '';
	}
	
	
	
	// get new authorization
	if ( isset( $_POST['reauthorize'] ) ) {
		if ( $_POST['reauthorize'] == 'Connect to Twitter account' ) {
			$twit_connection = new TwitterOAuth( WORDTWIT_CONSUMER_KEY, WORDTWIT_CONSUMER_SECRET );
			$request_token = $twit_connection->getRequestToken( admin_url( 'users.php?page=grofiles-user-settings' ) );
			$redirect_url = $twit_connection->getAuthorizeURL( $request_token, FALSE );
			
			// save token values
			$user_options[ $user->ID ]['oauth_token'] = $request_token['oauth_token'];
			$user_options[ $user->ID ]['oauth_token_secret'] = $request_token['oauth_token_secret'];
			update_option( $twit_plugin_prefix . 'user_options', $user_options );

			wp_redirect( $redirect_url );
			exit;
		}
	} 
	
	$oauth_token = $user_options[ $user->ID ]['oauth_token'];
	$oauth_token_secret = $user_options[ $user->ID ]['oauth_token_secret'];
	$twitter_status = 'not authorized';
	
	// handle callback of authorization
	if ( isset( $_GET['oauth_token'] ) && isset( $_GET['oauth_verifier'] ) && $oauth_token == $_GET['oauth_token'] ) {		
		// open new connection
		$twit_connection = new TwitterOAuth( WORDTWIT_CONSUMER_KEY, WORDTWIT_CONSUMER_SECRET, $oauth_token, $oauth_token_secret );
		// get access token
		$token_credentials = $twit_connection->getAccessToken( $_GET['oauth_verifier'] );
		// reconnect with new credentials 
		$twit_connection = new TwitterOAuth( WORDTWIT_CONSUMER_KEY, WORDTWIT_CONSUMER_SECRET, $token_credentials['oauth_token'], $token_credentials['oauth_token_secret'] );
		$content = $twit_connection->get( 'account/verify_credentials' );
		if ( is_object( $content ) && !empty( $content->screen_name ) ) {
			$screen_name = $content->screen_name;
			$twitter_status = 'authorized to ' . $screen_name;
			$user_options[ $user->ID ]['oauth_token'] = $token_credentials['oauth_token'];
			$user_options[ $user->ID ]['oauth_token_secret'] = $token_credentials['oauth_token_secret'];
			update_option( $twit_plugin_prefix . 'user_options', $user_options );
		}
		// set authorized flag
	} else {
		$twit_connection = new TwitterOAuth( WORDTWIT_CONSUMER_KEY, WORDTWIT_CONSUMER_SECRET, $oauth_token, $oauth_token_secret );
		$content = $twit_connection->get( 'account/verify_credentials' );
		if ( is_object( $content ) && !empty( $content->screen_name ) ) {
			$screen_name = $content->screen_name;
			$twitter_status = 'authorized to ' . $screen_name;
		}
	}
	
	?>
	
	<div class="section-info">
	<h3>WordTwit Twitter Info</h3>
		WordTwit allows you to publish a Twitter tweet whenever a new blog entry is published.	To enable it, simply authorize your Twitter account.<br /><br />You can also customize the message Twitter posts to your account by using the "message" field below.	You can use [title] to represent the title of the blog entry, and [link] to represent the permalink.
		<br /><br /><b>Note:</b> These options are stored on a per blog basis to allow different settings for each of your blogs.<br /><br />
	</div>
																													   
	<table class="form-table">
		<tr>
			<th>Authorization</th>
			<td><label for="reauthorize"><input type="submit" name="reauthorize" value="Connect to Twitter account" /> current status: <?php echo esc_html( $twitter_status ); ?></label></td>
		</tr>
		<tr>
			<th><label for="twitter_message">Twitter Message</label></th>
			<td><input type="text" name="twitter_message" id="twitter_message" value="<?php echo esc_attr($twitter_message) ?>" class="regular-text" /></td>
		</tr>
	</table>											   
	<?php
}

function twit_edit_user_profile( $user ) {
	twit_show_user_profile( $user );
}

function twit_personal_options_update( $user_id ) {
	global $wpdb, $twit_plugin_prefix;

	if ( empty( $wpdb->blogid ) )
		return;
	
	$user_options = get_option($twit_plugin_prefix . 'user_options');
	
	$user_options[ $user_id ]['twitter_message'] = $_POST[ 'twitter_message' ];

	update_option( $twit_plugin_prefix . 'user_options', $user_options );
}

function twit_edit_user_profile_update( $user_id ) {
	twit_personal_options_update( $user_id );
}


function twit_hit_server( $location, $username, $password, &$output, $post = false, $post_fields = '' ) {
   global $wordtwit_version;
   $output = '';
   
   $args = array(
	  'user-agent' => 'WordTwit ' . $wordtwit_version,
	  'headers' => array(),
   );

   if ( $username ) {
	  $args['headers']['Authorization'] = 'Basic ' . base64_encode( $username . ':' . $password );
   }

   if ( $post ) {
	  $args['body'] = $post_fields;
	  $response = wp_remote_get( $location, $args );
	  return '200' == wp_remote_retrieve_response_code( $response );
   } else {
	  $response = wp_remote_get( $location, $args );
	  $output = wp_remote_retrieve_body( $response );

	  return '200' == wp_remote_retrieve_response_code( $response );
   }
}

function twit_update_status( $oauth_token, $oauth_token_secret, $new_status ) {
	$output = '';
	$twit_connection = new TwitterOAuth( WORDTWIT_CONSUMER_KEY, WORDTWIT_CONSUMER_SECRET, $oauth_token, $oauth_token_secret );
	$content = $twit_connection->get( 'account/verify_credentials' );
	if ( is_object( $content ) && !empty( $content->screen_name ) ) {
		$result = $twit_connection->post( 'statuses/update', array( 'status' => $new_status, 'source' => 'wordtwit' ) );
		if ( is_object( $result ) && !empty( $result->id ) )
			return true;
		return false;
	}
	return false;
}

function twit_get_tiny_url( $link ) {
   $output = '';
   $result = twit_hit_server( 'http://tinyurl.com/api-create.php?url=' . $link, '', '', $output );

   return $output;
}

function twit_get_bitly_url( $link ) {
	global $twit_plugin_prefix;
	$bitly_user_name = get_option( $twit_plugin_prefix . 'bitly_user_name' );
	$bitly_api_key = get_option( $twit_plugin_prefix . 'bitly_api_key' );
	$output = false;
	$result = twit_hit_server( 'http://api.bit.ly/shorten?version=2.0.1&longUrl=' . urlencode( $link ) . '&format=xml&login=' . $bitly_user_name . '&apiKey=' . $bitly_api_key, '', '', $output );
	preg_match( '#<shortUrl>(.*)</shortUrl>#iUs', $output, $url );

	if ( isset( $url[1] ) ) {
		return $url[1];	
	} else {
		return $link;
	}
}

// workaround for local tests
if ( !function_exists( 'get_shortlink' ) ) {
	function get_shortlink( $post_id ) {
		$post = get_post( $post_id );
		return $post->guid;
	}
}

function post_now_published( $post_id ) {
	global $twit_plugin_prefix, $post;

	if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING )
		return;

	$post_update = apply_filters( 'wordtwit_post_update', true, $post_id );
	
	$max_age = get_option( $twit_plugin_prefix . 'max_age', 0 );
	if ( $max_age > 0 && ( ( current_time('timestamp', 1 ) - get_post_time( 'U', true, $post_id ) ) / 3600 ) > $max_age ) {
		return;
	}

	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		// Dear VIPs, Don't ever do this.
		$srtm = $GLOBALS['wpdb']->srtm;
		$GLOBALS['wpdb']->send_reads_to_masters();
		$nmc = isset( $_GET['nomemcache'] ) ? $_GET['nomemcache'] : null;
		$_GET['nomemcache'] = 'all';
	}

	$has_been_twittered = get_post_meta( $post_id, 'has_been_twittered', true );
	if ( 'yes' == $has_been_twittered ) {
		return;
	}

	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		$GLOBALS['wpdb']->srtm = $srtm;
		if ( is_null( $nmc ) ) {
			unset( $_GET['nomemcache'] );
		} else {
			$_GET['nomemcache'] = $nmc;
		}
	}
	
	add_post_meta( $post_id, 'has_been_twittered', 'yes' );

	if ( true == $post_update && !($has_been_twittered == 'yes')) {
		 global $post;
		 $post = get_post( $post_id );
		 setup_postdata( $post );

			$i = 'New blog entry \'' . the_title('','',false) . '\' - ' . get_permalink();

			$user_override = get_option( $twit_plugin_prefix . 'user_override' );
			$user_preference = get_option( $twit_plugin_prefix . 'user_preference' );
			$user_options = get_option( $twit_plugin_prefix . 'user_options' );

			// get user settings
			if ( $user_override && isset( $user_options[ $post->post_author ] ) ) {
				$twit_options = $user_options[ $post->post_author ];
				$oauth_token =  $twit_options[ 'oauth_token' ];
				$oauth_token_secret =  $twit_options[ 'oauth_token_secret' ];
				$message = $twit_options[ 'twitter_message' ];
				
				if ( empty( $message ) ) {
					$message = get_option( $twit_plugin_prefix . 'message' );
				}
			
			}

			// no user settings available or allowed then use global settings 
			if ( ( ( empty( $oauth_token ) || empty( $oauth_token_secret ) ) && false == $user_preference )
				 || false == $user_override ) {
					$message = get_option( $twit_plugin_prefix . 'message' );
					$oauth_token = get_option( $twit_plugin_prefix . 'oauth_token' );
					$oauth_token_secret = get_option( $twit_plugin_prefix . 'oauth_token_secret' );
			}

			if ( empty( $oauth_token ) || empty( $oauth_token_secret ) ) {
				update_post_meta( $post_id, 'wordtwit_fail', 'no-token' );
				return;
			}
			
			$message = apply_filters( 'wordtwit_pre_proc_message', $message, $post->ID );
			
			$message = str_replace( '[title]', $post->post_title, $message );
			
			$wordtwit_url_type = get_option( $twit_plugin_prefix . 'wordtwit_url_type' );
			
			if ( strstr( $message, "[link]" ) ) {
				if( 'tinyurl' == $wordtwit_url_type || empty( $wordtwit_url_type ) )
					$message = str_replace( '[link]', twit_get_tiny_url( get_permalink() ), $message );
				elseif( 'bitly' == $wordtwit_url_type )
					$message = str_replace( '[link]', twit_get_bitly_url( get_permalink() ), $message );
				elseif( 'wpme' == $wordtwit_url_type )
					$message = str_replace( '[link]', wp_get_shortlink( $post->ID ), $message );
			}
			
			$message = apply_filters( 'wordtwit_post_proc_message', $message, $post->ID );
			
			$update_status = twit_update_status( $oauth_token, $oauth_token_secret, $message );

			if ( ! $update_status )
				update_post_meta( $post_id, 'wordtwit_fail', 'api-fail' );

		 wp_reset_postdata();
	}
}

function wordtwit_admin_css() {
	$url = get_bloginfo('wpurl');
	echo '<link rel="stylesheet" type="text/css" href="' . esc_url( get_bloginfo('wpurl') ) . '/wp-content/themes/vip/plugins/wordtwit-1.3-mod/css/admin.css" />';
}

function wordtwit_plugin_url( $str = '' ) {
	$dir_name = '/wp-content/themes/vip/plugins/wordtwit-1.3-mod';
	echo esc_url($dir_name . $str);
}

function bnc_stripslashes_deep( $value ) {
	$value = is_array($value) ?
   array_map('bnc_stripslashes_deep', $value) :
   stripslashes($value);
	return $value;
}

function twit_options_reauthorize() {
   global $twit_plugin_prefix;
   // get new authorization
	if ( isset( $_POST['reauthorize'] ) ) {
		if ( $_POST['reauthorize'] == 'Connect to Twitter account' ) {
			$twit_connection = new TwitterOAuth( WORDTWIT_CONSUMER_KEY, WORDTWIT_CONSUMER_SECRET );
			$request_token = $twit_connection->getRequestToken( admin_url( 'options-general.php?page=wordtwit.php' ) );
			$redirect_url = $twit_connection->getAuthorizeURL( $request_token, FALSE );
			
			// save token values
			update_option( $twit_plugin_prefix . 'oauth_token', $request_token['oauth_token'] );
			update_option( $twit_plugin_prefix . 'oauth_token_secret', $request_token['oauth_token_secret'] );
			
			wp_redirect( $redirect_url );
			exit;
		}
	} 
}

function wordtwit_options_subpanel() {
	if (get_magic_quotes_gpc()) {
		$_POST = array_map( 'bnc_stripslashes_deep', $_POST );
		$_GET = array_map( 'bnc_stripslashes_deep', $_GET );
		$_COOKIE = array_map( 'bnc_stripslashes_deep', $_COOKIE );
		$_REQUEST = array_map( 'bnc_stripslashes_deep', $_REQUEST );
	}

	global $twit_plugin_name;
	global $twit_plugin_prefix;

  	if (isset($_POST['info_update'])) {
		if (isset($_POST['message'])) {
			$message = $_POST['message'];
		} else {
			$message = '';
		}

		if (isset($_POST['user_override'])) {
			$user_override = ( $_POST['user_override'] == "true" ) ? true : false;
		} else {
			$user_override = false;
		}

		if (isset($_POST['user_preference'])) {
			$user_preference = ( $_POST['user_preference'] == "true" ) ? true : false;
		} else {
			$user_preference = false;
		}
		
		if (isset($_POST['wordtwit_url_type'])) {
			$wordtwit_url_type = ( in_array( $_POST['wordtwit_url_type'], array( 'bitly', 'tinyurl', 'wpme' ) ) ) ? $_POST['wordtwit_url_type'] : 'wpme';
			
		} else {
			$wordtwit_url_type = 'wpme';
		}
		
		if ( 'bitly' === $wordtwit_url_type ) {
			if( isset( $_POST['bitly_user_name'] ) && isset( $_POST['bitly_api_key'] ) ) {
				$bitly_user_name = $_POST['bitly_user_name'];
				$bitly_api_key = $_POST['bitly_api_key'];
			}	
		}
		
		if (isset($_POST['max_age'])) {
			$max_age = (int) $_POST['max_age'];
		} else {
			$max_age = 24;
		}

		update_option( $twit_plugin_prefix . 'message', stripslashes($message) );
		update_option( $twit_plugin_prefix . 'user_override', $user_override );
		update_option( $twit_plugin_prefix . 'user_preference', $user_preference );
		update_option( $twit_plugin_prefix . 'max_age', $max_age );
		update_option( $twit_plugin_prefix . 'wordtwit_url_type', $wordtwit_url_type );
		update_option( $twit_plugin_prefix . 'bitly_user_name', $bitly_user_name );
		update_option( $twit_plugin_prefix . 'bitly_api_key', $bitly_api_key );
	}
	
	$oauth_token = get_option( $twit_plugin_prefix . 'oauth_token' );
	$oauth_token_secret = get_option( $twit_plugin_prefix . 'oauth_token_secret' );
	$twitter_status = 'not authorized';
	
	// handle callback of authorization
	if ( isset( $_GET['oauth_token'] ) && isset( $_GET['oauth_verifier'] ) && $oauth_token == $_GET['oauth_token'] ) {		
		// open new connection
		$twit_connection = new TwitterOAuth( WORDTWIT_CONSUMER_KEY, WORDTWIT_CONSUMER_SECRET, $oauth_token, $oauth_token_secret );
		// get access token
		$token_credentials = $twit_connection->getAccessToken( $_GET['oauth_verifier'] );
		// reconnect with new credentials 
		$twit_connection = new TwitterOAuth( WORDTWIT_CONSUMER_KEY, WORDTWIT_CONSUMER_SECRET, $token_credentials['oauth_token'], $token_credentials['oauth_token_secret'] );
		$content = $twit_connection->get( 'account/verify_credentials' );
		if ( is_object( $content ) && !empty( $content->screen_name ) ) {
			$screen_name = $content->screen_name;
			$twitter_status = 'authorized to ' . $screen_name;
			update_option( $twit_plugin_prefix . 'oauth_token', $token_credentials['oauth_token'] );
			update_option( $twit_plugin_prefix . 'oauth_token_secret', $token_credentials['oauth_token_secret'] );
		}
		// set authorized flag
	} else {
		$twit_connection = new TwitterOAuth( WORDTWIT_CONSUMER_KEY, WORDTWIT_CONSUMER_SECRET, $oauth_token, $oauth_token_secret );
		$content = $twit_connection->get( 'account/verify_credentials' );
		if ( is_object( $content ) && !empty( $content->screen_name ) ) {
			$screen_name = $content->screen_name;
			$twitter_status = 'authorized to ' . $screen_name;
		}
	}
	$message = get_option($twit_plugin_prefix . 'message');	
	$user_override = get_option( $twit_plugin_prefix . 'user_override' );
	$user_preference = get_option( $twit_plugin_prefix . 'user_preference' );
	$max_age = get_option( $twit_plugin_prefix . 'max_age' );
	$wordtwit_url_type = get_option( $twit_plugin_prefix . 'wordtwit_url_type' );
	$bitly_user_name = get_option( $twit_plugin_prefix . 'bitly_user_name' );
	$bitly_api_key = get_option( $twit_plugin_prefix . 'bitly_api_key' );
	
	
	if (strlen($message) == 0) {
		$message = 'New Blog Entry, "[title]" - [link]'; 
		update_option($twit_plugin_prefix . 'message', $message);
	}

   include( 'html/options.php' );
}

function wordtwit_add_plugin_option() {
	global $twit_plugin_name;
	if (function_exists('add_options_page')) {
		$hook = add_options_page($twit_plugin_name, $twit_plugin_name, 'manage_options', basename(__FILE__), 'wordtwit_options_subpanel');
		add_action( 'load-' . $hook, 'twit_options_reauthorize' );
   }	
}

add_action('admin_menu', 'wordtwit_add_plugin_option');
