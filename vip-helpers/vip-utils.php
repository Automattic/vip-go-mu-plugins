<?php

/**
 * Utility function to trigger a callback on a hook with priority or execute immediately if the hook has already been fired previously.
 *
 * @param function $function Callback to trigger.
 * @param string $hook Hook name to trigger on.
 * @param priority int Priority to trigger on.
 *
 * @private
 */
function _wpcom_vip_call_on_hook_or_execute( $function, $hook, $priority = 99 ) {
	if ( ! is_callable( $function ) ) {
		_doing_it_wrong( __FUNCTION__, 'Specified $function is not a valid callback!', '3.8-wpcom' );
		return;
	}

	if ( did_action( $hook ) ) {
		call_user_func( $function );
	} else {
		add_action( $hook, $function, $priority );
	}
}

/**
 * Returns the raw path to the VIP themes dir.
 *
 * @return string
 */
function wpcom_vip_themes_root() {
	return WP_CONTENT_DIR . '/themes';
}

/**
 * Returns the non-CDN URI to the VIP themes dir.
 *
 * Sometimes enqueuing/inserting resources can trigger cross-domain errors when
 * using the CDN, so this function allows bypassing the CDN to eradicate those
 * unwanted errors.
 *
 * @return string The URI
 */
function wpcom_vip_themes_root_uri() {
	if ( ! is_admin() ) {
		return home_url( '/wp-content/themes' );
	} else {
		return content_url( '/themes' );
	}
}

/**
 * Returns the non-CDN'd URI to the specified path.
 *
 * @param string $path Must be a full path, e.g. dirname( __FILE__ )
 * @return string
 */
function wpcom_vip_noncdn_uri( $path ) {
	// Be gentle on Windows, borrowed from core, see plugin_basename
	$path = str_replace( '\\','/', $path ); // sanitize for Win32 installs
	$path = preg_replace( '|/+|','/', $path ); // remove any duplicate slash

	return sprintf( '%s%s', wpcom_vip_themes_root_uri(), str_replace( wpcom_vip_themes_root(), '', $path ) );
}

/**
 * Returns a link the WordPress.com VIP site wrapped around an image (the VIP logo).
 *
 * @param int $image Which variant of the VIP logo to use; between 1-6.
 * @return string HTML
 */
function vip_powered_wpcom_img_html( $image ) {
	$vip_powered_wpcom_images = array(
		//image file, width, height
		1 => array('vip-powered-light-small.png', 187, 26),
		2 => array('vip-powered-light-normal.png', 209, 56),
		3 => array('vip-powered-light-long.png', 305, 56),
		4 => array('vip-powered-dark-small.png', 187, 26),
		5 => array('vip-powered-dark-normal.png', 209, 56),
		6 => array('vip-powered-dark-long.png', 305, 56)
		);

		if ( array_key_exists( $image, $vip_powered_wpcom_images ) )
			return '<a href="' . esc_url( vip_powered_wpcom_url() ) . '" rel="generator nofollow" class="powered-by-wpcom"><img src="' . esc_url( plugins_url( 'images/' . $vip_powered_wpcom_images[$image][0], __FILE__ ) ) . '" width="' . esc_attr( $vip_powered_wpcom_images[$image][1] ) . '" height="' . esc_attr( $vip_powered_wpcom_images[$image][2] ) . '" alt="'. esc_attr__( 'Powered by WordPress.com VIP', 'vip-helpers' ) .'" /></a>';
		else
			return '';
}

/**
 * Returns the "Powered by WordPress.com VIP" widget's content.
 *
 * @link http://vip.wordpress.com/documentation/code-and-theme-review-process/ Code Review
 * @link http://vip.wordpress.com/documentation/powered-by-wordpress-com-vip/ Powered By WordPress.com VIP
 * @param string $display Optional. Either: 1-6 or "text"*. If an integer, wrap an image in the VIP link. Otherwise, just return the link.
 * @param string $before_text Optional. Text to go in front of the VIP link. Defaults to 'Powered by '.
 * @return string HTML
 */
function vip_powered_wpcom( $display = 'text', $before_text = 'Powered by ' ) {
	switch ($display) {
		case 'text':
			$output = $before_text . '<a href="' . esc_url( vip_powered_wpcom_url() ) . '" rel="generator nofollow" class="powered-by-wpcom">WordPress.com VIP</a>';
			break;
		case 1:
		case 2:
		case 3:
		case 4:
		case 5:
		case 6:
			$output = vip_powered_wpcom_img_html($display);
			break;
		default:
			$output = '';
	}

	return $output;
}

/**
 * Returns the URL to the WordPress.com VIP site
 *
 * @return string
 */
function vip_powered_wpcom_url() {
	return 'https://vip.wordpress.com/';
}

/**
 * Allows users of contributor role to be able to upload media.
 *
 * Contrib users still can't publish.
 *
 * @author mdawaffe
 * @link http://vip.wordpress.com/documentation/allow-contributors-to-upload-images/ Allow Contributors to Upload Images
 */
function vip_contrib_add_upload_cap() {
	add_action( 'init', '_vip_contrib_add_upload_cap');
	add_action( 'xmlrpc_call', '_vip_contrib_add_upload_cap' ); // User is logged in after 'init' for XMLRPC
}

/**
 * Helper function for vip_contrib_add_upload_cap() to change the user roles
 *
 * @link http://vip.wordpress.com/documentation/allow-contributors-to-upload-images/ Allow Contributors to Upload Images
 * @see vip_contrib_add_upload_cap()
 */
