<?php
/*
Plugin Name: Livefyre Comments 3
Plugin URI: http://livefyre.com/wordpress/v3#
Description: Implements Livefyre Comments 3 for WordPress VIP
Author: Livefyre, Inc.
Version: 1.0
Author URI: http://livefyre.com/
*/


require_once( dirname( __FILE__ ) . "/livefyre_core.php" );

// Constants
define( 'LF_CMETA_PREFIX', 'livefyre_cmap_' );
define( 'LF_AMETA_PREFIX', 'livefyre_amap_' );
define( 'LF_DEFAULT_HTTP_LIBRARY', 'Livefyre_Http_Extension' );

class Livefyre_Application {

    function __construct( $lf_core ) {
    
        $this->lf_core = $lf_core;

    }

    function home_url() {
    
        return $this->get_option( 'home' );
        
    }
    
    function delete_option( $optionName ) {
    
        return delete_option( $optionName );
        
    }
    
    function update_option( $optionName, $optionValue ) {
    
        return update_option( $optionName, $optionValue );
        
    }
    
    function get_option( $optionName, $defaultValue = '' ) {
    
        return get_option( $optionName, $defaultValue );
        
    }
    
    function reset_caches() {
    
        global $cache_path, $file_prefix;
        if ( function_exists( 'prune_super_cache' ) ) {
            prune_super_cache( $cache_path, true );
        }
        if ( function_exists( 'wp_cache_clean_cache' ) ) {
            wp_cache_clean_cache( $file_prefix );
        }
    }

    function setup_activation( $Obj ) {

        register_activation_hook( __FILE__, array( &$Obj, 'activate' ) );
        register_deactivation_hook( __FILE__, array( &$Obj, 'deactivate' ) );

    }
    
    function setup_health_check( $Obj ) {

        add_action( 'init', array( &$Obj, 'livefyre_health_check' ) );

    }

    function setup_sync( $obj ) {

        add_action( 'livefyre_sync', array( &$obj, 'do_sync' ) );
        add_action( 'init', array( &$obj, 'comment_update' ) );
    
    }
    
    function debug_log( $debugStr ) {

        if ( $this->lf_core->debug_mode ) {
            // disabled for production
            return true;
        }
        return false;
    
    }
    
    function activity_log( $wp_comment_id = "", $lf_comment_id = "", $lf_activity_id = "" ) {
    
        // Use meta keys that will allow us to lookup by Livefyre comment i
        update_comment_meta( $wp_comment_id, LF_CMETA_PREFIX . $lf_comment_id, $lf_comment_id );
        update_comment_meta( $wp_comment_id, LF_AMETA_PREFIX . $lf_activity_id, $lf_activity_id );
        return false;

    }
    
    function get_app_comment_id( $lf_comment_id ) {
    
        global $wpdb;
        $wp_comment_id = wp_cache_get( $lf_comment_id, 'livefyre-comment-map' );
        if ( false === $wp_comment_id ) {
            $wp_comment_id = $wpdb->get_var( $wpdb->prepare( "SELECT comment_id FROM $wpdb->commentmeta WHERE meta_key = %s LIMIT 1", LF_CMETA_PREFIX . $lf_comment_id ) );
            if ( $wp_comment_id ) {
                wp_cache_set( $lf_comment_id, $wp_comment_id, 'livefyre-comment-map' );
            }
        }
        return $wp_comment_id;

    }
    
    function schedule_sync( $timeout ) {
    
        $hook = 'livefyre_sync';
        
        // try to clear the hook, for race condition safety
        wp_clear_scheduled_hook( $hook );
        $this->debug_log( time() . " scheduling sync to occur in $timeout" );
        wp_schedule_single_event( time() + $timeout, $hook );
    
    }
    
    private static $comment_fields = array(
        "comment_author",
        "comment_author_email",
        "comment_author_url",
        "comment_author_IP",
        "comment_content",
        "comment_ID",
        "comment_post_ID",
        "comment_parent",
        "comment_approved"
    );
    
    function sanitize_inputs ( $data ) {
        
        // sanitize inputs
        $cleaned_data = array();
        foreach ( $data as $key => $value ) {
            // 1. do we care ? if so, add it
            if ( in_array( $key, self::$comment_fields ) ) {
                $cleaned_data[ $key ] = $value;
            }
        }
        return wp_filter_comment( $cleaned_data );
        
    }
    
    function delete_comment( $data ) {

        return wp_delete_comment( $this->sanitize_inputs( $data ) );

    }

