<?php

/**
 * Loads the shared VIP helper file which defines some helpful functions.
 *
 * @deprecated Not applicable since VIP 2.0.0
 * @link http://vip.wordpress.com/documentation/development-environment/ Setting up your Development Environment
 */
function wpcom_vip_load_helper() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Loads the WordPress.com-only VIP helper file which defines some helpful functions.
 *
 * @deprecated Not applicable since VIP 2.0.0
 */
function wpcom_vip_load_helper_wpcom() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Loads the WordPress.com-only VIP helper file for stats which defines some helpful stats-related functions.
 *
 * @deprecated Not applicable since VIP 2.0.0
 */
function wpcom_vip_load_helper_stats() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Disable the WordPress.com filter that prevents orphans in titles
 *
 * See http://en.blog.wordpress.com/2006/12/24/no-orphans-in-titles/
 *
 * @author nickmomrik
 * @deprecated Not applicable since VIP 2.0.0
 */
function vip_allow_title_orphans() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Completely disable enhanced feed functionality
 *
 * @deprecated Not applicable since VIP 2.0.0
 */
function wpcom_vip_disable_enhanced_feeds() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Do not display the images in enhanced feeds.
 *
 * Helper function for wpcom_vip_disable_enhanced_feeds().
 *
 * @author nickmomrik
 * @deprecated Not applicable since VIP 2.0.0
 * @see wpcom_vip_disable_enhanced_feeds()
 */
function vip_remove_enhanced_feed_images() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

if ( ! function_exists( 'make_tag_local' ) ) {

/**
 * Keeps category and tag links local to the blog instead of linking to http://en.wordpress.com/tags/
 *
 * @deprecated No longer supported since VIP 2.0.0
 */
function make_tags_local() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

}

if ( ! function_exists( 'get_top_posts' ) ) {

/**
 * Gets the data used by the "Top Posts" widget.
 *
 * Our Top Posts widget (http://en.support.wordpress.com/widgets/top-posts-widget/) uses a display_top_posts() function to display a list of popular posts.
 * You can use this function in your themes. The function uses data from WordPress.com Stats (http://en.support.wordpress.com/stats/) to generate the list.
 *
 * If you would like more control over the output of display_top_posts(), use the get_top_posts() function.
 *
 * Note: in the results, post_ID = 0 is used to track home page views.
 *
 * @deprecated No longer supported since VIP 2.0.0
 * @param int $number Optional. At least 10 posts are always returned; this parameter controls how many extra you want. Valid values: 1-10 (default is 10).
 * @param int $days Optional. How many days of stats should be used in the calculation; defaults to 2.
 * @return array
 */
function get_top_posts( $number = 10, $days = 2 ) {
    _deprecated_function( __FUNCTION__, '2.0.0', 'wpcom_vip_top_posts_array' );

    return array();
}

}


/**
 * Overrides a theme's $content_width to remove the image constraint.
 *
 * @deprecated Not applicable since VIP 2.0.0
 * @author nickmomrik
 */
function wpcom_vip_allow_full_size_images_for_real() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Do not display the Polldaddy rating.
 *
 * Usually used for a page or post where ratings are not wanted.
 *
 * @deprecated Not applicable since VIP 2.0.0
 * @author nickmomrik
 */
function wpcom_vip_remove_polldaddy_rating() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Disable the post-post screen
 */
function wpcom_vip_disable_postpost() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Outputs Open Graph tags to various pages on the site
 *
 * @deprecated Deprecated since VIP 2.0.0 - Use the `jetpack_enable_open_graph` filter
 * @link http://vip.wordpress.com/documentation/open-graph/ Adding Open Graph Tags
 * @see http://developers.facebook.com/docs/opengraph/ Open Graph
 */
function wpcom_vip_enable_opengraph() {
	_deprecated_function( __FUNCTION__, '2.0.0', '`jetpack_enable_open_graph` filter' );
}

