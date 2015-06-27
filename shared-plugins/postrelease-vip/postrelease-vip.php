<?php
/*
 Plugin Name: PostRelease VIP
 Plugin URI: http://www.postrelease.com
 Description: PostRelease VIP
 Version: 1.2
 Author: PostRelease
 Author URI: http://www.postrelease.com
 */
 
/*
+-----------------------------------------------------------------------------------------
| Version | Date       | Notes
|---------|-------------------------------------------------------------------------------
| 1.0     | 2012/08/01 | First release live
| 1.1     | 2012/10/05 | Returning blog URL as the blog name as fallback
| 1.2     | 2012/10/08 | Added filters for date and author (display as Promoted)
+-----------------------------------------------------------------------------------------
*/
 
define('PRX_CUSTOM_TYPE', 'pr_sponsored_post'); //custom post type created for our template post 
define('PRX_WP_PLUGIN_VERSION', '1.2');
define('PRX_DB_VERSION', 1);

require_once( dirname(__FILE__) .'/config.php' );

add_action('init', 'postrelease_init');
add_action('init', 'postrelease_handle_enable_request' );
add_action('init', 'postrelease_handle_signup_requests');
add_action('admin_init', 'postrelease_admin_init');
add_action('wp_enqueue_scripts', 'postrelease_wp_enqueue_scripts', 1);
add_action('admin_menu', 'postrelease_create_menu'); // create custom plugin settings menu
add_filter('query_vars', 'postrelease_add_query_vars');
add_action('template_redirect', 'postrelease_template_redirect');
add_action('the_author', 'postrelease_the_author');
add_filter('author_link', 'postrelease_author_link', 100, 2);
add_action('the_author_display_name', 'postrelease_the_author_display_name');
add_filter('get_the_date', 'postrelease_get_the_date', 10, 2);
add_filter('get_the_time', 'postrelease_get_the_time', 10, 2);

if (!function_exists('wpcom_is_vip')) {
	register_activation_hook( __FILE__, 'postrelease_notify_activate' );
	register_deactivation_hook( __FILE__, 'postrelease_notify_deactivate' );	
}

/**
 * Appends the PostRelease javascript to header 
 */
function postrelease_wp_enqueue_scripts() {
	//if plugin is activated and we are not in an admin page
	if ( 1 == get_option( 'prx_plugin_activated', 0 ) && ! is_admin() ) {
	    wp_register_script('postrelease', PRX_JAVASCRIPT_URL);
	    wp_enqueue_script('postrelease');
	}
}

function postrelease_init() {
	$args = array('public' => false || PRX_DEV,
				  'publicly_queryable' => true,
	              'label' => 'Sponsored Post',
	    		  'exclude_from_search' => true,
	    		  'can_export' => false,	 
	    		  'supports' => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt' )
	);
	register_post_type( PRX_CUSTOM_TYPE, $args );
}

/**
 * Function when plugin is activated.
 * In WP VIP, the activate hook is not supported.
 * The plugin is activated when the user goes to the dashboard and
 * adds his publication to the PostRelease network. 
 */
function postrelease_activate() {
	if ( ! function_exists( 'wpcom_is_vip' ) ) {
		flush_rewrite_rules(); //not vip
	} else {
		do_action( 'postrelease_activate' ); //vip
	}
}

/**
 * Reset changes caused by the plugin 
 */
function postrelease_deactivate() {
	//delete all sponsored posts
	$sponsored_posts_array = get_pages(array('post_type' => PRX_CUSTOM_TYPE));
	foreach( $sponsored_posts_array as $sponsored_post ) {
		wp_delete_post( $sponsored_post->ID, true);
	}

	delete_option('prx_template_post_id');
	delete_option('prx_plugin_activated');
	delete_option('prx_plugin_key');
	delete_option('prx_database_version');
}

/**
 * Check if ad full page is created and OK
 * If not, recreate it 
 * 
 * Returns false if the page had to be created
 * That means that either:
 * - the plugin was just installed
 * - the template page was corrupted or deleted somehow and we need to create a new one
 */
function postrelease_check_full_page() {
	$page_id = get_option('prx_template_post_id', false);
	if( ! $page_id ) {
		postrelease_create_page();
		return false;
	}
	
	$page = get_page($page_id);
	
	if($page == null || 
	   strcmp($page->post_status,'publish') != 0 || 
	   strstr($page->post_name, 'postrelease') == false ||
	   strcmp($page->post_type, PRX_CUSTOM_TYPE) != 0 ||	   
	   strcmp($page->post_title,'<span class="prx_title"></span>') != 0 || 
	   strcmp($page->post_content,'<span class="prx_body"></span>') != 0
	   ) 
	{
		postrelease_create_page();
		return false;
	}
	
	return true;
}

