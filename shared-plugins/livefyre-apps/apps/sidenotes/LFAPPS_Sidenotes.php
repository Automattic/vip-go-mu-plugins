<?php
/*
Sub Plugin Name: Livefyre Sidenotes
Plugin URI: http://www.livefyre.com/
Description: Implements Livefyre Sidenotes
Version: 1.0.1
Author: Livefyre, Inc.
Author URI: http://www.livefyre.com/
 */

//Disallow direct access to this file
if(!defined('LFAPPS__PLUGIN_PATH')) 
    die('Bye');

define( 'LFAPPS_SIDENOTES_PLUGIN_VERSION', '1.0.1' );

require_once LFAPPS__PLUGIN_PATH . 'libs/php/LFAPPS_View.php';

if ( ! class_exists( 'LFAPPS_Sidenotes' ) ) {
    class LFAPPS_Sidenotes {
        private static $initiated = false;
        
        public static function init() {
            if ( ! self::$initiated ) {
                self::$initiated = true;
                self::set_default_options();
                self::load_resources();
                self::init_hooks();                
            }
        }
                
        /**
         * Initialise WP hooks
         */
        private static function init_hooks() {
            add_filter( 'the_content', array( 'LFAPPS_Sidenotes', 'content_wrapper' ) );
            add_action( 'wp_footer', array( 'LFAPPS_Sidenotes', 'init_sidenotes_script' ), 100 );
        }
        
        public static function init_sidenotes_script() {
            if(!self::display_sidenotes()) {
                return false;
            }
            Livefyre_Apps::init_auth();
            LFAPPS_View::render_partial('script', array(), 'sidenotes');            
        }
    
        /**
         * Add assets required by Livefyre Sidenotes
         */
        public static function load_resources() {
            wp_register_script('Livefyre.js', LFAPPS__PROTOCOL . '://cdn.livefyre.com/Livefyre.js');
            wp_enqueue_script('Livefyre.js');
        }
        
        /**
         * First time load set default Livefyre Comments options 
         * + import previous Livefyre plugin options
         */
        private static function set_default_options() {
            if(!get_option('livefyre_apps-livefyre_sidenotes_options_imported')) {
                self::import_options();
            }
            
            //set default display options
            if(get_option('livefyre_apps-livefyre_sidenotes_display_post', null) === null) {
                update_option('livefyre_apps-livefyre_sidenotes_display_post', 'true');
            }
            if(get_option('livefyre_apps-livefyre_sidenotes_display_page', null) === null) {
                update_option('livefyre_apps-livefyre_sidenotes_display_page', 'true');
            }
            
            if(get_option('livefyre_apps-livefyre_sidenotes_selectors', '') === '' || get_option('livefyre_apps-livefyre_sidenotes_selectors') === 'true') {
                update_option('livefyre_apps-livefyre_sidenotes_selectors', '#livefyre-sidenotes-wrap p:not(:has(img)),#livefyre-sidenotes-wrap > p:not(.fyre) img, #livefyre-sidenotes-wrap > ul > li, #livefyre-sidenotes-wrap > ol > li');
            }
        }
        
        /**
         * Import plugin options from previous Livefyre Sidenotes plugin
         */
        private static function import_options() {
            //import display options
            if(get_option('livefyre_sidenotes_display_posts', '') !== '') {
                update_option('livefyre_apps-livefyre_sidenotes_display_post', get_option('livefyre_sidenotes_display_posts'));
            } 
            if(get_option('livefyre_sidenotes_display_pages', '') !== '') {
                update_option('livefyre_apps-livefyre_sidenotes_display_page', get_option('livefyre_sidenotes_display_pages'));
            }
            
            $excludes = array( '_builtin' => false );
            $post_types = get_post_types( $args = $excludes );
            $post_types = array_merge(array('post'=>'post', 'page'=>'page'), $post_types);            
            foreach ($post_types as $post_type ) {
                $post_type_name = 'livefyre_sidenotes_display_' .$post_type;
                if(get_option($post_type_name, '') !== '') {
                    update_option('livefyre_apps-'.$post_type_name, get_option($post_type_name));
                }
            }
            
            update_option('livefyre_apps-livefyre_sidenotes_options_imported', true);
        }
        
        public static function content_wrapper($content) {
            if( !self::display_sidenotes() ) {
                return $content;
            }

            return "<div id='livefyre-sidenotes-wrap'>$content</div>";
        }
        
        private static function display_sidenotes() {
            global $post;
            /* Is this a post and is the settings checkbox on? */
            $display_posts = ( is_single() && get_option('livefyre_apps-livefyre_sidenotes_display_post','true') == 'true' );
            /* Is this a page and is the settings checkbox on? */
            $display_pages = ( is_page() && get_option('livefyre_apps-livefyre_sidenotes_display_page','true') == 'true' );
            /* Are comments open on this post/page? */
            $comments_open = ( $post->comment_status == 'open' );

            $display = $display_posts || $display_pages;
            $post_type = get_post_type();
            if ( $post_type != 'post' && $post_type != 'page' ) {

                $post_type_name = 'livefyre_sidenotes_display_' .$post_type;            
                $display = ( get_option('livefyre_apps-'. $post_type_name, 'true' ) == 'true' );
            }

            return $display
                && Livefyre_Apps::is_app_enabled('sidenotes')
                && !is_preview()
                && $comments_open;
        }
        
        /**
         * Check if comments are active and there are no issues stopping them from loading
         * @return boolean
         */
        public static function sidenotes_active() {
            return ( Livefyre_Apps::active());
        }
    }
}
?>
