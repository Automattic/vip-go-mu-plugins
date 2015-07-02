<?php

class CloudSearch_API {

	private $error_messages;
	private $submission_uri;
	private $search_uri;

	const API_VERSION = '2011-02-01';

	/**
	 *
	 * @var iLift_HTTP
	 */
	private $http_interface;

	/**
	 *
	 * @param string $search_domain_uri
	 * @param iLift_HTTP $http_interface
	 */
	public function __construct( $http_interface, $document_endpoint, $search_endpoint, $version = '2011-02-01' ) {

		$this->http_interface = $http_interface;

		$this->submission_uri = sprintf( 'http://%s/%s/documents/batch', $document_endpoint, $version );
		$this->search_uri = sprintf( 'http://%s/%s/search?', $search_endpoint, $version );
	}

	private function send( $method = 'POST', $data = null ) {

		$method = strtoupper( $method );

		// only use JSON for now
		$headers = apply_filters('lift_search_headers', array(
			'Content-Type' => 'application/json',
			'Accept' => 'application/json'
		), $method, $data, $this);
		switch ( $method ) {

			case 'POST':
				$response = $this->http_interface->post( $this->submission_uri, $data, $headers );
				break;

			case 'GET':
				$response = $this->http_interface->get( $this->search_uri . $data, $headers );
				break;

			default:
				throw new Exception( 'you did it wrong' );
		}

		$json = json_decode( $response );
		if ( !$json ) {
			$this->error_messages = $response;
		}

		return $json;
	}

	/**
	 * Sends the search to the CloudSearch API
	 * @param Cloud_Search_Query $query
	 */
	public function sendSearch( $query ) {
		$response = $this->send( 'GET', $query->get_query_string() );

		if ( $response && property_exists( $response, 'error' ) ) {
			$this->error_messages = $response->messages;
			return false;
		}

		if ( in_array( $this->http_interface->getStatusCode(), array( 200, 201, 204 ) ) ) {
			return $response;
		}

		return false;
	}

	/**
	 * Sends the batch to the CloudSearch API
	 * @param LiftBatch $batch
	 */
	public function sendBatch( $batch ) {

		$response = $this->send( 'POST', $batch->convert_to_JSON() );

		if ( $response && ( 'error' === $response->status ) ) {
			$this->error_messages = $response->errors;
			return false;
		}

		if ( in_array( $this->http_interface->getStatusCode(), array( 200, 201, 204 ) ) ) {
			return $response;
		}

		return false;
	}

	public function getErrorMessages() {
		return $this->error_messages;
	}

}