/**
 * Allow additional parameters in the URL
 * @param unknown_type $vars
 */
function postrelease_add_query_vars($vars) {
	$vars[] = "prx_t";
	$vars[] = "prx_rk";
	$vars[] = "prx_ro";
	$vars[] = "prx";
	return $vars;
}

/**
 * Create the ad template post 
 */
function postrelease_create_page() {
    $current_user = wp_get_current_user();
    
	$template_post = array(
	    		'post_title' => '<span class="prx_title"></span>',
	    		'post_content' => '<span class="prx_body"></span>',
	    		'post_type' => PRX_CUSTOM_TYPE,
	    		'post_status' => 'publish',
	    		'post_author' => $current_user->ID,
	    		'post_name' => 'postrelease',
				'post_date' => "1960-01-01 00:0:00",
				'post_date_gmt' => "1960-01-01 00:00:00",
	    		'comment_status' => 'closed',
				'ping_status' => 'closed',
    		);

    $template_post_id = wp_insert_post($template_post);

    //save in wordpress db so we can remove that when deactivating plugin
    update_option('prx_template_post_id', $template_post_id);
}


function postrelease_template_redirect() {
	global $wp_query;

	// functions that don't require security check
	if(isset($wp_query->query_vars['prx'])) { //prx is a URL parameter
		$function = $wp_query->query_vars['prx'];	
		
		if ($function == 'ad') {	// redirect to full ad page		
			if(get_option('prx_plugin_activated', 0) == 1) {

				//redirect to template page
				$template_post_id = get_option('prx_template_post_id', 1);

				if ( ! $template_post_id )
					return;

				$template_post_url = get_permalink($template_post_id);

				$query_struct = array();
				parse_str($_SERVER['QUERY_STRING'], $query_struct);
				$template_post_url = add_query_arg( $query_struct, $template_post_url ); //keeping URL parameters when we redirect (prx_t and prx_rk need to stay in the URL)				
				$template_post_url = add_query_arg( 'prx', 'page', $template_post_url ); //prx=page so that we do not get redirected anymore
				wp_redirect( $template_post_url );
				
				exit;
			}
		}
	}	
}

/**
 * Security check - validate key 
 * @param string $key security MD5 encoded
 */
function postrelease_is_key_valid($key) {
	if( false != get_option('prx_plugin_key', false) ) { //there is a key saved here
		$md5_key = md5(get_option('prx_plugin_key'));
		if(strcmp($md5_key,$key) === 0) {
			return true;
		}
	}
	
	return false;
}

 /**
  * Get client's IP
  * PostRelease will use this for geotargetting
  */
 function postrelease_get_client_IP() { 
    return $_SERVER['REMOTE_ADDR'];
}

/**
 * Authenticate if IP is coming from localhost or from a PostRelease server 
 */
function postrelease_authenticate_IP(){
	$ip = postrelease_get_client_IP();
	if (strlen($ip) == 0){
		$ip = getenv("REMOTE_ADDR");
	} else {
		$ip = trim($ip);
	}

	$args = array(
		'method' => 'GET',
		'timeout' => 2,
		'user-agent' => 'Wordpress_plugin',
		'sslverify' => true,
	);
	$response = wp_remote_get( PRX_POSTRELEASE_SERVER.'/plugins/Api/AuthenticateIP?ip=' . $ip, $args );
	if ( is_wp_error( $response ) ) {
		return false;		
	} else {
		$body = wp_remote_retrieve_body($response);		
		$obj = json_decode($body);
		return isset( $obj->{'result'} ) && 1 == $obj->{'result'};
	}
}

/**
 * Creates an entry in the admin menu for
 * Post Release settings 
 */
function postrelease_create_menu() {
	// Add PostRelease menu item under the "Settings" top-level menu
	$page = add_submenu_page('options-general.php','PostRelease Dashboard', 'PostRelease', 'manage_options', 'postrelease', 'postrelease_settings_page');
}

/**
 * Opens iframe to http://www.postrelease.com
 * User can check his PostRelease publication dashboard
 * and Edit the template of his publication.
 * All of this is done inside the iframe.
 */
function postrelease_settings_page() {
	$dashboard_path = PRX_POSTRELEASE_SERVER . '/wpplugin/Index/?PublicationUrl=' . urlencode( home_url( '/' , 'http') ) . '&vip=1';
	echo '<center><iframe src="'. esc_url( $dashboard_path ) .'" style="margin: 0 auto;" width="700px" height="900px" frameborder="0" scrolling="no"></iframe></center>';
}

/**
 * Check if needs to run upgrade routine 
 */
