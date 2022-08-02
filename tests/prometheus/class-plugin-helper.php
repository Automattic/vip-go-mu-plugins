<?php

namespace Automattic\VIP\Prometheus;

use Prometheus\RegistryInterface;

class Plugin_Helper extends Plugin {
	public static function clear_instance(): void {
		self::$instance = null;
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
