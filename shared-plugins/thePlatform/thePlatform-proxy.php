<?php

/* thePlatform Video Manager Wordpress Plugin
  Copyright (C) 2013-2014  thePlatform for Media Inc.

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License along
  with this program; if not, write to the Free Software Foundation, Inc.,
  51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA. */


if ( !isset( $account ) ) {
	$account = get_option( TP_ACCOUNT_OPTIONS_KEY );
}

/**
 * This class is responsible for uploading and publishing Media to MPX
 */
class ThePlatform_Proxy {

    function __construct() {   
        if ( is_admin() ) {     
            add_action( 'wp_ajax_start_upload',     array( $this, 'start_upload' ) );
            add_action( 'wp_ajax_upload_status',    array( $this, 'upload_status' ) );
            add_action( 'wp_ajax_upload_fragment',  array( $this, 'upload_fragment' ) );
            add_action( 'wp_ajax_finish_upload',    array( $this, 'finish_upload' ) );
            add_action( 'wp_ajax_cancel_upload',    array( $this, 'cancel_upload' ) );
            add_action( 'wp_ajax_publish_media',    array( $this, 'publish_media' ) );
        }        
    }

    private $cookies;

	private function check_nonce_and_permissions( $action = "") {
		if ( empty( $action ) ) {
			check_admin_referer( 'theplatform-ajax-nonce' );
		}
		else {			
			check_admin_referer( 'theplatform-ajax-nonce-' . $action);
		}
		
		$tp_uploader_cap = apply_filters( TP_UPLOADER_CAP, TP_UPLOADER_DEFAULT_CAP );
		if ( !current_user_can( $tp_uploader_cap ) ) {
			wp_die( 'You do not have sufficient permissions to modify MPX Media' );
		}
	}
	
	private function check_theplatform_proxy_response( $response, $returnsValue = false ) {
		
        // Check if we got an error back and return it
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}
	
		if ( isset( $response['data'] ) && $response['data'] === false ) {
			wp_send_json_error( $response['status']['http_code'] );
		}
		
        $responseBody = wp_remote_retrieve_body( $response );

        // This AJAX call should not return a value, in this case we send a json error with the body to the UI
        if ( !$returnsValue && !empty( $responseBody ) ) {
            wp_send_json_error( theplatform_decode_json_from_server( $response, TRUE, FALSE ) );
        } 

        $parsedResponse = theplatform_decode_json_from_server( $response, TRUE, FALSE );

        if ( isset($this->cookie ) ) {            
            $parsedResponse['cookie'] = array ( 'name' => $this->cookie->name, 'value' => $this->cookie->value );
        }
		wp_send_json_success( $parsedResponse );                
	}

    private function proxy_http_request( $data = array() ) {
        $method = strtolower( $_POST['method'] );
        $url = $_POST['url'];

        if ( isset( $_POST['cookie_name'] ) ) {
            $data['cookies'] = array(
                new WP_Http_Cookie( 
                    array(
                        'name' => $_POST['cookie_name'], 
                        'value' => $_POST['cookie_value']
                    )
                )
            );
        }
        switch ( $method ) {
            case 'put':
                $response = ThePlatform_API_HTTP::put( $url, $data );
                break;
            case 'get':
                $response = ThePlatform_API_HTTP::get( $url );
                break;
            case 'post':
                $response = ThePlatform_API_HTTP::post( $url, $data );
                break;
            default:
                $response = array();
                break;
        }            

        return $response;           
    }

    public function start_upload() {                           
        $this->check_nonce_and_permissions( $_POST['action'] );
                    
        $response = $this->proxy_http_request();
        $this->cookie = $response['cookies'][0];
        
        $this->check_theplatform_proxy_response ( $response );

        wp_send_json_error("Shouldn't be here.");
    }

    public function upload_status() {                       
        $this->check_nonce_and_permissions( $_POST['action'] );

        $response = $this->proxy_http_request();
        $this->check_theplatform_proxy_response ( $response, true );
     
        wp_send_json_error("Shouldn't be here.");
    }

    public function upload_fragment() {               
        
        $this->check_nonce_and_permissions( $_POST['action'] );

        $file = file_get_contents( $_FILES['file']['tmp_name'] );            
        $data = array(
            'body' => $file,
            'timeout' => 120,
            'headers' => array( 'content-type' => 'application/x-www-form-urlencode; charset=UTF-8' )
        );

        $response = $this->proxy_http_request( $data );

        $this->check_theplatform_proxy_response ( $response );

        wp_send_json_error("Shouldn't be here.");
    }

    public function finish_upload() {                       
        $this->check_nonce_and_permissions( $_POST['action'] );
        
        $data = array (
            'body' => 'finished',
            'timeout' => 30
        );

        $response = $this->proxy_http_request( $data );

        $this->check_theplatform_proxy_response ( $response );
     
        wp_send_json_error("Shouldn't be here.");
    }

    public function cancel_upload() {               
        $this->check_nonce_and_permissions( $_POST['action'] );

        $response = $this->proxy_http_request();
        $this->check_theplatform_proxy_response ( $response );
     
        wp_send_json_error("Shouldn't be here.");
     
    }

        /**
     * Publish an uploaded media asset using the 'Wordpress' profile
     * @return mixed JSON response or instance of WP_Error
     */
    public function publish_media() {
        $this->check_nonce_and_permissions( $_POST['action'] );

        if ( $_POST['profile'] == 'wp_tp_none' ) {
            wp_send_json_success();
        } 
        
        $profileUrl = TP_API_PUBLISH_PROFILE_ENDPOINT;
        $profileUrl .= '&byTitle=' . urlencode( $_POST['profile'] );        
        $profileUrl .= '&token=' . $_POST['token'];
        $profileUrl .= '&account=' . urlencode( $_POST['account'] );

        $profileResponse = ThePlatform_API_HTTP::get( esc_url_raw( $profileUrl ) );

        $content = theplatform_decode_json_from_server( $profileResponse, TRUE );

        if ( $content['entryCount'] == 0 ) {
            wp_send_json_error( "No Publishing Profile Found" );
        }
        
        $profileId = $content['entries'][0]['id'];
        $mediaId = $_POST['mediaId'];

        $publishUrl = TP_API_PUBLISH_BASE_URL;
        $publishUrl .= '&token=' . $_POST['token'];
        $publishUrl .= '&account=' . urlencode( $_POST['account'] );
        $publishUrl .= '&_mediaId=' . urlencode( $mediaId );
        $publishUrl .= '&_profileId=' . urlencode( $profileId );

        $response = ThePlatform_API_HTTP::get( esc_url_raw ( $publishUrl ), array( "timeout" => 120 ) );

        $this->check_theplatform_proxy_response( $response, true );
    }
}

new ThePlatform_Proxy();