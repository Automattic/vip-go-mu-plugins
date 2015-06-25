<?php
class LFAPPS_Comments_Activation {

    function __construct( $lf_core ) {
    
        $this->lf_core = $lf_core;
        $this->ext = $lf_core->ext;
        $this->ext->setup_activation( $this );

    }

    /*
     * Nuke the caches and mark LF as deactivated.
     *
     */
    function deactivate() {
        
        $this->reset_caches();
        $this->ext->update_option( 'livefyre_deactivated', 'Deactivated: ' . time() );

    }

    /*
     * Runs through a series of checks to make sure that we know the state of the 
     * customer.
     * States:
     * Upgraded: The user has upgraded from comments2 to LiveComments. Need to run a backfill. As
     * well as handle some other features.
     * Just reactivated: The user just had us off for a brief period of time. Do not rerun an import.
     *
     */
    function activate() {
        $existing_blogname = $this->ext->get_option( 'livefyre_blogname', false );
        if ( $existing_blogname ) {
            $site_id = $existing_blogname;
            $existing_key = $this->ext->get_option( 'livefyre_secret', false );
            update_option('livefyre_apps-'. 'livefyre_site_id', $site_id );
            $this->ext->delete_option( 'livefyre_blogname' );
            update_option('livefyre_apps-'. 'livefyre_site_key', $existing_key );
            $this->ext->delete_option( 'livefyre_secret' );
        } else {
            $site_id = get_option('livefyre_apps-livefyre_site_id', false );
        }
        
        if ( !get_option('livefyre_apps-livefyre_domain_name', false) ) {
            // Initialize default profile domain i.e. livefyre.com
            $defaultDomainName = get_option('livefyre_apps-livefyre_domain_name', LF_DEFAULT_PROFILE_DOMAIN);
            update_option('livefyre_apps-'. 'livefyre_domain_name', $defaultDomainName );
        }       
        if ( !get_option('livefyre_apps-livefyre_auth_delegate_name', false) ) {
            $defaultDelegate = get_option('livefyre_apps-livefyre_auth_delegate_name', '');
            update_option('livefyre_apps-'. 'livefyre_auth_delegate_name', $defaultDelegate );
        }   
        if ( !get_option('livefyre_apps-livefyre_domain_key', false) ) {
            $defaultKey = get_option('livefyre_apps-livefyre_domain_key', '');            
            update_option('livefyre_apps-'. 'livefyre_domain_key', $defaultKey );
        }
        
        if ( !$this->ext->get_option( 'livefyre_v3_installed', false ) ) {
            // Set a flag to show the 'hey you just upgraded' (or installed) flash message
            // Set the timestamp so we know which posts use V2 vs V3
            if ( $site_id ) {
                $this->ext->update_option( 'livefyre_v3_installed', current_time('timestamp') );
                $this->ext->update_option( 'livefyre_v3_notify_upgraded', 1 );
                $this->run_backfill( $site_id ); //only run backfill on existing blogs
            } else {
                // !IMPORTANT
                // livefyre_v3_installed == 0 is used elsewhere to determine if this
                // installation was derived from a former V2 installation
                $this->ext->update_option( 'livefyre_v3_installed', 0 );
                $this->ext->update_option( 'livefyre_v3_notify_installed', 1 );
                $this->ext->update_option( 'livefyre_backend_upgrade', 'skipped' );
            }
        }
    }

    /*
     * Upgrade comments in the Livefyre backend to be compatable in LiveComments.
     *
     */
    function run_backfill( $site_id ) {
        $backend_upgrade = $this->ext->get_option('livefyre_backend_upgrade', 'not_started' );
        if ( $backend_upgrade == 'not_started' ) {
            # Need to upgrade the backend for this plugin. It's never been done for this site.
            # Since this only happens once, notify the user and then run it.
            $url = LFAPPS_Comments_Core::$quill_url . '/import/wordpress/' . $site_id . '/upgrade';
            $http = new LFAPPS_Http_Extension;

            $resp = $http->request( $url, array( 'timeout' => 10 ) );
            if ( is_wp_error( $resp ) ) {
                update_option( 'livefyre_backend_upgrade', 'error' );
                update_option( 'livefyre_backend_msg', $resp->get_error_message() );
                return;
            }

            $resp_code = $resp['response']['code'];
            $resp_message = $resp['response']['message'];

            if ( $resp_code != '200' ) {
                update_option( 'livefyre_backend_upgrade', 'error' );
                $this->lf_core->Raven->captureMessage( "Backfill error for site " . $site_id . ": " . $resp->get_error_message() );
                return;
            }

            $json_data = json_decode( $resp['body'] );
            $backfill_status = $json_data->status;
            $backfill_msg = $json_data->msg;

            if ( $backfill_status == 'success' ) {
                $backfill_msg = 'Request for Comments 2 upgrade has been sent';
            }
            update_option( 'livefyre_backend_upgrade', $backfill_status );
            update_option( 'livefyre_backend_msg', $backfill_msg );
        }
    }

    /*
     * Nuke all of WordPress's caches.
     *
     */
    function reset_caches() {
    
        $this->ext->reset_caches();
        
    }

}