/**
 * Queries posts by a postmeta key/value pair directly from the master database.
 *
 * This is not intended for front-end usage. This purpose of this function is to avoid race conditions that could appear while the caches are primed.
 * A good scenario where this could be used is to ensure published posts are not syndicated multiple times by checking if a post with a certain meta value already exists.
 *
 * @deprecated No longer supported since VIP 2.0.0
 * @param string $meta_key Post meta key to query
 * @param string $meta_value Post meta value to check for
 * @param string $post_type Optional; post_type of the post to query. Defaults to 'post'.
 * @param array $post_stati Optional; array of the post_stati that are supposed to be included. Defaults to: publish, draft, pending, private.
 * @param integer $limit Optional. Amount of possible posts to receive; not more than 10. Default is 1.
 * @return array|WP_Error Array with post objects or a WP_Error
 */
function wpcom_uncached_get_post_by_meta( $meta_key, $meta_value, $post_type = 'post', $post_stati = array( 'publish', 'draft', 'pending', 'private' ), $limit = 1 ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );

    // No longer supported function

    return array();
}

/**
 * Removes the mobile app promotion from the bottom of the default mobile theme.
 *
 * Example: "Now Available! Download WordPress for iOS"
 *
 * @deprecated Not applicable since VIP 2.0.0
 */
function wpcom_disable_mobile_app_promotion() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Deprecated function. This used to work around a term ordering issue on a very old version of WordPress. No longer needed.
 *
 * @deprecated
 * @return bool Always returns false
 */
function wpcom_vip_enable_term_order_functionality() {
    _deprecated_function( __FUNCTION__, '2.0.0' );

    return false;
}

/**
 * Allows non-author users to submit any tags allowed via $allowedposttags instead of just $allowedtags
 *
 * @deprecated Not applicable since VIP 2.0.0
 */
function wpcom_vip_allow_more_html_in_comments() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Remove devicepx.js from pageloads
 *
 * devicepx.js loads retina/HiDPI versions of certain files (Gravatars, etc) for devices that run at a
 * higher resolution (such as smartphones), and is distributed inside Jetpack.

 * @deprecated Not applicable since VIP 2.0.0
 */
function wpcom_vip_disable_devicepx_js() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Allow VIP themes to disable hovercard functionality and removes the scripts.
 *
 * @deprecated Not applicable since VIP 2.0.0
 */
function wpcom_vip_disable_hovercards() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Allow VIP themes to disable global terms on WordPress.com.
 *
 * @deprecated Deprecated since VIP 2.0.0 - Apply the filter directly
 */
function wpcom_vip_disable_global_terms() {
    _deprecated_function( __FUNCTION__, '2.0.0' );

    add_filter( 'global_terms_enabled', '__return_false' );
}

/**
 * Disables the WordPress.com-specific Customizer and Custom Design
 *
 * @deprecated Not applicable since VIP 2.0.0
 */
function wpcom_vip_disable_custom_customizer() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

