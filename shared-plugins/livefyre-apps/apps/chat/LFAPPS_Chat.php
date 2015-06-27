<?php
/*
  Sub Plugin Name: LiveChat
  Plugin URI: http://www.livefyre.com/
  Description: Implements LiveChat for WordPress
  Version: 0.1
  Author: Livefyre, Inc.
  Author URI: http://www.livefyre.com/
 */

//Disallow direct access to this file
if (!defined('LFAPPS__PLUGIN_PATH'))
    die('Bye');

use Livefyre\Livefyre;

if (!class_exists('LFAPPS_Chat')) {

    class LFAPPS_Chat {

        private static $initiated = false;

        public static function init() {
            if (!self::$initiated) {
                self::$initiated = true;
                self::set_default_options();
                self::init_hooks();
            }
        }

        /**
         * Initialise WP hooks
         */
        private static function init_hooks() {
            if (LFAPPS_Chat::chat_active()) {
                add_action('wp_footer', array('LFAPPS_Chat', 'init_script'));

                // Set comments_template filter to maximum value to always override the default commenting widget

                add_filter('comments_template', array('LFAPPS_Chat', 'comments_template'), self::lf_widget_priority());
                add_filter('comments_number', array('LFAPPS_Chat', 'comments_number'), 10, 2);

                add_shortcode('livefyre_livechat', array('LFAPPS_Chat', 'init_shortcode'));
            }
        }

        /*
         * Builds the Livefyre JS code that will build the conversation and load it onto the page. The
         * bread and butter of the whole plugin.
         *
         */

        public static function init_script() {
            /*  Reset the query data because theme code might have moved the $post gloabl to point
              at different post rather than the current one, which causes our JS not to load properly.
              We do this in the footer because the wp_footer() should be the last thing called on the page.
              We don't do it earlier, because it might interfere with what the theme code is trying to accomplish. */
            wp_reset_query();

            global $post, $current_user, $wp_query;
            if (comments_open() && self::show_chat()) {   // is this a post page?
                Livefyre_Apps::init_auth();

                $network = get_option('livefyre_apps-livefyre_domain_name', 'livefyre.com');
                $network = ( $network == '' ? 'livefyre.com' : $network );

                $siteId = get_option('livefyre_apps-livefyre_site_id');
                $siteKey = get_option('livefyre_apps-livefyre_site_key');
                $network_key = get_option('livefyre_apps-livefyre_domain_key', '');
                $post = get_post();
                $articleId = apply_filters('livefyre_article_id', get_the_ID());
                $title = apply_filters('livefyre_collection_title', get_the_title(get_the_ID()));
                $url = apply_filters('livefyre_collection_url', get_permalink(get_the_ID()));
                $tags = array();
                $posttags = get_the_tags($wp_query->post->ID);
                if ($posttags) {
                    foreach ($posttags as $tag) {
                        array_push($tags, $tag->name);
                    }
                }

                $network = Livefyre::getNetwork($network, strlen($network_key) > 0 ? $network_key : null);
                $site = $network->getSite($siteId, $siteKey);

                $collectionMetaToken = $site->buildCollectionMetaToken($title, $articleId, $url, array("tags" => $tags, "type" => "livechat"));
                $checksum = $site->buildChecksum($title, $url, $tags, 'livechat');
                $strings = apply_filters( 'livefyre_custom_chat_strings', null );

                $livefyre_element = 'livefyre-chat';
                $display_template = false;
                LFAPPS_View::render_partial('script', compact('siteId', 'siteKey', 'network', 'articleId', 'collectionMetaToken', 'checksum', 'strings', 'livefyre_element', 'display_template'), 'chat');

                $ccjs = LFAPPS__PROTOCOL . '://cdn.livefyre.com/libs/commentcount/v1.0/commentcount.js';
                echo '<script type="text/javascript" data-lf-domain="' . esc_attr($network->getName()) . '" id="ncomments_js" src="' . esc_attr($ccjs) . '"></script>';
            }
        }

        /**
         * Run shortcode [livechat]
         * @param array $atts array of attributes passed to shortcode
         */
        public static function init_shortcode($atts = array()) {
            if (isset($atts['article_id'])) {
                $articleId = $atts['article_id'];
                $title = isset($pagename) ? $pagename : 'Chat (ID: ' . $atts['article_id'];
                global $wp;
                $url = add_query_arg($_SERVER['QUERY_STRING'], '', home_url($wp->request));
                $tags = array();
            } else {
                global $post;
                if (get_the_ID() !== false) {
                    $articleId = apply_filters('livefyre_article_id', get_the_ID());
                    $title = apply_filters('livefyre_collection_title', get_the_title(get_the_ID()));
                    $url = apply_filters('livefyre_collection_url', get_permalink(get_the_ID()));
                    $tags = array();
                    $posttags = get_the_tags($post->ID);
                    if ($posttags) {
                        foreach ($posttags as $tag) {
                            array_push($tags, $tag->name);
                        }
                    }
                } else {
                    return;
                }
            }
            Livefyre_Apps::init_auth();
            $network = get_option('livefyre_apps-livefyre_domain_name', 'livefyre.com');
            $network = ( $network == '' ? 'livefyre.com' : $network );

            $siteId = get_option('livefyre_apps-livefyre_site_id');
            $siteKey = get_option('livefyre_apps-livefyre_site_key');
            $network_key = get_option('livefyre_apps-livefyre_domain_key', '');

            $network = Livefyre::getNetwork($network, strlen($network_key) > 0 ? $network_key : null);
            $site = $network->getSite($siteId, $siteKey);

            $collectionMetaToken = $site->buildCollectionMetaToken($title, $articleId, $url, array("tags" => $tags, "type" => "livechat"));
            $checksum = $site->buildChecksum($title, $url, $tags, 'livechat');
            $strings = apply_filters( 'livefyre_custom_chat_strings', null );

            $livefyre_element = 'livefyre-chat-' . $articleId;
            $display_template = true;
            return LFAPPS_View::render_partial('script', compact('siteId', 'siteKey', 'network', 'articleId', 'collectionMetaToken', 'checksum', 'strings', 'livefyre_element', 'display_template'), 'chat', true);
        }

        /*
         * The template for the Livefyre div element.
         *
         */
        public static function comments_template() {
            if(!self::show_chat() && class_exists('LFAPPS_Comments_Display') && LFAPPS_Comments_Display::livefyre_show_comments()) {
                return LFAPPS_Comments_Display::livefyre_comments_template();
            }
            return LFAPPS__PLUGIN_PATH . 'apps/chat/views/comments-template.php';
        }

        /*
         * Build the Livefyre comment count variable.
         *
         */

        public static function comments_number($count) {

            global $post;
            return '<span data-lf-article-id="' . esc_attr($post->ID) . '" data-lf-site-id="' . esc_attr(get_option('livefyre_apps-livefyre_site_id', '')) . '" class="livefyre-commentcount">' . (int) $count . '</span>';
        }

        /**
         * First time load set default Livefyre Comments options
         * + import previous Livefyre plugin options
         */
        private static function set_default_options() {
            //set default display options
            //self::set_display_options();
        }

        /**
         * Set display options and make sure there is no conflict with LiveComments
         */
        private static function set_display_options() {
            $excludes = array('_builtin' => false);
            $post_types = get_post_types($args = $excludes);
            $post_types = array_merge(array('post' => 'post', 'page' => 'page'), $post_types);
            foreach ($post_types as $post_type) {
                $post_type_name_comments = 'livefyre_display_' . $post_type;
                $post_type_name_chat = 'livefyre_chat_display_' . $post_type;
                $display_comments = get_option('livefyre_apps-'.$post_type_name_comments, '');
                $display_chat = get_option('livefyre_apps-'.$post_type_name_chat, '');
                $display = false;
                if ($display_chat === '') {
                    if (Livefyre_Apps::is_app_enabled('comments') && ($display_comments === '' || $display_comments === false)) {
                        $display = true;
                    } elseif (!Livefyre_Apps::is_app_enabled('comments')) {
                        $display = true;
                    }
                } elseif ($display_chat === true
                        && (!Livefyre_Apps::is_app_enabled('comments') || ($display_comments === '' || $display_comments === false))) {
                    $display = true;
                }
                update_option('livefyre_apps-'.$post_type_name_chat, $display);
            }
        }

        /*
         * Handles the toggles on the settings page that decide which post types should be shown.
         * Also prevents comments from appearing on non single items and previews.
         *
         */

        public static function show_chat() {

            global $post;
            /* Is this a post and is the settings checkbox on? */
            $display_posts = ( is_single() && get_option('livefyre_apps-livefyre_chat_display_post'));
            /* Is this a page and is the settings checkbox on? */
            $display_pages = ( is_page() && get_option('livefyre_apps-livefyre_chat_display_page'));
            /* Are comments open on this post/page? */
            $comments_open = ( $post->comment_status == 'open' );

            $display = $display_posts || $display_pages;
            $post_type = get_post_type();
            if ($post_type != 'post' && $post_type != 'page') {

                $post_type_name = 'livefyre_chat_display_' . $post_type;
                $display = ( get_option('livefyre_apps-'.$post_type_name, 'true') == 'true' );
            }
            return $display 
                && Livefyre_Apps::is_app_enabled('chat')
                && !is_preview()
                && $comments_open;
        }

        /*
         * Gets the Livefyre priority.
         *
         */

        public static function lf_widget_priority() {

            return intval(get_option('livefyre_widget_priority', 99));
        }

        /**
         * Check if chat is active and there are no issues stopping them from loading
         * @return boolean
         */
        public static function chat_active() {
            return ( Livefyre_Apps::active());
        }

    }

}
?>
