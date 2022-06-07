<?php

namespace Automattic\VIP\Files;

class Curl_Streamer {
	private $file_path;

	public function __construct( $file_path ) {
		$this->file_path = $file_path;
	}

	public function init() {
		add_filter( 'http_api_transports', [ $this, 'enforce_curl_transport' ] );
		add_action( 'http_api_curl', [ $this, 'init_upload' ], 10 );
	}

	public function deinit() {
		remove_filter( 'http_api_transports', [ $this, 'enforce_curl_transport' ] );
		remove_action( 'http_api_curl', [ $this, 'init_upload' ] );
	}

	public function enforce_curl_transport() {
		return [ 'curl' ];
	}

	public function init_upload( $curl_handle ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen -- FP - the file is opened read-only
		$file_stream = fopen( $this->file_path, 'r' );

		if ( ! $file_stream ) {
			return;
		}

		$file_size = filesize( $this->file_path );

		// phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_setopt
		curl_setopt( $curl_handle, CURLOPT_PUT, true ); // The Requests lib only sets `CURLOPT_CUSTOMREQUEST`; we need to explicitly set `CURLOPT_PUT` as well.
		curl_setopt( $curl_handle, CURLOPT_INFILE, $file_stream );
		curl_setopt( $curl_handle, CURLOPT_INFILESIZE, $file_size );
		curl_setopt( $curl_handle, CURLOPT_READFUNCTION, [ $this, 'handle_upload' ] );
		// phpcs:enable WordPress.WP.AlternativeFunctions.curl_curl_setopt
	}

	public function handle_upload( $curl_handle, $file_stream, $length ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fread
		$data = fread( $file_stream, $length );
		if ( ! $data ) {
			return '';
		}

		return $data;
	}
}