    function insert_comment( $data ) {

        $sanitary_data = $this->sanitize_inputs( $data );
        return $this->without_wp_notifications( 'wp_insert_comment', array( $sanitary_data ) );

    }

    function update_comment( $data ) {

        $sanitary_data = $this->sanitize_inputs( $data );
        return $this->without_wp_notifications( 'wp_update_comment', array( $sanitary_data ) );

    }
    
    function without_wp_notifications( $func_name, $args ) {
    
        $old_notify_setting = get_option( 'comments_notify', false );
        if ( $old_notify_setting !== false ) {
            update_option( 'comments_notify', '' );
        }
        $ret_val = call_user_func_array( $func_name, $args );
        if ( $old_notify_setting !== false ) {
            update_option( 'comments_notify', $old_notify_setting );
        }
        return $ret_val;
    
    }
    
    function update_comment_status( $app_comment_id, $status ) {
    
        // Livefyre says unapproved, WordPress says hold.
        $wp_status = ( $status == 'unapproved' ? 'hold' : $status );
        $args = array( $app_comment_id, $wp_status );
        $this->without_wp_notifications( 'wp_set_comment_status', $args );
    
    }

} // Livefyre_Application

class Livefyre_Admin {
    
    function __construct( $lf_core ) {
        $this->lf_core = $lf_core;
        $this->ext = $lf_core->ext;
        
        add_action( 'admin_menu', array( &$this, 'register_admin_page' ) );
        add_action( 'admin_init', array( &$this, 'site_options_init' ) );
        
    }

    function register_admin_page() {
        
        add_submenu_page( 'options-general.php', 'Livefyre Settings', 'Livefyre', 
            'manage_options', 'livefyre', array( &$this, 'site_options_page' ) );

    }

    function settings_callback() {}
    
    function site_options_init() {
        $name = 'livefyre';
        $settings_section = 'livefyre_site_options';

		// Site ID / livefyre_site_id: This is always an integer, but with unspecified length, eg 310618
        register_setting($settings_section, 'livefyre_site_id', 'intval'); // 2147483647 is the highest allowed on 32 bit systems

		// Site Key / livefyre_site_key: This is a hex encoded string. Validation should be lite as the format can change.
        register_setting($settings_section, 'livefyre_site_key', 'esc_attr'); 

		// Network name / livefyre_domain_name / profile domain: This is a string of the form "{something}.fyre.co" OR "livefyre.com"
        register_setting($settings_section, 'livefyre_domain_name', 'esc_attr');

		// default TLD Domain
        register_setting($settings_section, 'livefyre_tld', 'esc_attr');

		// Network Key / livefyre_domain_key: This is a hex encoded string. Validation should be lite as the format can change.
        register_setting($settings_section, 'livefyre_domain_key', 'esc_attr');

        add_settings_section($settings_section,
            'Livefyre Site Settings',
            array( &$this, 'settings_callback' ),
            $name);

        add_settings_field('livefyre_domain_name',
            'Livefyre Network Name',
            array( &$this, 'domain_name_callback' ),
            $name,
            $settings_section);

        add_settings_field('livefyre_tld',
            'Livefyre Host',
            array( &$this, 'tld_callback' ),
            $name,
            $settings_section);

        add_settings_field('livefyre_domain_key',
            'Livefyre Network Key',
            array( &$this, 'domain_key_callback' ),
            $name,
            $settings_section);
        
        add_settings_field('livefyre_site_id',
            'Livefyre Site ID',
            array( &$this, 'site_id_callback' ),
            $name,
            $settings_section);
        
        add_settings_field('livefyre_site_key',
            'Livefyre Site Key',
            array( &$this, 'site_key_callback' ),
            $name,
            $settings_section);
        
    }

    function site_options_page() {

        ?>
            <div class="wrap">
                <h2>Livefyre Settings Page</h2>
                <form method="post" action="options.php">
                    <?php
                        settings_fields( 'livefyre_site_options' );
                        do_settings_sections( 'livefyre' );
                    ?>
                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php _e( 'Save Changes' ) ?>" />
                    </p>
                </form>
            </div>
        <?php
    
    }

    function site_id_callback() {
    
        echo "<input name='livefyre_site_id' value='" . esc_attr( get_option( 'livefyre_site_id' ) ) . "' />";
        
    }
    
    function site_key_callback() { 
    
        echo "<input name='livefyre_site_key' value='" . esc_attr( get_option( 'livefyre_site_key' ) ) . "' />";
        
    }
    
    function domain_name_callback() {
    
        echo "<input name='livefyre_domain_name' value='" . esc_attr( get_option( 'livefyre_domain_name', LF_DEFAULT_PROFILE_DOMAIN ) ) ."' />";
        
    }
    
