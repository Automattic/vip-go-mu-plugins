<?php
/*
Plugin Name: Dynamic Content Gallery
Plugin URI: http://www.studiograsshopper.ch/wordpress-plugins/dynamic-content-gallery-plugin-v2/
Version: 2.2-WPCOM
Author: Ade Walker, Studiograsshopper
Author URI: http://www.studiograsshopper.ch
Description: Creates a dynamic content gallery anywhere within your wordpress theme using <a href="http://smoothgallery.jondesign.net/">SmoothGallery</a>. Set up the plugin options in Settings>Dynamic Content Gallery.
*/

/*  Copyright 2008  Ade WALKER  (email : info@studiograsshopper.ch)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License 2 as published by
    the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    The license for this software can be found here: 
    http://www.gnu.org/licenses/gpl-2.0.html
	
*/

/* Version History

	2.2			- Added template tag function for theme files
				- Added "disable mootools" checkbox in Settings to avoid js framework
				being loaded twice if another plugin uses mootools.
				- Changed handling of WP constants - now works as intended
				- Removed activation_hook, not needed
				- Changed options page CSS to better match with 2.7 look
				- Fixed loading flicker with CSS change => dynamic-gallery.php
				- Fixed error if selected post doesn't exist => dynamic-gallery.php
				- Fixed XHTML validation error with user-defined styles/CSS moved to head
				with new file dfcg-user-styles.php for the output of user definable CSS
	
	2.1			- Bug fix re path to scripts thanks to WP.org zip file naming
				convention
				
	2.0 beta	- Major code rewrite and reorganisation of functions
				- Added WPMU support
				- Added RESET checkbox to reset options to defaults
				- Added Gallery CSS options in the Settings page
			
	1.0			Public Release
	
*/

/* ******************** DO NOT edit below this line! ******************** */

/* Prevent direct access to the plugin */
if (!defined('ABSPATH')) {
	exit("Sorry, you are not allowed to access this page directly.");
}


/* Set constant for plugin directory */
define( 'DFCG_URL', 'http://s.wordpress.com/wp-content/themes/vip/plugins/dynamic-content-gallery' );


/* Set constant for plugin version number */
define ( 'DFCG_VER', '2.2' );


/* Internationalization functionality */
define('DFCG_DOMAIN','Dynamic_Content_Gallery');
$dfcg_text_loaded = false;

function dfcg_load_textdomain() {
	global $dfcg_text_loaded;
   	if($dfcg_text_loaded) return;

   	load_plugin_textdomain(DFCG_DOMAIN, ABSPATH . 'wp-content/themes/vip/plugins/' . dirname(plugin_basename(__FILE__)), dirname(plugin_basename(__FILE__)));
   	$dfcg_text_loaded = true;
}


/* This is where the plugin does its stuff */
function dfcg_addheader_scripts() {
    
	$options = get_option('dfcg_plugin_settings');
    /* Add javascript and CSS files */
	echo '<!-- Dynamic Content Gallery plugin version ' . DFCG_VER . ' www.studiograsshopper.ch  Begin scripts -->' ."\n";
	echo '<link type="text/css" rel="stylesheet" href="' . DFCG_URL . '/css/jd.gallery.css" />' . "\n";
	/* Should mootools framework be loaded? */
	if ( $options['mootools'] !== '1' ) {
	echo '<script type="text/javascript" src="' . DFCG_URL . '/scripts/mootools.v1.11.js"></script>' ."\n";
	}
	/* Add gallery javascript file */
	$jd_gallery_js_file = DFCG_URL . '/scripts/jd.gallery.js';
	echo '<script type="text/javascript" src="' . apply_filters('dynamic-gallery-js-config-file', $jd_gallery_js_file) . '"></script>' ."\n";
	/* Add user defined CSS */
	include_once('dfcg-user-styles.php');
	echo '<!-- End of Dynamic Content Gallery scripts -->' ."\n";
}
add_action('wp_head', 'dfcg_addheader_scripts');


/* Template tag to display gallery in theme files */
function dynamic_content_gallery() {
	include_once('dynamic-gallery.php');
}


/* Setup the plugin and create Admin settings page */
function dfcg_setup() {
	dfcg_load_textdomain();
	if ( current_user_can('manage_options') && function_exists('add_options_page') ) {
		add_options_page('Dynamic Content Gallery Options', 'Dynamic Content Gallery', 'manage_options', 'dynamic-gallery-plugin.php', 'dfcg_options_page');
		add_filter( 'plugin_action_links', 'dfcg_filter_plugin_actions', 10, 2 );
		dfcg_set_gallery_options();
	}
}
add_action('admin_menu', 'dfcg_setup');


