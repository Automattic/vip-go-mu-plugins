<?php

namespace Automattic\VIP\Integrations;

class FakeIntegration extends Integration {
	public static $is_integrated = false;

	public function integrate( array $config ): void {
		self::$is_integrated = true;
	}
}
