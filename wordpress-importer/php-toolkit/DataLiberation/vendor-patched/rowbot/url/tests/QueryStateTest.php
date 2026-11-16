<?php

declare( strict_types=1 );

namespace VendorPrefix\Rowbot\URL\Tests;

use PHPUnit\Framework\TestCase;
use VendorPrefix\Rowbot\URL\BasicURLParser;
use VendorPrefix\Rowbot\URL\String\Utf8String;
use VendorPrefix\Rowbot\URL\URLRecord;

use function html_entity_decode;

use const ENT_HTML5;

class QueryStateTest extends TestCase {
	/**
	 * @see https://url.spec.whatwg.org/#query-encoding-example
	 */
	public function testParsingNonUtf8EncodedQueryString(): void {
		$string = 'http://example.com/?' . html_entity_decode( 'sm&ouml;rg&aring;sbord', ENT_HTML5, 'utf-8' );
		$input  = new Utf8String( $string );
		$parser = new BasicURLParser();
		$record = $parser->parse( $input, null, 'windows-1252' );

		self::assertInstanceOf( URLRecord::class, $record );
		self::assertSame( 'sm%F6rg%E5sbord', $record->query );

		$record = $parser->parse( $input, null );

		self::assertInstanceOf( URLRecord::class, $record );
		self::assertSame( 'sm%C3%B6rg%C3%A5sbord', $record->query );
	}

	public function testParsingWebsocketForcesUtf8EncodingInQueryString(): void {
		$string = 'wss://example.com/?' . html_entity_decode( 'sm&ouml;rg&aring;sbord', ENT_HTML5, 'utf-8' );
		$input  = new Utf8String( $string );
		$parser = new BasicURLParser();
		$record = $parser->parse( $input, null, 'windows-1252' );

		self::assertInstanceOf( URLRecord::class, $record );
		self::assertSame( 'sm%C3%B6rg%C3%A5sbord', $record->query );
	}
}
