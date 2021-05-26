<?php

namespace Automattic\VIP\Admin_Notice;

class Expression_Condition implements Condition {

	/**
	 * Any expression (or value) that will be casted to boolean
	 *
	 * @var mixed
	 */
	private $condition;

	public function __construct( $condition ) {
		$this->condition = $condition;
	}

	public function evaluate() {
		return (bool) $this->condition;
	}
}
