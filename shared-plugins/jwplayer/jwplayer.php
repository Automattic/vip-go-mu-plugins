<?php
/*
Plugin Name: JW Platform Plugin
Plugin URI: http://www.jwplayer.com/
Description: This plugin allows you to easily upload and embed videos using the JW Platform (formerly known as Bits on the Run). The embedded video links can be signed, making it harder for viewers to steal your content.
Author: JW Player
Version: 1.3
*/

define( 'JWPLAYER_PLUGIN_DIR', dirname( __FILE__ ) );

require_once( JWPLAYER_PLUGIN_DIR . '/JWPlayer-api.class.php' );
require_once( JWPLAYER_PLUGIN_DIR . '/proxy.php' );

// Default settings
define( 'JWPLAYER_PLAYER', 'ALJ3XQCI' );
define( 'JWPLAYER_TIMEOUT', '0' );
define( 'JWPLAYER_CONTENT_MASK', 'content.jwplatform.com' );
define( 'JWPLAYER_NR_VIDEOS', '5' );
define( 'JWPLAYER_SHOW_WIDGET', false );

// Determine if we are using vip or regular wp
$jwplayer_which_env = null;
if ( function_exists( 'vip_safe_wp_remote_get' ) ) {
	$jwplayer_which_env = 'wpvip';
}
else {
	$jwplayer_which_env = 'wp';
}

// Execute when the plugin is enabled
function jwplayer_add_options() {
	// Add (but do not override) the settings
	add_option( 'jwplayer_player', JWPLAYER_PLAYER );
	add_option( 'jwplayer_timeout', JWPLAYER_TIMEOUT );
	add_option( 'jwplayer_content_mask', JWPLAYER_CONTENT_MASK );
	add_option( 'jwplayer_nr_videos', JWPLAYER_NR_VIDEOS );
	add_option( 'jwplayer_show_widget', JWPLAYER_SHOW_WIDGET );
	add_option( 'jwplayer_login', null );
}

if ( $jwplayer_which_env == 'wpvip' ) {
	if ( ! get_option( 'jwplayer_player' ) ) {
			jwplayer_add_options();
	}
}
else if ( $jwplayer_which_env == 'wp' ) {
	register_activation_hook( __FILE__, 'jwplayer_add_options' );
}

// Get the API object
function jwplayer_get_api_instance() {
	$api_key = get_option( 'jwplayer_api_key' );
	$api_secret = get_option( 'jwplayer_api_secret' );

	if ( 8 == strlen( $api_key ) && 24 == strlen( $api_secret ) ) {
		return new JWPlayer_api( $api_key, $api_secret );
	}
	else {
		return null;
	}
}

function jwplayer_print_error( $message ) {
	?>
	<div class='error fade'>
		<p>
			<strong><?php echo esc_html( $message ); ?></strong>
		</p>
	</div>
	<?php
}

// Show the login notice in the admin area if necessary
function jwplayer_show_login_notice() {
	if ( isset( $_GET['page'] ) ) {// input var okay
		$page = sanitize_text_field( $_GET['page'] );// input var okay
		// Don't show the notice if we are logging in or signing up
		if ( $page == 'jwplayer_login' ) {
			return;
		}
	}
	if ( ! get_option( 'jwplayer_login' ) ) {
		$login_url = get_admin_url( null, 'admin.php?page=jwplayer_login_page' );
		echo '<div class="error fade"><p><strong>Don\'t forget to <a href="' . esc_url( $login_url ) . '">log in</a> to your JW Platform account.</strong></p></div>';
	}
}

add_action( 'admin_notices', 'jwplayer_show_login_notice' );

// Additions to the page head in the admin area
function jwplayer_admin_head() {

	$plugin_url = plugins_url( '', __FILE__ );
	$content_mask = jwplayer_get_content_mask();
	$nr_videos = intval( get_option( 'jwplayer_nr_videos' ) );
	?>

	<script type="text/javascript">
		jwplayer.plugin_url = '<?php echo esc_url( $plugin_url ); ?>';
		jwplayer.content_mask = '<?php echo esc_url( $content_mask ); ?>';
		jwplayer.nr_videos = <?php echo esc_js( $nr_videos ); ?>;
	</script>
	<?php
}

