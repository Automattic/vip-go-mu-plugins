<?php

declare( strict_types=1 );

namespace VendorPrefix\Rowbot\URL\Component\Host;

use VendorPrefix\Rowbot\URL\Component\Host\Serializer\HostSerializerInterface;
use VendorPrefix\Rowbot\URL\Component\Host\Serializer\StringHostSerializer;

class NullHost extends AbstractHost implements HostInterface {
	/**
	 * @param  HostInterface  $other
	 */
	public function equals( $other ): bool {
		return $other instanceof self;
	}

	public function getSerializer(): HostSerializerInterface {
		return new StringHostSerializer( '' );
	}

	public function isNull(): bool {
		return true;
	}
}
