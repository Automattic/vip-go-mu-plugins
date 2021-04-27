<?php

namespace Yoast\PHPUnitPolyfills\Polyfills;

/**
 * Polyfill the Assert::assertStringContainsString() et al methods, which replace the use of
 * Assert::assertContains() and Assert::assertNotContains() with string haystacks.
 *
 * Introduced in PHPUnit 7.5.0.
 * Use of Assert::assertContains() and Assert::assertNotContains() with string haystacks was
 * deprecated in PHPUnit 7.5.0 and removed in PHPUnit 9.0.0.
 *
 * @link https://github.com/sebastianbergmann/phpunit/issues/3422
 */
trait AssertStringContains {

	/**
	 * Asserts that a string haystack contains a needle.
	 *
	 * @param string $needle   The string to search for.
	 * @param string $haystack The string to treat as the haystack.
	 * @param string $message  Optional failure message to display.
	 *
	 * @return void
	 */
	public static function assertStringContainsString( $needle, $haystack, $message = '' ) {
		static::assertContains( $needle, $haystack, $message );
	}

	/**
	 * Asserts that a string haystack contains a needle (case-insensitive).
	 *
	 * @param string $needle   The string to search for.
	 * @param string $haystack The string to treat as the haystack.
	 * @param string $message  Optional failure message to display.
	 *
	 * @return void
	 */
	public static function assertStringContainsStringIgnoringCase( $needle, $haystack, $message = '' ) {
		static::assertContains( $needle, $haystack, $message, true );
	}

	/**
	 * Asserts that a string haystack does NOT contain a needle.
	 *
	 * @param string $needle   The string to search for.
	 * @param string $haystack The string to treat as the haystack.
	 * @param string $message  Optional failure message to display.
	 *
	 * @return void
	 */
	public static function assertStringNotContainsString( $needle, $haystack, $message = '' ) {
		static::assertNotContains( $needle, $haystack, $message );
	}

	/**
	 * Asserts that a string haystack does NOT contain a needle (case-insensitive).
	 *
	 * @param string $needle   The string to search for.
	 * @param string $haystack The string to treat as the haystack.
	 * @param string $message  Optional failure message to display.
	 *
	 * @return void
	 */
	public static function assertStringNotContainsStringIgnoringCase( $needle, $haystack, $message = '' ) {
		static::assertNotContains( $needle, $haystack, $message, true );
	}
}