add_action( 'admin_head-post.php', 'jwplayer_admin_head' );
add_action( 'admin_head-media-upload-popup', 'jwplayer_admin_head' );


// Add JQuery-UI Draggable to the included scripts, and other scripts needed for plugin
function jwplayer_enqueue_scripts( $hook_suffix ) {

	// only enqueue on relevant admin pages
	if ( $hook_suffix !== 'media-upload-popup' && $hook_suffix !== 'post.php' ) {
		return;
	}

	$ajaxupload_url = plugins_url( 'upload.js', __FILE__ );
	$style_url = plugins_url( 'style.css', __FILE__ );
	$logic_url = plugins_url( 'logic.js', __FILE__ );

	wp_register_style( 'jwplayer_wp_admin_css', $style_url, false, '1.0.0' );
	wp_enqueue_style( 'jwplayer_wp_admin_css' );
	wp_enqueue_script( 'jquery-ui-draggable' );
	wp_enqueue_script( 'ajaxupload_script', $ajaxupload_url );
	wp_enqueue_script( 'logic_script', $logic_url );
}

add_action( 'admin_enqueue_scripts', 'jwplayer_enqueue_scripts' );

// Add the video widget to the authoring page, if enabled in the settings
function jwplayer_add_video_box() {
	if ( get_option( 'jwplayer_show_widget' ) ) {
		if ( get_option( 'jwplayer_login' ) ) {
			add_meta_box( 'jwplayer-video-box', 'JW Platform', 'jwplayer_widget_body', 'post', 'side', 'high' );
			add_meta_box( 'jwplayer-video-box', 'JW Platform', 'jwplayer_widget_body', 'page', 'side', 'high' );
		}
	}
}

add_action( 'admin_menu', 'jwplayer_add_video_box' );

// The body of the widget
function jwplayer_widget_body() {
	?>
	<span class="jwplayer-dashboard-link">
		<a href="http://dashboard.jwplatform.com">Open Dashboard</a>
	</span>
	<div id="jwplayer-list-wrapper">
		<input type="text" value="Search videos" id="jwplayer-search-box"/>
		<ul id="jwplayer-video-list"></ul>
	</div>
	<select id="jwplayer-player-select">
		<option value="">Default Player</option>
	</select>
	<button id="jwplayer-upload-button" class="button-primary">Upload a video...</button>
	<input type="hidden" name="_wpnonce-widget" value="<?php echo esc_attr( wp_create_nonce( 'jwplayer-widget-nonce' ) ); ?>">
	<?php
}

// Add the JW Player settings to the media page in the admin panel
function jwplayer_add_settings() {
	add_settings_section( 'jwplayer_setting_section', 'JW Platform', '__return_true', 'media' );

	if ( get_option( 'jwplayer_login' ) ) {
		add_settings_field( 'jwplayer_logout_link', 'Log out', 'jwplayer_logout_link', 'media', 'jwplayer_setting_section' );
		add_settings_field( 'jwplayer_nr_videos', 'Number of videos', 'jwplayer_nr_videos_setting', 'media', 'jwplayer_setting_section' );
		add_settings_field( 'jwplayer_timeout', 'Timeout for signed links', 'jwplayer_timeout_setting', 'media', 'jwplayer_setting_section' );
		add_settings_field( 'jwplayer_content_mask', 'Content DNS mask', 'jwplayer_content_mask_setting', 'media', 'jwplayer_setting_section' );
		add_settings_field( 'jwplayer_player', 'Default player', 'jwplayer_player_setting', 'media', 'jwplayer_setting_section' );
		add_settings_field( 'jwplayer_show_widget', 'Show the widget', 'jwplayer_show_widget_setting', 'media', 'jwplayer_setting_section' );

		register_setting( 'media', 'jwplayer_nr_videos', 'absint' );
		register_setting( 'media', 'jwplayer_timeout', 'absint' );
		register_setting( 'media', 'jwplayer_content_mask', 'jwplayer_content_mask_validate' );
		register_setting( 'media', 'jwplayer_player', 'jwplayer_player_validate' );
		register_setting( 'media', 'jwplayer_show_widget', 'jwplayer_show_widget_validate' );
	}
	else {
		add_settings_field( 'jwplayer_login_link', 'Log in', 'jwplayer_login_link', 'media', 'jwplayer_setting_section' );
	}
}

