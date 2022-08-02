<?php

namespace Automattic\VIP\Prometheus;

use Prometheus\RegistryInterface;

class Plugin_Helper extends Plugin {
	private static ?Plugin_Helper $instance = null;

	public static function get_instance(): self {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function get_registry(): RegistryInterface {
		return $this->registry;
	}

	/**
	 * @return CollectorInterface[]
	 */
	public function get_collectors(): array {
		return $this->collectors;
	}
}
