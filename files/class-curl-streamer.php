<?php

namespace Automattic\VIP\Files;

class Curl_Streamer {
	private $file_path;

	public function __construct( $file_path ) {
		$this->file_path = $file_path;
	}

	public function init() {
		add_filter( 'http_api_transports', [ $this, 'enforce_curl_transport' ] );
		add_action( 'http_api_curl', [ $this, 'init_upload' ], 10, 3 );
	}

	public function deinit() {
		remove_filter( 'http_api_transports', [ $this, 'enforce_curl_transport' ] );
		remove_action( 'http_api_curl', [ $this, 'init_upload' ] );
	}

	public function enforce_curl_transport( $transports ) {
		return [ 'curl' ];
	}

	public function init_upload( $curl_handle, $request_args, $url ) {
		$file_stream = fopen( $this->file_path, 'r' );

		if ( ! $file_stream ) {
			return;
		}

		$file_size = filesize( $this->file_path );

		curl_setopt( $curl_handle, CURLOPT_PUT, true ); // The Requests lib only sets `CURLOPT_CUSTOMREQUEST`; we need to explicitly set `CURLOPT_PUT` as well.

		curl_setopt( $curl_handle, CURLOPT_INFILE, $file_stream );
		curl_setopt( $curl_handle, CURLOPT_INFILESIZE, $file_size );

		curl_setopt( $curl_handle, CURLOPT_READFUNCTION, [ $this, 'handle_upload' ] );
	}

	public function handle_upload( $curl_handle, $file_stream, $length ) {
		$data = fread( $file_stream, $length );
		if ( ! $data ) {
			return '';
		}

		return $data;
	}
}