function _vip_contrib_add_upload_cap() {
	if ( ! is_admin() && ! defined( 'XMLRPC_REQUEST' ) )
		return;

	wpcom_vip_add_role_caps( 'contributor', array( 'upload_files' ) );
}

/**
 * Returns a URL for a given attachment with the appropriate resizing querystring.
 *
 * Typically, you should be using image sizes for handling this.
 *
 * However, this function can come in handy if you want a specific artibitrary or varying image size.
 *
 * @link http://vip.wordpress.com/documentation/image-resizing-and-cropping/
 *
 * @param int $attachment_id ID of the attachment
 * @param int $width Width of our resized image
 * @param int $height Height of our resized image
 * @param bool $crop (optional) whether or not to crop the image
 * @return string URL of the resized attachmen
 */
function wpcom_vip_get_resized_attachment_url( $attachment_id, $width, $height, $crop = false ) {
	$url = wp_get_attachment_url( $attachment_id );

	if ( ! $url ) {
		return false;
	}

	$args = array(
		'w' => intval( $width ),
		'h' => intval( $height ),
	);

	// @todo crop handling?

	return jetpack_photon_url( $url, $args, '//' );
}

/**
 * Allows you to customize the /via and follow recommendation for the WP.com Sharing Twitter button.
 *
 * @param string $via Optional. What the /via should be set to. Empty value disables the feature (the default).
 */
function wpcom_vip_sharing_twitter_via( $via = '' ) {
	if( empty( $via ) ) {
		$via_callback = '__return_false';
	} else {
		// sanitize_key() without changing capitizalization
		$raw_via = $via;
		$via = preg_replace( '/[^A-Za-z0-9_\-]/', '', $via );
		$via = apply_filters( 'sanitize_key', $via, $raw_via );

		$via_callback = function() use ( $via ) { return $via; };
	}

	add_filter( 'jetpack_sharing_twitter_via', $via_callback );
	add_filter( 'jetpack_open_graph_tags', function( $tags ) use ( $via ) {
		if ( isset( $tags['twitter:site'] ) ) {
			if ( empty( $via ) )
				unset( $tags['twitter:site'] );
			else
				$tags['twitter:site'] = '@' . $via;
		}
		return $tags;
	}, 99 ); // later so we run after Twitter Cards have run
}

/**
 * Disables Jetpack Post Flair entirely on the frontend.
 * This removes the filters and doesn't allow the stylesheet to be enqueued.
 */
function wpcom_vip_disable_post_flair() {
	add_filter( 'post_flair_disable', '__return_true' );
}

/**
 * Disables Jetpack Sharing in Posts and Pages.
 *
 * Sharing can be disabled in the dashboard, by removing all buttons from Enabled Services.
 *
 * This function is primary for automating sharing when you have numerous sites to administer.
 * It also assists having consistent CSS containers between development and production.
 */
function wpcom_vip_disable_sharing() {
	// Post Flair sets things up on init so we need to call on that if init hasn't fired yet.
	_wpcom_vip_call_on_hook_or_execute( function() {
		remove_filter( 'post_flair', 'sharing_display', 20 );
		remove_filter( 'the_content', 'sharing_display', 19 );
   		remove_filter( 'the_excerpt', 'sharing_display', 19 );

		wpcom_vip_disable_sharing_resources();
	}, 'init', 99 );
}

/**
 * Disable CSS and JS output for Jetpack Sharing.
 *
 * Note: this disables things like smart buttons and share counts displayed alongside the buttons. Those will need to be handled manually if desired.
 */
function wpcom_vip_disable_sharing_resources() {
	_wpcom_vip_call_on_hook_or_execute( function() {
		add_filter( 'sharing_js', '__return_false' );
		remove_action( 'wp_head', 'sharing_add_header', 1 );
	}, 'init', 99 );
}

/**
 * Enables Jetpack Sharing in Posts and Pages.
 *
 * This feature is on by default, so the function is only useful if you've also used wpcom_vip_disable_sharing().
 */
function wpcom_vip_enable_sharing() {
	add_filter( 'post_flair', 'sharing_display', 20 );
}

/**
 * Enable CSS and JS output for WPCOM sharing.
 *
 * Note: if resources were disabled previously and this is called after wp_head, it may not work as expected.
 */
function wpcom_vip_enable_sharing_resources() {
	remove_filter( 'sharing_js', '__return_false' );
	add_action( 'wp_head', 'sharing_add_header', 1 );
}

/**
 * Disables Jetpack Likes for Posts and Custom Post Types
 *
 * Sharing can also be disabled from the Dashboard (Settings > Sharing).
 *
 * This function is primarily for programmatic disabling of the feature, for example when working with custom post types.
 */
function wpcom_vip_disable_likes() {
	add_filter( 'wpl_is_likes_visible', '__return_false', 999 );
}

/**
 * Disables Jetpack Likes for Posts and Custom Post Types
 *
 * This feature is on by default, so the function is only useful if you've also used wpcom_vip_disable_sharing().
 */
function wpcom_vip_enable_likes() {
	add_filter( 'wpl_is_likes_visible', '__return_true', 999 );
}

/**
 * Disables Olark live chat
 *
 * @see show_live_chat()
 */
function wpcom_vip_remove_livechat() {
	add_filter( 'vip_live_chat_enabled', '__return_false' );
}

