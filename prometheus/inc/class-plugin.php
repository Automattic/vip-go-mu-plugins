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
	private static ?Plugin $instance = null;
	private RegistryInterface $registry;
	/** @var CollectorInterface[] */
	private array $collectors = [];

	private const FLUSH_RULES_LOCK_NAME  = 'flush_rules_lock';
	private const FLUSH_RULES_LOCK_GROUP = 'vip_prometheus';

	public static function get_instance(): self {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->registry = self::create_registry();

		add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ] );
		add_action( 'init', [ $this, 'init' ] );
	}

	public function plugins_loaded(): void {
		$collectors = apply_filters( 'vip_prometheus_collectors', [] );
		if ( ! is_array( $collectors ) ) {
			$collectors = [];
		}

		foreach ( $collectors as $key => $collector ) {
			if ( is_object( $collector ) && $collector instanceof CollectorInterface ) {
				$collector->initialize( $this->registry );
			} else {
				unset( $collectors[ $key ] );
			}
		}

		$this->collectors = $collectors;
	}

	public function init(): void {
		add_rewrite_endpoint( 'metrics', EP_ROOT );
		add_filter( 'wp_headers', [ $this, 'wp_headers' ], 10, 2 );
		add_action( 'template_redirect', [ $this, 'template_redirect' ] );

		$version = (int) get_option( 'vip_prometheus_version', 0 );
		if ( $version < 1 && wp_cache_add( self::FLUSH_RULES_LOCK_NAME, 1, self::FLUSH_RULES_LOCK_GROUP, 10 ) ) {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules -- there are no activation hooks for mu-plugins
			flush_rewrite_rules();
			update_option( 'vip_prometheus_version', 1 );
			wp_cache_delete( self::FLUSH_RULES_LOCK_NAME, self::FLUSH_RULES_LOCK_GROUP );
		}
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
		}
	}

	private static function create_registry(): RegistryInterface {
		/** @var Adapter $storage */
		if ( extension_loaded( 'apcu' ) && apcu_enabled() ) {
			$storage = new APCng();
		} else {
			$storage = new InMemory();
		}

		return new CollectorRegistry( $storage );
	}
}
