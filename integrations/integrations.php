<?php
/**
 * Integrations.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

use InvalidArgumentException;

/**
 * Class used to track and activate registered integrations.
 *
 * @private
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
	 * @param Integration $integration Instantiated integration object.
	 *
	 * @throws InvalidArgumentException Excpetion if invalid argument are passed.
	 *
	 * @private
	 */
	public function register( $integration ): void {
		if ( ! is_subclass_of( $integration, Integration::class ) ) {
			throw new InvalidArgumentException( sprintf( 'Integration class "%s" must extend %s.', get_class( $integration ), Integration::class ) );
		}

		$slug = $integration->get_slug();

		if ( null !== $this->get( $slug ) ) {
			throw new InvalidArgumentException( sprintf( 'Integration with slug "%s" is already registered.', $slug ) );
		}

		$this->integrations[ $slug ] = $integration;
	}

	/**
	 * Returns a registered integration for a key, or null if not found.
	 *
	 * @param string $slug A unique identifier for the integration.
	 */
	private function get( string $slug ): ?Integration {
		return $this->integrations[ $slug ] ?? null;
	}

	/**
	 * Call load() for each registered and activated integration.
	 *
	 * @private
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
	 *
	 * @throws InvalidArgumentException Excpetion if invalid argument are passed.
	 *
	 * @private
	 */
	public function activate( string $slug, array $config = [] ): void {
		$integration = $this->get( $slug );

		if ( null === $integration ) {
			throw new InvalidArgumentException( sprintf( 'VIP Integration with slug "%s" is not a registered integration.', $integration ) );
		}
		
		$integration->activate( $config );
	}
}
