<?php

/**
 * Description 
 * 
 * An extension of the Sailthru_Client class to use WordPress' HTTP API instead of cURL.
 * Provides a drop in replacement for the PHP5 Sailthru library with improved WordPress integration 
 * by replacing all cURL calls with WordPress HTTP API calls.
 */


class WP_Sailthru_Client extends Sailthru_Client {


    /**
     * Overload method to transparently intercept calls.
     * Perform an HTTP request using the Wordpress HTTP API
     *
     * @param string $url
     * @param array $data
     * @param string $method
     * @return string
     */
    function httpRequestCurl($url, array $data, $method = 'POST') {

        if ( 'GET' == $method ) {
            $url_with_params = $url;
            if ( count( $data ) > 0 ) {
                $url_with_params .= '?' . http_build_query( $data );
            }
            $url = $url_with_params;
        } else {
            // build a WP approved array
            $data = array(
                'method' => 'POST',
                'timeout' => 45,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => array(),
                'body' => $data,        // data passed to us by the user
                'cookies' => array()
            );            
        }


        if ( 'GET' == $method ) {
            $reply = wp_remote_get( $url, $data );
        } else {
            $reply = wp_remote_post( $url, $data );
        }


        if ( isset( $reply ) ) {
            if ( is_wp_error( $reply ) ) {
                throw new Sailthru_Client_Exception("Bad response received from $url: " . $reply->get_error_message() );
            } else {

                if( wp_remote_retrieve_response_code( $reply ) == 200 ) {
                   return $reply['body']; 
                }
                
            }
        } else {
            throw new Sailthru_Client_Exception( 'A reply was never generated.' );
        }

    }	// end httpRequestCurl()


} // end of WP_Sailthru_Client	