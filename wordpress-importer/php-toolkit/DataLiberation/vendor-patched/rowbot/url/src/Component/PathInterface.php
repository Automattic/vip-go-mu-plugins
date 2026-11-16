<?php

declare( strict_types=1 );

namespace VendorPrefix\Rowbot\URL\Component;

use Countable;
use VendorPrefix\Rowbot\URL\URLRecord;

interface PathInterface extends Countable {
	public function first(): PathSegment;

	public function isEmpty(): bool;

	public function isOpaque(): bool;

	public function push( PathSegment $path ): void;

	/**
	 * @see https://url.spec.whatwg.org/#shorten-a-urls-path
	 */
	public function shorten( Scheme $scheme ): void;

	/**
	 * @see https://url.spec.whatwg.org/#potentially-strip-trailing-spaces-from-an-opaque-path
	 */
	public function potentiallyStripTrailingSpaces( URLRecord $url ): void;
}
