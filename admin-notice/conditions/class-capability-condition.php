<?php

namespace Automattic\VIP\Admin_Notice;

class Capability_Condition implements Condition {

	private $capabilities;

	public function __construct( string ...$capabilities ) {
		$this->capabilities = $capabilities ? $capabilities : [];
	}

	public function evaluate() {
		foreach ( $this->capabilities as $capability ) {
			if ( ! current_user_can( $capability ) ) {
				return false;
			}
		}

		return true;
	}
}