add_action( 'admin_init', 'jwplayer_add_settings' );


// Validate the settings for the default player
function jwplayer_player_validate( $player_key ) {
	$login = get_option( 'jwplayer_login' );
	$loggedin = ! empty( $login );
	if ( $loggedin ) {
		$jwplayer_api = jwplayer_get_api_instance();
		$response = $jwplayer_api->call( '/players/list' );
		foreach ( $response['players'] as $i => $p ) {
			if ( $player_key == $p['key'] ) {
				return $player_key;
			}
		}
		return $response['players'][0]['key'];
	}
	return '';
}

// The setting for the default player
function jwplayer_player_setting() {
	$login = get_option( 'jwplayer_login' );
	$loggedin = ! empty( $login );
	if ( $loggedin ) {
		$jwplayer_api = jwplayer_get_api_instance();
		$response = $jwplayer_api->call( '/players/list' );
		$player = get_option( 'jwplayer_player' );

		echo '<select name="jwplayer_player" id="jwplayer_player" />';

		foreach ( $response['players'] as $i => $p ) {
			$key = $p['key'];
			if ( $p['responsive'] ) {
				$description = htmlentities( $p['name'] ) . ' (Responsive, ' . $p['aspectratio'] . ')';
			}
			else {
				$description = htmlentities( $p['name'] ) . ' (Fixed size, ' . $p['width'] . 'x' . $p['height'] . ')';
			}
			echo '<option value="' . esc_attr( $key ) . '"' . esc_attr( selected( $key == $player, true, false ) ) . '>' . esc_html( $description ) . '</option>';
		}

		echo '</select>';

		echo '<br />The <a href="' . esc_url( 'http://dashboard.jwplatform.com/players/' ) . '">player</a> to use for embedding the videos.';
		echo 'If you want to override the default player for a given video, simply append a dash and the corresponding player key to video key in the quicktag. For example: <code>[jwplatform MdkflPz7-35rdi1pO]</code>.';
	}
	else {
		echo '<input type="hidden" name="jwplayer_player" value="' . esc_attr( JWPLAYER_PLAYER ) . '" />';
		echo 'You have to save log in before you can set this option.';
	}
}

// The setting for the signed player timeout
function jwplayer_timeout_setting() {
	$timeout = absint( get_option( 'jwplayer_timeout', JWPLAYER_TIMEOUT ) );

	echo '<input name="jwplayer_timeout" id="jwplayer_timeout" type="text" size="7" value="' . esc_attr( $timeout ) . '" />';
	echo '<br />The duration in minutes for which a <a href="' . esc_url( 'http://support.jwplayer.com/customer/portal/articles/1433647-secure-platform-videos-with-signing' ) . '">signed player</a> will be valid. Set this to 0 (the default) if you don\'t use signing.';
}

// The setting for the number of videos to show in the widget
function jwplayer_nr_videos_setting() {
	$nr_videos = absint( get_option( 'jwplayer_nr_videos', JWPLAYER_NR_VIDEOS ) );

	echo '<input name="jwplayer_nr_videos" id="jwplayer_nr_videos" type="text" size="2" value="' . esc_attr( $nr_videos ) . '" />';
	echo '<br />The number of videos to show in the widget on the <i>edit post</i> page.';
}

