<?php

namespace Automattic\VIP\Files;

function new_api_instance() {
	return new API(
		constant( 'FILE_SERVICE_ENDPOINT' ),
		constant( 'FILES_CLIENT_SITE_ID' ),
		constant( 'FILES_ACCESS_TOKEN' )
	);
}

class API {
	const DEFAULT_REQUEST_TIMEOUT = 10;

	private $api_base;
	private $files_site_id;
	private $files_token;

	public function __construct( $api_base, $files_site_id, $files_token ) {
		// TODO: user agent?
		$this->api_base = esc_url_raw( $api_base, [ 'https' ] );
		$this->files_site_id = $files_site_id;
		$this->files_token = $files_token;
	}

	private function get_api_url( $path ) {
		// TODO: slashes
		return $this->api_base . $path;
	}

	private function call_api( $path, $method, $headers ) {
		$request_headers = array_merge( [
			'X-Client-Site-ID' => $this->client_site_id,
			'X-Access-Token' => $this->token,
		], $headers );

		$request_url = $this->get_api_url( $path );

		$request_args = [
			'method' => $method,
			'headers' => $request_headers,
			'timeout' => self::DEFAULT_REQUEST_TIMEOUT, // TODO: will need a custom timeout for upload
		];

		$response = wp_remote_request( $request_url, $request_args );

		return $response;
	}

	// TODO: is_unique_filename()
	// TODO: get_file()
	// TODO: upload_file()
	// TODO: delete_file()

	public function is_file( $file_path ) {
		$response = $this->call_api( $file_path, 'GET', [
			'X-Action' => 'file_exists',
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		return 200 === $response_code;
	}
}
