<?php
/*
Sub Plugin Name: Livefyre Comments
Plugin URI: http://www.livefyre.com/
Description: Implements Livefyre realtime comments for WordPress
Version: 4.2.0
Author: Livefyre, Inc.
Author URI: http://www.livefyre.com/
 */

//Disallow direct access to this file
if(!defined('LFAPPS__PLUGIN_PATH')) 
    die('Bye');

define( 'LFAPPS_COMMENTS_PLUGIN_VERSION', '4.2.0' );
define( 'LFAPPS_COMMENTS_DEFAULT_PROFILE_DOMAIN', 'livefyre.com' );
define( 'LFAPPS_COMMENTS_DEFAULT_TLD', 'livefyre.com' );

define( 'LFAPPS_CMETA_PREFIX', 'livefyre_cmap_' );
define( 'LFAPPS_AMETA_PREFIX', 'livefyre_amap_' );
define( 'LFAPPS_DEFAULT_HTTP_LIBRARY', 'LFAPPS_Http_Extension' );
define( 'LFAPPS_NOTIFY_SETTING_PREFIX', 'livefyre_notify_' );

require_once( dirname( __FILE__ ) . "/src/LFAPPS_Comments_Core.php" );

if ( ! class_exists( 'LFAPPS_Comments' ) ) {
    class LFAPPS_Comments {
        private static $initiated = false;
        
        public static function init() {
            if ( ! self::$initiated ) {
                self::$initiated = true;
                self::set_default_options();
                self::init_hooks();
                
                new LFAPPS_Comments_Core;
            }
        }
                
        /**
         * Initialise WP hooks
         */
        private static function init_hooks() {
            
        }
        
        /**
         * First time load set default Livefyre Comments options 
         * + import previous Livefyre plugin options
         */
        private static function set_default_options() {
            if(!get_option('livefyre_apps-livefyre_comments_options_imported')) {
                //set default display options
                self::set_display_options();
                self::import_options();
            }
            
            
            
            if(get_option('livefyre_apps-livefyre_import_status', '') === '') {
                update_option('livefyre_apps-livefyre_import_status', 'uninitialized');
            }
        }
        
        /**
         * Import plugin options from previous Livefyre Comments plugin
         */
        private static function import_options() {
            //import display options
            if(get_option('livefyre_display_posts', '') !== '') {
                update_option('livefyre_apps-livefyre_display_post', get_option('livefyre_apps-livefyre_display_posts') === 'true' ? true : false);
            } 
            if(get_option('livefyre_display_pages', '') !== '') {
                update_option('livefyre_apps-livefyre_display_page', get_option('livefyre_apps-livefyre_display_pages') === 'true' ? true : false);
            }
            
            $excludes = array( '_builtin' => false );
            $post_types = get_post_types( $args = $excludes );
            $post_types = array_merge(array('post'=>'post', 'page'=>'page'), $post_types);            
            foreach ($post_types as $post_type ) {
                $post_type_name = 'livefyre_display_' .$post_type;
                if(get_option($post_type_name, '') !== '') {
                    update_option('livefyre_apps-' . $post_type_name, get_option($post_type_name) === 'true' ? true : false);
                }
            }
            
            if(get_option('livefyre_import_status', '') !== '') {
                $import_status = get_option('livefyre_apps-livefyre_import_status');
                // Handle legacy values
                if ( $import_status == 'csv_uploaded') {
                    $import_status = 'complete';
                } elseif ( $import_status == 'started' ) {
                    $import_status = 'pending';
                }
                
                update_option('livefyre_apps-livefyre_import_status', $import_status);            
            } else {
                update_option('livefyre_apps-livefyre_import_status', 'uninitialized');            
            }
            
            if(get_option('livefyre_apps-livefyre_import_message', '') !== '') {
                update_option('livefyre_apps-livefyre_import_message', get_option('livefyre_apps-livefyre_import_message'));
            }
            
            update_option('livefyre_apps-livefyre_comments_options_imported', true);
        }
        
        /**
         * Set display options and make sure there is no conflict with LiveChat
         */
        private static function set_display_options() {
            $excludes = array( '_builtin' => false );
            $post_types = get_post_types( $args = $excludes );
            $post_types = array_merge(array('post'=>'post', 'page'=>'page'), $post_types);
            foreach($post_types as $post_type) {
                $post_type_name_comments = 'livefyre_display_' .$post_type;
                $post_type_name_chat = 'livefyre_chat_display_' .$post_type;
                $display_comments = get_option('livefyre_apps-'.$post_type_name_comments, '');
                $display_chat = get_option('livefyre_apps-'.$post_type_name_chat, '');
                $display = false;
                if($display_comments === '') {
                    if(Livefyre_Apps::is_app_enabled('chat') && ($display_chat === '' || $display_chat === false)) {
                        $display = true;
                    } elseif(!Livefyre_Apps::is_app_enabled('chat')) {
                        $display = true;
                    }
                } elseif($display_comments === true) {
                    $display = true;
                }
                update_option('livefyre_apps-'.$post_type_name_comments, $display);
                
            }
        }
        
        /**
         * Check if comments are active and there are no issues stopping them from loading
         * @return boolean
         */
        public static function comments_active() {
            return ( Livefyre_Apps::active());
        }
    }
}
?>
