<?php

declare( strict_types=1 );

namespace VendorPrefix\Rowbot\URL\Component\Host;

abstract class AbstractHost {
	public function isEmpty(): bool {
		return false;
	}

	public function isLocalHost(): bool {
		return false;
	}

	public function isNull(): bool {
		return false;
	}
}
