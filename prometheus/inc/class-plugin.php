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
		if ( ! is_array( $vars ) ) {
			$vars = [];
		}

		$vars[] = 'metrics';
		return $vars;
	}

	public function request( $query_vars ): array {
		if ( ! is_array( $query_vars ) ) {
			$query_vars = [];
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- the value is used only for strict comparison
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		if ( '/metrics' === $request_uri && is_proxied_request() ) {
			$query_vars['metrics'] = true;
			unset( $query_vars['error'] );

			add_filter( 'pre_handle_404', [ $this, 'pre_handle_404' ], 10, 2 );
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

	public function pre_handle_404( $_result, WP_Query $query ): bool {
		unset( $query->query_vars['error'] );
		return true;
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
}