function wpcom_vip_remove_opensearch(){
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Update a user's attribute
 *
 * There is no need to serialize values, they will be serialized if it is
 * needed. The metadata key can only be a string with underscores. All else will
 * be removed.
 *
 * Will remove the attribute, if the meta value is empty.
 *
 * @deprecated Deprecated since VIP 2.0.0
 * @param int $user_id User ID
 * @param string $meta_key Metadata key.
 * @param mixed $meta_value Metadata value.
 * @return bool True on successful update, false on failure.
 */
function update_user_attribute( $user_id, $meta_key, $meta_value ) {
    _deprecated_function( __FUNCTION__, '2.0.0', 'update_user_meta' );

    do_action( 'updating_user_attribute', $user_id, $meta_key, $meta_value );

    $result = update_user_meta( $user_id, $meta_key, $meta_value );

    if ( $result )
        do_action( 'updated_user_attribute', $user_id, $meta_key, $meta_value );

    return $result;
}

/**
 * Retrieve user attribute data.
 *
 * If $user_id is not a number, then the function will fail over with a 'false'
 * boolean return value. Other returned values depend on whether there is only
 * one item to be returned, which be that single item type. If there is more
 * than one metadata value, then it will be list of metadata values.
 *
 * @deprecated Deprecated since VIP 2.0.0
 * @param int $user_id User ID
 * @param string $meta_key Optional. Metadata key.
 * @return mixed
 */
function get_user_attribute( $user_id, $meta_key ) {
    _deprecated_function( __FUNCTION__, '2.0.0', 'get_user_meta' );

    if ( !$usermeta = get_user_meta( $user_id, $meta_key ) )
        return false;

    if ( count($usermeta) == 1 )
        return reset($usermeta);

    return $usermeta;
}

/**
 * Remove user attribute data.
 *
 * @uses $wpdb WordPress database object for queries.
 *
 * @deprecated Deprecated since VIP 2.0.0
 * @param int $user_id User ID.
 * @param string $meta_key Metadata key.
 * @param mixed $meta_value Optional. Metadata value.
 * @return bool True deletion completed and false if user_id is not a number.
 */
function delete_user_attribute( $user_id, $meta_key, $meta_value = '' ) {
    _deprecated_function( __FUNCTION__, '2.0.0', 'delete_user_meta' );

    $result = delete_user_meta( $user_id, $meta_key, $meta_value );

    do_action( 'deleted_user_attribute', $user_id, $meta_key, $meta_value );

    return $result;
}

function vary_cache_on_function( $function ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

if ( ! function_exists( 'disable_autosave' ) ) {

/**
 * Disable post autosave
 *
 * @deprecated No longer supported since 2.0.0
 * @author mdawaffe
 */
function disable_autosave() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

}

/**
 * Send comment moderation emails to multiple addresses
 *
 * @author nickmomrik
 * @deprecated No longer supported since 2.0.0
 * @param array $emails Array of email addresses
 */
function vip_multiple_moderators( $emails ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Helper function to disable the WordPress.com wide Zemanta Tools for all users.
 *
 * @deprecated Not applicable since VIP 2.0.0
 */
function wpcom_vip_disable_zemanta_for_all_users() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Returns the HTTP_HOST for the current site's home_url()
 *
 * @deprecated Deprecated since 2.0.0
 * @return string
 */
function wpcom_vip_get_home_host() {
    _deprecated_function( __FUNCTION__, '2.0.0' );

    static $host;
    if ( ! isset( $host ) )
        $host = parse_url( home_url(), PHP_URL_HOST );
    return $host;
}

/**
 * Give themes the opportunity to disable WPCOM-specific smilies.
 * Note: Smilies disabled by this method will not fall back to core smilies.
 *
 * @deprecated Not applicable since VIP 2.0.0
 * @param  mixed $smilies_to_disable List of strings that will not be converted into smilies.
 *               A single string will be converted to an array & work
 * @uses filter smileyproject_smilies
 */
function wpcom_vip_disable_smilies( $smilies_to_disable ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Return the language code.
 *
 * Internal wpcom function that's used by the wpcom-sitemap plugin
 *
 * Note: Not overrideable in production - this function exists solely for dev environment
 * compatibility. To set blog language, use the Dashboard UI.
 *
 * @deprecated Deprecated since 2.0.0
 * @return string
 */
if ( ! function_exists( 'get_blog_lang_code' ) ) {

function get_blog_lang_code( $blog_id = 0 ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );

    return 'en';
}

}

/**
 * Set the roles that can view stats
 *
 * @deprecated No longer supported since 2.0.0
 * @param array $roles The roles that can view stats
 */
function wpcom_vip_stats_roles( array $roles ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Get the most shared posts of the current blog, ordered DESC by share count
 *
 * @author jjj
 * @access public
 *
 * @global WPDB $wpdb WordPress's Database class
 * @deprecated No longer supported since 2.0.0
 * @param int $limit Optional. Number of posts to retrieve. Defaults to 5.
 * @param int $cache_duration Optional. Length of time to cache the query. Defaults to 3600.
 * @return array Array of most shared post IDs
 */
function wpcom_vip_get_most_shared_posts( $limit = 5, $cache_duration = 3600 ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );

    return array();
}

if ( ! function_exists( 'w3cdate_from_mysql' ) ) {
/**
 * Convert a MySQL datetime string to an ISO 8601 string
 *
 * @deprecated Deprecated since 2.0.0
 * @link http://www.w3.org/TR/NOTE-datetime W3C date and time formats document
 * @param string $mysql_date UTC datetime in MySQL syntax of YYYY-MM-DD HH:MM:SS
 * @return string ISO 8601 UTC datetime string formatted as YYYY-MM-DDThh:mm:ssTZD where timezone offset is always +00:00
 */
function w3cdate_from_mysql($mysql_date) {
    _deprecated_function( __FUNCTION__, '2.0.0' );

	return str_replace(' ', 'T', $mysql_date).'+00:00';
}

}

if ( ! function_exists( 'sitemap_cache_key' ) ) {

/**
 * Common definition of sitemap cache key for use in getters, setters and clears
 *
 * @deprecated No longer supported since 2.0.0
 * @returns string cache key
 */
function sitemap_cache_key() {
    _deprecated_function( __FUNCTION__, '2.0.0' );

	return '';
}

}

if ( ! function_exists( 'sitemap_content_type' ) ) {

/**
 * Return the content type used to serve a Sitemap XML file
 * Uses text/xml by default, possibly overridden by sitemap_content_type filter
 *
 * @deprecated No longer supported since 2.0.0
 * @return string Internet media type for the sitemap XML
 */
function sitemap_content_type() {
    _deprecated_function( __FUNCTION__, '2.0.0' );

	return '';
}

}

function wpcom_print_sitemap_item($data) {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

function wpcom_print_xml_tag( $array ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Convert an array to a SimpleXML child of the passed tree.
 *
 * @deprecated No longer supported since 2.0.0
 * @param array $data array containing element value pairs, including other arrays, for XML contruction
 * @param SimpleXMLElement $tree A SimpleXMLElement class object used to attach new children
 * @return SimpleXMLElement full tree with new children mapped from array
 */
function wpcom_sitemap_array_to_simplexml($data, &$tree ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );

	return null;
}

/**
 * Define an array of attribute value pairs for use inside the root element of an XML document.
 * Intended for mapping namespace and namespace URI values.
 * Passes array through sitemap_ns for other functions to add their own namespaces
 *
 * @deprecated No longer supported since 2.0.0
 * @return array array of attribute value pairs passed through the sitemap_ns filter
 */
function wpcom_sitemap_namespaces() {
    _deprecated_function( __FUNCTION__, '2.0.0' );

	return array();
}

function wpcom_sitemap_initstr( $charset ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );

	return '';
}

/**
 * Print an XML sitemap conforming to the Sitemaps.org protocol
 * Outputs an XML list of up to the latest 1000 posts.
 *
 * @deprecated No longer supported since 2.0.0
 * @link http://sitemaps.org/protocol.php Sitemaps.org protocol
 * @todo set cache and expire on post publish, page publish or approved comment publish
 */
function wpcom_print_sitemap() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

function wpcom_print_news_sitemap($format) {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

if ( ! function_exists( 'sitemap_uri' ) ) {

/**
 * Absolute URL of the current blog's sitemap
 *
 * @deprecated No longer supported since 2.0.0
 * @return string sitemap URL
 */
function sitemap_uri() {
    _deprecated_function( __FUNCTION__, '2.0.0' );

	return null;
}

}

if ( ! function_exists( 'news_sitemap_uri' ) ) {

/**
 * Absolute URL of the current blog's news sitemap
 *
 * @deprecated No longer supported since 2.0.0
 */
function news_sitemap_uri() {
    _deprecated_function( __FUNCTION__, '2.0.0' );

	return null;
}

}

if ( ! function_exists( 'sitemap_endpoints' ) ) {

/**
 * A list or HTTP endpoints for a sitemap ping
 *
 * Note: disabled ping to http://submissions.ask.com/ping?sitemap=
 * See http://systemattic.wordpress.com/2010/02/11/sitemap-jobs-queue-showed-up-in-nagios/
 *
 * @deprecated No longer supported since 2.0.0
 * @return array List of endpoints waiting for a URI append
 */
function sitemap_endpoints() {
    _deprecated_function( __FUNCTION__, '2.0.0' );

    return null;
}

}

if ( ! function_exists( 'do_sitemap_pings' ) ) {

/**
 * Ping all registered HTTP endpoints for sitemap URIs
 *
 * @deprecated No longer supported since 2.0.0
 */
function do_sitemap_pings( $job ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

}

if ( ! function_exists( 'sitemap_discovery' ) ) {

/**
 * Output the master sitemap URLs for the current blog context
 *
 * @deprecated No longer supported since 2.0.0
 */
function sitemap_discovery() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

}

if ( ! function_exists( 'sitemap_handle_update' ) ) {

/**
 * Clear the sitemap cache when a sitemap action has changed
 * Add a job to the pings queue to send out update notifications
 *
 * @deprecated No longer supported since 2.0.0
 * @param int $post_id unique post identifier. not used.
 */
function sitemap_handle_update( $post_id ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

}

/**
 * Remove the tracking bug added to all WordPress.com feeds.
 *
 * @deprecated Not applicable since 2.0.0
 * Helper function for wpcom_vip_disable_enhanced_feeds().
 *
 * @see wpcom_vip_disable_enhanced_feeds()
 */
function wpcom_vip_remove_feed_tracking_bug() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * @deprecated Deprecated since 2.0.0 - use flush_rewrite_rules() directly
 */
function wpcom_initiate_flush_rewrite_rules() {
    _deprecated_function( __FUNCTION__, '2.0.0', 'flush_rewrite_rules' );

	flush_rewrite_rules( false );
}

/**
 * Technically this function used to return whether or not the given site was
 * flagged as VIP...though most VIP clients used it as a way to determine if
 * the code was in production vs. dev, before WPCOM_IS_VIP_ENV was introduced
 *
 * For back compat with that usage, we return the value of WPCOM_IS_VIP_ENV
 * so that v1 client code works as expected
 *
 * @deprecated Deprecated since 2.0.0 - use WPCOM_IS_VIP_ENV
 */
function wpcom_is_vip() {
    _deprecated_function( __FUNCTION__, '2.0.0' );

    return WPCOM_IS_VIP_ENV;
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
 * @deprecated Deprecated since 2.0.0 - use jetpack_photon_url()
 * @link http://vip.wordpress.com/documentation/image-resizing-and-cropping/ Image Resizing And Cropping
 * @param string $url The raw URL to the image (URLs that redirect are currently not supported with the exception of http://foobar.wordpress.com/files/ type URLs)
 * @param int $width The desired width of the final image
 * @param int $height The desired height of the final image
 * @param bool $escape Optional. If true (the default), the URL will be run through esc_url(). Set this to false if you need the raw URL.
 * @return string
 */
function wpcom_vip_get_resized_remote_image_url( $url, $width, $height, $escape = true ) {
    _deprecated_function( __FUNCTION__, '2.0.0', 'jetpack_photon_url' );

	$width = (int) $width;
	$height = (int) $height;

	$thumburl = jetpack_photon_url( $url, array( 'resize' => array( $width, $height ) ) );

	return ( $escape ) ? esc_url( $thumburl ) : $thumburl;
}

/**
 * Require a library in the VIP shared code library.
 *
 * @deprecated Since 2.0.0 - not yet fully supported
 * @param string $slug
 */
function wpcom_vip_require_lib( $slug ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );

    if ( !preg_match( '|^[a-z0-9/_.-]+$|i', $slug ) ) {
		trigger_error( "Cannot load a library with invalid slug $slug.", E_USER_ERROR );
		return;
	}
	$basename = basename( $slug );
	$lib_dir = WP_CONTENT_DIR . '/plugins/lib';
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
 * Filter plugins_url() so that it works for plugins inside the shared VIP plugins directory or a theme directory.
 *
 * Props to the GigaOm dev team for coming up with this method.
 *
 * @deprecated No longer supported since 2.0.0
 * @param string $url Optional. Absolute URL to the plugins directory.
 * @param string $path Optional. Path relative to the plugins URL.
 * @param string $plugin Optional. The plugin file that you want the URL to be relative to.
 * @return string
 */
function wpcom_vip_plugins_url( $url = '', $path = '', $plugin = '' ) {
    _deprecated_function( __FUNCTION__, '2.0.0', 'plugins_url' );

	return plugins_url( $path, $plugin );
}

/**
 * Return the directory path for a given VIP theme
 *
 * @link http://vip.wordpress.com/documentation/mobile-theme/ Developing for Mobile Phones and Tablets
 * @param string $theme Optional. Name of the theme folder
 * @return string Path for the specified theme
 */
function wpcom_vip_theme_dir( $theme = '' ) {
    _deprecated_function( __FUNCTION__, '2.0.0', 'get_stylesheet_directory' );

	if ( empty( $theme ) )
		$theme = get_stylesheet();

	// Simple sanity check, in case we get passed a lame path
	$theme = ltrim( $theme, '/' );
	$theme = str_replace( 'vip/', '', $theme );

	return sprintf( '%s/themes/%s', WP_CONTENT_DIR, $theme );
}

/**
 * If you don't want people (de)activating plugins via this UI
 * and only want to enable plugins via wpcom_vip_load_plugin()
 * calls in your theme's functions.php file, then call this
 * function to disable this plugin's (de)activation links.
 *
 * @deprecated No longer supported since 2.0.0
 */
function wpcom_vip_plugins_ui_disable_activation() {
    _deprecated_function( __FUNCTION__, '2.0.0', 'plugins_url' );
}

/**
 * Get the WP.com stats
 *
 * @deprecated No longer supported since 2.0.0
 * @author tott
 * @param string $table Optional. Table for stats can be views, postviews, authorviews, referrers, searchterms, clicks. Default is views.
 * @param string $end_data Optional. The last day of the desired time frame. Format is 'Y-m-d' (e.g. 2007-05-01) and default is UTC date.
 * @param int $num_days Optional. The length of the desired time frame. Default is 1. Maximum 90 days
 * @param string $and Optional. Possibility to refine the query with additional AND condition. Usually unused.
 * @param int $limit Optional. The maximum number of records to return. Default is 5. Maximum 100.
 * @param bool $summarize If present, summarizes all matching records.
 * @return array Result as array.
 */
function wpcom_vip_get_stats_array( $table = 'views', $end_date = false, $num_days = 1, $and = '', $limit = 5, $summarize = NULL ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );

    return array();
}

/**
 * Use a custom CDN host for displaying theme images and media library content.
 *
 * Please get in touch before using this as it can break your site.
 *
 * @deprecated No longer supported since 2.0.0
 * @param array $args Array of args
 * 		string|array cdn_host_media => Hostname of the CDN for media library assets.
 * 		string|array cdn_host_static => Optional. Hostname of the CDN for static assets.
 * 		bool include_admin => Optional. Whether the custom CDN host should be used in the admin context as well.
 * 		bool disable_ssl => Optional. Whether SSL should be disabled for the custom CDN.
 */
function wpcom_vip_load_custom_cdn( $args ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Dequeue wp-mediaelement.css for sites which don't use the playlist shortcode and thus don't need the stylesheet
 *
 * @deprecated Not applicable since VIP 2.0.0
 */
function wpcom_vip_remove_playlist_styles() {
	_deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Diables Instapost functionality
 *
 * @deprecated Not applicable since VIP 2.0.0
 */
function wpcom_vip_disable_instapost() {
	_deprecated_function( __FUNCTION__, '2.0.0' );
}
