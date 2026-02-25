<?php

/**
 * A class with a __wakeup() side effect, used to test insecure deserialization protection.
 */
class VIP_Test_Deserialization_Class {
	public static bool $wakeup_called = false;

	public function __construct() {
		self::$wakeup_called = false;
	}

	public function __wakeup(): void {
		self::$wakeup_called = true;
	}
}
