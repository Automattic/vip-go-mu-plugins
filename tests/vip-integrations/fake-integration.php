<?php

namespace Automattic\VIP\Integrations;

class FakeIntegration extends Integration {
	// Test integration is loaded via FakeIntegration::class registration
	public static $is_loaded_class = false;

	// Test integration is loaded via a new FakeIntegration() instance registration
	public $is_loaded_instance = false;

	public function load( array $config ): void {
		// Set flags to indicate this integration's load() method was called
		self::$is_loaded_class    = true;
		$this->is_loaded_instance = true;
	}
}
