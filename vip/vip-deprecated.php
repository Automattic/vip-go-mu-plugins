<?php

/**
 * Un-hide the extra size and alignment options in the gallery tab of the media upload box
 *
 * @author tellyworth
 */
function vip_admin_gallery_css_extras() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Helper function for vip_admin_gallery_css_extras()
 *
 * @see vip_admin_gallery_css_extras()
 */
function _vip_admin_gallery_css_extras() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Completely disable enhanced feed functionality
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
 * @param array $colours Key/value array of colours to override
 */
function wpcom_vip_audio_player_colors( $colors ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Prints the title of the most popular blog post
 *
 * @author nickmomrik
 * @param int $days Optional. Number of recent days to find the most popular posts from. Minimum of 2.
 */
function wpcom_vip_top_post_title( $days = 2 ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

if ( ! function_exists( 'make_tag_local' ) ) {

/**
 * Keeps category and tag links local to the blog instead of linking to http://en.wordpress.com/tags/
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
 * @author nickmomrik
 */
function wpcom_vip_disable_youtube_comment_embeds() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Overrides a theme's $content_width to remove the image constraint.
 *
 * @author nickmomrik
 */
function wpcom_vip_allow_full_size_images_for_real() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Makes the smallest sized thumbnails be cropped (i.e. the ones used in [gallery]).
 *
 * We've removed the checkbox from Settings -> Media on WordPress.com, so this re-enables the feature.
 */
function wpcom_vip_crop_small_thumbnail() {
    _deprecated_function( __FUNCTION__, '2.0.0' );
}

/**
 * Do not display the Polldaddy rating.
 *
 * Usually used for a page or post where ratings are not wanted.
 *
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
 * @param int $post_id The ID of the post from which you want the data.
 * @param string $key A string containing the name of the meta value you want.
 * @param bool $single Optional. If set to true then the function will return a single result as a string. If false (the default) the function returns an array.
 * @return mixed Value from get_post_meta
 */
function wpcom_uncached_get_post_meta( $post_id, $key, $single = false ) {
    _deprecated_function( __FUNCTION__, '2.0.0' );
    
	return get_post_meta( $post_id, $key, $single );
}