/**
* Eliminates widows in strings by replace the breaking space that appears before the last word with a non-breaking space.
*
* This function is defined on WordPress.com and can be a common source of frustration for VIP devs.
* Now they can be frustrated in their local environments as well :)
*
* @param string $str Optional. String to operate on.
* @return string
* @link http://www.shauninman.com/post/heap/2006/08/22/widont_wordpress_plugin Typesetting widows
*/
function widont( $str = '' ) {
	// Don't apply on non-tablet mobile devices so the browsers can fit to the viewport properly.
	if (
		function_exists( 'jetpack_is_mobile' ) && jetpack_is_mobile() &&
		class_exists( 'Jetpack_User_Agent_Info' ) && ! Jetpack_User_Agent_Info::is_tablet()
	) {
		return $str;
	}

	// We're dealing with whitespace from here out, let's not have any false positives. :)
	$str = trim( $str );

	// If string contains three or fewer words, don't join.
	if ( count( preg_split( '#\s+#', $str ) ) <= 3 ) {
		return $str;
	}

	// Don't join if words exceed a certain length: minimum 10 characters, default 15 characters, filterable via `widont_max_word_length`.
	$widont_max_word_length = max( 10, absint( apply_filters( 'widont_max_word_length', 15 ) ) );
	$regex = '#\s+([^\s]{1,' . $widont_max_word_length . '})\s+([^\s]{1,' . $widont_max_word_length . '})$#';

	return preg_replace( $regex, ' $1&nbsp;$2', $str );
}

// Leave these wrapped in function_exists() b/c they are so generically named
if ( ! function_exists( 'wp_startswith' ) ) :
	function wp_startswith( $haystack, $needle ) {
		return 0 === strpos( $haystack, $needle );
	}
endif;

if ( ! function_exists( 'wp_endswith' ) ) :
	function wp_endswith( $haystack, $needle ) {
		return $needle === substr( $haystack, -strlen( $needle ));
	}
endif;

if ( ! function_exists( 'wp_in' ) ) :
	function wp_in( $needle, $haystack ) {
		return false !== strpos( $haystack, $needle );
	}
endif;

/**
 * Simple 301 redirects
 *
 * @param array $vip_redirects_array Optional. Elements should be in the form of '/old' => 'http://wordpress.com/new/'
 * @param bool $case_insensitive Optional. Should the redirects be case sensitive? Defaults to false.
 */
function vip_redirects( $vip_redirects_array = array(), $case_insensitive = false ) {
	if ( empty( $vip_redirects_array ) )
		return;

	$redirect_url = '';

	// Sanitize the redirects array
	$vip_redirects_array = array_map( 'untrailingslashit', $vip_redirects_array );

	$uri_unslashed = untrailingslashit( $_SERVER['REQUEST_URI'] );

	if ( $case_insensitive ) {
		$vip_redirects_array = array_change_key_case( $vip_redirects_array );
		$uri_unslashed = strtolower( $uri_unslashed );
	}

	// Get the current URL minus query string
	$parsed_uri_path = parse_url( $uri_unslashed, PHP_URL_PATH );
	$parsed_uri_path = $parsed_uri_path ? $parsed_uri_path : '';
	$parsed_uri_path_slashed = trailingslashit( $parsed_uri_path );

	if ( $parsed_uri_path && array_key_exists( $parsed_uri_path, $vip_redirects_array ) )
		$redirect_url = $vip_redirects_array[ $parsed_uri_path ];
	elseif ( $parsed_uri_path_slashed && array_key_exists( $parsed_uri_path_slashed, $vip_redirects_array ) )
		$redirect_url = $vip_redirects_array[ $parsed_uri_path_slashed ];

	if ( $redirect_url ) {
		wp_redirect( $redirect_url, 301 );
		exit;
	}
}

/**
 * Wildcard redirects based on the beginning of the request path.
 *
 * This is basically an alternative to vip_regex_redirects() for when you only need to redirect /foo/bar/* to somewhere else.
 * Using regex to do this simple check would add lots of overhead.
 *
 * @param array $vip_redirects_array Optional. Elements should be in the form of '/some-path/' => 'http://wordpress.com/new/'
 * @param bool $append_old_uri Optional. If true, the full path past the match will be added to the new URL. Defaults to false.
 */
function vip_substr_redirects( $vip_redirects_array = array(), $append_old_uri = false ) {
	if ( empty( $vip_redirects_array ) )
		return;

	// Don't do anything for the homepage
	if ( '/' == $_SERVER['REQUEST_URI'] )
		return;

	foreach ( $vip_redirects_array as $old_path => $new_url ) {
		if ( substr( $_SERVER['REQUEST_URI'], 0, strlen( $old_path ) ) == $old_path ) {
			if ( $append_old_uri )
				$new_url .= str_replace( $old_path, '', $_SERVER['REQUEST_URI'] );
			wp_redirect( $new_url, 301 );
			exit();
		}
	}
}

/**
 * Advanced 301 redirects using regex to match and redirect URLs.
 *
 * Warning: Since regex is expensive and this will be run on every uncached pageload, you'll want to keep this small, lean, and mean.
 *
 * Some examples:
 *
 * Redirecting from /2011/12/dont-miss-it-make-live-holiday-giveaway.html (extra .html at the end)
 * '|/([0-9]{4})/([0-9]{2})/([0-9]{2})/([^/]+)\.html|' => '|/$1/$2/$3/$4/|'
 *
 * Redirecting from /archive/2011/12/dont-miss-it-make-live-holiday-giveaway
 * '|/archive/([0-9]{4})/([0-9]{2})/([^/]+)/?|' => '|/$3/|' // since we don't have the day, we should just send to /%postname% then WordPress can redirect from there
 *
 * Redirecting from /tax-tips/how-to-get-a-tax-break-for-summer-child-care/04152011-6163 (/%category%/%postname%/%month%%day%%year%-%post_id%)
 * '|/([^/]+)\/([^/]+)/([0-9]{1,2})([0-9]{1,2})([0-9]{4})-([0-9]{1,})/?|' => '|/$5/$3/$4/$2/|'
 *
 * @param array $vip_redirects_array Optional. Array of key/value pairs to redirect from/to.
 * @param bool $with_querystring Optional. Set this to true if your redirect string is in the format of an absolute URL. Defaults to false (just the path).
 *
 */
