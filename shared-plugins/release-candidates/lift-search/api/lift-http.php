<?php

interface iLift_HTTP {

	public function post( $url, $data, $headers = array( ) );

	public function get( $url, $headers = array( ) );

	public function getStatusCode();

	public function getResponse();

	public function getLastError();
}

class Lift_HTTP_WP implements iLift_HTTP {

	protected $response_code;
	protected $response;
	protected $last_error;


	private function makeRequest( $url, $method = 'POST', $data = '', $headers = array( ) ) {
		// @TODO: better error handling to pass up to caller

		$args = array(
			'method' => strtoupper( $method ),
			'timeout' => 45,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking' => true,
			'headers' => $headers,
			'body' => $data,
			'cookies' => array( )
		);

		$this->response = wp_remote_request( $url, $args );
		if ( is_wp_error( $this->response ) ) {
			$this->last_error = $this->response->get_error_message();
			return false;
		}

		$this->response_code = wp_remote_retrieve_response_code( $this->response );

		return wp_remote_retrieve_body( $this->response );
	}

	public function get( $url, $headers = array( ) ) {
		if ( empty( $url ) ) {
			return false;
		}

		return $this->makeRequest( $url, 'GET', '', $headers );
	}

	public function post( $url, $data, $headers = array( ) ) {
		if ( empty( $url ) || empty( $data ) ) {
			return false;
		}

		return $this->makeRequest( $url, 'POST', $data, $headers );
	}

	public function getStatusCode() {
		return $this->response_code;
	}

	public function getResponse() {
		return $this->response;
	}

	public function getLastError() {
		return $this->last_error;
	}

}

class Lift_HTTP_WP_VIP extends Lift_HTTP_WP implements iLift_HTTP {

	public function get( $url, $headers = array( ) ) {

		$this->response = vip_safe_wp_remote_get( $url, '', 3, 1, 20, compact( 'headers' ) );

		if ( is_wp_error( $this->response ) ) {
			$this->last_error = $this->response->get_error_message();
			return false;
		}

		$this->response_code = wp_remote_retrieve_response_code( $this->response );

		return wp_remote_retrieve_body( $this->response );
	}

}

class Lift_HTTP_Curl implements iLift_HTTP {

	protected $channel;
	protected $response;
	protected $response_code;

	function __construct() {

		//Setup cURL
		$this->setOptions();
	}

	private function setOptions() {
		$this->channel = curl_init();
		//curl_setopt( $this->channel, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT'] );
		curl_setopt( $this->channel, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $this->channel, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $this->channel, CURLOPT_VERBOSE, true );
		curl_setopt( $this->channel, CURLOPT_CONNECTTIMEOUT, 45 );
		curl_setopt( $this->channel, CURLOPT_TIMEOUT, 45 );
		curl_setopt( $this->channel, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $this->channel, CURLOPT_SSL_VERIFYHOST, false );
		curl_setopt( $this->channel, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $this->channel, CURLOPT_HEADER, false );
		curl_setopt( $this->channel, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
	}

	public function getStatusCode() {
		return $this->response_code;
	}

	public function getResponse() {
		return $this->response;
	}

	public function get( $url, $headers = array( ) ) {
		$request = $this->makeRequest( 'get', $url, '', $headers );
		return $request;
	}

	public function post( $url, $data, $headers = array( ) ) {
		$request = $this->makeRequest( 'post', $url, $data, $headers );
		return $request;
	}

	function makeRequest( $method, $url, $data, $headers ) {

		curl_setopt( $this->channel, CURLOPT_URL, $url );

		if ( strtolower( $method ) == 'post' ) {
			curl_setopt( $this->channel, CURLOPT_POST, true );
			curl_setopt( $this->channel, CURLOPT_POSTFIELDS, $data );
		}

		curl_setopt( $this->channel, CURLOPT_HTTPHEADER, $headers );

		$this->response = curl_exec( $this->channel );
		$response_info = curl_getinfo( $this->channel );
		$this->response_code = $response_info['http_code'];

		// @TODO: wp_error style handling here (timeouts, etc)
		if ( !in_array( $this->response_code, array( 200, 201, 204 ) ) ) {
			return false;
		}

		return $this->response;
	}

	public function getLastError() {
		throw new Exception('Unimplemented getLastError method called');
	}
}