<?php

namespace Automattic\VIP\Integrations;

use InvalidArgumentException;

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
	 * Registers an integration.
	 *
	 * @param string             $slug            A unique identifier for the integration.
	 * @param string|Integration $class_or_object Fully-qualified class or instantiated Integration object.
	 */
	public function register( string $slug, $class_or_object ): void {
		if ( isset( $this->integrations[ $slug ] ) ) {
			throw new InvalidArgumentException( sprintf( 'Integration with slug "%s" is already registered.', $slug ) );
		}

		if ( ! is_object( $class_or_object ) ) {
			$class_or_object = new $class_or_object();
		}

		$this->integrations[ $slug ] = $class_or_object;
	}
	/**
	 * Returns a registered integration for a key, or null if not found.
	 *
	 * @param string $slug A unique identifier for the integration.
	 */
	public function get( string $slug ): ?Integration {
		return $this->integrations[ $slug ] ?? null;
	}

	/**
	 * Call load() for each registered and activated integration.
	 */
	public function load_active(): void {
		foreach ( $this->integrations as $slug => $integration ) {
			if ( $integration->is_active() ) {
				$integration->load( $integration->get_config() );
			}
		}
	}

	/**
	 * Activates an integration with an optional configuration value.
	 *
	 * @param string $slug   A unique identifier for the integration.
	 * @param array  $config An associative array of configuration values for the integration.
	 */
	public function activate( string $integration_slug, array $config = [] ): void {
		$integration = $this->get( $integration_slug );

		if ( null === $integration ) {
			throw new InvalidArgumentException( sprintf( 'VIP Integration with slug "%s" is not a registered integration.', $integration_slug ) );
		} else {
			$integration->activate( $config );
		}
	}
}