function vip_regex_redirects( $vip_redirects_array = array(), $with_querystring = false ) {

	if ( empty( $vip_redirects_array ) )
		return;

	$uri = $_SERVER['REQUEST_URI'];

	if ( ! $with_querystring )
		$uri = parse_url( $uri, PHP_URL_PATH );

	if( $uri && '/' != $uri ) { // don't process for homepage

		foreach ( $vip_redirects_array as $old_url => $new_url ) {
			if ( preg_match( $old_url, $uri, $matches ) ) {
				$redirect_uri = preg_replace( $old_url, $new_url, $uri );
				wp_redirect( $redirect_uri, 301 );
				exit;
			}
		}
	}
}

/**
 * Fetch a remote URL and cache the result for a certain period of time.
 *
 * This function originally used file_get_contents(), hence the function name.
 * While it no longer does, it still operates the same as the basic PHP function.
 *
 * We strongly recommend not using a $timeout value of more than 3 seconds as this
 * function makes blocking requests (stops page generation and waits for the response).
 *
 * The $extra_args are:
 *  * obey_cache_control_header: uses the "cache-control" "max-age" value if greater than $cache_time.
 *  * http_api_args: see http://codex.wordpress.org/Function_API/wp_remote_get
 *
 * @link http://lobby.vip.wordpress.com/best-practices/fetching-remote-data/ Fetching Remote Data
 * @param string $url URL to fetch
 * @param int $timeout Optional. The timeout limit in seconds; valid values are 1-10. Defaults to 3.
 * @param int $cache_time Optional. The minimum cache time in seconds. Valid values are >= 60. Defaults to 900.
 * @param array $extra_args Optional. Advanced arguments: "obey_cache_control_header" and "http_api_args".
 * @return string The remote file's contents (cached)
 */
function wpcom_vip_file_get_contents( $url, $timeout = 3, $cache_time = 900, $extra_args = array() ) {
	global $blog_id;

	$extra_args_defaults = array(
		'obey_cache_control_header' => true, // Uses the "cache-control" "max-age" value if greater than $cache_time
		'http_api_args' => array(), // See http://codex.wordpress.org/Function_API/wp_remote_get
	);

	$extra_args = wp_parse_args( $extra_args, $extra_args_defaults );

	$cache_key       = md5( serialize( array_merge( $extra_args, array( 'url' => $url ) ) ) );
	$backup_key      = $cache_key . '_backup';
	$disable_get_key = $cache_key . '_disable';
	$cache_group     = 'wpcom_vip_file_get_contents';

	// Temporary legacy keys to prevent mass cache misses during our key switch
	$old_cache_key       = md5( $url );
	$old_backup_key      = 'backup:' . $old_cache_key;
	$old_disable_get_key = 'disable:' . $old_cache_key;

	// Let's see if we have an existing cache already
	// Empty strings are okay, false means no cache
	if ( false !== $cache = wp_cache_get( $cache_key, $cache_group) )
		return $cache;

	// Legacy
	if ( false !== $cache = wp_cache_get( $old_cache_key, $cache_group) )
		return $cache;

	// The timeout can be 1 to 10 seconds, we strongly recommend no more than 3 seconds
	$timeout = min( 10, max( 1, (int) $timeout ) );

	if ( $timeout > 3 )
		_doing_it_wrong( __FUNCTION__, 'Using a timeout value of over 3 seconds is strongly discouraged because users have to wait for the remote request to finish before the rest of their page loads.', null );

	$server_up = true;
	$response = false;
	$content = false;

	// Check to see if previous attempts have failed
	if ( false !== wp_cache_get( $disable_get_key, $cache_group ) ) {
		$server_up = false;
	}
	// Legacy
	elseif ( false !== wp_cache_get( $old_disable_get_key, $cache_group ) ) {
		$server_up = false;
	}
	// Otherwise make the remote request
	else {
		$http_api_args = (array) $extra_args['http_api_args'];
		$http_api_args['timeout'] = $timeout;
		$response = wp_remote_get( $url, $http_api_args );
	}

	// Was the request successful?
	if ( $server_up && ! is_wp_error( $response ) && 200 == wp_remote_retrieve_response_code( $response ) ) {
		$content = wp_remote_retrieve_body( $response );

		$cache_header = wp_remote_retrieve_header( $response, 'cache-control' );
		if ( is_array( $cache_header ) )
			$cache_header = array_shift( $cache_header );

		// Obey the cache time header unless an arg is passed saying not to
		if ( $extra_args['obey_cache_control_header'] && $cache_header ) {
			$cache_header = trim( $cache_header );
			// When multiple cache-control directives are returned, they are comma separated
			foreach ( explode( ',', $cache_header ) as $cache_control ) {
				// In this scenario, only look for the max-age directive
				if( 'max-age' == substr( trim( $cache_control ), 0, 7 ) )
					// Note the array_pad() call prevents 'undefined offset' notices when explode() returns less than 2 results
					list( $cache_header_type, $cache_header_time ) = array_pad( explode( '=', trim( $cache_control ), 2 ), 2, null );
			}
			// If the max-age directive was found and had a value set that is greater than our cache time
			if ( isset( $cache_header_type ) && isset( $cache_header_time ) && $cache_header_time > $cache_time )
				$cache_time = (int) $cache_header_time; // Casting to an int will strip "must-revalidate", etc.
		}

		// The cache time shouldn't be less than a minute
		// Please try and keep this as high as possible though
		// It'll make your site faster if you do
		$cache_time = (int) $cache_time;
		if ( $cache_time < 60 )
			$cache_time = 60;

		// Cache the result
		wp_cache_set( $cache_key, $content, $cache_group, $cache_time );

		// Additionally cache the result with no expiry as a backup content source
		wp_cache_set( $backup_key, $content, $cache_group );

		// So we can hook in other places and do stuff
		do_action( 'wpcom_vip_remote_request_success', $url, $response );
	}
	// Okay, it wasn't successful. Perhaps we have a backup result from earlier.
	elseif ( $content = wp_cache_get( $backup_key, $cache_group ) ) {
		// If a remote request failed, log why it did
		if ( ! defined( 'WPCOM_VIP_DISABLE_REMOTE_REQUEST_ERROR_REPORTING' ) || ! WPCOM_VIP_DISABLE_REMOTE_REQUEST_ERROR_REPORTING ) {
			if ( $response && ! is_wp_error( $response ) ) {
				error_log( "wpcom_vip_file_get_contents: Blog ID {$blog_id}: Failure for $url and the result was: " . maybe_serialize( $response['headers'] ) . ' ' . maybe_serialize( $response['response'] ) );
			} elseif ( $response ) { // is WP_Error object
				error_log( "wpcom_vip_file_get_contents: Blog ID {$blog_id}: Failure for $url and the result was: " . maybe_serialize( $response ) );
			}
		}
	}
	// Legacy
	elseif ( $content = wp_cache_get( $old_backup_key, $cache_group ) ) {
		// If a remote request failed, log why it did
		if ( ! defined( 'WPCOM_VIP_DISABLE_REMOTE_REQUEST_ERROR_REPORTING' ) || ! WPCOM_VIP_DISABLE_REMOTE_REQUEST_ERROR_REPORTING ) {
			if ( $response && ! is_wp_error( $response ) ) {
				error_log( "wpcom_vip_file_get_contents: Blog ID {$blog_id}: Failure for $url and the result was: " . maybe_serialize( $response['headers'] ) . ' ' . maybe_serialize( $response['response'] ) );
			} elseif ( $response ) { // is WP_Error object
				error_log( "wpcom_vip_file_get_contents: Blog ID {$blog_id}: Failure for $url and the result was: " . maybe_serialize( $response ) );
			}
		}
	}
	// We were unable to fetch any content, so don't try again for another 60 seconds
	elseif ( $response ) {
		wp_cache_set( $disable_get_key, 1, $cache_group, 60 );

		// If a remote request failed, log why it did
		if ( ! defined( 'WPCOM_VIP_DISABLE_REMOTE_REQUEST_ERROR_REPORTING' ) || ! WPCOM_VIP_DISABLE_REMOTE_REQUEST_ERROR_REPORTING ) {
			if ( $response && ! is_wp_error( $response ) ) {
				error_log( "wpcom_vip_file_get_contents: Blog ID {$blog_id}: Failure for $url and the result was: " . maybe_serialize( $response['headers'] ) . ' ' . maybe_serialize( $response['response'] ) );
			} elseif ( $response ) { // is WP_Error object
				error_log( "wpcom_vip_file_get_contents: Blog ID {$blog_id}: Failure for $url and the result was: " . maybe_serialize( $response ) );
			}
		}
		// So we can hook in other places and do stuff
		do_action( 'wpcom_vip_remote_request_error', $url, $response );
	}

	return $content;
}

