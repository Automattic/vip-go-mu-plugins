<?php

namespace Automattic\VIP\Admin_Notice;

interface Condition {

	/**
	 * Evaluate condition.
	 *
	 * @return bool True or false.
	 */
	public function evaluate();
}
