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

class Plugin {
	protected static ?Plugin $instance     = null;
	protected ?RegistryInterface $registry = null;
	/** @var CollectorInterface[] */
	protected array $collectors = [];

	private static string $endpoint_path = '/.vip-prom-metrics';

	/**
	 * @return static
	 */
	public static function get_instance(): self {
		if ( ! self::$instance ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	/**
	 * @codeCoverageIgnore -- invoked before the tests start
	 */
	protected function __construct() {
		add_action( 'vip_mu_plugins_loaded', [ $this, 'init_registry' ], 9 );

		add_action( 'vip_mu_plugins_loaded', [ $this, 'load_collectors' ] );
		add_action( 'mu_plugins_loaded', [ $this, 'load_collectors' ] );
		add_action( 'plugins_loaded', [ $this, 'load_collectors' ] );
		add_action( 'plugins_loaded', [ $this, 'intercept_request' ] );

		add_action( 'init', [ $this, 'init' ] );

		do_action( 'vip_prometheus_loaded' );
	}

	public function init_registry() {
		$this->registry = self::create_registry();
	}

	public function load_collectors(): void {
		$available_collectors = apply_filters( 'vip_prometheus_collectors', [], current_action() );
		if ( ! is_array( $available_collectors ) ) {
			$available_collectors = [];
		}

		$available_collectors = array_filter( $available_collectors, fn ( $collector ) => $collector instanceof CollectorInterface );

		$available_collectors = array_udiff(
			$available_collectors,
			$this->collectors,
			fn ( $a, $b ) => spl_object_id( $a ) - spl_object_id( $b )
		);

		array_walk( $available_collectors, fn ( CollectorInterface $collector ) => $collector->initialize( $this->registry ) );

		$this->collectors = array_merge( $this->collectors, $available_collectors );
	}

	public function init(): void {
		if ( ! defined( 'WP_RUN_CORE_TESTS' ) || ! WP_RUN_CORE_TESTS ) {
			add_filter( 'request', [ $this, 'request' ] );
		}
	}

	public function request( $query_vars ): array {
		if ( ! is_array( $query_vars ) ) {
			$query_vars = [];
		}

		if ( $this->is_prom_endpoint_request() ) {
			unset( $query_vars['error'] );
			add_filter( 'pre_handle_404', [ $this, 'pre_handle_404' ], 10, 2 );
		}

		return $query_vars;
	}

	public function pre_handle_404( $_result, WP_Query $query ): bool {
		unset( $query->query_vars['error'] );
		return true;
	}

	/**
	 * @global WP_Query $wp_query
	 */
	public function intercept_request(): void {
		if ( ! $this->is_prom_endpoint_request() ) {
			return;
		}

		nocache_headers();
		header( 'Content-Type: ' . RenderTextFormat::MIME_TYPE );

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

	private static function create_registry(): RegistryInterface {
		// @codeCoverageIgnoreStart -- APCu may or may not be available during tests
		/** @var Adapter $storage */
		if ( extension_loaded( 'apcu' ) && apcu_enabled() ) {
			$storage_backend = APCng::class;
		} else {
			$storage_backend = InMemory::class;
		}
		// @codeCoverageIgnoreEnd

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

	/**
	 * Validate if current request is for the Prometheus endpoint.
	 *
	 * @return bool
	 */
	private function is_prom_endpoint_request(): bool {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- the value is used only for strict comparison
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		return self::$endpoint_path === $request_uri;
	}
}