// Function to validate for a valid content mask
function jwplayer_content_mask_validate( $dns_mask ) {
	$pattern = '/^([a-z0-9]([-a-z0-9]*[a-z0-9])?\\.)+((a[cdefgilmnoqrstuwxz]|aero|arpa)|(b[abdefghijmnorstvwyz]|biz)|(c[acdfghiklmnorsuvxyz]|cat|com|coop)|d[ejkmoz]|(e[ceghrstu]|edu)|f[ijkmor]|(g[abdefghilmnpqrstuwy]|gov)|h[kmnrtu]|(i[delmnoqrst]|info|int)|(j[emop]|jobs)|k[eghimnprwyz]|l[abcikrstuvy]|(m[acdghklmnopqrstuvwxyz]|mil|mobi|museum)|(n[acefgilopruz]|name|net)|(om|org)|(p[aefghklmnrstwy]|pro)|qa|r[eouw]|s[abcdeghijklmnortvyz]|(t[cdfghjklmnoprtvwz]|travel)|u[agkmsyz]|v[aceginu]|w[fs]|y[etu]|z[amw])$/i';
	if ( preg_match( $pattern, $dns_mask ) ) {
		return $dns_mask;
	}
	return '';
}

// Function to return the jwplayer_content_mask
function jwplayer_get_content_mask() {
	$content_mask = sanitize_text_field( get_option( 'jwplayer_content_mask' ) );
	if ( 'content.bitsontherun.com' == $content_mask ) {
		$content_mask = 'content.jwplatform.com';
	}
	return $content_mask;
}

// The setting for the content mask
function jwplayer_content_mask_setting() {
	$content_mask = jwplayer_get_content_mask();
	if ( ! $content_mask ) {
		// An empty content mask, or the variable was somehow removed entirely
		$content_mask = JWPLAYER_CONTENT_MASK;
		update_option( 'jwplayer_content_mask', $content_mask );
	}
	echo '<input name="jwplayer_content_mask" id="jwplayer_content_mask" type="text" value="' . esc_attr( $content_mask ) . '" class="regular-text" />';
	echo '<br />The <a href="' . esc_url( 'http://support.jwplayer.com/customer/portal/articles/1433702-dns-masking-the-jw-platform' ) . '">DNS mask</a> of the JW Player content server.';
}

function jwplayer_show_widget_validate( $value ) {
	if ( $value ) {
		return true;
	}
	return false;
}

// The setting which determines whether we show the widget on the authoring page (or only in the "Add media" window)
function jwplayer_show_widget_setting() {
	$show_widget = get_option( 'jwplayer_show_widget' );
	echo '<input name="jwplayer_show_widget" id="jwplayer_show_widget" type="checkbox" ';
	checked( true, $show_widget );
	echo ' value="true" /> ';
	echo '<label for="jwplayer_show_widget">Show the JW Platform widget on the authoring page.</label><br />';
	echo 'Note that the widget is also accessible from the <em>Add media</em> window.';
}

// The login link on the settings page
function jwplayer_login_link() {
	$login_url = get_admin_url( null, 'admin.php?page=jwplayer_login_page' );
	echo 'In order to use this plugin, please <a href="' . esc_url( $login_url ) . '">log in</a> first.';
}

// The logout link on the settings page
function jwplayer_logout_link() {
	$logout_url = get_admin_url( null, 'admin.php?page=jwplayer_logout_page' );
	$user = get_option( 'jwplayer_login' );
	echo 'Logged in as user <em>' . esc_html( $user ) . '</em><br><a href="' . esc_url( $logout_url ) . '">Log out</a>';
}

// Print the login page
function jwplayer_login_form() {
	?>
	<div class="wrap">
		<h2>JW Platform login</h2>

		<form method="post" action="">
			<p>In order to use the JW Platform plugin, you are required to log in.</p>
			<table class="form-table">

				<tr valign="top">
					<th scope="row">Username</th>
					<td><input type="text" name="username"></td>
				</tr>

				<tr valign="top">
					<th scope="row">Password</th>
					<td><input type="password" name="password">

						<p class="description">Your password will not be stored in the Wordpress database.</p></td>
				</tr>

			</table>

			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'jwplayer-login-nonce' ) ); ?>">

			<p class="submit"><input type="submit" class="button-primary" value="Log in"></p>

		</form>
	</div>
	<?php
}

