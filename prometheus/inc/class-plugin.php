<?php

namespace Automattic\VIP\Prometheus;

use Prometheus\CollectorRegistry;
use Prometheus\RegistryInterface;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\APCng;
use Prometheus\Storage\InMemory;
use WP;
use WP_Query;

final class Plugin {
	private static ?Plugin $instance     = null;
	private ?RegistryInterface $registry = null;
	/** @var CollectorInterface[] */
	private array $collectors = [];

	public static function get_instance(): self {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ] );
		add_action( 'init', [ $this, 'init' ] );
	}

	public function plugins_loaded(): void {
		$this->registry = self::create_registry();

		$available_collectors = apply_filters( 'vip_prometheus_collectors', [] );
		if ( ! is_array( $available_collectors ) ) {
			$available_collectors = [];
		}

		foreach ( $available_collectors as $key => $collector ) {
			if ( is_object( $collector ) && $collector instanceof CollectorInterface ) {
				$collector->initialize( $this->registry );
			} else {
				unset( $available_collectors[ $key ] );
			}
		}

		$this->collectors = $available_collectors;
	}

	public function init(): void {
		if ( ! defined( 'WP_RUN_CORE_TESTS' ) || ! WP_RUN_CORE_TESTS ) {
			add_filter( 'query_vars', [ $this, 'query_vars' ] );
			add_filter( 'request', [ $this, 'request' ] );
			add_filter( 'wp_headers', [ $this, 'wp_headers' ], 10, 2 );
			add_action( 'template_redirect', [ $this, 'template_redirect' ], 0 );
		}
	}

	/**
	 * @param string[] $vars
	 * @return string[]
	 */
	public function query_vars( $vars ): array {
		$vars[] = 'metrics';
		return $vars;
	}

	public function request( array $query_vars ): array {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- the value is used only for strict comparison
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		if ( '/metrics' === $request_uri ) {
			$query_vars['metrics'] = true;
			unset( $query_vars['error'] );
		}

		return $query_vars;
	}

	public function wp_headers( $headers, WP $wp ): array {
		if ( ! is_array( $headers ) ) {
			$headers = [];
		}

		if ( isset( $wp->query_vars['metrics'] ) ) {
			$headers['Content-Type'] = RenderTextFormat::MIME_TYPE;
			$headers                 = array_merge( $headers, wp_get_nocache_headers() );
		}

		return $headers;
	}

	/**
	 * @global WP_Query $wp_query
	 */
	public function template_redirect(): void {
		/** @var WP_Query $wp_query */
		global $wp_query;

		if ( isset( $wp_query->query_vars['metrics'] ) ) {
			array_walk( $this->collectors, fn ( CollectorInterface $collector ) => $collector->collect_metrics() );

			$renderer = new RenderTextFormat();
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- this is a text/plain endpoint
			echo $renderer->render( $this->registry->getMetricFamilySamples() );
			die();

			// In case you want or need to debug queries:
			// 1. Comment out the calls to `$renderer->render()` and `die()` above;
			// 2. Uncomment the following lines:
			//
			// remove_all_actions( current_action() );
			// do_action( 'wp_head' );
			// do_action( 'wp_footer' );
			// die();
		}
	}

	private static function create_registry(): RegistryInterface {
		/** @var Adapter $storage */
		if ( extension_loaded( 'apcu' ) && apcu_enabled() ) {
			$storage_backend = APCng::class;
		} else {
			$storage_backend = InMemory::class;
		}

		$storage_backend = apply_filters( 'vip_prometheus_storage_backend', $storage_backend );
		if ( is_string( $storage_backend ) && class_exists( $storage_backend ) ) {
			$storage = new $storage_backend();
		} elseif ( ! is_object( $storage_backend ) || ! ( $storage_backend instanceof Adapter ) ) {
			trigger_error( 'Invalid storage backend', E_USER_WARNING );
			$storage = new InMemory();
		} else {
			$storage = $storage_backend;
		}

		return new CollectorRegistry( $storage );
	}
}
