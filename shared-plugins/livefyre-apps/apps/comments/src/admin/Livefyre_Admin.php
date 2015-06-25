<?php
/*
Author: Livefyre, Inc.
Version: 4.2.0
Author URI: http://livefyre.com/
*/

define( 'LF_SITE_SETTINGS_PAGE', '/enterprise-settings.php' );
#define( 'LF_SITE_SETTINGS_PAGE', '/settings-template.php' );

class Livefyre_Admin {
      
    /*
     * Sets all the actions for building the admin pages.
     *
     */
    function __construct( $lf_core ) {
        
        $this->lf_core = $lf_core;
        $this->ext = $lf_core->ext;
        
        add_action( 'admin_menu', array( &$this, 'register_admin_page' ) );
        add_action( 'admin_notices', array( &$this, 'lf_install_warning') );
        add_action( 'admin_init', array( &$this->lf_core->Admin, 'plugin_upgrade' ) );
        add_action( 'admin_init', array( &$this, 'site_options_init' ) );       
        add_action( 'network_admin_edit_save_network_options', array($this, 'do_save_network_options'), 10, 0);
    }
    
    /*
     * We have to way to hook into an action that happens on auto-upgrade.
     * This is the work-around for that.
     *
     */
    function plugin_upgrade() {
    
        if ( get_option( 'livefyre_v3_installed', false ) === false ) {
           $this->lf_core->Activation->activate();
        } else if ( get_option( 'livefyre_blogname', false ) !== false ) {
           $this->lf_core->Activation->activate();
        }
    
    }

    /*
     * Default callback for settings. IE nothing.
     *
     */
    function settings_callback() {}
    
    private function allow_domain_settings() {
    
        # Should we collect domain (Livefyre profile domain) settings at
        # the blog level or multisite-wide?
        return is_multisite();
    
    }

    /*
     * Registers an admin page with WordPress.
     *
     */
    function register_admin_page() {
        
        add_submenu_page( 'options-general.php', 'Livefyre Settings', 'Livefyre', 'manage_options', 'livefyre', array( &$this, 'site_options_page' ) );
    }
    
    /*
     * Sets the site settings for Multisite and Non-Multisite.
     *
     */
    function site_options_init() {
    
        $name = 'livefyre';
        $section_name = 'lf_site_settings';
        $settings_section = 'livefyre_site_options';
        register_setting( $settings_section, 'livefyre_site_id' );
        register_setting( $settings_section, 'livefyre_site_key' );
        register_setting( $settings_section, 'livefyre_domain_name' );
        register_setting( $settings_section, 'livefyre_domain_key' );
        register_setting( $settings_section, 'livefyre_auth_delegate_name' );
        register_setting( $settings_section, 'livefyre_environment' );
        
        if( self::returned_from_setup() ) {
            $this->ext->update_option( "livefyre_site_id", sanitize_text_field( $_GET["site_id"] ) );
            $this->ext->update_option( "livefyre_site_key", sanitize_text_field( $_GET["secretkey"] ) );
        }
        
        add_settings_section('lf_site_settings',
            '',
            array( &$this, 'settings_callback' ),
            $name
        );
        
        add_settings_field('livefyre_site_id',
            'Livefyre Site ID',
            array( &$this, 'site_id_callback' ),
            $name,
            $section_name
        );
        
        add_settings_field('livefyre_site_key',
            'Livefyre Site Key',
            array( &$this, 'site_key_callback' ),
            $name,
            $section_name
        );

        add_settings_field('livefyre_domain_name',
            'Livefyre Network Name',
            array( &$this, 'domain_name_callback' ),
            $name,
            $section_name
        );
        
        add_settings_field('livefyre_domain_key',
            'Livefyre Network Key',
            array( &$this, 'domain_key_callback' ),
            $name,
            $section_name
        );
        
        add_settings_field('livefyre_auth_delegate_name',
            'Livefyre AuthDelagate Name',
            array( &$this, 'auth_delegate_callback' ),
            $name,
            $section_name
        );

        add_settings_field('livefyre_environment',  
            'Production Credentials',  
            array( &$this, 'environment_callback' ),
            $name,
            $section_name
        ); 
        
    }

    /*
     * Include the site options page.
     *
     */
    function site_options_page() {

        /* Should we display the Enterprise or Regular version of the settings?
         * Needs to be decided by the build process
         * The file gets set in the bash script that builds this.
         * The default is community
        */

        include( dirname(__FILE__) . LF_SITE_SETTINGS_PAGE);
    
    }

    /*
     * Decide which environment (production or development) the customer's keys are.
     * This will change the JS lib that import from.
     *
     */
    function environment_callback() {
       
        $html = '<input type="checkbox" id="livefyre_environment" name="livefyre_environment" 
          value="1"' . checked( 1, get_option( 'livefyre_environment' ), false ) . '/>';
        $html .= '<label for="livefyre_environment">    Check this if you are using Production Credentials</label>';  
          
        echo $html;

    }

    /*
     * Keeps the current site id value up to date with the backend
     *
     */
    function site_id_callback() {

        echo "<input name='livefyre_site_id' value='" . esc_attr( get_option( 'livefyre_site_id' ) ) . "' />";

    }
    
    /*
     * Keeps the current site key value up to date with the backend
     *
     */
    function site_key_callback() { 

        echo "<input name='livefyre_site_key' value='" . esc_attr(get_option( 'livefyre_site_key' )) . "' />";

    }

    /*
     * Keeps the current authentication delegate value up to date with the backend
     *
     */
    function auth_delegate_callback() {

        echo "<input name='livefyre_auth_delegate_name' value='". esc_attr($this->ext->get_option( 'livefyre_auth_delegate_name', '', $this->ext->networkMode)) ."' />";

    }
    
    /*
     * Keeps the current domain name value up to date with the backend
     *
     */
    function domain_name_callback() {

        echo "<input name='livefyre_domain_name' value='". esc_attr($this->ext->get_option( 'livefyre_domain_name', 'livefyre.com', $this->ext->networkMode)) ."' />";
    
    }
    
    /*
     * Keeps the current domain key value up to date with the backend
     *
     */
    function domain_key_callback() { 
    
        echo "<input name='livefyre_domain_key' value='". esc_attr($this->ext->get_option( 'livefyre_domain_key', '', $this->ext->networkMode)) ."' />";
        
    }
    
    /*
     * Displays a message if the customer has upgraded.
     *
     */
    static function lf_warning_display( $message ) {
        echo '<div id="livefyre-warning" class="updated fade"><p>' . esc_html( $message ) . '</p></div>';
    }
    
    /*
     * Handles messages that are passed onto the WordPress Admin notifier.
     *
     */
    function lf_install_warning() {
        $livefyre_http_url = $this->lf_core->http_url;
        $livefyre_site_domain = "rooms." . LF_DEFAULT_PROFILE_DOMAIN;
    
        if (function_exists( 'home_url' )) {
            $home_url= $this->ext->home_url();
        } else {
            $home_url=$this->ext->get_option( 'home' );
        }
    }
    
    /*
     * Lets the plugin know if it's returned from Livefyre's site registration page.
     *
     */
    function returned_from_setup() {
        return ( isset( $_GET['lf_login_complete'] ) && $_GET['lf_login_complete']=='1' &&
            self::is_settings_page() );
    }
    
    /*
     * Check to make sure we are on a settings page.
     *
     */
    function is_settings_page() {
        return ( isset( $_GET['page'] ) && $_GET['page'] == 'livefyre' );
    }

}
