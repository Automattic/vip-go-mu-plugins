<?php
require_once( dirname( __FILE__ ) . "/LFAPPS_Comments_Sync.php" );

define( 'LFAPPS_SYNC_LONG_TIMEOUT', 25200 );
define( 'LFAPPS_SYNC_SHORT_TIMEOUT ', 3 );
define( 'LFAPPS_SYNC_MAX_INSERTS', 25 );
define( 'LFAPPS_SYNC_ACTIVITY', 'lf-activity' );
define( 'LFAPPS_SYNC_MORE', 'more-data' );
define( 'LFAPPS_SYNC_ERROR', 'error' );

class LFAPPS_Comments_Sync_Impl implements LFAPPS_Comments_Sync {
    
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

        $inserts_remaining = LFAPPS_SYNC_MAX_INSERTS;
        $max_activity = $this->ext->get_option( 'livefyre_activity_id', '0' );
        if ( $max_activity == '0' ) {
            $final_path_seg = '';
        } else {
            $final_path_seg = $max_activity . '/';
        }
        $url = $this->site_rest_url() . '/sync/' . $final_path_seg;
        $qstring = 'page_size=' . $inserts_remaining . '&plugin_version=' . LFAPPS_COMMENTS_PLUGIN_VERSION . '&sig_created=' . time();
        $key = get_option('livefyre_apps-livefyre_site_key' );
        $url .= '?' . $qstring . '&sig=' . urlencode( getHmacsha1Signature( base64_decode( $key ), $qstring ) );
        $http = new LFAPPS_Http_Extension;
        $http_result = $http->request( $url, array('timeout' => 120) );
        if (is_array( $http_result ) && isset($http_result['response']) && $http_result['response']['code'] == 200) {
            $str_comments = $http_result['body'];
        } else {
            $str_comments = '';
        }
        $json_array = json_decode( $str_comments );
        if ( !is_array( $json_array ) ) {
            $this->schedule_sync( LFAPPS_SYNC_LONG_TIMEOUT );
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
        $timeout = LFAPPS_SYNC_LONG_TIMEOUT;
        $first = true;
        foreach ( $json_array as $json ) {
            $mtype = $json->message_type;
            if ( $mtype == LFAPPS_SYNC_ERROR ) {
                // An error was encountered, don't schedule next sync for near-term
                $timeout = LFAPPS_SYNC_LONG_TIMEOUT;
                break;
            }
            if ( $mtype == LFAPPS_SYNC_MORE ) {
                // There is more data we need to sync, schedule next sync soon
                $timeout = LFAPPS_SYNC_SHORT_TIMEOUT ;
                break;
            }
            if ( $mtype == LFAPPS_SYNC_ACTIVITY ) {
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
                    'lf_comment_id'  => $json->lf_comment_id,
                    'user_id'  => null,
                    'comment_author_IP'  => $json->author_ip,
                    'comment_agent'  => 'Livefyre, Inc .  Comments Agent', 
                    'comment_date'  => $comment_date,
                    'lf_state'  => $json->state
                );
                if($first) {
                    $first_id_msg = 'Livefyre: Processing activity page starting with ' . $data['lf_activity_id'];
                    $first = false;
                }
                if ( isset( $json->lf_parent_comment_id ) ) {
                    $data[ 'lf_comments_parent' ] = $json->lf_parent_comment_id;
                }
                if ( isset( $json->body_text ) ) {
                    $data[ 'comment_content' ] = $json->body_text;
                }
                $this->livefyre_insert_activity( $data );
                if ( !$inserts_remaining ) {
                    $timeout = LFAPPS_SYNC_SHORT_TIMEOUT ;
                    break;
                }
            }
        }
        $result[ 'last-message-type' ] = $mtype;
        $result[ 'activities-handled' ] = LFAPPS_SYNC_MAX_INSERTS - $inserts_remaining;
        $result[ 'last-activity-id' ] = $last_activity_id;
        if ( $last_activity_id ) {
            $activity_update = $this->ext->update_option( 'livefyre_activity_id', $last_activity_id );
            if ( !$activity_update ) {
            }
            $last_id_msg = 'Livefyre: Set last activity ID processed to ' . $last_activity_id;
        }
        $this->schedule_sync( $timeout );
        return $result;

    }

    function schedule_sync( $timeout ) {

        $hook = 'livefyre_sync';

        // try to clear the hook, for race condition safety
        wp_clear_scheduled_hook( $hook );
        wp_schedule_single_event( time() + $timeout, $hook );

    }
    
    function comment_update() {
        
        if (isset($_GET['lf_wp_comment_postback_request']) && $_GET['lf_wp_comment_postback_request']=='1') {
            $result = $this->do_sync();
            // Instruct the backend to use the site sync postback mechanism for future updates.
            $result[ 'plugin-version' ] = LFAPPS_COMMENTS_PLUGIN_VERSION;
            echo json_encode( $result );
            exit;
        }
    
    }
    
    /* START: This code not approved by automattic, yet */
    function profile_update( $user_id ) {
        
        $systemuser = $this->lf_core->lf_domain_object->user( 'system' );
        $systemuser->push( $this->ext->profile_update_data( $user_id ) );

    }

    function check_profile_pull() {

        if ( ! $this->is_signed_profile_pull() )
            return;

        $domain = $this->lf_core->lf_domain_object;
        $server_token = base64_decode( sanitize_text_field( $_GET[ 'server_token' ] ) );
        header( 'Content-type: application/json' );
        if ( $domain->validate_server_token( $server_token ) ) {
            // Everything looks good, we respond with the current user's details.
            $lf_user_info = $this->ext->profile_pull_data();
            echo json_encode($lf_user_info);
        } else {
            echo '{"error":"Invalid Signature using PSK."}';
        }
        exit();

    }
    /* END: This code not approved by automattic, yet */

    function save_post( $post_id ) {

        $this->ext->save_post( $post_id );
    
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
    
    /* START: This code not approved by automattic, yet */
    function is_signed_profile_pull() {
    
        return ( 
            isset( $_GET[ 'lf_profile_pull_request' ] )
            && 
            ( $_GET[ 'lf_profile_pull_request' ] == '1' )
            &&
            isset( $_GET[ 'server_token' ] )
        );
    
    }
    /* END: This code not approved by automattic, yet */

    function site_rest_url() {

        return $this->lf_core->http_url . '/site/' . get_option('livefyre_apps-livefyre_site_id' );

    }

    function livefyre_report_error( $message ) { 

        $args = array( 'data' => array( 'message' => $message, 'method' => 'POST' ) );
        $this->lf_core->lf_domain_object->http->request( $this->site_rest_url() . '/error', $args );

    }

    function get_app_comment_id( $lf_comment_id ) {
    
        global $wpdb;
        $wp_comment_id = wp_cache_get( $lf_comment_id, 'livefyre-comment-map' );
        if ( false === $wp_comment_id ) {
            $wp_comment_id = $wpdb->get_var( $wpdb->prepare( "SELECT comment_id FROM $wpdb->commentmeta WHERE meta_key = %s LIMIT 1", LFAPPS_CMETA_PREFIX . $lf_comment_id ) );
            if ( $wp_comment_id ) {
                wp_cache_set( $lf_comment_id, $wp_comment_id, 'livefyre-comment-map' );
            }
        }
        return $wp_comment_id;

    }

    function livefyre_insert_activity( $data ) {
        if ( isset( $data[ 'lf_comment_parent' ] ) && $data[ 'lf_comment_parent' ]!= null ) {
            $app_comment_parent = $this->get_app_comment_id( $data[ 'lf_comment_parent' ] );
            if ( $app_comment_parent == null ) {
                //something is wrong.  might want to log this, essentially flattening because parent is not mapped
            }
        } else { 
            $app_comment_parent = null;
        }
        $app_comment_id = $this->get_app_comment_id( $data[ 'lf_comment_id' ] );
        $at = $data[ 'lf_action_type' ];
        $data[ 'comment_approved' ] = ( ( isset( $data[ 'lf_state' ] ) && $data[ 'lf_state' ] == 'active' ) ? 1 : 0 );
        $data[ 'comment_parent' ] = $app_comment_parent;
        $action_types = array( 
            'comment-add', 
            'comment-moderate:mod-approve', 
            'comment-moderate:mod-hide',
            'comment-moderate:mod-unapprove',
            'comment-moderate:mod-mark-spam',
            'comment-moderate:mod-bozo',
            'comment-update',
            'comment-delete'
        );
        if ( $app_comment_id > '' && in_array( $at, $action_types ) ) {

            // update existing comment
            $data[ 'comment_ID' ] = $app_comment_id;
            $at_parts = explode( ':', $at );
            $action = $at_parts[ 0 ];
            $mod = count( $at_parts ) > 1 ? $at_parts[ 1 ] : '';
            if ( $action == 'comment-moderate' ) {
                if ( $mod == 'mod-approve' ) {
                    $this->update_comment_status( $app_comment_id, 'approve' );
                } elseif ( $mod == 'mod-hide' && $data[ 'lf_state' ] == 'hidden' ) {
                    $this->ext->delete_comment( $app_comment_id );
                } elseif ( $mod == 'mod-unapprove') {
                    $this->update_comment_status( $app_comment_id, 'hold' );
                } elseif ( $mod == 'mod-mark-spam' || $mod == 'mod-bozo') {
                    $this->update_comment_status( $app_comment_id, 'spam' );
                }
            } elseif ( ($action == 'comment-update' || $action == 'comment-add') && isset( $data[ 'comment_content' ] ) && $data[ 'comment_content' ] != '' ) {
                // even if its supposed to be an "add", when we find the app comment ID, it must be an update
                $this->ext->update_comment( $data );
                if ( $data[ 'lf_state' ] == 'unapproved' ) {
                    $this->update_comment_status( $app_comment_id, 'hold' );
                }
            } elseif ($action == 'comment-delete') {
                $this->ext->delete_comment( $app_comment_id );
            }
        } elseif ( $at == 'comment-add' ) {
            // insert new comment
            if ( !isset( $data[ 'comment_content' ] ) ) {
                livefyre_report_error( 'comment_content missing for synched activity id:' . $data[ 'lf_activity_id' ] );
            }
            if ( $data[ 'lf_state' ] != 'deleted' && $data[ 'lf_state' ] != 'hidden' ) {
                $app_comment_id = $this->ext->insert_comment( $data );
                if ( $data[ 'lf_state' ] == 'unapproved' ) {
                    $this->update_comment_status( $app_comment_id, 'unapproved' );
                }
            }
        } else {
            return false; //we do not know how to handle this condition
        }

        if ( !( $app_comment_id > 0 ) ) return false;
        $this->ext->activity_log( $app_comment_id, $data[ 'lf_comment_id' ], $data[ 'lf_activity_id' ] );
        return true;

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
        "comment_approved",
        "comment_date"
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

    function delete_comment( $id ) {

        return wp_delete_comment( $id );

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

        if ( get_comment( $app_commetn_id ) == NULL ) {
            return;
        }
    
        // Livefyre says unapproved, WordPress says hold.
        $wp_status = ( $status == 'unapproved' ? 'hold' : $status );
        $args = array( $app_comment_id, $wp_status );
        $this->without_wp_notifications( 'wp_set_comment_status', $args );
    
    }
    
}
