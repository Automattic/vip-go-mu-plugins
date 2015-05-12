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
 * Loads a plugin out of our shared plugins directory.
 *
 * @link http://lobby.vip.wordpress.com/plugins/ VIP Shared Plugins
 * @param string $plugin Optional. Plugin folder name (and filename) of the plugin
 * @param string $folder Optional. Folder to include from; defaults to "plugins". Useful for when you have multiple themes and your own shared plugins folder.
 * @return bool True if the include was successful
 */
function wpcom_vip_load_plugin( $plugin = false, $folder = 'plugins', $load_release_candidate = false ) {

	// Make sure there's a plugin to load
	if ( empty($plugin) ) {
		// On WordPress.com, use an internal function to message VIP about a bad call to this function
		if ( function_exists( 'wpcom_is_vip' ) ) {
			if ( function_exists( 'send_vip_team_debug_message' ) ) {
				// Use an expiring cache value to avoid spamming messages
				if ( ! wp_cache_get( 'noplugin', 'wpcom_vip_load_plugin' ) ) {
					send_vip_team_debug_message( 'WARNING: wpcom_vip_load_plugin() is being called without a $plugin parameter', 1 );
					wp_cache_set( 'noplugin', 1, 'wpcom_vip_load_plugin', 3600 );
				}
			}
			return false;
		}
		// die() in non-WordPress.com environments so you know you made a mistake
		else {
			die( 'wpcom_vip_load_plugin() was called without a first parameter!' );
		}
	}

	// Make sure $plugin and $folder are valid
	$plugin = _wpcom_vip_load_plugin_sanitizer( $plugin );
	if ( 'plugins' !== $folder )
		$folder = _wpcom_vip_load_plugin_sanitizer( $folder );

	// Shared plugins are located at /wp-content/themes/vip/plugins/example-plugin/
	// You should keep your local copies of the plugins in the same location

	$includepath 					= WP_CONTENT_DIR . "/themes/vip/$folder/$plugin/$plugin.php";
	$release_candidate_includepath 	= WP_CONTENT_DIR . "/themes/vip/$folder/release-candidates/$plugin/$plugin.php";

	if( true === $load_release_candidate && file_exists( $release_candidate_includepath ) ) {
		$includepath = $release_candidate_includepath;
	}

	if ( file_exists( $includepath ) ) {

		wpcom_vip_add_loaded_plugin( "$folder/$plugin" );

		// Since we're going to be include()'ing inside of a function,
		// we need to do some hackery to get the variable scope we want.
		// See http://www.php.net/manual/en/language.variables.scope.php#91982

		// Start by marking down the currently defined variables (so we can exclude them later)
		$pre_include_variables = get_defined_vars();

		// Now include
		include_once( $includepath );

		// If there's a wpcom-helper file for the plugin, load that too
		$helper_path = WP_CONTENT_DIR . "/themes/vip/$folder/$plugin/wpcom-helper.php";
		if ( file_exists( $helper_path ) )
			require_once( $helper_path );

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
	} else {
		// On WordPress.com, use an internal function to message VIP about the bad call to this function
		if ( function_exists( 'wpcom_is_vip' ) ) {
			if ( function_exists( 'send_vip_team_debug_message' ) ) {
				// Use an expiring cache value to avoid spamming messages
				$cachekey = md5( $folder . '|' . $plugin );
				if ( ! wp_cache_get( "notfound_$cachekey", 'wpcom_vip_load_plugin' ) ) {
					send_vip_team_debug_message( "WARNING: wpcom_vip_load_plugin() is trying to load a non-existent file ( /$folder/$plugin/$plugin.php )", 1 );
					wp_cache_set( "notfound_$cachekey", 1, 'wpcom_vip_load_plugin', 3600 );
				}
			}
			return false;

		// die() in non-WordPress.com environments so you know you made a mistake
		} else {
			die( "Unable to load $plugin ({$folder}) using wpcom_vip_load_plugin()!" );
		}
	}
}

