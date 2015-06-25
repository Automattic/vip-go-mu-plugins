<?php
/*
Sub Plugin Name: LiveBlog
Plugin URI: http://www.livefyre.com/
Description: Implements LiveBlog
Version: 0.1
Author: Livefyre, Inc.
Author URI: http://www.livefyre.com/
 */

//Disallow direct access to this file
if(!defined('LFAPPS__PLUGIN_PATH')) 
    die('Bye');

use Livefyre\Livefyre;

require_once LFAPPS__PLUGIN_PATH . 'libs/php/LFAPPS_View.php';

if ( ! class_exists( 'LFAPPS_Blog' ) ) {
    class LFAPPS_Blog {
        private static $initiated = false;
        
        public static function init() {
            if ( ! self::$initiated ) {
                self::$initiated = true;
                self::init_hooks();                
            }
        }
                
        /**
         * Initialise WP hooks
         */
        private static function init_hooks() {
            if(self::blog_active())
                add_shortcode('livefyre_liveblog', array('LFAPPS_Blog', 'init_shortcode'));
        }
        
        public static function init_shortcode($atts=array()) {
            
            if(isset($atts['article_id'])) {
                $articleId = $atts['article_id'];
                $title = isset($pagename) ? $pagename : 'Comments (ID: ' . $atts['article_id'];
                global $wp;
                $url = add_query_arg( $_SERVER['QUERY_STRING'], '', home_url( $wp->request ) );
                $tags = array();
            } else {
                global $post;
                if(get_the_ID() !== false) {
                    $articleId = apply_filters('livefyre_article_id', get_the_ID());
                    $title = apply_filters('livefyre_collection_title', get_the_title(get_the_ID()));
                    $url = apply_filters('livefyre_collection_url', get_permalink(get_the_ID()));
                    $tags = array();
                    $posttags = get_the_tags( $post->ID );
                    if ( $posttags ) {
                        foreach( $posttags as $tag ) {
                            array_push( $tags, $tag->name );
                        }
                    }
                } else {
                    return;
                }
            }
            Livefyre_Apps::init_auth();
            $network = get_option('livefyre_apps-livefyre_domain_name', 'livefyre.com' );
            $network = ( $network == '' ? 'livefyre.com' : $network );

            $siteId = get_option('livefyre_apps-livefyre_site_id' );
            $siteKey = get_option('livefyre_apps-livefyre_site_key' );
            $network_key = get_option('livefyre_apps-livefyre_domain_key', '');

            $network = Livefyre::getNetwork($network, strlen($network_key) > 0 ? $network_key : null);            
            $site = $network->getSite($siteId, $siteKey);

            $collectionMetaToken = $site->buildCollectionMetaToken($title, $articleId, $url, array("tags"=>$tags, "type"=>"liveblog"));
            $checksum = $site->buildChecksum($title, $url, $tags, 'liveblog');
            $strings = apply_filters( 'livefyre_custom_blog_strings', null );
            $livefyre_element = 'livefyre-blog-'.$articleId;
            return LFAPPS_View::render_partial('script', 
                    compact('siteId', 'siteKey', 'network', 'articleId', 'collectionMetaToken', 'checksum', 'strings', 'livefyre_element'), 
                    'blog', true);   
        }
                
        /**
         * Check if comments are active and there are no issues stopping them from loading
         * @return boolean
         */
        public static function blog_active() {
            return ( Livefyre_Apps::active());
        }
    }
}
?>
