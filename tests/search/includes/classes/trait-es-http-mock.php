<?php

namespace Automattic\VIP\Search;

/**
 * Fakes OK responses from the Elasticsearch server so that tests make no real ES requests.
 *
 * Search::filter__ep_do_intercept_request() sends the request before any `ep_do_intercept_request`
 * fakes at PHP_INT_MAX replace its result, so those alone don't prevent real HTTP requests.
 */
trait ES_HTTP_Mock {
	protected function add_es_http_mock(): void {
		add_filter( 'pre_http_request', [ $this, 'filter_pre_http_request_es_ok' ], PHP_INT_MAX, 3 );
	}

	protected function remove_es_http_mock(): void {
		remove_filter( 'pre_http_request', [ $this, 'filter_pre_http_request_es_ok' ], PHP_INT_MAX );
	}

	/**
	 * Only answers requests to the VIP_ELASTICSEARCH_ENDPOINTS hosts that no other filter has answered.
	 */
	public function filter_pre_http_request_es_ok( $preempt, $args, $url ) {
		if ( false !== $preempt || ! $this->is_es_url( $url ) ) {
			return $preempt;
		}

		return [
			'headers'  => [],
			'body'     => '{}',
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}

	/**
	 * Matches on host only, since migration mirroring swaps the port on the same host.
	 */
	private function is_es_url( $url ): bool {
		if ( ! defined( 'VIP_ELASTICSEARCH_ENDPOINTS' ) || ! is_array( constant( 'VIP_ELASTICSEARCH_ENDPOINTS' ) ) ) {
			return false;
		}

		$host     = wp_parse_url( $url, PHP_URL_HOST );
		$es_hosts = array_map( fn( $endpoint ) => wp_parse_url( $endpoint, PHP_URL_HOST ), constant( 'VIP_ELASTICSEARCH_ENDPOINTS' ) );

		return is_string( $host ) && in_array( $host, $es_hosts, true );
	}
}
