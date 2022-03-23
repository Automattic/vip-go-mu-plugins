<?php

namespace Automattic\VIP\Admin_Notice;

class Constant_Condition implements Condition {

	private $constant;
	private $value;

	public function __construct( string $constant, $value ) {
		$this->constant = $constant;
		$this->value    = $value;
	}

	public function evaluate() {
		return defined( $this->constant ) && constant( $this->constant ) === $this->value;
	}
}
