<?php

namespace Automattic\VIP\Search;

use WP_Error;

class Concurrency_Limiter {
	const KEY_NAME = 'vip_es_request_count';

	/** @var int */
	private $cache_ttl = 60;

	/** @var int */
	private $max_concurrent_requests = 100;

	/** @var bool */
	private $doing_request = false;

	public function __construct() {
		if ( function_exists( 'apcu_enabled' ) && apcu_enabled() ) {
			$this->init();
		}
	}

	public function __destruct() {
		if ( $this->doing_request ) {
			$this->dec_key();
		}
	}

	public function init(): void {
		// Ensure the key exists and is numeric
		$this->get_key();

		$this->max_concurrent_requests = (int) apply_filters( 'vip_es_max_concurrent_requests', $this->max_concurrent_requests );
		$this->cache_ttl               = (int) apply_filters( 'vip_es_cache_ttl', $this->cache_ttl );

		add_filter( 'ep_do_intercept_request', [ $this, 'ep_do_intercept_request' ], 0 );
		add_action( 'ep_remote_request', [ $this, 'ep_remote_request' ] );
	}

	/**
	 * Called when ElasticPress calls ElasticSearch API.
	 * 
	 * @param mixed $response 
	 * @return mixed 
	 */
	public function ep_do_intercept_request( $response ) {
		// This filter can be called inside a loop; we need to make sure not to increment the counter more than once
		if ( ! $this->doing_request ) {
			$this->doing_request = true;
			$value               = $this->inc_key();
		} else {
			$value = $this->get_key();
		}

		return $value < $this->max_concurrent_requests ? $response : new WP_Error( 503, 'Concurrency limit exceeded' );
	}

	/**
	 * Called after ElasticSearch remote request
	 */
	public function ep_remote_request(): void {
		if ( $this->doing_request ) {
			$this->dec_key();
			$this->doing_request = false;
		}
	}

	private function get_key(): int {
		$value = apcu_entry( self::KEY_NAME, '__return_zero', $this->cache_ttl );
		if ( ! is_int( $value ) ) {
			$success = apcu_store( self::KEY_NAME, 0, $this->cache_ttl );
			if ( ! $success ) {
				// Out of memory
				$value = PHP_INT_MAX;
			}
		}

		return $value;
	}

	private function inc_key(): int {
		$success = null;
		$value   = apcu_inc( self::KEY_NAME, 1, $success, $this->cache_ttl );
		if ( $success ) {
			return $value;
		}

		return PHP_INT_MAX;
	}

	private function dec_key(): void {
		$success = null;
		apcu_dec( self::KEY_NAME, 1, $success, $this->cache_ttl );
	}
}