/**
 * Redirect http://blog.wordpress.com/feed/ to $target URL
 *
 * Don't redirect if a feed service user agent, because that could result in a loop.
 *
 * This can be executed before WP init because it checks the URI directly to see if the main feed is being requested.
 *
 * @author lloydbudd
 * @link http://vip.wordpress.com/documentation/redirect-the-feed-to-feedburner/ Redirect the Feed To Feedburner
 * @param string $target URL to direct feed services to
 */
function vip_main_feed_redirect( $target ) {
	if ( wpcom_vip_is_main_feed_requested() && !wpcom_vip_is_feedservice_ua() ) {
		wp_redirect( $target, '302' );
		die;
	}
}

/**
 * Returns if any of the formats of the main feed are requested
 *
 * @author lloydbudd
 * @return bool Returns true if main feed is requested
 */
function wpcom_vip_is_main_feed_requested() {
	$toMatch = '#^/(wp-(rdf|rss|rss2|atom|rssfeed).php|index.xml|feed|rss)/?$#i';
	$request = $_SERVER['REQUEST_URI'];
	return (bool) preg_match( $toMatch, $request );
}

/**
 * Returns if the current visitor has a feed service user agent
 *
 * @author lloydbudd
 * @return bool Returns true if the current visitor has a feed service user agent.
 */
function wpcom_vip_is_feedservice_ua() {
	if ( function_exists( 'wpcom_feed_cache_headers' ) ) {
		// Workaround so that no feed request served from nginx wpcom-feed-cache
		// If you are checking you must already know is a feed
		// and don't want any requests cached
		// ASSUMPTION: you've already confirmed is_feed() b/f calling
		// wpcom_vip_is_feedservice_ua
			header( "X-Accel-Expires: 0" );
	}

	return (bool) preg_match("/feedburner|feedvalidator|MediafedMetrics/i", $_SERVER["HTTP_USER_AGENT"]);
}

/**
 * Responds to a blog.wordpress.com/crossdomain.xml request with the contents of a crossdomain.xml file located in the root of your theme.
 *
 * @author lloydbudd
 */
function vip_crossdomain_redirect() {
	add_action( 'init', '_vip_crossdomain_redirect');
}

