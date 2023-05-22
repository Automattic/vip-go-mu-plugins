<?php

namespace Automattic\VIP\Integrations;

/**
 * Class used to track and activate registered integrations.
 */
class Integrations {
	/**
	 * Collection of registered integrations.
	 *
	 * @var array<Integration>
	 */
	private array $integrations = [];

	/**
	 * Return singleton instance of class.
	 */
	public static function instance(): Integrations {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Registers an integration.
	 *
	 * @param string       $slug        A unique identifier for the integration.
	 * @param class-string $class_name Fully-qualified class name that will be instantiated.
	 */
	public function register( string $slug, string $class_name ): void {
		$integration = new $class_name();

		$this->integrations[ $slug ] = $integration;
	}

	/**
	 * Returns a registered integration for a key, or null if not found.
	 *
	 * @param string $slug A unique identifier for the integration.
	 */
	public function get_registered( string $slug ): ?Integration {
		return $this->integrations[ $slug ] ?? null;
	}

	/**
	 * Call integrate() for each registered and activated integration.
	 */
	public function integrate(): void {
		foreach ( $this->integrations as $integration ) {
			if ( $integration->is_active() ) {
				$integration->integrate( $integration->get_config() );
			}
		}
	}
}
