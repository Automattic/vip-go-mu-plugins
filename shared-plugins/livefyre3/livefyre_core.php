<?php
/*
Livefyre Realtime Comments Core Module

This library is shared between all Livefyre plugins.

Author: Livefyre, Inc. 
Author URI: http://livefyre.com/
*/
define( 'LF_DEFAULT_PROFILE_DOMAIN', 'livefyre.com' );
define( 'LF_DEFAULT_TLD', 'livefyre.com' );
define( 'LF_SYNC_LONG_TIMEOUT', 25200 );
define( 'LF_SYNC_SHORT_TIMEOUT', 3 );
define( 'LF_SYNC_MAX_INSERTS', 25 );
define( 'LF_SYNC_ACTIVITY', 'lf-activity' );
define( 'LF_SYNC_MORE', 'more-data' );
define( 'LF_SYNC_ERROR', 'error' );
define( 'LF_PLUGIN_VERSION', '1.0' );

global $livefyre;

class Livefyre_core {

    function __construct() { 

        $this->add_extension();
        $this->require_php_api();
        $this->define_globals();
        $this->require_subclasses();
        
    }
    
    function define_globals() {
    
        $this->options = array( 
            'livefyre_site_id', // - name ( id ) of the livefyre record associated with this blog
            'livefyre_site_key' // - shared key used to sign requests to/from livefyre
        );

        $client_key = $this->ext->get_option( 'livefyre_domain_key', '' );
        $profile_domain = $this->ext->get_option( 'livefyre_domain_name', LF_DEFAULT_PROFILE_DOMAIN );
        $tld = $this->ext->get_option( 'livefyre_tld', LF_DEFAULT_TLD );
        $dopts = array(
            'livefyre_tld' => $tld
        );
        $uses_default_tld = ( $tld === LF_DEFAULT_TLD );
        $this->lf_domain_object = new Livefyre_Domain( $profile_domain, $client_key, null, $dopts );
        $site_id = $this->ext->get_option( 'livefyre_site_id' );
        $this->site = $this->lf_domain_object->site( 
            $site_id, 
            trim( $this->ext->get_option( 'livefyre_site_key' ) )
        );
        $this->debug_mode = false;
        $this->top_domain = ( $profile_domain == LF_DEFAULT_PROFILE_DOMAIN ? $tld : $profile_domain );
        $this->http_url = "http://" . $tld;
        $this->api_url = "http://api.$this->top_domain";
        $this->quill_url = "http://quill.$this->top_domain";
        $this->admin_url = "http://admin.$this->top_domain";
        $this->assets_url = "http://zor." . $tld;
        $this->bootstrap_url = "http://bootstrap.$this->top_domain";
        
        // for non-production environments, we use a dev url and prefix the path with env name
        $bootstrap_domain = 'bootstrap-json-dev.s3.amazonaws.com';
        $environment = $dopts['livefyre_tld'] . '/';
        if ( $uses_default_tld ) {
            $bootstrap_domain = 'data.bootstrap.fyre.co';
            $environment = '';
        }

        $this->bootstrap_url_v3 = "http://$bootstrap_domain/$environment$profile_domain/$site_id";
        
        $this->home_url = $this->ext->home_url();
        $this->plugin_version = LF_PLUGIN_VERSION;

    }
    
    function require_php_api() {

        require_once(dirname(__FILE__) . "/livefyre-api/libs/php/Livefyre.php");

    }

    function add_extension() {

        if ( class_exists( 'Livefyre_Application' ) ) {
            $this->ext = new Livefyre_Application( $this );
        } else {
            die( "There is no Application Module ( WordPress, Joomla, or other )included with this plugin .  Error: Class Livefyre_Application not defined . " );
        }
    }

    function require_subclasses() {

        $this->Health_Check = new Livefyre_Health_Check( $this );
        $this->Activation = new Livefyre_Activation( $this );
        $this->Sync = new Livefyre_Sync( $this );
        $this->Admin = new Livefyre_Admin( $this );
        $this->Display = new Livefyre_Display( $this );

    }

} //  Livefyre_core

class Livefyre_Health_Check {

    function __construct( $lf_core ) {

        $this->lf_core = $lf_core;
        $this->ext = $lf_core->ext;
        $this->ext->setup_health_check( $this );

    }

    function livefyre_health_check() {

        if ( !isset( $_GET[ 'livefyre_ping_hash' ] ) )
            return;

        // Check the signature
        if ( $_GET[ 'livefyre_ping_hash' ] != md5( $this->lf_core->home_url ) ) {
            echo "hash does not match! my url is: $this->lf_core->home_url";
            exit;
        } else {
            echo "\nhash matched for url: $this->lf_core->home_url\n";
            echo "site's server thinks the time is: " . gmdate( 'd/m/Y H:i:s', time() );
            $notset = '[NOT SET]';
			$whitelist = array( 'livefyre_activation_pending', 'livefyre_activity_id', 'livefyre_commentseq', 'livefyre_domain_name', 'livefyre_site_id' );
            foreach ( $this->lf_core->options as $optname ) {		
				// This is to be considered a non-secure function. 
				// Any non-public options must be ommitted. 
				if ( isset( $whitelist[ $optname ] ) ) {
					echo "\n\nlivefyre option: $optname";
					$optval = $this->ext->get_option( $optname, $notset );
					echo "\n          value: $optval";
				}
            }
            exit;
        }
    }
}