/**
 * Helper function for wpcom_vip_load_plugin(); sanitizes plugin folder name.
 *
 * You shouldn't use this function.
 *
 * @param string $folder Folder name
 * @return string Sanitized folder name
 */
function _wpcom_vip_load_plugin_sanitizer( $folder ) {
	$folder = preg_replace( '#([^a-zA-Z0-9-_.]+)#', '', $folder );
	$folder = str_replace( '..', '', $folder ); // To prevent going up directories

	return $folder;
}

/**
 * Require a library in the VIP shared code library.
 *
 * @param string $slug
 */
function wpcom_vip_require_lib( $slug ) {
	if ( !preg_match( '|^[a-z0-9/_.-]+$|i', $slug ) ) {
		trigger_error( "Cannot load a library with invalid slug $slug.", E_USER_ERROR );
		return;
	}
	$basename = basename( $slug );
	$lib_dir = WP_CONTENT_DIR . '/themes/vip/plugins/lib';
	$choices = array(
		"$lib_dir/$slug.php",
		"$lib_dir/$slug/0-load.php",
		"$lib_dir/$slug/$basename.php",
	);
	foreach( $choices as $file_name ) {
		if ( is_readable( $file_name ) ) {
			require_once $file_name;
			return;
		}
	}
	trigger_error( "Cannot find a library with slug $slug.", E_USER_ERROR );
}

/**
 * Store the name of a VIP plugin that will be loaded
 *
 * @param string $plugin Plugin name and folder
 * @see wpcom_vip_load_plugin()
 */
function wpcom_vip_add_loaded_plugin( $plugin ) {
	global $vip_loaded_plugins;

	if ( ! isset( $vip_loaded_plugins ) )
		$vip_loaded_plugins = array();

	array_push( $vip_loaded_plugins, $plugin );
}

/**
 * Get the names of VIP plugins that have been loaded
 *
 * @return array
 */
function wpcom_vip_get_loaded_plugins() {
	global $vip_loaded_plugins;

	if ( ! isset( $vip_loaded_plugins ) )
		$vip_loaded_plugins = array();

	return $vip_loaded_plugins;
}

/**
 * Returns the raw path to the VIP themes dir.
 *
 * @return string
 */
