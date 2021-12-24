<?php

namespace Automattic\VIP\Search;

use Automattic\VIP\Search\ConcurrencyLimiter\BackendInterface;
use Automattic\VIP\Search\ConcurrencyLimiter\Object_Cache_Backend;
use Automattic\VIP\Utils\Context;
use WP_Error;

use function Automattic\VIP\Logstash\log2logstash;

require_once __DIR__ . '/concurrency-limiter/class-object-cache-backend.php';

class Concurrency_Limiter {
	/** @var int */
	private $cache_ttl = 60;

	/** @var int */
	private $max_concurrent_requests = 100;

	/** @var bool */
	private $doing_request = false;

	/** @var bool */
	private $should_fail = false;

	/** @var BackendInterface|null */
	private $backend = null;

	public function __construct() {
		$this->init();
	}

	public function init(): bool {
		$backend_class = defined( 'WP_CLI' ) && true === constant( 'WP_CLI' ) ? '' : Object_Cache_Backend::class;
		$backend_class = apply_filters( 'vip_search_concurrency_limit_backend', $backend_class );

		if ( ! empty( $backend_class ) && is_subclass_of( $backend_class, BackendInterface::class, true ) ) {
			/** @psalm-var class-string<BackendInterface> $backend_class */
			if ( $backend_class::is_supported() ) {
				$this->max_concurrent_requests = (int) apply_filters( 'vip_search_max_concurrent_requests', $this->max_concurrent_requests );
				$this->cache_ttl               = (int) apply_filters( 'vip_search_cache_ttl', $this->cache_ttl );

				/** @var BackendInterface */
				$this->backend = new $backend_class();
				$this->backend->initialize( $this->max_concurrent_requests, $this->cache_ttl );

				add_filter( 'ep_do_intercept_request', [ $this, 'ep_do_intercept_request' ], 0 );
				add_action( 'ep_remote_request', [ $this, 'ep_remote_request' ] );
				// We will remove this one once we have enough stats
				add_filter( 'vip_search_should_fail_excessive_request', [ $this, 'vip_search_should_fail_excessive_request' ] );
				return true;
			}

			trigger_error( esc_html( sprintf( 'Backend "%s" is not supported.', $backend_class ) ), E_USER_WARNING );
		}

		return false;
	}

	public function is_enabled(): bool {
		return null !== $this->backend;
	}

	public function get_backend(): ?BackendInterface {
		return $this->backend;
	}

	public function cleanup(): void {
		remove_filter( 'ep_do_intercept_request', [ $this, 'ep_do_intercept_request' ], 0 );
		remove_action( 'ep_remote_request', [ $this, 'ep_remote_request' ] );
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
			$this->should_fail   = ! $this->backend->inc_value();
		}

		$fail = apply_filters( 'vip_search_should_fail_excessive_request', $this->should_fail, $this->should_fail );
		return $fail ? new WP_Error( 429, 'Concurrency limit exceeded' ) : $response;
	}

	/**
	 * Called after ElasticSearch remote request
	 */
	public function ep_remote_request(): void {
		if ( $this->doing_request ) {
			$this->backend->dec_value();
			$this->doing_request = false;
			$this->should_fail   = false;
		}
	}

	/**
	 * We use this filter to log limit overruns to logstash.
	 * Currently, we allow all requests to ES while we are gathering stats.
	 * 
	 * @param bool $should_fail 
	 * @return bool 
	 */
	public function vip_search_should_fail_excessive_request( bool $should_fail ): bool {
		if ( $should_fail ) {
			log2logstash( [
				'severity' => 'warning',
				'feature'  => 'search_concurrency_limiter',
				'message'  => 'Concurrency limit exceeded',
			] );

			$should_fail = false;
		}

		return $should_fail;
	}
}