/**
 * Helper function for vip_crossdomain_redirect(); serves up /vip/your_theme/crossdomain.xml
 *
 * @see vip_crossdomain_redirect()
 */
function _vip_crossdomain_redirect() {
	$request = $_SERVER['REQUEST_URI'];
	if ( '/crossdomain.xml' == $request ) {
		header( 'Content-Type: text/xml' );
		echo file_get_contents( get_stylesheet_directory() . $request );
		exit();
	}
}

/**
 * Get random posts; a simple, more efficient approach..
 *
 * MySQL queries that use ORDER BY RAND() can be pretty challenging and slow on large datasets.
 * This function is an alternative method for getting random posts, though it's not as good but at least it won't destroy your site :).
 *
 * @param int $number Optional. Amount of random posts to get. Default 1.
 * @param string $post_type Optional. Specify the post_type to use when randomizing posts. Default 'post'.
 * @param bool $return_ids Optional. To just get the IDs, set this to true, otherwise post objects are returned (the default).
 * @return array
 */
function vip_get_random_posts( $number = 1, $post_type = 'post', $return_ids = false ) {
	$query = new WP_Query( array( 'posts_per_page' => 100, 'fields' => 'ids', 'post_type' => $post_type ) );

	$post_ids = $query->posts;
	shuffle( $post_ids );
	$post_ids = array_splice( $post_ids, 0, $number );

	if ( $return_ids )
		return $post_ids;

	$random_posts = get_posts( array( 'post__in' => $post_ids, 'numberposts' => count( $post_ids ), 'post_type' => $post_type ) );

	return $random_posts;
}

/**
 * This is a sophisticated extended version of wp_remote_get(). It is designed to more gracefully handle failure than wpcom_vip_file_get_contents() does.
 *
 * Note that like wp_remote_get(), this function does not cache.
 *
 * @author tottdev
 * @link http://vip.wordpress.com/documentation/fetching-remote-data/ Fetching Remote Data
 * @param string $url URL to fetch
 * @param string $fallback_value Optional. Set a fallback value to be returned if the external request fails.
 * @param int $threshold Optional. The number of fails required before subsequent requests automatically return the fallback value. Defaults to 3, with a maximum of 10.
 * @param int $timeout Optional. Number of seconds before the request times out. Valid values 1-3; defaults to 1.
 * @param int $retry Optional. Number of seconds before resetting the fail counter and the number of seconds to delay making new requests after the fail threshold is reached. Defaults to 20, with a minimum of 10.
 * @param array Optional. Set other arguments to be passed to wp_remote_get().
 * @return string|WP_Error|array Array of results. If fail counter is met, returns the $fallback_value, otherwise return WP_Error.
 * @see wp_remote_get()
 */
function vip_safe_wp_remote_get( $url, $fallback_value='', $threshold=3, $timeout=1, $retry=20, $args = array() ) {
	global $blog_id;

	$cache_group = "$blog_id:vip_safe_wp_remote_get";
	$cache_key = 'disable_remote_get_' . md5( parse_url( $url, PHP_URL_HOST ) );

	// valid url
	if ( empty( $url ) || !parse_url( $url ) )
		return ( $fallback_value ) ? $fallback_value : new WP_Error('invalid_url', $url );

	// Ensure positive values
	$timeout   = abs( $timeout );
	$retry     = abs( $retry );
	$threshold = abs( $threshold );

	// timeouts > 3 seconds are just not reasonable for production usage
	$timeout = ( (int) $timeout > 3 ) ? 3 : (int) $timeout;
	// retry time < 10 seconds will default to 10 seconds.
	$retry =  ( (int) $retry < 10 ) ? 10 : (int) $retry;
	// more than 10 faulty hits seem to be to much
	$threshold = ( (int) $threshold > 10 ) ? 10 : (int) $threshold;

	$option = wp_cache_get( $cache_key, $cache_group );

	// check if the timeout was hit and obey the option and return the fallback value
	if ( false !== $option && time() - $option['time'] < $retry ) {
		if ( $option['hits'] >= $threshold )
			return ( $fallback_value ) ? $fallback_value : new WP_Error('remote_get_disabled', 'Remote requests disabled: ' . maybe_serialize( $option ) );
	}

	$start = microtime( true );
	$response = wp_remote_get( $url, array_merge( $args, array( 'timeout' => $timeout ) ) );
	$end = microtime( true );

	$elapsed = ( $end - $start ) > $timeout;
	if ( true === $elapsed ) {
		if ( false !== $option && $option['hits'] < $threshold )
			wp_cache_set( $cache_key, array( 'time' => floor( $end ), 'hits' => $option['hits']+1 ), $cache_group, $retry );
		else if ( false !== $option && $option['hits'] == $threshold )
			wp_cache_set( $cache_key, array( 'time' => floor( $end ), 'hits' => $threshold ), $cache_group, $retry );
		else
			wp_cache_set( $cache_key, array( 'time' => floor( $end ), 'hits' => 1 ), $cache_group, $retry );
	}
	else {
		if ( false !== $option && $option['hits'] > 0 && time() - $option['time'] < $retry )
			wp_cache_set( $cache_key, array( 'time' => $option['time'], 'hits' => $option['hits']-1 ), $cache_group, $retry );
		else
			wp_cache_delete( $cache_key, $cache_group);
	}

	if ( is_wp_error( $response ) ) {
		// Log errors for internal WP.com debugging
		if ( ! defined( 'WPCOM_VIP_DISABLE_REMOTE_REQUEST_ERROR_REPORTING' ) || ! WPCOM_VIP_DISABLE_REMOTE_REQUEST_ERROR_REPORTING ) {
			error_log( "vip_safe_wp_remote_get: Blog ID {$blog_id}: Fetching $url with a timeout of $timeout failed. Result: " . maybe_serialize( $response ) );
		}
		do_action( 'wpcom_vip_remote_request_error', $url, $response );

		return ( $fallback_value ) ? $fallback_value : $response;
	}

	return $response;
}

