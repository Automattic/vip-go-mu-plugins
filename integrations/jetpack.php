<?php
/**
 * Integration: Jetpack.
 */
namespace Automattic\VIP\Integrations;

class JetpackIntegration extends Integration {
	protected string $version = '';

	public function is_loaded(): bool {
		return class_exists( 'Jetpack' );
	}

	public function configure(): void {
		if ( isset( $this->get_env_config()['version'] ) ) {
			$this->version = $this->get_env_config()['version'];
		} elseif ( defined( 'VIP_JETPACK_PINNED_VERSION' ) ) {
			$this->version = constant( 'VIP_JETPACK_PINNED_VERSION' );
		} else {
			$this->version = vip_default_jetpack_version();
		}
	}

	public function load(): void {
		if ( $this->is_loaded() ) {
			return;
		}

		// Pass through to the old code for now which will respect all existing constants
		if ( ! defined( 'WP_INSTALLING' ) ) {
			vip_jetpack_load();
		}
	}
}
