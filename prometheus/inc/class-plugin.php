<?php
namespace Automattic\VIP\Prometheus;

use Automattic\VIP\Utils\Context;
use Prometheus\CollectorRegistry;
use Prometheus\RegistryInterface;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\APCng;
use Prometheus\Storage\InMemory;

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

		// Currently there's no way to persist the storage on non-web requests because APCu is not available.
		if ( Context::is_web_request() ) {
			add_action( 'shutdown', [ $this, 'shutdown' ], PHP_INT_MAX );
		}

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
	 * We're going to send the response to the client, then collect metrics from each registered collector
	 */
	public function shutdown(): void {
		// This is expensive, potentially, so be mindful about when this method is called
		// Currently it only runs on web requests with a 60 interval, see the constructor
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}

		// Don't run on unauthorized requests.
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		if ( wp_cache_add( 'last_prom_run', time(), 'vip-prom', 90 ) ) {
			foreach ( $this->collectors as $collector ) {
				$collector->collect_metrics();
			}
		}
	}
}
