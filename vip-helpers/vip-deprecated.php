<?php

/**
 * VIP Legacy Related Posts (HTML formatted results)
 *
 * Don't use for new projects, just use WPCOM_RelatedPosts directly, since it has hooks
 * like jetpack_relatedposts_filter_args, jetpack_relatedposts_filter_filters
 *
 * @deprecated No longer supported since VIP 2.0.0 - Use Jetpack Related Posts
 * @param int $max_num Optional. Maximum number of results you want (default: 5).
 * @param array $additional_stopwords No longer used, we leave the stopwords magic to ES which knows more about word frequencies across articles.
 * @param bool $exclude_own_titles No longer used.
 * @return string Returns an HTML unordered list of related posts from the same blog.
 */
function wpcom_vip_flaptor_related_posts( $max_num = 5, $additional_stopwords = array(), $exclude_own_titles = true ){
    _deprecated_function( __FUNCTION__, '2.0.0' );

    return '';
}

/**
 * VIP Legacy Related Posts (get post_id, title, url)
 *
 * Don't use for new projects, just use WPCOM_RelatedPosts directly, since it has hooks
 * like jetpack_relatedposts_filter_args, jetpack_relatedposts_filter_filters
 *
 * For backwards compatability, this function finds related posts on the current blog
 * using Elasticsearch, then converts the results to match the original sphere results format.
 *
 * @deprecated No longer supported since VIP 2.0.0 - Use Jetpack Related Posts
 * @param int $max_num Optional. Maximum number of results you want (default: 5).
 * @param array $additional_stopwords No longer used.
 * @param bool $exclude_own_titles No longer used.
 * @return array of related posts.
 */
function wpcom_vip_get_flaptor_related_posts( $max_num = 5, $additional_stopwords = array(), $exclude_own_titles = true ) {
    return array();
}

/**
 * Un-hide the extra size and alignment options in the gallery tab of the media upload box
 *
 * @author tellyworth
 * @deprecated Not applicable since VIP 2.0.0
 */
function vip_admin_gallery_css_extras() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Helper function for vip_admin_gallery_css_extras()
 *
 * @deprecated Not applicable since VIP 2.0.0
 * @see vip_admin_gallery_css_extras()
 */
function _vip_admin_gallery_css_extras() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Override default colors of audio player.
 *
 * Colors specified in the shortcode still can override.
 *
 * @author nickmomrik
 * @deprecated Not applicable since VIP 2.0.0
 * @param array $colours Key/value array of colours to override
 */
function wpcom_vip_audio_player_colors( $colors ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Prints the title of the most popular blog post
 *
 * @author nickmomrik
 * @deprecated No longer supported since VIP 2.0.0
 * @param int $days Optional. Number of recent days to find the most popular posts from. Minimum of 2.
 */
function wpcom_vip_top_post_title( $days = 2 ) {
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
 * Prevent Youtube embeds in comments
 *
 * Feature: http://en.support.wordpress.com/videos/youtube/#comment-embeds
 *
 * @deprecated Not applicable since VIP 2.0.0
 * @author nickmomrik
 */
function wpcom_vip_disable_youtube_comment_embeds() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Makes the smallest sized thumbnails be cropped (i.e. the ones used in [gallery]).
 *
 * We've removed the checkbox from Settings -> Media on WordPress.com, so this re-enables the feature.
 *
 * @deprecated Not applicable since VIP 2.0.0
 */
function wpcom_vip_crop_small_thumbnail() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Removes the <media:content> tags from the RSS2 feed.
 *
 * You should really call this when creating a custom feed (best to leave them in your normal feed).
 *
 * @deprecated Not applicable since VIP 2.0.0
 */
function wpcom_vip_remove_mediacontent_from_rss2_feed() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Outputs Open Graph tags to various pages on the site
 *
 * @deprecated Deprecated since VIP 2.0.0 - Use the `jetpack_enable_open_graph` filter
 * @link https://developer.jetpack.com/hooks/jetpack_enable_open_graph/
 * @see https://developers.facebook.com/docs/sharing/opengraph Open Graph
 */
function wpcom_vip_enable_opengraph() {
	_deprecated_function( __FUNCTION__, '2.0.0', '`jetpack_enable_open_graph` filter' );
}

/**
 * Force a site invitation to a user to only be accepted by a user who has the matching WordPress.com account's email address.
 *
 * The default behavior for invitations is to allow any WordPress.com user accept an invitation
 * regardless of whether their email address matches what the invitation was sent to. This helper
 * function forces the invitation email to match the WordPress.com user's email address.
 *
 * @deprecated Not applicable since VIP 2.0.0
 */
function wpcom_invite_force_matching_email_address() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Reads a postmeta value directly from the master database.
 *
 * This is not intended for front-end usage. This purpose of this function is to avoid race conditions that could appear while the caches are primed.
 * A good scenario where this could be used is to ensure published posts are not syndicated multiple times by checking a postmeta flag that is set on syndication.
 *
 * Note: this looks complicated, but the intention was to use API functions rather than direct DB queries for upward compatibility.
 *
 * @deprecated No longer supported since VIP 2.0.0
 * @param int $post_id The ID of the post from which you want the data.
 * @param string $key A string containing the name of the meta value you want.
 * @param bool $single Optional. If set to true then the function will return a single result as a string. If false (the default) the function returns an array.
 * @return mixed Value from get_post_meta
 */
function wpcom_uncached_get_post_meta( $post_id, $key, $single = false ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );

    return get_post_meta( $post_id, $key, $single );
}

/**
 * Sets the default for subscribe to comments to off
 *
 * @deprecated Not applicable since VIP 2.0.0
 */
function wpcom_vip_disable_default_subscribe_to_comments() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}
/**
 * Helper function for wpcom_vip_allow_more_html_in_comments()
 *
 * @see wpcom_vip_allow_more_html_in_comments()
 */
