<?php

namespace Automattic\VIP\Search;

require_once __DIR__ . '/../../../../search/includes/classes/class-concurrency-limiter.php';

// phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found

class Concurrency_Limiter_Helper extends Concurrency_Limiter {
	public function get_key(): int /* NOSONAR */ {
		return parent::get_key();
	}
}