/* dfcg_filter_plugin_actions() - Adds a "Settings" action link to the plugins page */
function dfcg_filter_plugin_actions($links, $file){
	static $this_plugin;

	if( !$this_plugin ) $this_plugin = plugin_basename(__FILE__);

	if( $file == $this_plugin ){
		$settings_link = '<a href="admin.php?page=dynamic-gallery-plugin.php">' . __('Settings') . '</a>';
		$links = array_merge( array($settings_link), $links); // before other links
	}
	return $links;
}


/* Create the options and provide some defaults */
function dfcg_set_gallery_options() {
	// Are we in WPMU?
	if ( function_exists('wpmu_create_blog') ) {
		// Add WPMU options
		$dfcg_new_options = array(
			'cat01' => '1',
			'cat02' => '1',
			'cat03' => '1',
			'cat04' => '1',
			'cat05' => '1',
			'off01' => '1',
			'off02' => '1',
			'off03' => '1',
			'off04' => '1',
			'off05' => '1',
			'homeurl' => '',
			'imagepath' => '',
			'defimagepath' => '',
			'defimagedesc' => '',
			'gallery-width' => '460',
			'gallery-height' => '250',
			'slide-height' => '50',
			'gallery-border-thick' => '1',
			'gallery-border-colour' => '#000000',
			'slide-h2-size' => '12',
			'slide-h2-marglr' => '5',
			'slide-h2-margtb' => '2',
			'slide-h2-colour' => '#FFFFFF',
			'slide-p-size' => '11',
			'slide-p-marglr' => '5',
			'slide-p-margtb' => '2',
			'slide-p-colour' => '#FFFFFF',
			'reset' => 'false',
			'mootools' => '0',
		);
	} else {
		// Add WP options
		$dfcg_new_options = array(
			'cat01' => '1',
			'cat02' => '1',
			'cat03' => '1',
			'cat04' => '1',
			'cat05' => '1',
			'off01' => '1',
			'off02' => '1',
			'off03' => '1',
			'off04' => '1',
			'off05' => '1',
			'homeurl' => get_option('home'),
			'imagepath' => '/wp-content/uploads/custom/',
			'defimagepath' => '/wp-content/uploads/dfcgimages/',
			'defimagedesc' => '',
			'gallery-width' => '460',
			'gallery-height' => '250',
			'slide-height' => '50',
			'gallery-border-thick' => '1',
			'gallery-border-colour' => '#000000',
			'slide-h2-size' => '12',
			'slide-h2-marglr' => '5',
			'slide-h2-margtb' => '2',
			'slide-h2-colour' => '#FFFFFF',
			'slide-p-size' => '11',
			'slide-p-marglr' => '5',
			'slide-p-margtb' => '2',
			'slide-p-colour' => '#FFFFFF',
			'reset' => 'false',
			'mootools' => '0',
		);
	
		// if old Version 1.0 options exist, which are prefixed "dfcg-", update to new system
		foreach( $dfcg_new_options as $key => $value ) {
			if( $existing = get_option( 'dfcg-' . $key ) ) {
				$dfcg_new_options[$key] = $existing;
				delete_option( 'dfcg-' . $key );
			}
		}
	}
	add_option('dfcg_plugin_settings', $dfcg_new_options );
}


/* Only for WP versions less than 2.7
Delete the options when plugin is deactivated */
function dfcg_unset_gallery_options() {
	delete_option('dfcg_plugin_settings');
}

/* Display and handle the options page */
function dfcg_options_page(){
	include_once('dfcg-wpmu-ui.php');
}

// Add the custom field box on the post page
add_action( 'admin_menu', 'gallery_meta_box' );
function gallery_meta_box() {
        add_meta_box( 'dfcg_image', 'Dynamic Content Gallery Image', 'dfcg_image_meta_box', 'post', 'normal' );
        add_meta_box( 'dfcg_desc', 'Dynamic Content Gallery Description', 'dfcg_desc_meta_box', 'post', 'normal' );
}

// Outputs the Dynamic Content Gallery Image text form
function dfcg_image_meta_box( $post, $meta_box ) {
        if ( $post_id = (int) $post->ID )
                $dfcg_image = (string) get_post_meta( $post_id, 'dfcg_image', true );
        else
                $dfcg_image = '';
        $dfcg_image = format_to_edit( $dfcg_image );
?>
<p><label class="hidden" for="dfcg_image">Dynamic Content Gallery Image (Optional)</label><input type="text" name="dfcg_image" id="dfcg_image" value="<?php echo $dfcg_image; ?>" /></p>
        <p><label for="dfcg_image">Full path to the Image file as per the "Link URL" that you made a note of when uploading the image eg. http://myblog.blogs.com/files/2008/11/myImage.jpg</label></p>

<?php
        wp_nonce_field( 'dfcg_image', 'dfcg_image_nonce', false );
}

