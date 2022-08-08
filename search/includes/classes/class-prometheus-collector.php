<?php

namespace Automattic\VIP\Search;

use Automattic\VIP\Prometheus\CollectorInterface;
use Prometheus\Counter;
use Prometheus\Histogram;
use Prometheus\RegistryInterface;

class Prometheus_Collector implements CollectorInterface {
	public const QUERY_FAILED_ERROR   = 'error';
	public const QUERY_FAILED_TIMEOUT = 'timeout';

	public const OBSERVATION_TYPE_ENGINE  = 'engine';
	public const OBSERVATION_TYPE_REQUEST = 'request';
	public const OBSERVATION_TYPE_PER_DOC = 'per_doc';

	protected static ?self $instance = null;

	private ?Histogram $request_times_histogram = null;
	private ?Counter $query_counter             = null;
	private ?Counter $failed_query_counter      = null;
	private ?Counter $ratelimited_query_counter = null;

	/**
	 * @return static
	 */
	public static function get_instance(): self {
		if ( ! self::$instance ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	private function __construct() {
		add_filter( 'vip_prometheus_collectors', [ $this, 'vip_prometheus_collectors' ] );
	}

	public function vip_prometheus_collectors( array $collectors ): array {
		$collectors[] = $this;
		return $collectors;
	}

	public function initialize( RegistryInterface $registry ): void {
		$this->request_times_histogram = $registry->getOrRegisterHistogram(
			'es',
			'request_times',
			'Request times',
			[ 'site_id', 'host', 'mode', 'type' ]
		);

		$this->query_counter = $registry->getOrRegisterCounter(
			'es',
			'queries_total',
			'Query count',
			[ 'site_id', 'host', 'mode' ]
		);

		$this->failed_query_counter = $registry->getOrRegisterCounter(
			'es',
			'faled_queries_total',
			'Failed query count',
			[ 'site_id', 'host', 'mode', 'reason' ]
		);

		$this->ratelimited_query_counter = $registry->getOrRegisterCounter(
			'es',
			'ratelimited_queries_total',
			'Ratelimited query count',
			[ 'site_id', 'host' ]
		);
	}

	public function collect_metrics(): void {
		// Do nothing
	}

	public static function observe_request_time( string $method, string $url, string $type, float $time ): void {
		$instance = self::get_instance();
		if ( $instance->request_times_histogram ) {
			$host = $instance->get_host( $url );
			$mode = $instance->get_mode( $url, $method );
			$instance->request_times_histogram->observe(
				$time,
				[
					(string) get_current_blog_id(),
					$host,
					$mode,
					$type,
				]
			);
		}
	}

	public static function increment_query_counter( string $method, string $url ): void {
		$instance = self::get_instance();
		if ( $instance->query_counter ) {
			$host = $instance->get_host( $url );
			$mode = $instance->get_mode( $url, $method );
			$instance->query_counter->inc(
				[
					(string) get_current_blog_id(),
					$host,
					$mode,
				]
			);
		}
	}

	public static function increment_failed_query_counter( string $method, string $url, string $reason ): void {
		$instance = self::get_instance();
		if ( $instance->failed_query_counter ) {
			$host = $instance->get_host( $url );
			$mode = $instance->get_mode( $url, $method );
			$instance->failed_query_counter->inc(
				[
					(string) get_current_blog_id(),
					$host,
					$mode,
					$reason,
				]
			);
		}
	}

	public static function increment_ratelimited_query_counter( string $url ): void {
		$instance = self::get_instance();
		if ( $instance->ratelimited_query_counter ) {
			$host = $instance->get_host( $url );
			$instance->ratelimited_query_counter->inc(
				[
					(string) get_current_blog_id(),
					$host,
				]
			);
		}
	}

	private function get_host( string $url ): string {
		$host = wp_parse_url( $url, \PHP_URL_HOST );
		$port = wp_parse_url( $url, \PHP_URL_PORT );

		if ( empty( $host ) ) {
			$host = 'unknown';
		}

		if ( ! empty( $port ) ) {
			$host .= ':' . $port;
		}

		return $host;
	}

	private function get_mode( string $url, string $method ): string {
		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['path'] ) ) {
			return 'unknown';
		}

		$path   = explode( '/', $parsed['path'] );
		$method = strtolower( $method );

		$last   = empty( $path ) ? '' : $path[ count( $path ) - 1 ];
		$penult = count( $path ) < 2 ? '' : $path[ count( $path ) - 2 ];

		if ( '_search' === $last ) {
			return 'search';
		}

		if ( '_doc' === $penult && ( 'delete' === $method || 'get' === $method ) ) {
			return $method;
		}

		if ( '_mget' === $last ) {
			return 'get';
		}

		if ( ( '_create' === $penult && ( 'put' === $method || 'post' === $method ) )
			|| ( '_update' === $penult )
			|| ( '_doc' === $last && 'post' === $method )
			|| ( '_doc' === $penult && 'put' === $method )
			|| ( '_bulk' === $last )
		) {
			return 'index';
		}

		return 'other';
	}
}
