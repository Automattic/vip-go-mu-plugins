<?php

class Livefyre_http {

    public function __construct() {
        $this->default_content_type = 'application/x-www-form-urlencoded';
    }

    public function request($url, $args = array()) {
        /* valid $args members (all optional):
            method: HTTP method
            data: associative array of "form" data
            timeout: time to wait for a response, in seconds
        */
        if ( !isset($args['method']) ) {
            $args['method'] = isset($args['data']) ? 'POST' : 'GET';
        }
        $result = array( 'response' => false,
                         'body' => false);
        $method_name = $this->has_curl() ? 'curl_request' : 'gfc_request';
        return $this->$method_name($url, $args, $result);
    }

    private function has_curl() {
        return function_exists('curl_init');
    }

    private function curl_request($url, $args = array(), &$result) {
        if ( ! isset( $args[ 'timeout' ] ) ) {
            $args[ 'timeout' ] = 5;
        }
        $ch = curl_init($url); 
        if ( $args['method'] == 'POST' ) {
            curl_setopt_array($ch, array(
                CURLOPT_POST            => 1,
                CURLOPT_POSTFIELDS      => http_build_query($args['data']),
                CURLOPT_HTTPHEADER      => array("Content-Type: $this->default_content_type")
            ));
        }
        curl_setopt_array($ch, array(
            CURLOPT_TIMEOUT         => $args[ 'timeout' ],
            CURLOPT_RETURNTRANSFER  => true
        ));
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $result['response'] = array( 'code' => curl_getinfo($ch, CURLINFO_HTTP_CODE) );
        $result['body'] = $response;
        return $result;
    }

    private function gfc_request($url, $args = array(), &$result) {
        if ( $args['method'] == 'POST' ) {
            $data_url = http_build_query($args['data']);
            $data_len = strlen($data_url);
            $result['body'] = file_get_contents(
                $url, false, 
                stream_context_create(
                    array(
                        'http'=>array(
                            'method'=>'POST',
                            'header'=>"Connection: close\r\nContent-Length: $data_len\r\nContent-Type: $this->default_content_type\r\n",
                            'content'=>$data_url
                        )
                    )
                )
            );
        } else {
            $result['body'] = file_get_contents($url);
        }
        // we don't have a resp code, so lets fake it!
        $result_code = $result['body'] ? 200 : 500;
        $result['response'] =  array( 'code' => $result_code );
        return $result;
    }

}

?>
