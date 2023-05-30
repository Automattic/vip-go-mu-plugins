<?php

namespace Automattic\VIP\Integrations;

class FakeIntegration extends Integration {
	public static $is_loaded = false;

	public function load( array $config ): void {
		self::$is_loaded = true;
	}
}