    function tld_callback() {
    
        echo "<input name='livefyre_tld' value='" . esc_attr( get_option( 'livefyre_tld', LF_DEFAULT_TLD  ) ) ."' />";
        
    }
    
    function domain_key_callback() { 
    
        echo "<input name='livefyre_domain_key' value='" . esc_attr( get_option( 'livefyre_domain_key' ) ) ."' />";
        
    }
    
    function get_app_comment_id( $lf_comment_id ) {

        return $this->ext->get_app_comment_id( $lf_comment_id );

    }
    
    static function lf_warning_display( $message ) {
        echo '<div id="livefyre-warning" class="updated fade"><p>' . esc_html( $message ) . '</p></div>';
    }

}


class Livefyre_Display {

    function __construct( $lf_core ) {
    
        $this->lf_core = $lf_core;
        $this->ext = $lf_core->ext;
        
        if ( ! $this->livefyre_comments_off() ) {
            add_action( 'wp_head', array( &$this, 'lf_embed_head_script' ) );
            add_action( 'wp_footer', array( &$this, 'lf_init_script' ) );
            add_filter( 'comments_template', array( &$this, 'livefyre_comments' ) );
            add_filter( 'comments_number', array( &$this, 'livefyre_comments_number' ), 10, 2 );
        }
    
    }

    function livefyre_comments_off() {
    
        return ( $this->ext->get_option( 'livefyre_site_id', '' ) == '' );

    }
    
    function lf_embed_head_script() {
    
        echo $this->lf_core->lf_domain_object->source_js_v3();
    
    }
    
    function lf_init_script() {
        global $post, $current_user, $wp_query;
        $network = $this->ext->get_option( 'livefyre_domain_name', LF_DEFAULT_PROFILE_DOMAIN );
        try{
            if ( $this->livefyre_show_comments() && comments_open() ) {// is this a post page?
                // It is possible that the ID of the queried object is for a revision. Be sure to get it back to the originating post.
                if( $parent_id = wp_is_post_revision( $wp_query->get_queried_object_id() ) ) {
                    $original_id = $parent_id;
                } else {
                    $original_id = $wp_query->get_queried_object_id();
                }
                $post_obj = get_post( $wp_query->get_queried_object_id() );
                $domain = $this->lf_core->lf_domain_object;
                $site = $this->lf_core->site;
                $article = $site->article( $original_id, get_permalink($original_id), get_the_title($original_id) );
                $conv = $article->conversation();
                $initcfg = array();
                $initcfg['betaBanner'] = false;
                if ( function_exists( 'livefyre_onload_handler' ) ) {
                    $initcfg['onload'] = livefyre_onload_handler();
                }
                if ( function_exists( 'livefyre_delegate_name' ) ) {
                    $initcfg['delegate'] = livefyre_delegate_name();
                }
                echo $conv->to_initjs_v3('comments', $initcfg);
            } else if ( !is_single() ) {
                echo '<script type="text/javascript" data-lf-domain="' . esc_attr( $network ) . '" id="ncomments_js" src="' . esc_url( $this->lf_core->assets_url ) .'/wjs/v1.0/javascripts/CommentCount.js"></script>';
            }
        } catch ( Exception $error ) {
            // do nothing but silence the jsonEncode utf-8 error see http://99deploys.wordpress.com/2014/05/27/these-fatal-errors-have-been/
            // http://99deploys.wordpress.com/2014/05/21/i-got-99-fatals-and/
            return false;
        }
    }

    function livefyre_comments( $cmnts ) {

        return dirname( __FILE__ ) . '/comments-template.php';

    }

    function livefyre_show_comments(){

        return ( is_singular() || is_page() ) && ! is_preview();

    }

    function livefyre_comments_number( $count ) {

        global $post;
        return '<span data-lf-article-id="' . $post->ID . '" data-lf-site-id="' . esc_attr( get_option('livefyre_site_id', '') ) . '" class="livefyre-commentcount">' . intval( $count ) . '</span>';

    }  
}

class Livefyre_Http_Extension {
    // Map the Livefyre request signature to what WordPress expects.
    // This just means changing the name of the payload argument.
    public function request( $url, $args = array() ) {
        $http = new WP_Http;
        if ( isset( $args[ 'data' ] ) ) {
            $args[ 'body' ] = $args[ 'data' ];
            unset( $args[ 'data' ] );
        }
        return $http->request( $url, $args );
    }
}


$livefyre = new Livefyre_core;