// The login page
function jwplayer_login() {
	if ( ! current_user_can( 'manage_options' ) ) {
		jwplayer_print_error( 'You do not have sufficient privileges to access this page.' );
		return;
	}

	if ( ! isset( $_POST['username'], $_POST['password'] ) ) {//input var okay
		jwplayer_login_form();
		return;
	}

	// Check the nonce (counter XSRF)
	$nonce = sanitize_text_field( $_POST['_wpnonce'] );//input var okay
	if ( ! wp_verify_nonce( $nonce, 'jwplayer-login-nonce' ) ) {
		jwplayer_print_error( 'Could not verify the form data.' );
		jwplayer_login_form();
		return;
	}

	if ( isset($_POST['username']) ){
		$login = sanitize_text_field( $_POST['username'] );//input var okay
	}

	if ( isset($_POST['password']) ){
		$password = sanitize_text_field( $_POST['password'] );//input var okay
	}

	$keysecret = jwplayer_get_api_key_secret( $login, $password );

	if ( null === $keysecret ) {
		jwplayer_print_error( 'Communications with the JW Platform API failed. Please try again later.' );
		jwplayer_login_form();
	}
	elseif ( ! isset( $keysecret['key'], $keysecret['secret'] ) ) {
		jwplayer_print_error( 'Your login credentials were not accepted. Please try again.' );
		jwplayer_login_form();
	}
	else {
		// Perform the login.
		update_option( 'jwplayer_login', $login );
		update_option( 'jwplayer_api_key', $keysecret['key'] );
		update_option( 'jwplayer_api_secret', $keysecret['secret'] );
		echo '<h2>Logged in</h2><p>Logged in successfully. Returning you to the <a href="options-media.php">media settings</a> page...</p>';
		// Perform a manual JavaScript redirect
		echo '<script type="application/x-javascript">document.location.href = "options-media.php"</script>';
	}
}

/**
 * Return an associative array with keys 'key' and 'secret', containing the API
 * key and secret for the account with the specified login credentials.
 *
 * If the credentials are invalid, return an empty array.
 *
 * If the API call failed, return NULL.
 */
function jwplayer_get_api_key_secret( $login, $password ) {
	require_once 'JWPlayer-api.class.php';

	// Create an API object without key and secret.
	$api = new JWPlayer_api( '', '' );
	$params = array(
		'account_login' => $login,
		'account_password' => $password,
	);
	$response = $api->call( '/accounts/credentials/show', $params );

	if ( ! $response ) {
		return null;
	}
	if ( 'ok' != $response['status'] ) {
		if ( 'error' == $response['status'] && 'NotFound' == $response['code'] ) {
			return array();
		}
		return null;
	}

	// No errors.
	return array(
		'key' => $response['account']['key'],
		'secret' => $response['account']['secret'],
	);
}

// Print the logout page
function jwplayer_logout_form() {
	?>
	<div class="wrap">
		<h2>JW Platform log out</h2>

		<form method="post" action="">
			<p>You can use this page to log out of your JW Platform account.<br>
				Note that, while signed out, videos will not be embedded.</p>

			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'jwplayer-logout-nonce' ) ); ?>">

			<p class="submit"><input type="submit" class="button-primary" value="Log out" name="logout"></p>

		</form>
	</div>
	<?php
}

// The logout page
function jwplayer_logout() {
	if ( ! current_user_can( 'manage_options' ) ) {
		jwplayer_print_error( 'You do not have sufficient privileges to access this page.' );
		return;
	}

	if ( ! isset( $_POST['logout'] ) ) {//input var okay
		jwplayer_logout_form();
		return;
	}

	// Check the nonce (counter XSRF)
	$nonce = sanitize_text_field( $_POST['_wpnonce'] );//input var okay
	if ( ! wp_verify_nonce( $nonce, 'jwplayer-logout-nonce' ) ) {
		jwplayer_print_error( 'Could not verify the form data.' );
		jwplayer_logout_form();
		return;
	}

	// Perform the logout.
	update_option( 'jwplayer_login', null );
	update_option( 'jwplayer_api_key', '' );
	update_option( 'jwplayer_api_secret', '' );
	echo '<h2>Logged out</h2><p>Logged out successfully. Returning you to the <a href="' . esc_url( 'options-media.php' ) . '">media settings</a> page...</p>';
	// Perform a manual JavaScript redirect
	echo '<script type="application/x-javascript">document.location.href = "options-media.php"</script>';
}

