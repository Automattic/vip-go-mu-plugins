<?php

namespace Automattic\SharedPlugins;

class VIPElasticsearch {
	static private $_instance = null;

	protected $adapter = null;

	public static function instance() {
		if ( self::$_instance instanceof \Automattic\SharedPlugins\VIPElasticsearch ) {
			self::$_instance = new \Automattic\SharedPlugins\VIPElasticsearch();
		}

		return self::$_instance;
	}

	public function init() {
		add_action( 'plugins_loaded', [ $this, 'setup_adapter' ] );
	}

	public function setup_adapter() {
		$this->adapter = $this->find_adapter();

		if ( ! $this->adapter ) {
			// TODO Add an admin warning or _doing_it_wrong() call
			return;
		}

		$this->adapter->setup();
	}

	public function find_adapter() {
		// Is ElasticPress loaded?
		if ( did_action( 'elasticpress_loaded' ) ) {
			require_once( __DIR__ . '/adapters/class-elasticpress.php' );

			return new \Automattic\SharedPlugins\VIPElasticsearch\Adapters\ElasticPress();
		}

		return null;
	}
}