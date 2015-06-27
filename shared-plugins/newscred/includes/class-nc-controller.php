<?php
/**
 * @package nc-plugin
 * @author  Md Imranur Rahman <imranur@newscred.com>
 *
 *
 *  Copyright 2012 NewsCred, Inc.  (email : sales@newscred.com)
 *
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License, version 2, as
 *  published by the Free Software Foundation.
 *
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

/**
 * NC_Controller Class
 * this class control all the actions
 *  of this plugin
 */

require_once( NC_INCLUDE_PATH . "/class-nc-exception.php" );


class NC_Controller {

    protected $_template; // Necessary to generate output
    protected $_params; // Parameters
    protected $_nc_utility;

    /**
     * _instance class variable
     *
     * Class instance
     *
     * @var null | object
     **/
    private static $_instance = NULL;


    /**
     * get_instance function
     *
     * Return singleton instance
     *
     * @return object
     **/
    static function get_instance () {
        if ( self::$_instance === NULL ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }


    /**
     *  Constructs the controller and assigns protected variables to be
     * @param array $params
     */
    public function __construct ( array $params = array() ) {


        global $nc_utility, $nc_author, $pagenow;

        $this->_nc_utility = $nc_utility;
        $this->_params = $params;
        $this->_template = new NC_Template();

        // ---------------------------------------------------------------------------------------------
        //  plugin actions
        // ---------------------------------------------------------------------------------------------

        // custom post type
        add_action( 'init', array( &$this, 'nc_create_post_type' ) );
        // admin init for settings API
        add_action( 'admin_init', array( &$this, 'admin_init_settings' ) );
        // nc author filter in the site
        add_filter( 'the_author_posts_link', array(&$this,'add_nc_author'));
        // nc author filter in rss feed
        add_filter( 'the_author', array(&$this, 'nc_feed_author' ) );


        // ---------------------------------------------------------------------------------------------
        //  admin menu and meta box actions
        // ---------------------------------------------------------------------------------------------


        if (  'impossible_default_value_1234' !== get_option( 'nc_plugin_access_key', 'impossible_default_value_1234' ) ) {
            // admin menu action
            add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
            // Define the NewsCred Editors Picks Meta  box
            add_action( 'add_meta_boxes', array( &$this, 'nc_editors_picks_meta_box' ) );
            // laod scripts
            add_action( 'admin_enqueue_scripts', array(&$this, 'nc_scripts' ));

        }

        // admin notice action  //
        add_action( 'admin_notices', array( &$nc_utility, 'show_nc_admin_messages' ) );


        // ajax actions

        $this->ajax_actions();


        /**
         *  save post author and categories
         */
        add_action( 'save_post', array( &$nc_author, 'nc_add_post_author' ) );


        /**
         * nc plugin
         * activating actions
         */
        register_activation_hook( NC_PATH . "/newscred-wp.php", array( &$this, 'add_nc_plugin_setting_options' ) );


    }

    /**
     *  All ajax action for
     *  nc plugins
     */
    public function ajax_actions(){


        // ---------------------------------------------------------------------------------------------
        //  ajax request actions
        // ---------------------------------------------------------------------------------------------


        // ---------------------------------------------------------------------------------------------
        // NewsCred MyFeeds ajax call
        // ---------------------------------------------------------------------------------------------

        // search source suggestion
        add_action( 'wp_ajax_ncajax-source-submit', array( &$this, "ncajax_get_sources_suggestion" ) );
        // search topics suggestion
        add_action( 'wp_ajax_ncajax-topic-submit', array( &$this, 'ncajax_get_topics_suggestion' ) );
        // update myFeed cron
        add_action( 'wp_ajax_ncajax-update-myfeed-cron', array( &$this, 'ncajax_update_myfeed_cron' ) );
        // add wp category for my feed
        add_action( 'wp_ajax_ncajax-add-myfeed-category', array( &$this, 'ncajax_create_myfeed_wp_category' ) );
        // create api call
        add_action( 'wp_ajax_ncajax-create-api-call', array( &$this, 'ncajax_create_api_call' ) );
        // get all myFeeds list
        add_action( 'wp_ajax_ncajax-get-all-myfeeds', array( &$this, 'ncajax_get_all_myfeeds' ) );


        // ---------------------------------------------------------------------------------------------
        // NewsCred MetaBox  ajax call
        // ---------------------------------------------------------------------------------------------


        // get sources and topics auto suggestions
        add_action( 'wp_ajax_ncajax-get-topics-sources', array( &$this, 'ncajax_get_topics_sources' ) );

        // search articles / images from meta box
        add_action( 'wp_ajax_ncajax-metabox-search', array( &$this, 'ncajax_metabox_search' ) );

        // add feature image in meta box
        add_action( 'wp_ajax_ncajax-add-image', array( &$this, 'ncajax_add_feature_image' ) );

        // get article image sets for auto publish
        add_action( 'wp_ajax_ncajax-add-article-image-set', array( &$this, 'ncajax_get_article_image_set' ) );

        // remove the feature image
        add_action( 'wp_ajax_ncajax-remove-feature-image', array( &$this, 'ncajax_remove_feature_image' ) );




    }



    public function admin_init_settings(){

        global $nc_settings_api;
        $nc_settings_api->init();

        if(isset($_GET['active_nc_plugin'])  && check_admin_referer("myfeed_active_plugin_nonce" ) ){
            $this->add_nc_plugin_setting_options();
            wp_safe_redirect( get_admin_url() );
            exit;
        }


    }

    /**
     * @param $author_name
     * @return string
     */
    function add_nc_author($author_name){
        global $post;
        $author =  get_post_meta($post->ID, '_nc_post_author', true);
        if($author){
            return "<strong>" . esc_html( $author ) ."</strong>";
        }

        return $author_name;

    }


    /**
     * nc author filter for the feeds
     * @param $author_name
     * @return string
     */

    function nc_feed_author($author_name) {
        global $post;
        $author = get_post_meta($post->ID, '_nc_post_author', true);

        if( is_feed() ) {
            if($author){
                return esc_html( $author );
            }
        }else{
            if($author){
                return "<strong>" . esc_html( $author ) ."</strong>";
            }
        }
        return $author_name;
    }



    /**
     * nc_create_post_type
     */
    public function nc_create_post_type () {

        // create myFeeds Post type

        // nc_myfeeds post type
        register_post_type( 'nc_myfeeds', array( 'show_ui' => false ) );

        // nc_myfeeds_publish post type
        register_post_type( 'nc_myfeeds_publish', array( 'show_ui' => false ) );


    }

    /***
     * load plugin
     */
    public function load () {
        $this->init();
    }

    /**
     * admin menu functions
     */
    function admin_menu () {

        if(current_user_can("manage_options")){
            add_menu_page(
                'Newscred Settings',
                'NewsCred',
                'manage_options',
                'nc-main-settings-page',
                array(
                    $this,
                    'newscred_settings_page'
                ),
                NC_IMAGES_URL . "/nc-icon.png", '6.69' );


            add_submenu_page(
                'nc-main-settings-page',
                'MyFeeds Settings',
                'MyFeeds',
                'manage_options',
                'nc-myfeeds-settings-page',
                array(
                    $this,
                    'myfeeds_settings_page'
                ) );

            global $submenu,$menu;
            $submenu[ 'nc-main-settings-page' ][ 0 ][ 0 ] = "Settings";
        }

    }

    /**
     * newscred_settings  page
     */

    function newscred_settings_page () {
        $this->_nc_utility->load_controller( "Settings" );

    }

    /**
     * myfeeds_settings_page
     */
    function myfeeds_settings_page () {
        $this->_nc_utility->load_controller( "Myfeeds" );
    }

    /* Adds a box to the main column on the Post and Page edit screens */
    function nc_editors_picks_meta_box () {
        foreach ( $this->get_supported_post_types() as $post_type ) {
            add_meta_box( 'ncmeta_sectionid', 'NewsCred', array( $this->_nc_utility, 'load_controller' ),
                          $post_type, 'side', 'high', array( 'controller' => "Metabox" ) );
        }
    }

    function get_custom_post_types() {
        $custom_post_type = get_option( "nc_article_custom_post_type" );

        $custom_post_types = array();

        if ( isset( $custom_post_type ) && $custom_post_type ) {
            $custom_post_types = explode( ",", $custom_post_type );
        }

        return $custom_post_types;
    }

    function get_supported_post_types() {
        $post_types = $this->get_custom_post_types();

        $post_types[] = 'post';

        return $post_types;
    }

    function add_nc_plugin_setting_options () {

        add_option( "nc_plugin_access_key", "" );

        // article settings
        add_option( "nc_article_author_role", "subscriber" );
        add_option( "nc_article_custom_post_type", "" );

        add_option( "nc_article_fulltext", 1 );
        add_option( "nc_article_has_images", 1 );
        add_option( "nc_article_publish_time", 1 );
        add_option( "nc_article_tags", 1 );
        add_option( "nc_article_categories", 1 );

        // image search
        add_option( "nc_image_minwidth", 300 );
        add_option( "nc_image_minheight", 200 );
        add_option( "nc_image_post_width", 400 );
        add_option( "nc_image_post_height", 300 );

    }
    /**
     * Check Nonce and Capability of the Ajax Calls
     * @param $nonce_str
     * @return bool
     */
    public function check_nonce_capability($nonce_str){

        if ($_SERVER['REQUEST_METHOD'] === 'POST')
            $nonce = $_POST[$nonce_str];
        else
            $nonce = $_GET[$nonce_str];

        // check nonce
        if ( ! wp_verify_nonce( $nonce, $nonce_str ) )
		    return false;

        // check capability
        if ( ! current_user_can( 'edit_posts' ) )
            return false;

        return true;
    }

    //----------------------------------------
    // ajax calls
    //----------------------------------------

    // myFeeds ajax callback functions
    // ---------------------------------------
    /**
     * source auto suggestion ajax call
     * ncajax_get_sources_suggestion
     * */

    function ncajax_get_sources_suggestion () {
        global $nc_source;
        $nc_source->get_sources_suggestion();
    }

    /**
     * topic auto suggestion ajax call
     * ncajax_get_topics_suggestion
     * */
    function  ncajax_get_topics_suggestion () {
        global $nc_topic;
        $nc_topic->get_topics_suggestion();
    }

    /**
     * ncajax_update_myfeed_cron
     */
    function ncajax_update_myfeed_cron () {
        echo $this->_nc_utility->load_controller( "Myfeeds", "update_myfeed_cron" );
    }
    /**
     * ncajax_create_myfeed_wp_category
     */
    function ncajax_create_myfeed_wp_category () {
        $this->_nc_utility->load_controller( "Myfeeds", "create_wp_category" );
    }
    /**
     * ncajax_create_api_call
     */
    function ncajax_create_api_call () {
        $this->_nc_utility->load_controller( 'Myfeeds', 'create_api_call' );
    }

    /**
     * get all myFeeds list
     */
    function ncajax_get_all_myfeeds () {
        $this->_nc_utility->load_controller( "Myfeeds", "get_all_myfeeds" );
    }






    // MetaBox  ajax callback functions
    // ---------------------------------------

    /**
     * ncajax_get_topics_sources
     */
    function ncajax_get_topics_sources () {
        $this->_nc_utility->load_controller( "Metabox", "get_suggested_topics_source" );
    }



    /**
     * artcile search
     * */

    function ncajax_metabox_search () {
        $this->_nc_utility->load_controller( "Metabox", "search" );
    }

    /**
     * ncajax_add_feature_image
     */

    function ncajax_add_feature_image () {
        $this->_nc_utility->load_controller( "Metabox", "add_feature_image" );
    }



    /**
     * ncajax_get_article_image_set
     */
    function ncajax_get_article_image_set () {
        $this->_nc_utility->load_controller( 'Myfeeds', 'get_image_sets' );
    }

    /**
     * ncajax_remove_feature_image
     */
    function ncajax_remove_feature_image () {
        $this->_nc_utility->load_controller( "Metabox", "remove_feature_image" );
    }






    /**
     * load the nc_scripts of this plugin
     */
    function nc_scripts () {

        global $pagenow;


        if ( $pagenow == "post.php"
            || $pagenow == "post-new.php"
            || (isset( $_GET[ 'page' ] ) && $_GET[ 'page' ] == "nc-myfeeds-settings-page" ) ){
            /**
             *  nc style
             * */

            $id = 'nc_style';
            $file = sprintf( '%s/style.min.css', NC_CSS_URL );
            wp_register_style( $id, $file );
            wp_enqueue_style( $id );


            /**
             * ie 8  style
             */
            wp_enqueue_style( 'nc-ie8', NC_CSS_URL . '/nc-ie8.css' );
            global $wp_styles;
            $wp_styles->add_data( 'nc-ie8', 'conditional', 'lte IE 8' );


            /**
             * ie 9  style
             */
            wp_enqueue_style( 'nc-ie9', NC_CSS_URL . '/nc-ie9.css' );
            global $wp_styles;
            $wp_styles->add_data( 'nc-ie9', 'conditional', 'gte IE 9' );



            // js lib file
            wp_enqueue_script(
                'nc-lib',
                NC_BUILD_URL . '/js/lib.js',
                array( 'jquery' ),
                false,
                true
            );

        }

        // meta box scripts
        if ( ( $pagenow == "post.php" || $pagenow == "post-new.php" ) && ( in_array( get_current_screen()->post_type, $this->get_supported_post_types() ) ) ) {
            wp_enqueue_script( 'nc-backbone-script', NC_BUILD_URL . '/js/metabox.js',
                array(
                    'jquery',
                    'jquery-ui-tabs',
                    'jquery-ui-autocomplete',
                    'jquery-effects-drop',
                    'underscore',
                    'backbone'),
                false, true );

            wp_localize_script( 'nc-backbone-script', 'NC_globals',
                array(
                    'is_login'           => is_user_logged_in(),
                    'domain'            => site_url(),
                    'ajaxurl'           => admin_url( 'admin-ajax.php' ),
                    'jsurl'             => NC_JS_URL,
                    'imageurl'          => NC_IMAGES_URL,
                    "default_width"      => get_option('nc_image_post_width'),
                    "default_height"     => get_option('nc_image_post_height'),
                    'nc_get_myfeeds_nonce'    => wp_create_nonce( 'nc_get_myfeeds_nonce' ),
                    'nc_get_source_topic_nonce'    => wp_create_nonce( 'nc_get_source_topic_nonce' ),
                    'nc_search_nonce'    => wp_create_nonce( 'nc_search_nonce' ),
                    'nc_add_feature_image_nonce'    => wp_create_nonce( 'nc_add_feature_image_nonce' ),
                    'nc_get_image_set_nonce'    => wp_create_nonce( 'nc_get_image_set_nonce' ),
                    'nc_remove_feature_image_nonce'    => wp_create_nonce( 'nc_remove_feature_image_nonce' )
                )
            );
        }

        // myFeeds scripts
        if ( isset( $_GET[ 'page' ] ) && $_GET[ 'page' ] == "nc-myfeeds-settings-page" ){

            /**
             * nc js  script
             * */

            wp_enqueue_script(
                'nc-script',
                NC_BUILD_URL . '/js/myfeeds.js',
                array( 'jquery', 'jquery-effects-drop', ),
                false,
                true
            );

            wp_localize_script(
                'nc-script',
                'nc_ajax',
                array(
                    'ajaxurl'               => admin_url( 'admin-ajax.php' ),
                    'nc_get_sources_nonce'     => wp_create_nonce( 'nc_get_sources_nonce' ),
                    'nc_get_topics_nonce'      => wp_create_nonce( 'nc_get_topics_nonce' ),
                    'nc_myfeeds_update_corn_nonce' => wp_create_nonce( 'nc_myfeeds_update_corn_nonce' ),
                    'nc_add_category_nonce'    => wp_create_nonce( 'nc_add_category_nonce' ),
                    'nc_create_apicall_nonce'    => wp_create_nonce( 'nc_create_apicall_nonce' )
                )
            );


        }

    }

}