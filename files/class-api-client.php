<?php

namespace Automattic\VIP\Files;

use WP_Error;

require( __DIR__ . '/class-curl-streamer.php' );

function new_api_client() {
	return new API_Client(
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

	private function call_api( $path, $method, $request_args = [] ) {
		$request_url = $this->get_api_url( $path );

		$headers = [
			'X-Client-Site-ID' => $this->files_site_id,
			'X-Access-Token' => $this->files_token,
		];

		$request_args = array_merge_recursive( [
			'method' => $method,
			'headers' => $headers,
			'timeout' => self::DEFAULT_REQUEST_TIMEOUT,
		], $request_args );

		$response = wp_remote_request( $request_url, $request_args );

		return $response;
	}

	// TODO: implement get_unique_filename()

	public function upload_file( $file_path ) {
		$file_size = filesize( $file_path );
		$file_name = basename( $file_path );
		$file_mime = wp_check_filetype( $file_name );

		// Uploads take longer so we need a custom timeout.
		// Use default timeout plus 1 second per 500kb.
		$request_timeout = self::DEFAULT_REQUEST_TIMEOUT + intval( $file_size / ( 500 * KB_IN_BYTES ) );

		$curl_streamer = new Curl_Streamer( $file_path );
		$curl_streamer->init();

		$response = $this->call_api( $file_path, 'PUT', [
			'headers' => [
				'Content-Type' => $file_mime,
				'Content-Length' => $file_size,
				'Connection' => 'Keep-Alive',
			],
			'timeout' => $request_timeout,
		] );

		$curl_streamer->deinit();

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( 204 === $response_code ) {
			return new WP_Error( 'upload_file-quota-reached', __( 'Failed to upload file; file space quota has been exceeded.' ) );
		} elseif ( 200 !== $response_code ) {
			return new WP_Error( 'upload_file-failed', sprintf( __( 'Failed to upload file `%1$s` (response code: %2$d)' ), $file_path, $response_code ) );
		}

		$response_body = wp_remote_retrieve_body( $response );
		$response_data = json_decode( $response_body );

		if ( ! $response_data ) {
			return new WP_Error( 'upload_file-json_decode-error', sprintf( __( 'Failed to process response data after file upload (body: %s)' ), $response_body ) );
		}

		// response looks like {"filename":"/wp-content/uploads/path/to/file.ext"}
		return $response_data->filename;
	}

	public function get_file( $file_path ) {
		$response = $this->call_api( $file_path, 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return new WP_Error( 'get_file-failed', sprintf( __( 'Failed to get file `%1$s` (response code: %2$d)' ), $file_path, $response_code ) );
		}

		return wp_remote_retrieve_body( $response );
	}

	public function delete_file( $file_path ) {
		$response = $this->call_api( $file_path, 'DELETE' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return new WP_Error( 'delete_file-failed', sprintf( __( 'Failed to delete file `%1$s` (response code: %2$d)' ), $file_path, $response_code ) );
		}

		return true;
	}

	public function is_file( $file_path ) {
		$response = $this->call_api( $file_path, 'GET', [
			'headers' => [
				'X-Action' => 'file_exists',
			],
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( 200 === $response_code ) {
			return true;
		} elseif ( 404 === $response_code ) {
			return false;
		}

		return new WP_Error( 'is_file-failed', sprintf( __( 'Failed to check if file `%1$s` exists (response code: %2$d)' ), $file_path, $response_code ) );
	}
}