/**
 * Returns profile information for a WordPress/Gravatar user
 *
 * @param string|int $email_or_id Email, ID, or username for user to lookup
 * @return false|array Profile info formatted as noted here: http://en.gravatar.com/site/implement/profiles/php/. If user not found, returns false.
 */
function wpcom_vip_get_user_profile( $email_or_id ) {

	if ( is_numeric( $email_or_id ) ) {
		$user = get_user_by( 'id', $email_or_id );
		if ( ! $user )
			return false;

		$email = $user->user_email;
	} elseif ( is_email( $email_or_id ) ) {
		$email = $email_or_id;
	} else {
		$user_login = sanitize_user( $email_or_id, true );
		$user = get_user_by( 'login', $user_login );
		if ( ! $user )
			return;

		$email = $user->user_email;
	}

	$hashed_email = md5( strtolower( trim( $email ) ) );
	$profile_url = esc_url_raw( sprintf( '%s.gravatar.com/%s.php', ( is_ssl() ? 'https://secure' : 'http://www' ), $hashed_email ), array( 'http', 'https' ) );

	$profile = wpcom_vip_file_get_contents( $profile_url, 1, 900 );
	if ( $profile ) {
		$profile = unserialize( $profile );

		if ( is_array( $profile ) && ! empty( $profile['entry'] ) && is_array( $profile['entry'] ) ) {
			$profile = $profile['entry'][0];
		} else {
			$profile = false;
		}
	}
	return $profile;
}

/**
 * Checks to see if a given e-mail address has a Gravatar or not.
 *
 * You can use this function to only call get_avatar() when the user has a Gravatar and display nothing (rather than a placeholder image) when they don't.
 *
 * @param string $email Email to check for a gravatar
 * @return bool Returns true if $email has a gravatar
 */
function wpcom_vip_email_has_gravatar( $email ) {

	$hash = md5( strtolower( trim( $email ) ) );

	// If not in the cache, check again
	if ( false === $has_gravatar = wp_cache_get( $hash, 'email_has_gravatar' ) ) {

		$request = wp_remote_head( 'http://0.gravatar.com/avatar/' . $hash . '?d=404' );

		$has_gravatar = ( 404 == wp_remote_retrieve_response_code( $request ) ) ? 0 : 1;

		wp_cache_set( $hash, $has_gravatar, 'email_has_gravatar', 86400 ); // Check daily
	}

	return (bool) $has_gravatar;
}

/**
 * Check if a URL is in a specified whitelist
 *
 * Example whitelist: array( 'mydomain.com', 'mydomain.net' )
 *
 * @param string $url URL to check for
 * @param array $whitelisted_domains Array of whitelisted domains
 * @return bool Returns true if $url is in the $whitelisted_domains
 */
function wpcom_vip_is_valid_domain( $url, $whitelisted_domains ) {
	$domain = parse_url( $url, PHP_URL_HOST );

	if ( ! $domain )
		return false;

	// Check if we match the domain exactly
	if ( in_array( $domain, $whitelisted_domains ) )
		return true;

	$valid = false;

	foreach( $whitelisted_domains as $whitelisted_domain ) {
		$whitelisted_domain = '.' . $whitelisted_domain; // Prevent things like 'evilsitetime.com'
		if( strpos( $domain, $whitelisted_domain ) === ( strlen( $domain ) - strlen( $whitelisted_domain ) ) ) {
			$valid = true;
			break;
		}
	}
	return $valid;
}

/**
 * Helper function to enable bulk user management on a per-user basis
 *
 * @param array $users Array of user logins
 */
function wpcom_vip_bulk_user_management_whitelist( $users ) {
	add_filter( 'bulk_user_management_admin_users', function() use ( $users ) { return $users; } );
}

/**
 * A version of wp_oembed_get() that provides caching.
 *
 * Note that if you're using this within the contents of a post, it's probably better to use the existing
 * WordPress functionality: http://codex.wordpress.org/Embeds. This helper function is more meant for other
 * places, such as sidebars.
 *
 * @param string $url The URL that should be embedded
 * @param array $args Addtional arguments and parameters the embed
 * @return string
 */
function wpcom_vip_wp_oembed_get( $url, $args = array() ) {
	$cache_key = md5( $url . '|' . serialize( $args ) );

	if ( false === $html = wp_cache_get( $cache_key, 'wpcom_vip_wp_oembed_get' ) ) {
		$html = wp_oembed_get( $url, $args );

		wp_cache_set( $cache_key, $html, 'wpcom_vip_wp_oembed_get' );
	}

	return $html;
}

/**
 * Loads a plugin out of our shared plugins directory.
 *
 * Note - This function does not trigger plugin activation / deactivation hooks.
 * As such, it may not be compatible with all plugins
 *
 * @link http://lobby.vip.wordpress.com/plugins/ VIP Shared Plugins
 * @param string $plugin Optional. Plugin folder name of the plugin, or the folder and
 * plugin file name (such as wp-api/plugin.php), relative to either the VIP shared-plugins folder, or WP_PLUGIN_DIR
 * @param string $folder Deprecated. No longer used
 * @param bool $load_release_candidate Whether to load a release candidate version of this plugin, if available
 * @return bool True if the include was successful
 */
