<?php

namespace Automattic\VIP\Files;

use WP_Error;

function new_api_client() {
	return new API(
		constant( 'FILE_SERVICE_ENDPOINT' ),
		constant( 'FILES_CLIENT_SITE_ID' ),
		constant( 'FILES_ACCESS_TOKEN' )
	);
}

class API_Client {
	const DEFAULT_REQUEST_TIMEOUT = 10;

	private $api_base;
	private $files_site_id;
	private $files_token;

	public function __construct( $api_base, $files_site_id, $files_token ) {
		$api_base = esc_url_raw( $api_base, [ 'https', 'http' ] );
		$api_base = untrailingslashit( $api_base );
		$this->api_base = $api_base;

		$this->files_site_id = $files_site_id;
		$this->files_token = $files_token;
	}

	public function get_api_url( $path ) {
		$path = ltrim( $path, '/\\' );
		return $this->api_base . '/' . $path;
	}

	private function call_api( $path, $method, $headers = [] ) {
		$request_url = $this->get_api_url( $path );

		$headers = array_merge( [
			'X-Client-Site-ID' => $this->files_site_id,
			'X-Access-Token' => $this->files_token,
		], $headers );

		$request_args = [
			'method' => $method,
			'headers' => $headers,
			'timeout' => self::DEFAULT_REQUEST_TIMEOUT,
			// TODO: will need a custom timeout for upload
		];

		$response = wp_remote_request( $request_url, $request_args );

		return $response;
	}

	// TODO: is_unique_filename()
	// TODO: get_file()
	// TODO: upload_file()

	public function delete_file( $file_path ) {
		$response = $this->call_api( $file_path, 'DELETE' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return new WP_Error( 'delete_file-failed', sprintf( __( 'Failed to delete file `%1$s` (response code: %2$d)' ), esc_html( $file_path ), $response_code ) );
		}

		return true;
	}

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
