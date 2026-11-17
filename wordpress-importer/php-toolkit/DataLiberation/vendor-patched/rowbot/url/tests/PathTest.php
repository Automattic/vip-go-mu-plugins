<?php

declare( strict_types=1 );

namespace VendorPrefix\Rowbot\URL\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use VendorPrefix\Rowbot\URL\Component\OpaquePath;
use VendorPrefix\Rowbot\URL\Component\PathSegment;
use VendorPrefix\Rowbot\URL\Component\Scheme;
use VendorPrefix\Rowbot\URL\Exception\URLException;

class PathTest extends TestCase {
	public static function isNormalizedWindowsDriveLetterProvider(): array {
		return [
			[ 'c:', true ],
			[ 'c:/', false ],
			[ 'c:a', false ],
			[ '4:', false ],
			[ 'az:', false ],
			[ 'a|', false ],
			[ 'a:|', false ],
			[ '', false ],
			[ 'c:\\', false ],
			[ 'c:?', false ],
			[ 'c:#', false ],
			[ 'c:/f', false ],
		];
	}

	public function testIsNormalizedWindowsDriveLetter( string $input, bool $expected ): void {
		$s = new PathSegment( $input );
		self::assertSame( $expected, $s->isNormalizedWindowsDriveLetter() );
	}

	public function testOpaquePathThrowsOnShorten(): void {
		$path = new OpaquePath( new PathSegment() );
		$this->expectException( URLException::class );
		$path->shorten( new Scheme( 'file' ) );
	}

	public function testOpaquePathThrowsOnPush(): void {
		$path = new OpaquePath( new PathSegment() );
		$this->expectException( URLException::class );
		$path->push( new PathSegment() );
	}
}