function postrelease_admin_init() {
	$current_db_version = get_option('prx_database_version', 0);
	if ($current_db_version < PRX_DB_VERSION) {
		postrelease_upgrade(PRX_DB_VERSION);
		update_option('prx_database_version', PRX_DB_VERSION);
	}
}

/**
 * Upgrade routine
 */
function postrelease_upgrade($current_db_version) {
	//create template post
	if ($current_db_version <= 1) {
		postrelease_check_full_page();
	}
	
	//for any future modifications in the database we can add more if()'s here...
}

function postrelease_handle_signup_requests() {
	if(isset($_REQUEST['prx'])) { //prx is a URL parameter
		$function = $_REQUEST['prx'];	

		if($function == 'prx_generate_key') { 
			postrelease_generate_security_key();
			exit;
		} 
		
		//security check, block any requests that do not have the security key
		$security_check_ok = false || PRX_DEV;

		if(isset($_REQUEST['id'])) { //there is a key saved here
			$key_url = sanitize_text_field( $_REQUEST['id'] );
			$security_check_ok = postrelease_is_key_valid( $key_url );
		}		

		// these functions require security check to be called
		if( $security_check_ok && postrelease_authenticate_IP() ) {

			if($function == 'enable') { // enable plugin during sign up process
				if ( isset( $_REQUEST['status'] ) )
					postrelease_enable_plugin( sanitize_text_field( $_REQUEST['status'] ) );
			} else if ($function == 'getposts') { //get latest posts so that our server can index blog's content
				postrelease_get_posts_xml_for_indexing();
			} else if($function == 'status') {
				postrelease_get_status();
			} else if ($function == 'check') {
				postrelease_get_check();
			}
			exit;
		}
	}
}

function postrelease_handle_enable_request() {
	if( isset( $_GET['do'] ) && 'update' == $_GET['do']
	   && isset( $_GET['postrelease_enable'] ) && '1' == $_GET['postrelease_enable'] ) {
		postrelease_send_success_response();
		exit;
	}
}

/**
 * This code returns a XML that follows the schema
 * specified in PostRelease API documentation.
 * This XML will be used to hydrate our indexing system that is necessary to
 * suggest what are the best publications for an ad.
 */
function postrelease_get_posts_xml_for_indexing() {
	$default_number_posts = 200; //by default return 200 posts
		
	//sanitizing num URL parameter
	$number_posts = isset( $_REQUEST['num'] ) ? intval( $_REQUEST['num'] ) : 0;
	
	if( 0 > $number_posts || 200 < $number_posts ) {
		$number_posts = $default_number_posts;
	}
		
	$args = array( 'numberposts' => $number_posts );
	$posts = get_posts( $args );
		
	$xml = '<?xml version="1.0" encoding="UTF-8" ?>';
	$xml .= "<articles>";
	foreach($posts as $p) {
		$xml .= "<article>";
		$xml .= "<id>$p->ID</id>";
		$xml .= "<link><![CDATA[$p->guid]]></link>";
		$xml .= "<title><![CDATA[$p->post_title]]></title>";
		$xml .= "<content><![CDATA[$p->post_content]]></content>";
		$xml .= "</article>";
	}
	$xml .= "</articles>";
	echo ($xml);
		
}

/**
 * Does nothing and just returns a result=1 json
 * This is called by sign up process (always returns 1)
 * The plugin will be enabled with the prx=enable call (function postrelease_enable_plugin)
 * We need to maintain this call because it's part of server sign up process
 */		
function postrelease_send_success_response() {
	if (preg_match('/\W/', $_REQUEST['callback'])) {
		header('HTTP/1.1 400 Bad Request');
		exit();
	}
	header('Cache-Control: no-cache, must-revalidate');
	header('Content-type: application/javascript; charset=utf-8');
	$data = array('result' => 1);
	print sprintf('%s(%s);', $_REQUEST['callback'], json_encode($data));
}

/**
 * Generates a random security key that will be saved both
 * on the blog side as well as on the PostRelease side
 * This key will be necessary to make calls to the plugin during the sign up
 * process and later for indexing purposes
 */
function postrelease_generate_security_key() {
	//if key already exists
	if( false != get_option('prx_plugin_key', false) ) { 
		print('FAIL: key already exists');
		return;
	}

	if( postrelease_authenticate_IP() ) {
		$key = wp_generate_password( 10, false, false );
		update_option('prx_plugin_key', $key);
		$data['Key'] = $key;
		$output = json_encode($data);
		print($output);
	} else {
		print('FAIL: IP authentication error');
	}
}

/**
 * Returns information about the blog
 * This is called by the PostRelease server during 
 * sign up 
 */
