<?php

use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestSuite;
use Yoast\PHPUnitPolyfills\TestListeners\TestListenerDefaultImplementation;

class VIP_Test_Listener implements TestListener {
	use TestListenerDefaultImplementation;

	private static $hooks_saved = [];
	private static $globals     = [ 'wp_actions', 'wp_filters', 'wp_current_filter' ];

	public function startTestSuite( TestSuite $suite ): void {
		$this->backup_hooks();
	}

	public function endTestSuite( TestSuite $suite ): void {
		$this->restore_hooks();
	}

	private function backup_hooks(): void {
		self::$hooks_saved['wp_filter'] = [];

		foreach ( $GLOBALS['wp_filter'] as $hook_name => $hook_object ) {
			self::$hooks_saved['wp_filter'][ $hook_name ] = clone $hook_object;
		}

		foreach ( self::$globals as $key ) {
			if ( array_key_exists( $key, $GLOBALS ) ) {
				self::$hooks_saved[ $key ] = $GLOBALS[ $key ];
			}
		}
	}

	private function restore_hooks(): void {
		if ( isset( self::$hooks_saved['wp_filter'] ) ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$GLOBALS['wp_filter'] = [];

			foreach ( self::$hooks_saved['wp_filter'] as $hook_name => $hook_object ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				$GLOBALS['wp_filter'][ $hook_name ] = clone $hook_object;
			}
		}

		foreach ( self::$globals as $key ) {
			if ( isset( self::$hooks_saved[ $key ] ) ) {
				$GLOBALS[ $key ] = self::$hooks_saved[ $key ];
			}
		}
	}
}