function jwplayer_create_login_pages(){
	//adds the login page
	add_submenu_page( null, 'JW Player Login', 'JW Player login', 'manage_options', 'jwplayer_login_page', 'jwplayer_login' );
	//adds the logout page
	add_submenu_page( null, 'JW Player Logout', 'JW Player Logout', 'manage_options', 'jwplayer_logout_page', 'jwplayer_logout' );
}
add_action( 'admin_menu', 'jwplayer_create_login_pages' );

function jwplayer_handle_shortcode( $atts ) {
	$login = get_option( 'jwplayer_login' );
	if ( empty( $login ) ) {
		return '';
	}
	if ( array_keys( $atts ) == array( 0 ) ) {
		$regex = '/([0-9a-z]{8})(?:[-_])?([0-9a-z]{8})?/i';
		$m = array();
		if ( preg_match( $regex, $atts[0], $m ) ) {
			return jwplayer_create_js_embed( $m );
		}
	}
	// Invalid shortcode
	return '';
}

add_shortcode( 'jwplatform', 'jwplayer_handle_shortcode' );
add_shortcode( 'bitsontherun', 'jwplayer_handle_shortcode' );

// Create the JS embed code for the jwplayer player
// $arguments is an array:
// - 0: ignored
// - 1: the video hash
// - 2: the player hash (or null for default player)
function jwplayer_create_js_embed( $arguments ) {
	$video_hash = $arguments[1];
	$player_hash = ( ! empty( $arguments[2] ) ) ? $arguments[2] : get_option( 'jwplayer_player' );
	$content_mask = jwplayer_get_content_mask();
	$timeout = intval( get_option( 'jwplayer_timeout' ) );
	$path = 'players/' . $video_hash . '-' . $player_hash . '.js';
	if ( $timeout < 1 ) {
		$url = 'http://' . $content_mask . '/' .$path;
	} else {
		$api_secret = get_option( 'jwplayer_api_secret' );
		$expires = time() + 60 * $timeout;
		$signature = md5( $path . ':' . $expires . ':' . $api_secret );
		if ( is_ssl() ) {
			$url = 'https://' . $content_mask . '/' . $path . '?exp=$expires&sig=' .$signature;
		}
		else {
			$url = 'http://' . $content_mask . '/' . $path . '?exp=' . $expires . '&sig=' . $signature;
		}
	}

	return '<script type="text/javascript" src="' . esc_url( $url ) . '"></script>';
}

// Add the JW Player tab to the menu of the "Add media" window
function jwplayer_media_menu( $tabs ) {
	if ( get_option( 'jwplayer_login' ) ) {
		$newtab = array( 'jwplayer' => 'JW Platform' );
		return array_merge( $tabs, $newtab );
	} else {
		return $tabs;
	}
}

add_filter( 'media_upload_tabs', 'jwplayer_media_menu' );

// output the contents of the JW Player tab in the "Add media" page
function media_jwplayer_page() {
	media_upload_header();

	?>
	<form class="media-upload-form type-form validate" id="video-form" enctype="multipart/form-data" method="post"
	      action="">
		<h3 class="media-title">Embed videos from JW Platform</h3>

		<div id="media-items">
			<div id="jwplayer-video-box" class="media-item">
				<?php jwplayer_widget_body(); ?>
			</div>
		</div>
		<input type="hidden" name="_wpnonce-widget"
		       value="<?php echo esc_attr( wp_create_nonce( 'jwplayer-widget-nonce' ) ); ?>">
	</form>
	<?php
}

// Make our iframe show up in the "Add media" page
function jwplayer_media_handle() {
	return wp_iframe( 'media_jwplayer_page' );
}

add_action( 'media_upload_jwplayer', 'jwplayer_media_handle' );

// ajax calls for the jwplayer plugin
// utlizes proxy.php
add_action( 'wp_ajax_jwplayer', function(){
		if ( isset( $_GET['method'] ) ){//input var okay
			if ( 'upload_ready' == sanitize_text_field( $_GET['method'] ) ){//input var okay
				echo '{"status" : "ok"}';
			}
			else {
				jwplayer_proxy();
			}
		}
		die();
} );