function postrelease_get_status() {
	$response = array();
	$response['PublicationTitle'] = postrelease_get_blog_name();
	$response['Enabled'] = get_option('prx_plugin_activated');
	$response['PlatformVersion'] = get_bloginfo('version');
	$response['PluginVersion'] = PRX_WP_PLUGIN_VERSION.'.vip';
	$output = json_encode($response);
	print($output);
}

/**
 * Returns detailed information about the blog 
 */
function postrelease_get_check() {
	$response = array();
	$response['plugin_activated'] = get_option('prx_plugin_activated');
	$response['site_url'] = site_url();
	$response['plugin_version'] = PRX_WP_PLUGIN_VERSION.'.vip';
	$response['postrelease_server'] = PRX_POSTRELEASE_SERVER;
	$response['javascript_url'] = PRX_JAVASCRIPT_URL;
	$response['db_version'] = PRX_DB_VERSION;
	$response['php_version'] = phpversion();
	$response['wordpress_version'] = get_bloginfo('version');
	$response['blog_title'] = postrelease_get_blog_name();
	$response['template_post_id'] = get_option('prx_template_post_id', '-1');
	$response['prx_dev'] = PRX_DEV;
	$output = json_encode($response);
	print($output);
}

/**
 * Enable or Disable the plugin
 * This basically updates the WP option prx_plugin_activated
 * This function is called by the PostRelease server during 
 * sign up
 */
function postrelease_enable_plugin( $enable ) {
	$enable = sanitize_text_field( $enable );
	$data['result'] = 0;

	if( $enable == '1' || $enable == '0' ) {
		//plugin activated changed
		if( get_option('prx_plugin_activated') != (int) $enable ) {
			if($enable == '0') {
				postrelease_deactivate();
			} else if($enable == '1') {
				postrelease_activate();
			}
		}

		update_option( 'prx_plugin_activated', (int) $enable );
		$data['result'] = 1;
	}
	print json_encode($data);
}

/**
 * Return the blog name.
 * If it's not set, return the blog URL. 
 */
function postrelease_get_blog_name() {
	if(strlen(trim(get_bloginfo('name'))) == 0) {
		return site_url();
	} else {
		return get_bloginfo('name');	
	}
}

/**
 * Change author display name to "Promoted" in secondary impression
 */
function postrelease_the_author($author) {
	global $post;
	$template_post_id = get_option('prx_template_post_id');
	if(isset($post) && $post->ID == get_option('prx_template_post_id', -1)) {
		$author = 'Promoted';
	}
	return $author;
}

/**
 * Change author display name to "Promoted" in secondary impression 
 */
function postrelease_the_author_display_name($author) {
	global $post;
	$template_post_id = get_option('prx_template_post_id');
	if(isset($post) && $post->ID == get_option('prx_template_post_id', -1)) {
		$author = 'Promoted';
	}
	return $author;
}

/**
 * Change author link to blog homepage URL in secondary impression
 */
function postrelease_author_link($link, $author_id) {
	global $post;
	if(isset($post) && $post->ID == get_option('prx_template_post_id', -1)) {
		$link = home_url();
	}
	return $link;
}

/**
 * Change date to today's date in secondary impression
 * We do not want to display that fake date of 1/1/1960
 */
function postrelease_get_the_date($date, $d) {	
	global $post;
	if($post->ID == get_option('prx_template_post_id', -1)) {
		$date = date($d, time());		
	}
	return $date;
}

/**
 * Change date to today's date in secondary impression
 * We do not want to display that fake date of 1/1/1960
 */
function postrelease_get_the_time($the_time, $d) {
	global $post;
	if($post->ID == get_option('prx_template_post_id', -1)) {
		$the_time = date($d, time());	
	}
	return $the_time;	
}

/**
 * Notify the PostRelease server that the plugin in this publication was activated 
 */
function postrelease_notify_activate() {
	//notify PostRelease team that plugin was activated
	$args = array(
		'method' => 'POST',
		'timeout' => 2,
		'user-agent' => 'Wordpress_plugin',
		'sslverify' => true,
	);
	$response = wp_remote_post( PRX_POSTRELEASE_SERVER . '/plugins/Api/PluginActivated?url=' . site_url(), $args );		
}

/**
 * Notify the PostRelease server that the plugin in this publication was deactivated
 */
function postrelease_notify_deactivate() {
	//notify PostRelease team that plugin was deactivated
	$args = array(
		'method' => 'POST',
		'timeout' => 2,
		'user-agent' => 'Wordpress_plugin',
		'sslverify' => true,
	);
	$response = wp_remote_post( PRX_POSTRELEASE_SERVER . '/plugins/Api/PluginDeactivated?url=' . site_url(), $args );		
}
