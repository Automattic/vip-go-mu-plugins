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
 * Overrides a theme's $content_width to remove the image constraint.
 *
 * @deprecated Not applicable since VIP 2.0.0
 * @author nickmomrik
 */
function wpcom_vip_allow_full_size_images_for_real() {
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
 * Removes the <media:content> tags from the RSS2 feed.
 *
 * You should really call this when creating a custom feed (best to leave them in your normal feed)
 * For details on creating a custom feed, see http://lobby.vip.wordpress.com/custom-made/altering-feeds/
 *
 * @deprecated Not applicable since VIP 2.0.0
 */
function wpcom_vip_remove_mediacontent_from_rss2_feed() {
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
 * @deprecated Deprecated since VIP 2.0.0 - Use jetpack_enable_open_graph()
 * @link http://vip.wordpress.com/documentation/open-graph/ Adding Open Graph Tags
 * @see http://developers.facebook.com/docs/opengraph/ Open Graph
 */
function wpcom_vip_enable_opengraph() {
    _deprecated_function( __FUNCTION__, '2.0.0', 'jetpack_enable_open_graph' );
}

/**
 * Force a site invitation to a user to only be accepted by a user who has the matching WordPress.com account's email address.
 *
 * The default behavior for invitations is to allow any WordPress.com user accept an invitation
 * regardless of whether their email address matches what the invitation was sent to. This helper
 * function forces the invitation email to match the WordPress.com user's email address.
 *
 * @deprecated Not applicable since VIP 2.0.0
 * @link http://vip.wordpress.com/documentation/customizing-invites/ Customizing Invites
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
 * Sets the default for subscribe to comments to off
 *
 * @deprecated Not applicable since VIP 2.0.0
 */
function wpcom_vip_disable_default_subscribe_to_comments() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
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
 * Disables output of geolocation information in "public" locations--post content, meta tags, and feeds.
 *
 * @deprecated Not applicable since VIP 2.0.0
 * @see http://en.support.wordpress.com/geotagging/
 */
function wpcom_vip_disable_geolocation_output() {
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

function wpcom_vip_debug( $type, $data ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

function vary_cache_on_function( $function ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}