class Livefyre_Activation {

    function __construct( $lf_core ) {
    
        $this->lf_core = $lf_core;
        $this->ext = $lf_core->ext;
        $this->ext->setup_activation( $this );

    }

    function deactivate() {

        $this->reset_caches();

    }

    function activate() {
    
        if ( !$this->ext->get_option( 'livefyre_domain_name', false ) ) {
            // Initialize default profile domain i.e. livefyre.com
            $this->ext->update_option( 'livefyre_domain_name', LF_DEFAULT_PROFILE_DOMAIN );
        }
        $this->reset_caches();
    
    }

    function reset_caches() {
    
        $this->ext->reset_caches();
        
    }

}

class Livefyre_Sync {
    
    function __construct( $lf_core ) {

        $this->lf_core = $lf_core;
        $this->ext = $lf_core->ext;
        $this->ext->setup_sync( $this );

    }

    function do_sync() {
    
        /*
            Fetch comments from the livefyre server, providing last activity id we have.
            Schedule the next sync if we got >50 or the server says "more-data".
            If there are no more comments, schedule a sync for several hrs out.
        */
        $result = array(
            'status' => 'ok',
            'message' => 'The sync process completed successfully.',
            'last-message-type' => null,
            'activities-handled' => 0
        );
        $inserts_remaining = LF_SYNC_MAX_INSERTS;
        $this->ext->debug_log( time() . ' livefyre synched' );
        $max_activity = $this->ext->get_option( 'livefyre_activity_id', '0' );
        if ( $max_activity == '0' ) {
            $final_path_seg = '';
        } else {
            $final_path_seg = $max_activity . '/';
        }
        $url = $this->site_rest_url() . '/sync/' . $final_path_seg;
        $qstring = 'page_size=' . $inserts_remaining . '&sig_created=' . time();
        $key = $this->ext->get_option( 'livefyre_site_key' );
        $url .= '?' . $qstring . '&sig=' . urlencode( lfgetHmacsha1Signature( base64_decode( $key ), $qstring ) );
        $http_result = $this->lf_core->lf_domain_object->http->request( $url, array('timeout' => 120) );
        if (is_array( $http_result ) && isset($http_result['response']) && $http_result['response']['code'] == 200) {
            $str_comments = $http_result['body'];
        } else {
            $str_comments = '';
        }
        $json_array = json_decode( $str_comments );
        if ( !is_array( $json_array ) ) {
            $this->schedule_sync( LF_SYNC_LONG_TIMEOUT );
            $error_message = 'Error during do_sync: Invalid response ( not a valid json array ) from sync request to url: ' . $url . ' it responded with: ' . $str_comments;
            $this->livefyre_report_error( $error_message );
            return array_merge(
                $result,
                array( 'status' => 'error', 'message' => $error_message )
            );
        }
        $data = array();
        // What to record for the "latest" id we know about, when done inserting
        $last_activity_id = 0;
        // By default, we don't queue an other near-term sync unless we discover the need to
        $timeout = LF_SYNC_LONG_TIMEOUT;
        foreach ( $json_array as $json ) {
            $mtype = $json->message_type;
            if ( $mtype == LF_SYNC_ERROR ) {
                // An error was encountered, don't schedule next sync for near-term
                $timeout = LF_SYNC_LONG_TIMEOUT;
                break;
            }
            if ( $mtype == LF_SYNC_MORE ) {
                // There is more data we need to sync, schedule next sync soon
                $timeout = LF_SYNC_SHORT_TIMEOUT;
                break;
            }
            if ( $mtype == LF_SYNC_ACTIVITY ) {
                $last_activity_id = $json->activity_id;
                $inserts_remaining--;
                $comment_date  = (int) $json->created;
                $comment_date = get_date_from_gmt( date( 'Y-m-d H:i:s', $comment_date ) );
                $data = array( 
                    'lf_activity_id'  =>  $json->activity_id,
                    'lf_action_type'  => $json->activity_type,
                    'comment_post_ID'  => $json->article_identifier,
                    'comment_author'  => $json->author,
                    'comment_author_email'  => $json->author_email,
                    'comment_author_url'  => $json->author_url,
                    'comment_type'  => '', 
                    'lf_comment_parent'  => $json->lf_parent_comment_id,
                    'lf_comment_id'  => $json->lf_comment_id,
                    'user_id'  => null,
                    'comment_author_IP'  => $json->author_ip,
                    'comment_agent'  => 'Livefyre, Inc .  Comments Agent', 
                    'comment_date'  => $comment_date,
                    'lf_state'  => $json->state
                );
                if ( isset( $json->body_text ) ) {
                    $data[ 'comment_content' ] = $json->body_text;
                }
                $this->livefyre_insert_activity( $data );
                if ( !$inserts_remaining ) {
                    $timeout = LF_SYNC_SHORT_TIMEOUT;
                    break;
                }
            }
        }
        $result[ 'last-message-type' ] = $mtype;
        $result[ 'activities-handled' ] = LF_SYNC_MAX_INSERTS - $inserts_remaining;
        $result[ 'last-activity-id' ] = $last_activity_id;
        if ( $last_activity_id ) {
            $this->ext->update_option( 'livefyre_activity_id', $last_activity_id );
        }
        $this->schedule_sync( $timeout );
        return $result;
    
    }

