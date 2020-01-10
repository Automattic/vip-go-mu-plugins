<?php

namespace Automattic\VIP\Files;

class Curl_Download_Streamer {
	private $stream;

	public function __construct( $file ) {
		$this->stream = $file;
	}

	public function init() {
		add_filter( 'http_api_transports', [ $this, 'enforce_curl_transport' ] );
		add_action( 'http_api_curl', [ $this, 'init_download' ], 10, 3 );
	}

	public function deinit() {
		remove_filter( 'http_api_transports', [ $this, 'enforce_curl_transport' ] );
		remove_action( 'http_api_curl', [ $this, 'init_download' ] );
	}

	public function enforce_curl_transport( $transports ) {
		return [ 'curl' ];
	}

	public function init_download( $curl_handle, $request_args, $url ) {
		if ( ! $this->stream ) {
			return;
		}

		curl_setopt( $curl_handle, CURLOPT_FILE, $this->stream );
	}
}