// Saves the entered Dynamic Content Gallery Image text
add_action( 'save_post', 'dfcg_image_save_meta' );
function dfcg_image_save_meta( $post_id ) {
        // Checks to see if we're POSTing
        if ( 'post' !== strtolower( $_SERVER['REQUEST_METHOD'] ) || !isset($_POST['dfcg_image']) )
                return;

        // Checks to make sure we came from the right page
        if ( !wp_verify_nonce( $_POST['dfcg_image_nonce'], 'dfcg_image' ) )
                return;

        // Checks user caps
        if ( !current_user_can( 'edit_post', $post_id ) )
                return;

        // Already have a dfcg_image?
        $old_dfcg_image = get_post_meta( $post_id, 'dfcg_image', true );

        // Sanitize
        $dfcg_image = wp_filter_post_kses( $_POST['dfcg_image'] );
        $dfcg_image = trim( stripslashes( $dfcg_image ) );

        // nothing new, and we're not deleting the old
        if ( !$dfcg_image && !$old_dfcg_image )
                return;
                
        // Nothing new, and we're deleting the old
        if ( !$dfcg_image && $old_dfcg_image ) {
                delete_post_meta( $post_id, 'dfcg_image' );
                return;
        }

        // Nothing to change
        if ( $dfcg_image === $old_dfcg_image )
                return;

        // Save the dfcg_image
        if ( $old_dfcg_image ) {
                update_post_meta( $post_id, 'dfcg_image', $dfcg_image );
        } else {
                if ( !add_post_meta( $post_id, 'dfcg_image', $dfcg_image, true ) )
                        update_post_meta( $post_id, 'dfcg_image', $dfcg_image ); // Just in case it was deleted and saved as ""
        }
}

// Outputs the Dynamic Content Gallery Description text form
function dfcg_desc_meta_box( $post, $meta_box ) {
        if ( $post_id = (int) $post->ID )
                $dfcg_desc = (string) get_post_meta( $post_id, 'dfcg_desc', true );
        else
                $dfcg_desc = '';
        $dfcg_desc = format_to_edit( $dfcg_desc );
?>
<p><label class="hidden" for="dfcg_desc">Dynamic Content Gallery Description (Optional)</label><input type="text" name="dfcg_desc" id="dfcg_desc" value="<?php echo $dfcg_desc; ?>" /></p>
        <p><label for="dfcg_desc">Description text eg. Here's our latest news!</label></p>

<?php
        wp_nonce_field( 'dfcg_desc', 'dfcg_desc_nonce', false );
}

// Saves the entered Dynamic Content Gallery Description text
add_action( 'save_post', 'dfcg_desc_save_meta' );
function dfcg_desc_save_meta( $post_id ) {
        // Checks to see if we're POSTing
        if ( 'post' !== strtolower( $_SERVER['REQUEST_METHOD'] ) || !isset($_POST['dfcg_desc']) )
                return;

        // Checks to make sure we came from the right page
        if ( !wp_verify_nonce( $_POST['dfcg_desc_nonce'], 'dfcg_desc' ) )
                return;

        // Checks user caps
        if ( !current_user_can( 'edit_post', $post_id ) )
                return;

        // Already have a dfcg_desc?
        $old_dfcg_desc = get_post_meta( $post_id, 'dfcg_desc', true );

        // Sanitize
        $dfcg_desc = wp_filter_post_kses( $_POST['dfcg_desc'] );
        $dfcg_desc = trim( stripslashes( $dfcg_desc ) );

        // nothing new, and we're not deleting the old
        if ( !$dfcg_desc && !$old_dfcg_desc )
                return;
                
        // Nothing new, and we're deleting the old
        if ( !$dfcg_desc && $old_dfcg_desc ) {
                delete_post_meta( $post_id, 'dfcg_desc' );
                return;
        }

        // Nothing to change
        if ( $dfcg_desc === $old_dfcg_desc )
                return;

        // Save the dfcg_desc
        if ( $old_dfcg_desc ) {
                update_post_meta( $post_id, 'dfcg_desc', $dfcg_desc );
        } else {
                if ( !add_post_meta( $post_id, 'dfcg_desc', $dfcg_desc, true ) )
                        update_post_meta( $post_id, 'dfcg_desc', $dfcg_desc ); // Just in case it was deleted and saved as ""
        }
}