    function schedule_sync( $timeout ) {

        $this->ext->schedule_sync( $timeout );

    }
    
    function comment_update() {
        
        if (isset($_GET['lf_wp_comment_postback_request']) && $_GET['lf_wp_comment_postback_request']=='1') {
            $result = $this->do_sync();
            // Instruct the backend to use the site sync postback mechanism for future updates.
            $result[ 'plugin-version' ] = LF_PLUGIN_VERSION;
            echo json_encode( $result );
            exit;
        }
    
    }

    function post_param( $name, $plain_to_html = false, $default = null ) {

        $in = ( isset( $_POST[$name] ) ) ? trim( $_POST[$name] ) : $default;
        if ( $plain_to_html ) {
            $out = str_replace( "&", "&amp;", $in );
            $out = str_replace( "<", "&lt;", $out );
            $out = str_replace( ">", "&gt;", $out );
        } else {$out = $in;}
        return $out;

    }
    
    function site_rest_url() {

        return $this->lf_core->http_url . '/site/' . $this->ext->get_option( 'livefyre_site_id' );

    }

    function livefyre_report_error( $message ) { 

        $args = array( 'data' => array( 'message' => $message, 'method' => 'POST' ) );
        $this->lf_core->lf_domain_object->http->request( $this->site_rest_url() . '/error', $args );

    }

    function livefyre_insert_activity( $data ) {

        if ( isset( $data[ 'lf_comment_parent' ] ) && $data[ 'lf_comment_parent' ]!= null ) {
            $app_comment_parent = $this->ext->get_app_comment_id( $data[ 'lf_comment_parent' ] );
            if ( $app_comment_parent == null ) {
                //something is wrong.  might want to log this, essentially flattening because parent is not mapped
            }
        } else { 
            $app_comment_parent = null;
        }
        $app_comment_id = $this->ext->get_app_comment_id( $data[ 'lf_comment_id' ] );
        $at = $data[ 'lf_action_type' ];
        $data[ 'comment_approved' ] = ( ( isset( $data[ 'lf_state' ] ) && $data[ 'lf_state' ] == 'active' ) ? 1 : 0 );
        $data[ 'comment_parent' ] = $app_comment_parent;
        $action_types = array( 
            'comment-add', 
            'comment-moderate:mod-approve', 
            'comment-moderate:mod-hide', 
            'comment-update'
        );
        if ( $app_comment_id > '' && in_array( $at, $action_types ) ) {
            // update existing comment
            $data[ 'comment_ID' ] = $app_comment_id;
            $at_parts = explode( ':', $at );
            $action = $at_parts[ 0 ];
            $mod = count( $at_parts ) > 1 ? $at_parts[ 1 ] : '';
            if ( $action == 'comment-moderate' ) {
                if ( $mod == 'mod-approve' ) {
                    $this->ext->update_comment_status( $app_comment_id, 'approve' );
                } elseif ( $mod == 'mod-hide' && $data[ 'lf_state' ] == 'hidden' ) {
                    $this->ext->update_comment_status( $app_comment_id, 'spam' );
                }
            } elseif ( ($action == 'comment-update' || $action == 'comment-add') && isset( $data[ 'comment_content' ] ) && $data[ 'comment_content' ] != '' ) {
                // even if its supposed to be an "add", when we find the app comment ID, it must be an update
                $this->ext->update_comment( $data );
                if ( $data[ 'lf_state' ] == 'unapproved' ) {
                    $this->ext->update_comment_status( $app_comment_id, 'hold' );
                }
            }
        } elseif ( in_array( $at, array( 'comment-add', 'comment-moderate:mod-approve' ) ) ) {
            // insert new comment
            if ( !isset( $data[ 'comment_content' ] ) ) {
                livefyre_report_error( 'comment_content missing for synched activity id:' . $data[ 'lf_activity_id' ] );
            }
            if ( $data[ 'lf_state' ] != 'deleted' && $data[ 'lf_state' ] != 'hidden' ) {
                $app_comment_id = $this->ext->insert_comment( $data );
                if ( $data[ 'lf_state' ] == 'unapproved' ) {
                    $this->ext->update_comment_status( $app_comment_id, 'unapproved' );
                }
            }
        } else {
            return false; //we do not know how to handle this condition
        }

        if ( !( $app_comment_id > 0 ) ) return false;
        $this->ext->activity_log( $app_comment_id, $data[ 'lf_comment_id' ], $data[ 'lf_activity_id' ] );
        return true;
    }
    
}