function _wpcom_vip_allow_more_html_in_comments() {
    remove_filter( 'pre_comment_content', 'wp_filter_kses' );
    add_filter( 'pre_comment_content', 'wp_filter_post_kses' );
}

/**
 * Sends an e-mail when a new user accepts an invite to join a site.
 *
 * @deprecated Not applicable since VIP 2.0.0
 * @param array $emails Array of email address to notify when a user accepts an invitation to a site
 */
function wpcom_vip_notify_on_new_user_added_to_site( $emails ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Disables output of geolocation information in "public" locations--post content, meta tags, and feeds.
 *
 * @deprecated Not applicable since VIP 2.0.0
 * @see http://en.support.wordpress.com/geotagging/
 */
function wpcom_vip_disable_geolocation_output() {
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

function wpcom_vip_debug( $type, $data ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}


/**
 * This is the old deprecated version of wpcom_vip_file_get_contents(). Please don't use this function in any new code.
 *
 * @deprecated
 * @link https://wpvip.com/documentation/fetching-remote-data/ Fetching Remote Data
 * @param string $url URL to fetch
 * @param bool $echo_content Optional. If true (the default), echo the remote file's contents. If false, return it.
 * @param int $timeout Optional. The timeout limit in seconds; valid values are 1-10. Defaults to 3.
 * @return string|null If $echo_content is true, there will be no return value.
 * @see wpcom_vip_file_get_contents
 */
function vip_wp_file_get_content( $url, $echo_content = true, $timeout = 3 ) {
    _deprecated_function( __FUNCTION__, '2.0.0', 'wpcom_vip_file_get_contents' );

    $output = wpcom_vip_file_get_contents( $url, $timeout );

    if ( $echo_content )
        echo $output;
    else
        return $output;
}

/**
 * Disables the tag suggest on the post screen.
 *
 * @deprecated No longer supported since 2.0.0
 * @author mdawaffe
 */
function vip_disable_tag_suggest() {
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
 * Responds to a blog.wordpress.com/DARTIframe.html request with the contents of a DARTIframe.html file located in the root of your theme.
 *
 * @deprecated No longer supported since 2.0.0 - Use AdBusters https://github.com/Automattic/Adbusters/
 */
function vip_doubleclick_dartiframe_redirect() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Automatically insert meta description tag into posts/pages.
 *
 * You shouldn't need to use this function nowadays because WordPress.com and Jetpack takes care of this for you.
 *
 * @author Thorsten Ott
 * @deprecated No longer supported since 2.0.0
 */
function wpcom_vip_meta_desc() {
    _deprecated_function( __FUNCTION__, '2.0.0' );

    $text = wpcom_vip_get_meta_desc();
    if ( !empty( $text ) ) {
        echo "\n<meta name=\"description\" content=\"$text\" />\n";
    }
}

/**
 * Filter this function to change the meta description value set by wpcom_vip_meta_desc().
 *
 * Can be configured to use either first X chars/words of the post content or post excerpt if available
 * Can use category description for category archive pages if available
 * Can use tag description for tag archive pages if available
 * Can use blog description for everything else
 * Can use a default description if no suitable value is found
 * Can use the value of a custom field as description
 *
 * Usage:
 * // add a custom configuration via filter
 * function set_wpcom_vip_meta_desc_settings( $settings ) {
 * 		return array( 'length' => 10, 'length_unit' => 'char|word', 'use_excerpt' => true, 'add_category_desc' => true, 'add_tag_desc' => true, 'add_other_desc' => true, 'default_description' => '', 'custom_field_key' => '' );
 * }
 * add_filter( 'wpcom_vip_meta_desc_settings', 'set_wpcom_vip_meta_desc_settings' );
 * add_action( 'wp_head', 'wpcom_vip_meta_desc' );
 *
 * @return string The meta description
 * @deprecated No longer supported since 2.0.0
 * @see wpcom_vip_meta_desc()
 */
function wpcom_vip_get_meta_desc() {
    _deprecated_function( __FUNCTION__, '2.0.0' );

    $default_settings = array(
        'length' => 25,              // amount of length units to use for the meta description
        'length_unit' => 'word',     // the length unit can be either "word" or "char"
        'use_excerpt' => true,       // if the post/page has an excerpt it will overwrite the generated description if this is set to true
        'add_category_desc' => true, // add the category description to category views if this value is true
        'add_tag_desc' => true,      // add the category description to category views if this value is true
        'add_other_desc' => true,    // add the blog description/tagline to all other pages if this value is true
        'default_description' => '', // in case no description is defined use this as a default description
        'custom_field_key' => '',    // if a custom field key is set we try to use the value of this field as description
    );

    $settings = apply_filters( 'wpcom_vip_meta_desc_settings', $default_settings );

    extract( shortcode_atts( $default_settings, $settings ) );

    global $wp_query;

    if( is_single() || is_page() ) {
        $post = $wp_query->post;

        // check for a custom field holding a description
        if ( !empty( $custom_field_key ) ) {
            $post_custom = get_post_custom_values( $custom_field_key, $post->ID );
            if ( !empty( $post_custom ) )
                $text = $post_custom[0];
        }
        // check for an excerpt we can use
        elseif ( $use_excerpt && !empty( $post->post_excerpt ) ) {
            $text = $post->post_excerpt;
        }
        // otherwise use the content
        else {
            $text = $post->post_content;
        }

        $text = str_replace( array( "\r\n", "\r", "\n", "  " ), " ", $text ); // get rid of all line breaks
        $text = strip_shortcodes( $text ); // make sure to get rid of shortcodes
        $text = apply_filters( 'the_content', $text ); // make sure it's save
        $text = trim( strip_tags( $text ) ); // get rid of tags and html fragments
        if ( empty( $text ) && !empty( $default_description ) )
            $text = $default_description;

    } else if( is_category() && true == $add_category_desc ) {
        $category = $wp_query->get_queried_object();
        $text = trim( strip_tags( $category->category_description ) );
        if ( empty( $text ) && !empty( $default_description ) )
            $text = $default_description;

    } else if( is_tag() && true == $add_tag_desc ) {
        $tag = $wp_query->get_queried_object();
        $text = trim( strip_tags( $tag->description ) );
        if ( empty( $text ) && !empty( $default_description ) )
            $text = $default_description;

    } else if ( true == $add_other_desc ) {
        $text = trim( strip_tags( get_bloginfo('description') ) );
        if ( empty( $text ) && !empty( $default_description ) )
            $text = $default_description;
    }

    if ( empty( $text ) )
        return;

    if ( 'word' == $length_unit ) {
        $words = explode(' ', $text, $length + 1);
        if ( count( $words ) > $length ) {
            array_pop( $words );
            array_push( $words, '...' );
            $text = implode( ' ', $words );
        }
    } else {
        if ( strlen( $text ) > $length ) {
            $text = mb_strimwidth( $text, 0, $length, '...' );
        }
    }

    return $text;
}

/**
 * Disable comment counts in "Right Now" Dashboard widget as it can take a while to query the data.
 *
 * @deprecated No longer supported since 2.0.0
 */
function disable_right_now_comment_count() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Checks if the current site_url() matches from a specified list.
 *
 * @deprecated No longer supported since 2.0.0
 * @param array|string $site_urls List of site URL hosts to check against
 * @return bool If current site_url() matches one in the list
 */
function wpcom_vip_check_site_url( $site_urls ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );

    return false;
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
 * Get the URL of theme files relative to the home_url
 *
 * @deprecated Deprecated since 2.0.0
 * @param string $path The path of the file to get a URL for
 */
function wpcom_vip_home_template_uri( $path ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );

    return str_replace( site_url(), home_url(), get_template_directory_uri() . $path );
}

/**
 * Get the WP.com stats as CSV
 *
 * Strings containing double quotes, commas, or "\n" are enclosed in double-quotes. Double-quotes in strings are escaped by inserting another double-quote.
 * Example: "pet food" recipe
 * Becomes: """pet food"" recipe"
 *
 * @author tott
 * @deprecated No longer supported since 2.0.0
 * @param string $table Optional. Table for stats can be views, postviews, referrers, searchterms, clicks. Default is views.
 * @param string $end_data Optional. The last day of the desired time frame. Format is 'Y-m-d' (e.g. 2007-05-01) and default is UTC date.
 * @param int $num_days Optional. The length of the desired time frame. Default is 1. Maximum 90 days
 * @param string $and Optional. Possibility to refine the query with additional AND condition. Usually unused.
 * @param int $limit Optional. The maximum number of records to return. Default is 5. Maximum 100.
 * @param bool $summarize Optional. If present, summarizes all matching records.
 * @return string Result format is CSV with one row per line and column names in first row.
 */
function wpcom_vip_get_stats_csv( $table = 'views', $end_date = false, $num_days = 1, $and = '', $limit = 5, $summarize = NULL ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Get the WP.com stats as XML
 *
 * @author tott
 * @deprecated No longer supported since 2.0.0
 * @param string $table Optional. Table for stats can be views, postviews, referrers, searchterms, clicks. Default is views.
 * @param string $end_data Optional. The last day of the desired time frame. Format is 'Y-m-d' (e.g. 2007-05-01) and default is UTC date.
 * @param int $num_days Optional. The length of the desired time frame. Default is 1. Maximum 90 days
 * @param string $and Optional. Possibility to refine the query with additional AND condition. Usually unused.
 * @param int $limit Optional. The maximum number of records to return. Default is 5. Maximum 100.
 * @param bool $summarize Optional. If present, summarizes all matching records.
 * @return string Result format is XML dataset.
 */
function wpcom_vip_get_stats_xml( $table = 'views', $end_date = false, $num_days = 1, $and = '', $limit = 5, $summarize = NULL ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );

	return null;
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
* Transitionary solution in migration from n to news namespace
*
* While we get all the VIP clients that have filters that changing the namespace
* would impact we will convert n: to news: as late as possible.
*
* @deprecated No longer supported since 2.0.0
* @param mixed $url
*/
function wpcom_sitemap_n_to_news_namespace( $url ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );

    return null;
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
 * @link https://wpvip.com/documentation/image-resizing-and-cropping/ Image Resizing And Cropping
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
 * Return a URL for given VIP theme and path. Does not work with VIP shared plugins.
 *
 * @deprecated No longer supported since 2.0.0
 * @param string $path Optional. Path to suffix to the theme URL.
 * @param string $theme Optional. Name of the theme folder.
 * @return string|bool URL for the specified theme and path. If path doesn't exist, returns false.
 */
function wpcom_vip_theme_url( $path = '', $theme = '' ) {
    _deprecated_function( __FUNCTION__, '2.0.0', 'get_stylesheet_directory_uri' );

    if ( empty( $theme ) )
		$theme = str_replace( 'vip/', '', get_stylesheet() );

	// We need to reference a file in the specified theme; style.css will almost always be there.
	$theme_folder = sprintf( '%s/themes/%s', WP_CONTENT_DIR, $theme );
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
 * @link https://lobby.vip.wordpress.com/wordpress-com-documentation/mobile-theme/ Developing for Mobile Phones and Tablets
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
 * Dequeue wp-mediaelement.css for sites which don't use the playlist shortcode and thus don't need the stylesheet
 *
 * @deprecated Not applicable since VIP 2.0.0
 */
function wpcom_vip_remove_playlist_styles() {
	_deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Conditionally dequeues the geo-location-flair.css
 *
 * @deprecated Not applicable since VIP 2.0.0
 */
function wpcom_vip_load_geolocation_styles_only_when_needed() {
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
