<?php

declare( strict_types=1 );

namespace VendorPrefix\Rowbot\URL\Component\Host\Serializer;

/**
 * @see https://url.spec.whatwg.org/#concept-host-serializer
 */
interface HostSerializerInterface {
	public function toFormattedString(): string;

	public function toString(): string;
}
