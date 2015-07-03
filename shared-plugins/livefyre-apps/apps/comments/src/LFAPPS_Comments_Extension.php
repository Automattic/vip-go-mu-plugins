<?php
require_once( LFAPPS__PLUGIN_PATH . "/libs/php/LFAPPS_Http_Extension.php");

class LFAPPS_Comments_Extension {
    
    /*
     * Grab the current URL of the site.
     *
     */
    function home_url() {
    
        return $this->get_option( 'home' );
        
    }
    
    /*
     * Delete a WordPress option.
     *
     */
    function delete_option( $optionName ) {
    
        return delete_option( $optionName );
        
    }
    
    /*
     * Update a WordPress option.
     *
     */
    function update_option( $optionName, $optionValue ) {
    
        return update_option( $optionName, $optionValue );
        
    }
    
    /*
     * Get a WordPress option.
     *
     */
    function get_option( $optionName, $defaultValue = '' ) {
    
        return get_option( $optionName, $defaultValue );
        
    }
          
    /*
     * Reset all WordPress caches.
     *
     */
    function reset_caches() {
    
        global $cache_path, $file_prefix;
        if ( function_exists( 'prune_super_cache' ) ) {
            prune_super_cache( $cache_path, true );
        }
        if ( function_exists( 'wp_cache_clean_cache' ) ) {
            wp_cache_clean_cache( $file_prefix );
        }
    }

    /*
     * Set up activation code.
     *
     */
    function setup_activation( $Obj ) {
        register_activation_hook( __FILE__, array( &$Obj, 'activate' ) );
        register_deactivation_hook( __FILE__, array( &$Obj, 'deactivate' ) );

    }

    /*
     * Set up site sync code.
     *
     * TODO: sed this out for enterprise.
     */
    function setup_sync( $obj ) {

        add_action( 'livefyre_sync', array( &$obj, 'do_sync' ) );
        $obj->comment_update();
        
        /* START: Public Plugin Only */
        if ( $this->get_option( 'livefyre_profile_system', 'livefyre' ) == 'wordpress' ) {
            $obj->check_profile_pull();
            add_action( 'profile_update', array( &$obj, 'profile_update' ) );
            add_action( 'profile_update', array( &$this, 'profile_update' ) );
        }
        /* END: Public Plugin Only */
    
    }
    
    /*
     * Set up import code.
     *
     * TODO: sed this out for enterprise.
     */
    function setup_import( $obj ) {
        $obj->check_import();
        $obj->check_activity_map_import();
        $obj->begin();  
    }
    
    /*
     * Updates comment's meta data. Currently used by both sync and import.
     *
     * TODO: sed this out for enterprise.
     */
    function activity_log( $wp_comment_id = "", $lf_comment_id = "", $lf_activity_id = "" ) {
    
        // Use meta keys that will allow us to lookup by Livefyre comment i
        update_comment_meta( $wp_comment_id, LFAPPS_CMETA_PREFIX . $lf_comment_id, $lf_comment_id );
        update_comment_meta( $wp_comment_id, LFAPPS_AMETA_PREFIX . $lf_activity_id, $lf_activity_id );
        return false;

    }

} // LFAPPS_Comments_Extension
