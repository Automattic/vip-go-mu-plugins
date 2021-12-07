<?php

namespace Automattic\VIP\Logstash;

class Testable_Logger extends Logger {
	public static $logged_entries = [];

	public static function set_entries( array $entries ): void {
		self::$entries             = $entries;
		static::$processed_entries = false;
		static::$logged_entries    = [];
	}

	public static function wp_debug_log( array $entry ) : void {
		self::$logged_entries[] = $entry;
	}

	public static function get_entries(): array {
		return static::$entries;
	}
}
