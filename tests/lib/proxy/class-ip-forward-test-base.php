<?php

namespace Automattic\VIP\Tests;

use PHPUnit\Framework\TestCase;

// phpcs:disable WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__
// phpcs:disable WordPress.Security.ValidatedSanitizedInput

abstract class IP_Forward_Test_Base extends TestCase {
	const DEFAULT_REMOTE_ADDR = '1.0.1.0';

	public function setUp(): void {
		$this->original_remote_addr     = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : null;
		$this->original_x_forwarded_for = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : null;

		$_SERVER['REMOTE_ADDR'] = self::DEFAULT_REMOTE_ADDR;
	}

	public function tearDown(): void {
		if ( $this->original_remote_addr ) {
			$_SERVER['REMOTE_ADDR'] = $this->original_remote_addr;
		}

		if ( $this->original_x_forwarded_for ) {
			$_SERVER['HTTP_X_FORWARDED_FOR'] = $this->original_x_forwarded_for;
		}
	}
}
