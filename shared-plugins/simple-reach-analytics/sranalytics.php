<?php
/**
 Plugin Name: SimpleReach Analytics
 Plugin URI: http://www.simplereach.com/docs/wordpress-plugin/
 Text Domain: sranalytics
 Description: After installation, you must click '<a href='options-general.php?page=SimpleReach-Analytics'>Settings &rarr; SimpleReach Analytics</a>' to turn on the Analytics.
 Version: 0.1.4
 Author: SimpleReach
 Author URI: https://www.simplereach.com
 */


/*	Copyright 2014	SimpleReach  (email : support@simplereach.com)

		This program is free software; you can redistribute it and/or modify
		it under the terms of the GNU General Public License, version 2, as 
		published by the Free Software Foundation.

		This program is distributed in the hope that it will be useful,
		but WITHOUT ANY WARRANTY; without even the implied warranty of
		MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	See the
		GNU General Public License for more details.

		You should have received a copy of the GNU General Public License
		along with this program; if not, write to the Free Software
		Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

define( 'SRANALYTICS_PLUGIN_VERSION', '0.1.4' );

/**
 * Insert analytics code onto the post page
 */
function sranalytics_insert_js() {

	global $post;

	// Do not show SimpleReach tags by default
	$sranalytics_show_beacon = false;

	// Get the options
	$sranalytics_pid = get_option( 'sranalytics_pid' );
	$sranalytics_show_on_tac_pages = get_option( 'sranalytics_show_on_tac_pages' );
	$sranalytics_show_on_wp_pages = get_option( 'sranalytics_show_on_wp_pages' );
	$sranalytics_show_on_attachment_pages = get_option( 'sranalytics_show_on_attachment_pages' );
	$sranalytics_show_everywhere = get_option( 'sranalytics_show_everywhere' );
	$sranalytics_force_http = get_option( 'sranalytics_force_http' );
	$sranalytics_disable_iframe_loading = get_option( 'sranalytics_disable_iframe_loading' );

	// Try and check the validity of the PID
	if ( empty( $sranalytics_pid) || 24 != strlen( $sranalytics_pid ) ) {
		return False;
	}

	// Show everywhere
	if ( $sranalytics_show_everywhere ) {
		$sranalytics_show_beacon = true;
	}
	//Show on attachment pages if option set
	if ( is_attachment() && $sranalytics_show_on_attachment_pages ) {
		$sranalytics_show_beacon = true;
	}

	// Ensure we show on post pages
	if ( is_single() && !is_attachment() ) {
		$sranalytics_show_beacon = true;
	}

	// Ensure we show on WP pages if we are supposed to
	if ( is_page() && $sranalytics_show_on_wp_pages ) {
		$sranalytics_show_beacon = true;
	}

	// Ensure we show on WP pages if we are supposed to
	if ( is_page() && $sranalytics_show_on_wp_pages ) {
		$sranalytics_show_beacon = true;
	}

	$post_id = $post->ID;

	// If the post isn't published yet, don't show the __reach_config
	// attachments don't have published status though so always show for them.
	if ( 'publish' != $post->post_status && !is_attachment() ) {
		return False;
	}

	// default case of a regular post
	$title = $post->post_title;
	$authors = array( get_author_name( $post->post_author ) );
	$tags = wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );
	$channels = wp_get_post_categories( $post->ID, array( 'fields' => 'slugs' ) );
	$published_date = $post->post_date_gmt;
	$canonical_url = get_permalink( $post->ID );

	// Show the tags if we are on a tag/author/category page and we are supposed to
	if ( ( is_category() || is_author() || is_tag() ) && ( $sranalytics_show_on_tac_pages || $sranalytics_show_everywhere ) ) {
		$sranalytics_show_beacon = true;
		$channels = array();
		$authors = array();
		$tags = array();

		//handle archive-style pages. WordPress has a different pattern for retrieving each one
		if ( is_tag() ) {
			$tag_name = single_cat_title( '', false );
			if ( function_exists( 'wpcom_vip_get_term_by' ) ) {
				$tag = wpcom_vip_get_term_by( 'name', $tag_name, 'post_tag' );
			 } else {
				$tag = get_term_by( 'name', $tag_name, 'post_tag' );
			}
			$tag_url = get_tag_link($tag->term_id);

			$title = "Tag: ${tag_name}";
			$tags[] = $tag_name;
			$canonical_url = $tag_url;

		} elseif ( is_author() ) {
			$author_id = get_the_author_meta( 'ID' );
			$author_name = get_the_author();

			$title = "Author: ${author_name}";
			$authors[] = $author_name;
			$canonical_url = get_author_posts_url( $author_id );

		} elseif ( is_category() ) {
			$channel_name = single_cat_title( '', false );
			$category_id = get_cat_ID( $channel_name );

			$title = "Category: ${channel_name}";
			$channels[] = $channel_name;
			$canonical_url = get_category_link( $category_id );

		} else {
			// We should NEVER get here
			$title = "Unkown Page Type";
		}

		// If we are on a page, then we need to add it
		$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
		if ($paged > 1) {
			$title = "${title} - Page ${paged}";
		}
	}

	// Handle the homepage properly if we are supposed to fire on it
	if ( (is_home() || is_page( 'home' ) ) && $sranalytics_show_everywhere ) {
		$title = "Homepage";
		$channels = array();
		$authors = array();
		$tags = array();
		$canonical_url = get_home_url();
	}

	//force https to http if option is checked
	if($sranalytics_force_http){
		$pattern = '/^https:\/\//';
		$canonical_url = preg_replace( $pattern , "http://" , $canonical_url);
	}

	//prepare and escape all JS variables
	$javascript_array = array(
		'version' => SRANALYTICS_PLUGIN_VERSION,
		'pid' => esc_js( $sranalytics_pid ),
		'iframe' => esc_js( $sranalytics_disable_iframe_loading ),
		'title' => esc_js( apply_filters( 'sranalytics_title', $title ) ),
		'url' => esc_js( apply_filters( 'sranalytics_url', $canonical_url ) ),
		'date' => esc_js( apply_filters( 'sranalytics_date', $published_date ) ),
		'channels' => array_map( 'esc_js', apply_filters( 'sranalytics_channels', $channels ) ),
		'tags' => array_map( 'esc_js', apply_filters( 'sranalytics_tags', $tags ) ),
		'authors' => array_map( 'esc_js', apply_filters( 'sranalytics_authors', $authors ) ),
	);

	// Get the JS ready to go
	if ($sranalytics_show_beacon) {
		wp_register_script( 'sranalytics', plugins_url( 'javascripts/sranalytics.js', __FILE__) );
		wp_localize_script( 'sranalytics', 'sranalytics', $javascript_array );
		wp_enqueue_script( 'sranalytics' );
	} else {
		return false;
	}
}
/**
 * Add the SimpleReach admin section
 */
function sranalytics_load_admin() {
	include_once( 'sranalytics_admin.php' );
}

/**
 * Add the SimpleReach admin options to the Settings Menu
 */
function sranalytics_admin_actions() {
	add_options_page("SimpleReach Analytics", "SimpleReach Analytics", "manage_options", "SimpleReach-Analytics", "sranalytics_load_admin");
}

/**
 * Setup the locales for i18n
 */
function sranalytics_textdomain() {
	$locale				 = apply_filters( 'sranalytics_locale', get_locale() );
	$mofile				 = sprintf( 'sranalytics-%s.mo', $locale );
	$mofile_local  = plugin_dir_path( __FILE__ ) . 'languages/' . $mofile;

  return load_textdomain( 'sranalytics', $mofile_local );
}

// Determine when specific methods are supposed to fire
add_action( 'wp_head', 'sranalytics_insert_js', 5 );
add_action( 'admin_menu','sranalytics_admin_actions' );
add_action( 'plugins_loaded', 'sranalytics_textdomain' );
