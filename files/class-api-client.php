<?php

namespace Automattic\VIP\Files;

use WP_Error;

require( __DIR__ . '/class-curl-streamer.php' );
require( __DIR__ . '/class-vip-filesystem-api-cache.php' );

function new_api_client() {
	return new API_Client(
		'https://' . constant( 'FILE_SERVICE_ENDPOINT' ),
		constant( 'FILES_CLIENT_SITE_ID' ),
		constant( 'FILES_ACCESS_TOKEN' ),
		API_Cache::get_instance()
	);
}

class API_Client {
	const DEFAULT_REQUEST_TIMEOUT = 10;

	private $api_base;
	private $files_site_id;
	private $files_token;

	/**
	 * @var API_Cache
	 */
	private $cache;

	public function __construct( $api_base, $files_site_id, $files_token, $cache ) {
		$api_base = untrailingslashit( $api_base );
		$this->api_base = $api_base;

		$this->files_site_id = $files_site_id;
		$this->files_token = $files_token;

		$this->cache = $cache;
	}

	protected function is_valid_path( $path ) {
		$path = ltrim( $path, '/\\' );
		return 0 === strpos( $path, 'wp-content/uploads/' );
	}

	public function get_api_url( $path ) {
		$path = ltrim( $path, '/\\' );
		return $this->api_base . '/' . $path;
	}

	private function call_api( $path, $method, $request_args = [] ) {
		$is_valid_path = $this->is_valid_path( $path );
		if ( ! $is_valid_path ) {
			/* translators 1: file path */
			return new WP_Error( 'invalid-path', sprintf( __( 'The specified file path (`%s`) does not begin with `/wp-content/uploads/`.' ), $path ) );
		}

		$request_url = $this->get_api_url( $path );

		$headers = [
			'X-Client-Site-ID' => $this->files_site_id,
			'X-Access-Token' => $this->files_token,
		];

		if ( isset( $request_args['headers'] ) ) {
			$headers = array_merge( $headers, $request_args['headers'] );
		}

		$timeout = $request_args['timeout'] ?? self::DEFAULT_REQUEST_TIMEOUT;

		$request_args = [
			'method' => $method,
			'headers' => $headers,
			'timeout' => $timeout,
		];

		$response = wp_remote_request( $request_url, $request_args );

		return $response;
	}

	// TODO: implement get_unique_filename()

	public function upload_file( $local_path, $upload_path ) {
		if ( ! file_exists( $local_path ) ) {
			/* translators: 1: local file path 2: remote upload path */
			return new WP_Error( 'upload_file-failed-invalid_path', sprintf( __( 'Failed to upload file `%1$s` to `%2$s`; the file does not exist.' ), $local_path, $upload_path ) );
		}

		$file_size = filesize( $local_path );
		$file_name = basename( $local_path );
		$file_info = wp_check_filetype( $file_name );
		$file_mime = $file_info['type'] ?? '';

		$request_timeout = $this->calculate_upload_timeout( $file_size );

		$curl_streamer = new Curl_Streamer( $local_path );
		$curl_streamer->init();

		$response = $this->call_api( $upload_path, 'PUT', [
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
			/* translators: 1: local file path 2: remote upload path */
			return new WP_Error( 'upload_file-failed-quota_reached', __( 'Failed to upload file `%1$s` to `%2$s`; file space quota has been exceeded.' ), $local_path, $upload_path );
		} elseif ( 200 !== $response_code ) {
			/* translators: 1: local file path 2: remote upload path 3: HTTP status code */
			return new WP_Error( 'upload_file-failed', sprintf( __( 'Failed to upload file `%1$s` to `%2$s` (response code: %3$d)' ), $local_path, $upload_path, $response_code ) );
		}

		$response_body = wp_remote_retrieve_body( $response );
		$response_data = json_decode( $response_body );

		if ( ! $response_data ) {
			/* translators: 1: local file path 2: remote upload path 3: response body */
			return new WP_Error( 'upload_file-failed-json_decode-error', sprintf( __( 'Failed to process response data after file upload for `%1$s` to `%2$s` (body: %3$s)' ), $local_path, $upload_path, $response_body ) );
		}

		// response looks like {"filename":"/wp-content/uploads/path/to/file.ext"}
		// save to cache
		$this->cache->cache_file( $response_data->filename, file_get_contents( $local_path ) );

		return $response_data->filename;
	}

	private function calculate_upload_timeout( $file_size ) {
		// Uploads take longer so we need a custom timeout.
		// Use default timeout plus 1 second per 500kb.
		return self::DEFAULT_REQUEST_TIMEOUT + intval( $file_size / ( 500 * KB_IN_BYTES ) );
	}

	public function get_file( $file_path ) {
		// check in cache first
		$file_content = $this->cache->get_file( $file_path );
		if ( $file_content ) {
			return $file_content;
		}

		// not in cache so get from API
		$response = $this->call_api( $file_path, 'GET' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			/* translators: 1: file path 2: HTTP status code */
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
			/* translators: 1: file path 2: HTTP status code */
			return new WP_Error( 'delete_file-failed', sprintf( __( 'Failed to delete file `%1$s` (response code: %2$d)' ), $file_path, $response_code ) );
		}

		$this->cache->remove_file( $file_path );

		return true;
	}

	public function is_file( $file_path, &$info = null ) {
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
			$response_body = wp_remote_retrieve_body( $response );
			$info = json_decode( $response_body, true );

			return true;
		} elseif ( 404 === $response_code ) {
			return false;
		}

		/* translators: 1: file path 2: HTTP status code */
		return new WP_Error( 'is_file-failed', sprintf( __( 'Failed to check if file `%1$s` exists (response code: %2$d)' ), $file_path, $response_code ) );
	}

	/**
	 * Use the filesystem API to generate a unique filename based on
	 * provided file path
	 *
	 * @param string    $file_path
	 *
	 * @return string|WP_Error New unique filename
	 */
	public function get_unique_filename( $file_path ) {
		$response = $this->call_api( $file_path, 'GET', [
			'headers' => [
				'X-Action' => 'unique_filename',
			],
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return new WP_Error( 'invalid-file-type',
				sprintf( __( 'Failed to generate new unique file name `%1$s` (response code: %2$d)' ), $file_path, $response_code )
			);
		}

		$content = wp_remote_retrieve_body( $response );
		$obj = json_decode( $content );

		return $obj->filename;
	}
}