function wpcom_vip_load_plugin( $plugin = false, $folder_not_used = null, $load_release_candidate = false ) {
	// Make sure there's a plugin to load
	if ( empty($plugin) ) {
		if ( ! WPCOM_IS_VIP_ENV ) {
			die( 'wpcom_vip_load_plugin() was called without a first parameter!' );
		}
	}

	// Is it a plugin file with multiple directories? Not supported. _could_ be supported
	// with some refactoring, but no real need to, just complicates things
	$exploded = explode( DIRECTORY_SEPARATOR, $plugin );

	if ( count( $exploded ) > 2 ) {
		if ( ! WPCOM_IS_VIP_ENV ) {
			die( 'wpcom_vip_load_plugin() was called with multiple subdirectories' );
		} else {
			_doing_it_wrong( 'wpcom_vip_load_plugin', 'Subdirectories not supported in file paths' );

			return false;
		}
	}

	// Is this a valid path? We know it has less than 3 parts, but it could still be
	// 'plugin/plugin' (need a php file, if specifying a path rather than a slug)
	if ( count( $exploded ) === 2 ) {
		$pathinfo = pathinfo( $plugin );

		if ( ! isset( $pathinfo['extension'] ) || 'php' !== $pathinfo['extension'] ) {
			if ( ! WPCOM_IS_VIP_ENV ) {
				die( 'wpcom_vip_load_plugin() was called with a path, but no php file was specified' );
			} else {
				_doing_it_wrong( 'wpcom_vip_load_plugin', 'Must specify php file when loading via path' );

				return false;
			}
		}
	}

	// Array of files to check for loading the plugin. This is to support
	// non-standard plugin structures, such as $folder/plugin.php
	$test_files = array(
		"{$plugin}.php",
		'plugin.php'
	);

	// Is $plugin a filepath? If so, that's the only file we should test
	if ( basename( $plugin ) !== $plugin ) {
		$test_files = array( basename( $plugin ) );

		// Update the $plugin to the slug, so we store it correctly and build paths correctly
		$plugin = dirname( $plugin );
	}

	// Make sure $plugin and $folder are valid
	$plugin = _wpcom_vip_load_plugin_sanitizer( $plugin );

	// Array of directories to check for the above files in, in priority order
	$test_directories = array();

	if ( true === $load_release_candidate ) {
		$test_directories[] = WP_CONTENT_DIR . '/mu-plugins/shared-plugins/release-candidates';
	}

	$test_directories[] = WP_CONTENT_DIR . '/mu-plugins/shared-plugins';
	$test_directories[] = WP_PLUGIN_DIR;

	$includepath = null;
	$plugin_type = null;

	foreach ( $test_directories as $directory ) {
		foreach ( $test_files as $file ) {
			// Prevent any traversal here
			$plugin = basename( $plugin ); // Just to be double, extra sure
			$file   = basename( $file );

			$path = "{$directory}/{$plugin}/{$file}";

			if ( file_exists( $path ) ) {
				$includepath = $path;

				// Store where we found it, so we can properly represent that in UI
				// This is usually the directory above
				$plugin_type = basename( $directory );

				// release-candidates is a special case, as it's in a nested folder,
				// so we must look up one level
				if ( 'release-candidates' === $plugin_type ) {
					$plugin_type = dirname( $directory );
				}

				// We found what we were looking for, break from both loops
				break 2;
			}
		}
	}

	if ( $includepath && file_exists( $includepath ) ) {
		wpcom_vip_add_loaded_plugin( "{$plugin_type}/{$plugin}" );

		return _wpcom_vip_include_plugin( $includepath );
	} else {
		if ( ! WPCOM_IS_VIP_ENV ) {
			die( "Unable to load $plugin using wpcom_vip_load_plugin()!" );
		}
	}
}

function _wpcom_vip_include_plugin( $file ) {
	// Since we're going to be include()'ing inside of a function,
	// we need to do some hackery to get the variable scope we want.
	// See http://www.php.net/manual/en/language.variables.scope.php#91982

	// Start by marking down the currently defined variables (so we can exclude them later)
	$pre_include_variables = get_defined_vars();

	// Now include
	include_once( $file );

	// Blacklist out some variables
	$blacklist = array( 'blacklist' => 0, 'pre_include_variables' => 0, 'new_variables' => 0 );

	// Let's find out what's new by comparing the current variables to the previous ones
	$new_variables = array_diff_key( get_defined_vars(), $GLOBALS, $blacklist, $pre_include_variables );

	// global each new variable
	foreach ( $new_variables as $new_variable => $devnull )
		global $$new_variable;

	// Set the values again on those new globals
	extract( $new_variables );

	return true;
}

/**
 * Is the given user an automattician?
 *
 * Note: This does a relatively weak check based on email address and their
 * VIP Support email address verification status (separate from other email verification)
 * It's possible to fake that data (it's just meta and user_email), so don't use this
 * for protecting sensitive info or performing sensitive tasks
 *
 * @param int The WP User id
 * @return bool Bool indicating if user is an Automattician
 */
function is_automattician( $user_id = false ) {
	if ( $user_id ) {
		$user = new WP_User( $user_id );
	} else {
		$user = wp_get_current_user();
	}

	if ( ! isset( $user->ID ) || ! $user->ID ) {
		return false;
	}

	// Check that their address is an a8c one, *and* they have validated that address
	if ( ! class_exists( 'WPCOM_VIP_Support_User' ) ) {
		return false;
	}

	$vip_support = WPCOM_VIP_Support_User::init();

	if ( WPCOM_VIP_Support_User::is_valid_automattician( $user->ID ) ) {
		return true;
	}

	return false;
}
