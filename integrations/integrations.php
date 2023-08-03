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
	 * @var array<string,Integration>
	 */
	private array $integrations = [];

	/**
	 * Registers an integration.
	 *
	 * @param Integration $integration Instantiated integration object.
	 *
	 * @throws InvalidArgumentException Exception if invalid argument are passed.
	 *
	 * @private
	 */
	public function register( $integration ): void {
		if ( ! is_subclass_of( $integration, Integration::class ) ) {
			throw new InvalidArgumentException( sprintf( 'Integration class "%s" must extend %s.', get_class( $integration ), Integration::class ) );
		}

		$slug = $integration->get_slug();

		if ( null !== $this->get_integration( $slug ) ) {
			throw new InvalidArgumentException( sprintf( 'Integration with slug "%s" is already registered.', $slug ) );
		}

		$this->integrations[ $slug ] = $integration;
	}

	/**
	 * Returns a registered integration for a key, or null if not found.
	 *
	 * @param string $slug A unique identifier for the integration.
	 */
	private function get_integration( string $slug ): ?Integration {
		return $this->integrations[ $slug ] ?? null;
	}

	/**
	 * Activates integrations based on the configuration provided by VIP
	 * (only if not already activated by customer).
	 *
	 * @return void
	 */
	public function activate_integrations_via_vip_config() {
		foreach ( $this->integrations as $slug => $integration ) {
			// Don't activate again if integration is already activated and configured by customer.
			if ( $integration->is_active() ) {
				continue;
			}

			$vip_config = new IntegrationConfig( $slug );

			if ( $vip_config->is_active_via_vip() ) {
				$this->activate( $slug, $vip_config->get_site_config() );
			}
		}
	}

	/**
	 * Call load() for each registered and activated integration.
	 *
	 * @private
	 */
	public function load_active(): void {
		foreach ( $this->integrations as $slug => $integration ) {
			if ( $integration->is_active() ) {
				$integration->load();
			}
		}
	}

	/**
	 * Activates an integration with an optional configuration value.
	 *
	 * @param string              $slug A unique identifier for the integration.
	 * @param array<string,mixed> $config An associative array of configuration values for the integration.
	 *
	 * @throws InvalidArgumentException Exception if invalid argument are passed.
	 *
	 * @private
	 */
	public function activate( string $slug, array $config = [] ): void {
		$integration = $this->get_integration( $slug );

		if ( null === $integration ) {
			throw new InvalidArgumentException( sprintf( 'VIP Integration with slug "%s" is not a registered integration.', $slug ) );
		}

		$integration->activate( $config );
	}
}
