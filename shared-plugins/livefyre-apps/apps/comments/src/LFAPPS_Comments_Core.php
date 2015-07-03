<?php
//Disallow direct access to this file
if(!defined('LFAPPS__PLUGIN_PATH')) 
    die('Bye');

class LFAPPS_Comments_Core {
    public static $bootstrap_url_v3;
    public static $quill_url;
    /*
     * Build the plugins core functionality.
     *
     */
    function __construct() {
        self::add_extension();
        self::require_php_api();
        self::define_globals();
        self::require_subclasses();
    }
    
    /*
     * Helper function that allows classes to use this as a bank for
     * all useful Livefyre values.
     *
     */
    function define_globals() {

        $client_key = get_option('livefyre_apps-livefyre_domain_key', '' );
        $profile_domain = get_option('livefyre_apps-livefyre_domain_name', 'livefyre.com' );
        $dopts = array(
            'livefyre_tld' => LFAPPS_COMMENTS_DEFAULT_TLD
        );
        $uses_default_tld = (strpos(LFAPPS_COMMENTS_DEFAULT_TLD, 'livefyre.com') === 0);
        
        $this->top_domain = ( $profile_domain == LFAPPS_COMMENTS_DEFAULT_PROFILE_DOMAIN ? LFAPPS_COMMENTS_DEFAULT_TLD : $profile_domain );
        $this->http_url = ( $uses_default_tld ? "http://www." . LFAPPS_COMMENTS_DEFAULT_TLD : "http://" . LFAPPS_COMMENTS_DEFAULT_TLD );
        $this->api_url = "http://api.$this->top_domain";
        self::$quill_url = "http://quill.$this->top_domain";
        $this->admin_url = "http://admin.$this->top_domain";
        $this->assets_url = "http://zor." . LFAPPS_COMMENTS_DEFAULT_TLD;
        $this->bootstrap_url = "http://bootstrap.$this->top_domain";
        
        // for non-production environments, we use a dev url and prefix the path with env name
        $bootstrap_domain = 'bootstrap-json-dev.s3.amazonaws.com';
        $environment = $dopts['livefyre_tld'] . '/';
        if ( $uses_default_tld ) {
            $bootstrap_domain = 'data.bootstrap.fyre.co';
            $environment = '';
        }
        
        $existing_blogname = $this->ext->get_option( 'livefyre_blogname', false );
        if ( $existing_blogname ) {
            $site_id = $existing_blogname;
        } else {
            $site_id = get_option('livefyre_apps-livefyre_site_id', false );
        }

        self::$bootstrap_url_v3 = "http://$bootstrap_domain/$environment$profile_domain/$site_id";
        
        $this->home_url = $this->ext->home_url();
        $this->plugin_version = LFAPPS_COMMENTS_PLUGIN_VERSION;

    }
    
    /*
     * Grabs the Livefyre PHP api.
     *
     */
    function require_php_api() {

        require_once(LFAPPS__PLUGIN_PATH . "/libs/php/LFAPPS_JWT.php");

    }

    /*
     * Adds the extension for WordPress.
     *
     */
    function add_extension() {

        require_once( dirname( __FILE__ ) . '/LFAPPS_Comments_Extension.php' );
        $this->ext = new LFAPPS_Comments_Extension();
    }

    /*
     * Builds necessary classes for the WordPress plugin.
     *
     */
    function require_subclasses() {

        require_once( dirname( __FILE__ ) . '/display/LFAPPS_Comments_Display.php' );
        require_once( dirname( __FILE__ ) . '/import/LFAPPS_Comments_Import_Impl.php' );
        require_once( dirname( __FILE__ ) . '/LFAPPS_Comments_Activation.php' );
        require_once( dirname( __FILE__ ) . '/LFAPPS_Comments_Utility.php' );
        require_once( dirname( __FILE__ ) . '/sync/LFAPPS_Comments_Sync_Impl.php' );

        $this->Activation = new LFAPPS_Comments_Activation( $this );
        $this->Sync = new LFAPPS_Comments_Sync_Impl( $this );
        $this->Import = new LFAPPS_Comments_Import_Impl( $this );
        $this->Display = new LFAPPS_Comments_Display( $this );
        $this->Livefyre_Utility = new LFAPPS_Comments_Utility( $this );
    }

} //  Livefyre_core
