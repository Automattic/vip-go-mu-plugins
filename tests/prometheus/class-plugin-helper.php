<?php

namespace Automattic\VIP\Prometheus;

class Plugin_Helper extends Plugin {
	public static function clear_instance(): void {
		static::$instance = null;
	}
}
