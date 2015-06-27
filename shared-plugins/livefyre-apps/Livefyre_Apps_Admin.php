<?php
//Disallow direct access to this file
if(!defined('LFAPPS__PLUGIN_PATH')) 
    die('Bye');

require_once LFAPPS__PLUGIN_PATH . 'libs/php/LFAPPS_View.php';

if ( ! class_exists( 'Livefyre_Apps_Admin' ) ) {
    class Livefyre_Apps_Admin {        
        private static $initiated = false;
    
        public static function init() {
            if ( ! self::$initiated ) {
                self::$initiated = true;     
                if(isset($_GET['type'])) {
                    if($_GET['type'] === 'community' || $_GET['type'] === 'enterprise') {
                        update_option('livefyre_apps-initial_modal_shown', true);
                        update_option('livefyre_apps-package_type', sanitize_text_field($_GET['type']));
                        wp_redirect(self::get_page_url('livefyre_apps') . '&settings-updated=environment_changed');
                    }
                }
                self::init_hooks();     
                self::init_apps();                 
            }
        }
        
        /**
         * Initialise WP hooks
         */
        private static function init_hooks() {            
            add_action( 'admin_menu', array('Livefyre_Apps_Admin', 'init_admin_menu' ) );           
            add_action( 'admin_enqueue_scripts', array( 'Livefyre_Apps_Admin', 'load_resources' ) );
        }
        
        /**
         * Initialise admin menu items
         */
        public static function init_admin_menu() {
            add_submenu_page( 'livefyre_apps', 'General', 'General', "manage_options", 'livefyre_apps', array('Livefyre_Apps_Admin', 'menu_general'));
            add_menu_page('Livefyre Apps', 'Livefyre Apps', 'manage_options', 'livefyre_apps', array('Livefyre_Apps_Admin', 'menu_general'), LFAPPS__PLUGIN_URL."assets/img/livefyre-icon_x16.png"); 
            //community authentication page (invisible and only handles data sent back from livefyre.com)
            add_submenu_page( null, 'Livefyre', 'Livefyre', "manage_options", 'livefyre', array('Livefyre_Apps_Admin', 'menu_general'));            
        }
        
        
        /**
         * Initialise Livefyre Apps that have been switched on (Admin Classes)
         */
        private static function init_apps() {
            $conflicting_plugins = Livefyre_Apps::get_conflict_plugins();
            if(count($conflicting_plugins) > 0) {
                return;
            }
            
            if(isset($_GET['type'])) {
                if($_GET['type'] === 'community' || $_GET['type'] === 'enterprise') {
                    update_option('livefyre_apps-package_type', sanitize_text_field($_GET['type']));
                }
            }
            
            $apps = get_option('livefyre_apps-apps');
            if(is_array($apps)) {
                foreach($apps as $app) {
                    $switch = true;
                    if(get_option('livefyre_apps-package_type') == 'community' && ($app == 'chat' || $app == 'blog')) {
                        $switch = false;
                    }
                    if($switch) {
                        self::init_app($app);
                    }
                }
            }
        }
        
        /**
         * Init app
         * @param string $app
         */
        public static function init_app($app) {
            if(isset(Livefyre_Apps::$apps[$app])) {
                $app_class = Livefyre_Apps::$apps[$app] . '_Admin';
                $app_class_path = LFAPPS__PLUGIN_PATH . "apps". DIRECTORY_SEPARATOR . $app . DIRECTORY_SEPARATOR . $app_class . ".php";
                if(file_exists($app_class_path)) {
                    require_once ( $app_class_path );
                    $app_class::init();
                }
            }
        }
        
        /**
         * Add assets required by Livefyre Apps Admin section
         */
        public static function load_resources() {
            wp_register_style( 'lfapps.css', LFAPPS__PLUGIN_URL . 'assets/css/lfapps.css', array(), LFAPPS__VERSION );
			wp_enqueue_style( 'lfapps.css');
            
            wp_register_script( 'lfapps-admin.js', LFAPPS__PLUGIN_URL . 'assets/js/lfapps-admin.js', array('jquery', 'postbox', 'thickbox'), LFAPPS__VERSION );
			wp_enqueue_script( 'lfapps-admin.js');
        }
        
        /**
         * Generate admin URL for specified page
         * @param string $page
         * @return string URL
         */
        public static function get_page_url( $page ) {
            $args = array( 'page' => $page );
            
            $url = add_query_arg( $args, admin_url( 'admin.php' ) );

            return $url;
        }
        
        /**
         * Check to see if this request is a response back from livefyre.com which sets the site id + key
         * @return boolean
         */

        public static function verified_blog() {
            return isset($_GET['lf_login_complete']) && $_GET['lf_login_complete'] === 'true' 
                    && isset( $_GET['page'] ) && $_GET['page'] === 'livefyre_apps';
        }
        
        /**
         * Declare settings used in Livefyre Apps general options page
         */
        public static function init_settings() {
            register_setting('livefyre_apps_settings_general', 'livefyre_apps-livefyre_domain_name');  
            register_setting('livefyre_apps_settings_general', 'livefyre_apps-livefyre_domain_key'); 
            register_setting('livefyre_apps_settings_general', 'livefyre_apps-livefyre_site_id'); 
            register_setting('livefyre_apps_settings_general', 'livefyre_apps-livefyre_site_key');                          
            register_setting('livefyre_apps_settings_general', 'livefyre_apps-auth_type');   
            register_setting('livefyre_apps_settings_general', 'livefyre_apps-livefyre_auth_delegate_name');   
            register_setting('livefyre_apps_settings_general', 'livefyre_apps-livefyre_environment');   
                        
            register_setting('livefyre_apps_settings_apps', 'livefyre_apps-apps');
            
            //LiveComments
            $excludes = array( '_builtin' => false );
            $post_types = get_post_types( $args = $excludes );
            $post_types = array_merge(array('post'=>'post', 'page'=>'page'), $post_types);
            foreach ($post_types as $post_type ) {
                $post_type_name = 'livefyre_apps-livefyre_display_' .$post_type;
                register_setting('livefyre_apps_settings_comments', $post_type_name);
            } 
            
            //LiveChat
            foreach ($post_types as $post_type ) {
                $post_type_name = 'livefyre_apps-livefyre_chat_display_' .$post_type;
                register_setting('livefyre_apps_settings_chat', $post_type_name);
            } 
            
            //Sidenotes
            foreach ($post_types as $post_type ) {
                $post_type_name = 'livefyre_apps-livefyre_sidenotes_display_' .$post_type;
                register_setting('livefyre_apps_settings_sidenotes', $post_type_name);
            } 
            register_setting('livefyre_apps_settings_sidenotes', 'livefyre_apps-livefyre_sidenotes_selectors');
        }
        
        public static function menu_plugin_conflict() {
            LFAPPS_View::render('plugin_conflict');
        }
        
        /**
         * Run Livefyre Apps General page
         */
        public static function menu_general() {           
            $conflicting_plugins = Livefyre_Apps::get_conflict_plugins();
            if(count($conflicting_plugins) > 0) {
                self::menu_plugin_conflict();
                return;
            }
                        
            //process data returned from livefyre.com community sign up
            if(self::verified_blog()) {
                update_option('livefyre_apps-livefyre_domain_name', 'livefyre.com');
                update_option('livefyre_apps-livefyre_site_id', sanitize_text_field( $_GET["site_id"] ));
                update_option('livefyre_apps-livefyre_site_key', sanitize_text_field( $_GET["secretkey"] ));
            }
                        
            LFAPPS_View::render('general');
        }
    }   
}