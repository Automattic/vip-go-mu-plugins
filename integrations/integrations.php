<?php
/**
 * Integrations.
 *
 * @package Automattic\VIP\Integrations
 */

namespace Automattic\VIP\Integrations;

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
	 * @private
	 */
	public function register( $integration ): void {
		if ( ! is_subclass_of( $integration, Integration::class ) ) {
			trigger_error( sprintf( 'Integration class "%s" must extend %s.', esc_html( get_class( $integration ) ), esc_html( Integration::class ) ), E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			return;
		}

		$slug = $integration->get_slug();

		if ( null !== $this->get_integration( $slug ) ) {
			trigger_error( sprintf( 'Integration with slug "%s" is already registered.', esc_html( $slug ) ), E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			return;
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
	public function activate_platform_integrations() {
		foreach ( $this->integrations as $slug => $integration ) {
			// Don't activate again if integration is already activated and configured by customer.
			if ( $integration->is_active() ) {
				continue;
			}

			$vip_config = $this->get_integration_vip_config( $slug );

			if ( $vip_config->is_active_via_vip() ) {
				$this->activate( $slug );

				// If integration is activated successfully without any error then configure.
				if ( $integration->is_active() ) {
					$integration->set_vip_config( $vip_config );
					$integration->configure();
				}
			}
		}
	}

	/**
	 * Get IntegrationVipConfig instance (having this a separate method for mocking in tests).
	 *
	 * @param string $slug A unique identifier for the integration.
	 *
	 * @return IntegrationVipConfig
	 */
	protected function get_integration_vip_config( string $slug ): IntegrationVipConfig {
		return new IntegrationVipConfig( $slug );
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
	 * Activates an integration with given options array.
	 *
	 * @param string              $slug A unique identifier for the integration.
	 * @param array<string,mixed> $options An associative options array for the integration.
	 *
	 * @private
	 */
	public function activate( string $slug, array $options = [] ): void {
		$integration = $this->get_integration( $slug );

		if ( null === $integration ) {
			trigger_error( sprintf( 'VIP Integration with slug "%s" is not a registered integration.', esc_html( $slug ) ), E_USER_WARNING ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error
			return;
		}

		$integration->activate( $options );
	}
}
