<?php
/**
 * LivePress boot.
 */
require_once( LP_PLUGIN_PATH . 'php/livepress-administration.php' );
require_once( LP_PLUGIN_PATH . 'php/livepress-admin-settings.php' );

// @todo Add comments for each require.
require_once( LP_PLUGIN_PATH . 'php/livepress-updater.php' );
require_once( LP_PLUGIN_PATH . 'php/livepress-compatibility-fixes.php' );
require_once( LP_PLUGIN_PATH . 'php/livepress-collaboration.php' );
require_once( LP_PLUGIN_PATH . 'php/livepress-template.php' );
require_once( LP_PLUGIN_PATH . 'php/livepress-feed.php' );
require_once( LP_PLUGIN_PATH . 'php/livepress-xmlrpc.php' );
require_once( LP_PLUGIN_PATH . 'php/livepress-themes-helper.php' );
require_once( LP_PLUGIN_PATH . 'php/livepress-wp-utils.php' );
require_once( LP_PLUGIN_PATH . 'php/livepress-admin-bar-status-menu.php' );
require_once( LP_PLUGIN_PATH . 'php/livepress-user-settings.php' );
require_once( LP_PLUGIN_PATH . 'php/livepress-blogging-tools.php' );
require_once( LP_PLUGIN_PATH . 'php/livepress-post-format-controller.php' );
require_once( LP_PLUGIN_PATH . 'php/livepress-fix-twitter-oembed.php' );

// load wp-cli
require_once( LP_PLUGIN_PATH . 'php/livepress-cli.php' );

// Handle i10n/i18n
add_action( 'plugins_loaded', 'livepress_init' );

// Handle plugin install / upgrade
add_action( 'activate_' . LP_PLUGIN_NAME . '/livepress.php', 'LivePress_Administration::plugin_install' );
add_action( 'plugins_loaded',                             'LivePress_Administration::install_or_upgrade' );

if ( ! defined( 'WPCOM_IS_VIP_ENV' ) || false === WPCOM_IS_VIP_ENV ) {
	add_action( 'deactivate_' . LP_PLUGIN_NAME . '/livepress.php', 'LivePress_Administration::deactivate_livepress' );
}

add_action( 'init', 'LivePress_Updater::instance' );

// The fixes should run after all plugins are initialized
add_action( 'init', 'LivePress_Compatibility_Fixes::instance', 100 );

if ( defined( 'XMLRPC_REQUEST' ) && constant( 'XMLRPC_REQUEST' ) ) {
	add_action( 'init', 'LivePress_XMLRPC::initialize' );
}

add_action( 'init', 'LivePress_Themes_Helper::instance' );

// Custom Feeds (PuSH)
add_action( 'wp', 'LivePress_Feed::initialize' );

// Admin menu
add_action( 'admin_menu', 'LivePress_Administration::initialize' );


// Ajax response on server-side
add_action( 'wp_ajax_lp_api_key_validate',                    'LivePress_Administration::api_key_validate' );
add_action( 'wp_ajax_lp_post_to_twitter',                     'LivePress_Administration::post_to_twitter_ajaxed' );

add_action( 'wp_ajax_lp_check_oauth_authorization_status',    'LivePress_Administration::check_oauth_authorization_status' );
add_action( 'wp_ajax_lp_collaboration_comments_number',       'Collaboration::comments_number' );
add_action( 'wp_ajax_lp_collaboration_get_live_edition_data', 'Collaboration::get_live_edition_data_ajax' );
add_action( 'wp_ajax_lp_im_integration',                      'LivePress_IM_Integration::initialize' );


// Live Blogging Tools
$blogging_tools = new LivePress_Blogging_Tools();
$blogging_tools->setup_tabs();
add_action( 'wp_ajax_lp_get_blogging_tools',   array( $blogging_tools, 'ajax_render_tabs' ) );
add_action( 'wp_ajax_lp_update-live-notes',    array( $blogging_tools, 'update_author_notes' ) );
add_action( 'wp_ajax_lp_update-live-comments', array( $blogging_tools, 'update_live_comments' ) );
add_action( 'wp_ajax_lp_update-live-status',   array( $blogging_tools, 'toggle_live_status' ) );

add_action( 'manage_posts_custom_column' , array( $blogging_tools, 'display_posts_livestatus' ) , 10, 2 );

/**
 * Add custom column to post list.
 *
 * @param array  $columns   Array of columns
 * @param string $post_type Post type.
 * @return array Filtered array of columns.
 */
function add_livepress_status_column( $columns, $post_type ) {
	if ( in_array( $post_type, apply_filters( 'livepress_post_types', array( 'post' ) ) ) ) {
		$columns = array_merge( $columns, array( 'livepress_status' => esc_html__( 'Live Status', 'livepress' ) ) );
	}
	return $columns;
}
add_filter( 'manage_posts_columns' , 'add_livepress_status_column', 10, 2 );

/**
 * When the site's URL changes, automatically inform
 * the LivePress API of the change.
 */
function livepress_update_blog_name() {
	$settings = get_option( 'livepress' );
	$api_key  = isset( $settings['api_key'] ) ? $settings['api_key'] : '';
	$comm     = new LivePress_Communication( $api_key );

	// Note: site_url is the admin url on VIP
	$comm->validate_on_livepress( site_url() );
}
add_action( 'update_option_blogname', 'livepress_update_blog_name' );

/**
 * Add content CSS.
 *
 * @param $init
 * @return mixed
 */
function add_content_css( $init ) {
	if ( ! LivePress_Updater::instance()->blogging_tools->get_post_live_status( get_the_ID() ) ) {
		return $init;
	}
	$css_for_tinymce      = LP_PLUGIN_URL . 'tinymce/css/inside.css';
	$css                  = LP_PLUGIN_URL . 'css/livepress.css';
	$init['content_css'] .= ',' . $css . ',' . $css_for_tinymce;
	return $init;
}
	add_filter( 'tiny_mce_before_init', 'add_content_css' );

/**
 * Render LivePress Real-time Tools.
 */
function livepress_render_dashboard() {
	global $post_type;
	if ( ! in_array( $post_type, apply_filters( 'livepress_post_types', array( 'post' ) ) ) ) {
		return;
	}
	add_meta_box(
		'lp-dashboard',
		esc_html__( 'LivePress Real-time Tools', 'livepress' ),
		'livepress_dashboard_template',
		'post',
		'side',
		'high'
	);
}

function livepress_init() {
	$textdomain = defined( 'WPCOM_IS_VIP_ENV' ) && true === WPCOM_IS_VIP_ENV ? 'default' : 'livepress';
	load_plugin_textdomain( $textdomain, false, plugin_basename( LP_PLUGIN_PATH ) . '/languages/' );
}