function wpcom_vip_themes_root() {
	return WP_CONTENT_DIR . '/themes/vip';
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
		return home_url( '/wp-content/themes/vip' );
	} else {
		return content_url( '/themes/vip' );
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
 * Filter plugins_url() so that it works for plugins inside the shared VIP plugins directory or a theme directory.
 *
 * Props to the GigaOm dev team for coming up with this method.
 *
 * @param string $url Optional. Absolute URL to the plugins directory.
 * @param string $path Optional. Path relative to the plugins URL.
 * @param string $plugin Optional. The plugin file that you want the URL to be relative to.
 * @return string
 */
function wpcom_vip_plugins_url( $url = '', $path = '', $plugin = '' ) {
	static $content_dir, $vip_dir, $vip_url;

	if ( ! isset( $content_dir ) ) {
		// Be gentle on Windows, borrowed from core, see plugin_basename
		$content_dir = str_replace( '\\','/', WP_CONTENT_DIR ); // sanitize for Win32 installs
		$content_dir = preg_replace( '|/+|','/', $content_dir ); // remove any duplicate slash
	}

	if ( ! isset( $vip_dir ) ) {
		$vip_dir = $content_dir . '/themes/vip';
	}

	if ( ! isset( $vip_url ) ) {
		$vip_url = content_url( '/themes/vip' );
	}

	// Don't bother with non-VIP or non-path URLs
	if ( ! $plugin || 0 !== strpos( $plugin, $vip_dir ) ) {
		return $url;
	}

	if( 0 === strpos( $plugin, $vip_dir ) )
		$url_override = str_replace( $vip_dir, $vip_url, dirname( $plugin ) );
	elseif  ( 0 === strpos( $plugin, get_stylesheet_directory() ) )
		$url_override = str_replace(get_stylesheet_directory(), get_stylesheet_directory_uri(), dirname( $plugin ) );

	if ( isset( $url_override ) )
		$url = trailingslashit( $url_override ) . $path;

	return $url;
}
add_filter( 'plugins_url', 'wpcom_vip_plugins_url', 10, 3 );

/**
 * Return a URL for given VIP theme and path. Does not work with VIP shared plugins.
 *
 * @param string $path Optional. Path to suffix to the theme URL.
 * @param string $theme Optional. Name of the theme folder.
 * @return string|bool URL for the specified theme and path. If path doesn't exist, returns false.
 */
function wpcom_vip_theme_url( $path = '', $theme = '' ) {
	if ( empty( $theme ) )
		$theme = str_replace( 'vip/', '', get_stylesheet() );

	// We need to reference a file in the specified theme; style.css will almost always be there.
	$theme_folder = sprintf( '%s/themes/vip/%s', WP_CONTENT_DIR, $theme );
	$theme_file = $theme_folder . '/style.css';

	// For local environments where the theme isn't under /themes/vip/themename/
	$theme_folder_alt = sprintf( '%s/themes/%s', WP_CONTENT_DIR, $theme );
	$theme_file_alt = $theme_folder_alt . '/style.css';

	$path = ltrim( $path, '/' );

	// We pass in a dummy file to plugins_url even if it doesn't exist, otherwise we get a URL relative to the parent of the theme folder (i.e. /themes/vip/)
	if ( is_dir( $theme_folder ) ) {
		return plugins_url( $path, $theme_file );
	} elseif ( is_dir( $theme_folder_alt ) ) {
		return plugins_url( $path, $theme_file_alt );
	}

	return false;
}

/**
 * Return the directory path for a given VIP theme
 *
 * @link http://vip.wordpress.com/documentation/mobile-theme/ Developing for Mobile Phones and Tablets
 * @param string $theme Optional. Name of the theme folder
 * @return string Path for the specified theme
 */
function wpcom_vip_theme_dir( $theme = '' ) {
	if ( empty( $theme ) )
		$theme = get_stylesheet();

	// Simple sanity check, in case we get passed a lame path
	$theme = ltrim( $theme, '/' );
	$theme = str_replace( 'vip/', '', $theme );

	return sprintf( '%s/themes/vip/%s', WP_CONTENT_DIR, $theme );
}


/**
 * VIPs and other themes can declare the permastruct, tag and category bases in their themes.
 * This is done by filtering the option.
 *
 * To ensure we're using the freshest values, and that the option value is available earlier
 * than when the theme is loaded, we need to get each option, save it again, and then
 * reinitialize wp_rewrite.
 *
 * On WordPress.com this happens auto-magically when theme updates are deployed
 */
function wpcom_vip_local_development_refresh_wp_rewrite() {
	// No-op on WordPress.com
	if ( defined( 'WPCOM_IS_VIP_ENV' ) && WPCOM_IS_VIP_ENV )
		return;

	global $wp_rewrite;

	// Permastructs available in the options table and their core defaults
	$permastructs = array(
			'permalink_structure',
			'category_base',
			'tag_base',
		);

	$needs_flushing = false;

	foreach( $permastructs as $option_key ) {
		$filter = 'pre_option_' . $option_key;
		$callback = '_wpcom_vip_filter_' . $option_key;

		$option_value = get_option( $option_key );
		$filtered = has_filter( $filter, $callback );
		if ( $filtered ) {
			remove_filter( $filter, $callback, 99 );
			$raw_option_value = get_option( $option_key );
			add_filter( $filter, $callback, 99 );

			// Are we overriding this value in the theme?
			if ( $option_value != $raw_option_value ) {
				$needs_flushing = true;
				update_option( $option_key, $option_value );
			}
		}

	}

	// If the options are different from the theme let's fix it.
	if ( $needs_flushing ) {
		// Reconstruct WP_Rewrite and make sure we persist any custom endpoints, etc.
		$old_values = array();
		$custom_rules = array(
				'extra_rules',
				'non_wp_rules',
				'endpoints',
			);
		foreach( $custom_rules as $key ) {
			$old_values[$key] = $wp_rewrite->$key;
		}
		$wp_rewrite->init();
		foreach( $custom_rules as $key ) {
			$wp_rewrite->$key = array_merge( $old_values[$key], $wp_rewrite->$key );
		}

		flush_rewrite_rules( false );
	}
}
if ( defined( 'WPCOM_IS_VIP_ENV' ) && ! WPCOM_IS_VIP_ENV ) {
	add_action( 'init', 'wpcom_vip_local_development_refresh_wp_rewrite', 9999 );
}


/**
 * If you don't want people (de)activating plugins via this UI
 * and only want to enable plugins via wpcom_vip_load_plugin()
 * calls in your theme's functions.php file, then call this
 * function to disable this plugin's (de)activation links.
 */
function wpcom_vip_plugins_ui_disable_activation() {
	//The Class is not loaded on local environments
	if ( class_exists( "WPcom_VIP_Plugins_UI" )){
		WPcom_VIP_Plugins_UI()->activation_disabled = true;
	}
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
			return '<a href="' . esc_url( vip_powered_wpcom_url() ) . '" rel="generator nofollow" class="powered-by-wpcom"><img src="' . esc_url( plugins_url( 'images/' . $vip_powered_wpcom_images[$image][0], __FILE__ ) ) . '" width="' . esc_attr( $vip_powered_wpcom_images[$image][1] ) . '" height="' . esc_attr( $vip_powered_wpcom_images[$image][2] ) . '" alt="'. esc_attr__( 'Powered by WordPress.com VIP' ) .'" /></a>';
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
 * Remove the tracking bug added to all WordPress.com feeds.
 *
 * Helper function for wpcom_vip_disable_enhanced_feeds().
 *
 * @see wpcom_vip_disable_enhanced_feeds()
 */
function wpcom_vip_remove_feed_tracking_bug() {
	remove_filter( 'the_content', 'add_bug_to_feed', 100 );
	remove_filter( 'the_excerpt_rss', 'add_bug_to_feed', 100 );
}

/**
 * Returns the URL to an image resized and cropped to the given dimensions.
 *
 * You can use this image URL directly -- it's cached and such by our servers.
 * Please use this function to generate the URL rather than doing it yourself as
 * this function uses staticize_subdomain() makes it serve off our CDN network.
 *
 * Somewhat contrary to the function's name, it can be used for ANY image URL, hosted by us or not.
 * So even though it says "remote", you can use it for attachments hosted by us, etc.
 *
 * @link http://vip.wordpress.com/documentation/image-resizing-and-cropping/ Image Resizing And Cropping
 * @param string $url The raw URL to the image (URLs that redirect are currently not supported with the exception of http://foobar.wordpress.com/files/ type URLs)
 * @param int $width The desired width of the final image
 * @param int $height The desired height of the final image
 * @param bool $escape Optional. If true (the default), the URL will be run through esc_url(). Set this to false if you need the raw URL.
 * @return string
 */
function wpcom_vip_get_resized_remote_image_url( $url, $width, $height, $escape = true ) {
	$width = (int) $width;
	$height = (int) $height;

	if ( ! function_exists( 'wpcom_is_vip' ) || ! wpcom_is_vip() )
		return ( $escape ) ? esc_url( $url ) : $url;

	// Photon doesn't support redirects, so help it out by doing http://foobar.wordpress.com/files/ to http://foobar.files.wordpress.com/
	if ( function_exists( 'new_file_urls' ) )
		$url = new_file_urls( $url );

	$thumburl = jetpack_photon_url( $url, array( 'resize' => array( $width, $height ) ) );

	return ( $escape ) ? esc_url( $thumburl ) : $thumburl;
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

	$url = add_query_arg( array(
		'w' => intval( $width ),
		'h' => intval( $height ),
	), $url );

	if ( $crop ) {
		$url = add_query_arg( 'crop', 1, $url );
	}

	return $url;
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

function wpcom_initiate_flush_rewrite_rules() {
	flush_rewrite_rules( false );
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
